<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\SalesOrder;
use App\Models\WorkOrder;
use App\Services\DocxTemplateService;
use App\Services\NotifyService;
use App\Services\PdfRenderService;
use App\Services\ActivityLogger;
use App\Services\DocumentArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        return view('work-orders.index', [
            'orders' => WorkOrder::with('salesOrder.customer')->withCount('items')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->latest('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function create(Request $request)
    {
        $so = $request->filled('so') ? SalesOrder::with('customer')->find($request->so) : null;
        $customer = $so?->customer;
        $user = $request->user();

        $stockCounts = \App\Models\Item::query()
            ->whereIn('status', ['Available', 'In Stock'])
            ->whereNull('reserved_sales_order_item_id')
            ->whereNull('reserved_work_order_item_id')
            ->whereNotNull('part_number')
            ->selectRaw('lower(trim(part_number)) as pn, count(*) as total')
            ->groupByRaw('lower(trim(part_number))')
            ->pluck('total', 'pn');

        $inventories = Inventory::with('type')->orderBy('part_number')->get();
        foreach ($inventories as $inv) {
            $inv->available_stock = (int) ($stockCounts[mb_strtolower(trim($inv->part_number))] ?? 0);
        }

        return view('work-orders.create', [
            'inventories' => $inventories,
            'customers' => \App\Models\Customer::orderBy('name')->get(),
            'salesOrders' => SalesOrder::with('customer')->whereIn('status', ['approved', 'processing', 'delivered'])->latest()->get(),
            'selectedSo' => $so,
            'prefill' => [
                'cust_name' => $customer?->name, 'cust_phone' => $customer?->phone,
                'cust_email' => $customer?->email, 'cust_address' => $customer?->address,
                'user_name' => $user->name, 'user_email' => $user->email,
                'sales_name' => $user->name,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'customer_id' => 'nullable|exists:customers,id',
            'wo_date' => 'required|date',
            'ref_number' => 'nullable|string|max:255',
            'ref_company' => 'nullable|string|max:255',
            'work_type' => 'nullable|string|max:50',
            'po_date' => 'nullable|date',
            'rfs_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'business_type' => 'nullable|string|max:50',
            'subject' => 'required|string|max:255',
            'cust_name' => 'nullable|string|max:255', 'cust_position' => 'nullable|string|max:255',
            'cust_phone' => 'nullable|string|max:255', 'cust_email' => 'nullable|string|max:255', 'cust_address' => 'nullable|string',
            'user_name' => 'nullable|string|max:255', 'user_position' => 'nullable|string|max:255',
            'user_phone' => 'nullable|string|max:255', 'user_email' => 'nullable|string|max:255', 'user_address' => 'nullable|string',
            'sales_name' => 'nullable|string|max:255', 'sales_title' => 'nullable|string|max:50',
            'approver_name' => 'nullable|string|max:255', 'approver_title' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.part_number' => 'required|string|max:255',
            'items.*.type_name' => 'nullable|string|max:255',
            'items.*.item_name' => 'nullable|string|max:255',
            'items.*.brand' => 'nullable|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.serial_numbers' => 'nullable|string',
            'items.*.qty' => 'required|integer|min:1|max:100000',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.sow_explanation' => 'nullable|string|max:255',
        ], [
            'required' => ':attribute is required.',
            'items.required' => 'Please add at least 1 item row.',
            'items.*.part_number.required' => 'Part Number is required on every item row.',
        ], ['wo_date' => 'WO Date', 'subject' => 'Subject']);

        $inventories = Inventory::with('type')->get()->keyBy(fn ($i) => mb_strtolower($i->part_number));
        $seenSn = [];
        foreach ($data['items'] as $idx => &$line) {
            $inv = $inventories->get(mb_strtolower(trim($line['part_number'])));
            $line['type_name'] = filled($line['type_name'] ?? null) ? $line['type_name'] : ($inv?->type?->name ?? '-');
            $line['item_name'] = filled($line['item_name'] ?? null) ? $line['item_name'] : $line['part_number'];
            $line['brand'] = filled($line['brand'] ?? null) ? $line['brand'] : $inv?->brand;
            $line['description'] = filled($line['description'] ?? null) ? $line['description'] : $inv?->description;
            $line['unit'] = filled($line['unit'] ?? null) ? trim($line['unit']) : 'pcs';

            // One SN per line; the qty is the number of scanned SN lines (same concept as Add Item).
            $sns = collect(preg_split('/\r\n|\r|\n/', (string) ($line['serial_numbers'] ?? '')))->map(fn ($x) => trim($x))->filter()->values();
            if ($dupe = $sns->duplicates()->first() ?? $sns->first(fn ($x) => isset($seenSn[mb_strtolower($x)]))) {
                return back()->withInput()->withErrors(["items.{$idx}.serial_numbers" => "Serial Number {$dupe} is duplicated in this WO."]);
            }
            $sns->each(function ($x) use (&$seenSn) { $seenSn[mb_strtolower($x)] = true; });
            $line['serial_numbers'] = $sns->isNotEmpty() ? $sns->implode("\n") : null;
            if ($sns->isNotEmpty()) {
                $line['qty'] = $sns->count();

                // Verify each provided SN is actually in stock and unreserved
                foreach ($sns as $sn) {
                    $itemExists = \App\Models\Item::query()
                        ->whereIn('status', ['Available', 'In Stock'])
                        ->whereNull('reserved_sales_order_item_id')
                        ->whereNull('reserved_work_order_item_id')
                        ->whereRaw('lower(trim(part_number)) = ?', [mb_strtolower(trim($line['part_number']))])
                        ->whereRaw('lower(trim(serial_number)) = ?', [mb_strtolower($sn)])
                        ->exists();

                    if (! $itemExists) {
                        return back()->withInput()->withErrors([
                            "items.{$idx}.serial_numbers" => "Serial Number '{$sn}' for Part Number '{$line['part_number']}' is not in stock or already reserved.",
                        ]);
                    }
                }
            }
        }
        unset($line);

        // Enforce available warehouse stock per Part Number
        $requestedPerPn = [];
        foreach ($data['items'] as $idx => $line) {
            $pnKey = mb_strtolower(trim($line['part_number']));
            $requestedPerPn[$pnKey] = ($requestedPerPn[$pnKey] ?? 0) + (int) $line['qty'];
        }

        foreach ($requestedPerPn as $pnKey => $totalRequested) {
            $available = \App\Models\Item::query()
                ->whereIn('status', ['Available', 'In Stock'])
                ->whereNull('reserved_sales_order_item_id')
                ->whereNull('reserved_work_order_item_id')
                ->whereRaw('lower(trim(part_number)) = ?', [$pnKey])
                ->count();

            if ($available < $totalRequested) {
                $origPn = collect($data['items'])->first(fn ($i) => mb_strtolower(trim($i['part_number'])) === $pnKey)['part_number'];
                return back()->withInput()->withErrors([
                    'items' => "Part Number '{$origPn}': Requested quantity ({$totalRequested}) exceeds available warehouse stock ({$available} units in stock).",
                ]);
            }
        }

        if (empty($data['customer_id'])) {
            $companyName = trim((string) ($data['ref_company'] ?? $data['cust_name'] ?? ''));
            if ($companyName !== '') {
                $matchedCust = \App\Models\Customer::where('name', $companyName)->first();
                if ($matchedCust) {
                    $data['customer_id'] = $matchedCust->id;
                }
            }
        }

        $data['work_type'] = filled($data['work_type'] ?? null) ? $data['work_type'] : 'DELIVERY';
        $data['business_type'] = filled($data['business_type'] ?? null) ? $data['business_type'] : 'NEW-PO';
        $data['user_name'] = filled($data['user_name'] ?? null) ? $data['user_name'] : '-';
        $data['user_position'] = filled($data['user_position'] ?? null) ? $data['user_position'] : '-';
        $data['user_phone'] = filled($data['user_phone'] ?? null) ? $data['user_phone'] : '-';
        $data['user_email'] = filled($data['user_email'] ?? null) ? $data['user_email'] : '-';
        $data['user_address'] = filled($data['user_address'] ?? null) ? $data['user_address'] : '-';
        $data['sales_title'] = filled($data['sales_title'] ?? null) ? $data['sales_title'] : 'SALES';
        $data['approver_title'] = filled($data['approver_title'] ?? null) ? $data['approver_title'] : 'OPERATIONAL MANAGER';

        $targetStatus = $request->input('target_item_status', 'Reserved');
        if (! in_array($targetStatus, config('item_options.statuses'), true)) {
            $targetStatus = 'Reserved';
        }
        unset($data['target_item_status']);

        $wo = DB::transaction(function () use ($data, $request, $targetStatus) {
            $wo = WorkOrder::create([
                'wo_number' => WorkOrder::nextNumber(), 'status' => 'issued', 'created_by' => $request->user()->id,
            ] + $data);

            foreach ($data['items'] as $line) {
                $woItem = $wo->items()->create($line);

                // Update warehouse item status to matched status selected on WO creation
                $sns = $woItem->serialList();
                if ($sns->isNotEmpty()) {
                    $units = \App\Models\Item::query()
                        ->whereIn('status', ['Available', 'In Stock'])
                        ->whereNull('reserved_sales_order_item_id')
                        ->whereNull('reserved_work_order_item_id')
                        ->whereRaw('lower(trim(part_number)) = ?', [mb_strtolower(trim($woItem->part_number))])
                        ->whereIn(DB::raw('lower(trim(serial_number))'), $sns->map(fn ($s) => mb_strtolower($s))->all())
                        ->get();
                } else {
                    $units = \App\Models\Item::query()
                        ->whereIn('status', ['Available', 'In Stock'])
                        ->whereNull('reserved_sales_order_item_id')
                        ->whereNull('reserved_work_order_item_id')
                        ->whereRaw('lower(trim(part_number)) = ?', [mb_strtolower(trim($woItem->part_number))])
                        ->orderBy('id')
                        ->limit($woItem->qty)
                        ->get();
                }

                foreach ($units as $unit) {
                    $unit->update([
                        'reserved_work_order_item_id' => $woItem->id,
                        'reserved_at' => now(),
                        'no_wo' => $wo->wo_number,
                        'status' => $targetStatus,
                    ]);
                }
            }

            return $wo;
        });

        NotifyService::toPermission(
            'work-orders',
            "WO {$wo->wo_number} created",
            "{$wo->subject} — awaiting acceptance before proceeding to delivery.",
            route('work-orders.show', $wo),
            $request->user()->id,
        );

        return redirect()->route('work-orders.show', $wo)->with('success', "WO {$wo->wo_number} created successfully.");
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load('items', 'salesOrder.customer', 'createdBy', 'acceptedBy', 'deliveryOrders');

        $known = \App\Models\Item::whereIn('serial_number', $workOrder->items->flatMap(fn ($i) => $i->serialList())->all())->pluck('serial_number')->map(fn ($x) => mb_strtolower($x))->flip();

        return view('work-orders.show', ['wo' => $workOrder, 'knownSn' => $known]);
    }

    public function accept(Request $request, WorkOrder $workOrder)
    {
        if ($workOrder->status === 'accepted') {
            return back()->with('error', 'This WO has already been accepted.');
        }

        $workOrder->update(['status' => 'accepted', 'accepted_by' => $request->user()->id, 'accepted_at' => now()]);

        if ($workOrder->created_by) {
            NotifyService::toUser(
                $workOrder->created_by,
                "WO {$workOrder->wo_number} accepted",
                "Accepted by {$request->user()->name}. Delivery (DO) can now be processed.",
                $workOrder->sales_order_id ? route('sales-orders.show', $workOrder->sales_order_id) : route('work-orders.show', $workOrder),
            );
        }

        return back()->with('success', "WO {$workOrder->wo_number} marked as accepted.");
    }

    public function printPdf(WorkOrder $workOrder, PdfRenderService $pdf)
    {
        $workOrder->load('items');

        ActivityLogger::log('print', "Print WO (PDF) {$workOrder->wo_number}", $workOrder, ['format' => 'PDF']);

        return $pdf->render('pdf.work-order', ['wo' => $workOrder], "{$workOrder->wo_number}.pdf", archiveFor: $workOrder);
    }

    public function print(WorkOrder $workOrder, DocxTemplateService $docx)
    {
        $workOrder->load('items');
        $w = $workOrder;
        $fmt = fn ($d) => $d?->format('d F Y');

        $values = [
            'wo_date' => $fmt($w->wo_date), 'wo_number' => $w->wo_number,
            'ref_number' => $w->ref_number, 'ref_company' => $w->ref_company,
            'work_type' => $w->work_type, 'po_date' => $fmt($w->po_date),
            'rfs_date' => $fmt($w->rfs_date), 'contract_end_date' => $fmt($w->contract_end_date),
            'business_type' => $w->business_type, 'subject' => $w->subject,
            'cust_name' => $w->cust_name, 'cust_position' => $w->cust_position ?: '-',
            'cust_phone' => $w->cust_phone, 'cust_email' => $w->cust_email ?: '-', 'cust_address' => $w->cust_address,
            'user_name' => $w->user_name, 'user_position' => $w->user_position ?: '-',
            'user_phone' => $w->user_phone, 'user_email' => $w->user_email ?: '-', 'user_address' => $w->user_address,
            'sales_name' => $w->sales_name, 'sales_title' => $w->sales_title,
            'approver_name' => $w->approver_name, 'approver_title' => $w->approver_title,
        ];

        $rows = $w->items->map(fn ($i, $idx) => [
            'no' => $idx + 1,
            'type' => trim(($i->item_name && $i->item_name !== $i->part_number ? $i->item_name . ' - ' : '') . $i->type_name . ($i->brand ? ' (' . $i->brand . ')' : '') . ($i->description ? ' - ' . $i->description : '')),
            'part' => $i->part_number, 'qty' => $i->qty,
            'sow' => trim($i->sow_explanation . ($i->serial_numbers ? ($i->sow_explanation ? ' | ' : '') . 'SN: ' . implode(', ', $i->serialList()->all()) : '')),
        ])->all();

        $path = $docx->render(resource_path('doc-templates/wo_template.docx'), $values, $rows);

        ActivityLogger::log('print', "Print WO (Word) {$w->wo_number}", $w, ['format' => 'Word']);

        DocumentArchive::storeFile($path, "{$w->wo_number}.docx", $w);

        return response()->download($path, "{$w->wo_number}.docx")->deleteFileAfterSend();
    }
}
