<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SerialScan;
use App\Models\WorkOrder;
use App\Services\DocxTemplateService;
use App\Services\PdfRenderService;
use App\Services\ActivityLogger;
use App\Services\DocumentArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryOrder::with('salesOrder.customer', 'workOrder')->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Accepted Work Orders that still have units to ship and no DO in picking: DO can start from here (no Sales Order needed).
        $ready = WorkOrder::with('items', 'deliveryOrders')->where('status', 'accepted')->latest('id')->get()
            ->filter(fn ($w) => $w->items->sum(fn ($i) => $i->remaining) > 0 && ! $w->deliveryOrders->contains('status', 'picking'))
            ->values();

        return view('delivery-orders.index', [
            'readyWorkOrders' => $ready,
            'orders' => $query->latest('id')->paginate(15)->withQueryString(),
            'statuses' => array_keys(config('sales_options.do_status_labels')),
        ]);
    }

    /** Create DO from SO: lock matching In Stock units (partial if stock shortage). */
    public function store(Request $request, SalesOrder $salesOrder)
    {
        if (! in_array($salesOrder->status, ['approved', 'processing'], true)) {
            return back()->with('error', 'Only approved SOs can be processed for delivery.');
        }
        if ($salesOrder->deliveryOrders()->where('status', 'picking')->exists()) {
            return back()->with('error', 'There is already an active Delivery Order in picking for this SO. Please complete or cancel it first.');
        }
        if (! $salesOrder->hasAcceptedWorkOrder()) {
            return back()->with('error', 'Create a Work Order first and wait for it to be accepted before processing delivery.');
        }

        try {
            $do = DB::transaction(function () use ($salesOrder, $request) {
                $do = DeliveryOrder::create([
                    'do_number' => DeliveryOrder::nextNumber(),
                    'sales_order_id' => $salesOrder->id,
                    'status' => 'picking',
                    'created_by' => $request->user()->id,
                ]);

                $picked = 0;
                foreach ($salesOrder->items as $line) {
                    $need = $line->remaining;
                    if ($need < 1) {
                        continue;
                    }

                    $units = $line->matchingUnits()
                        ->whereIn('status', ['Available', 'In Stock'])
                        ->whereNull('reserved_sales_order_item_id')
                        ->whereNull('reserved_work_order_item_id')
                        ->orderBy('id')
                        ->limit($need)
                        ->lockForUpdate()
                        ->get();

                    foreach ($units as $unit) {
                        $unit->update(['reserved_sales_order_item_id' => $line->id, 'reserved_at' => now()]);
                        $do->items()->create(['item_id' => $unit->id, 'sales_order_item_id' => $line->id]);
                        $picked++;
                    }
                }

                if ($picked === 0) {
                    throw new \RuntimeException('no-stock'); // rollback: do not save empty DO
                }

                $salesOrder->update(['status' => 'processing']);

                return $do;
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', 'No In Stock units available. Create a Purchase Order first for missing items.');
        }

        return redirect()->route('delivery-orders.show', $do)
            ->with('success', "DO {$do->do_number} created, {$do->items()->count()} unit(s) reserved. Please scan SN for each unit.");
    }

    /** Create a DO straight from an accepted Work Order (PO -> WO -> DO, no Sales Order needed). */
    public function storeFromWorkOrder(Request $request, WorkOrder $workOrder)
    {
        if ($workOrder->status !== 'accepted') {
            return back()->with('error', 'The Work Order must be accepted before a Delivery Order can be created.');
        }
        if ($workOrder->deliveryOrders()->where('status', 'picking')->exists()) {
            return back()->with('error', 'There is already an active Delivery Order in picking for this WO. Please complete or cancel it first.');
        }

        $workOrder->load('items');
        $missing = [];

        try {
            $do = DB::transaction(function () use ($workOrder, $request, &$missing) {
                $do = DeliveryOrder::create([
                    'do_number' => DeliveryOrder::nextNumber(),
                    'work_order_id' => $workOrder->id,
                    'status' => 'picking',
                    'created_by' => $request->user()->id,
                ]);

                $picked = 0;
                foreach ($workOrder->items as $line) {
                    $need = $line->remaining;
                    if ($need < 1) {
                        continue;
                    }

                    $units = collect();
                    $sns = $line->serialList();
                    if ($sns->isNotEmpty()) {
                        // The WO names the exact serial numbers: lock those units.
                        foreach ($sns as $sn) {
                            $unit = $line->matchingUnits()->whereRaw('lower(serial_number) = ?', [mb_strtolower($sn)])->lockForUpdate()->first();
                            $unit ? $units->push($unit) : $missing[] = "{$sn} (not in stock or already locked)";
                        }
                    } else {
                        $units = $line->matchingUnits()->orderBy('id')->limit($need)->lockForUpdate()->get();
                        if ($units->count() < $need) {
                            $missing[] = ($line->part_number ?: $line->item_name) . ': short by ' . ($need - $units->count());
                        }
                    }

                    foreach ($units->take($need) as $unit) {
                        $unit->update(['reserved_work_order_item_id' => $line->id, 'reserved_at' => now()]);
                        $do->items()->create(['item_id' => $unit->id, 'work_order_item_id' => $line->id]);
                        $picked++;
                    }
                }

                if ($picked === 0) {
                    throw new \RuntimeException('no-stock');
                }

                return $do;
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', 'No matching In Stock units available' . ($missing ? ': ' . implode('; ', $missing) : '') . '. Receive the items first (Purchase Order or Import).');
        }

        $msg = "DO {$do->do_number} created, {$do->items()->count()} unit(s) reserved. Please scan SN for each unit.";
        if ($missing) {
            $msg .= ' Not reserved: ' . implode('; ', $missing) . '.';
        }

        return redirect()->route('delivery-orders.show', $do)->with('success', $msg);
    }

    public function show(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load('salesOrder.customer', 'workOrder', 'items.item.location', 'items.salesOrderItem', 'createdBy', 'deliveredBy');

        return view('delivery-orders.show', ['do' => $deliveryOrder]);
    }

    public function scan(Request $request, DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'picking') {
            return back()->with('error', 'This DO is no longer in the picking stage.');
        }

        $request->validate(['serial_number' => 'required|string|max:255'], ['serial_number.required' => 'Please scan or enter a Serial Number.']);
        $sn = trim($request->input('serial_number'));

        $rows = $deliveryOrder->items()->with('item')
            ->whereHas('item', fn ($q) => $q->whereRaw('lower(serial_number) = ?', [mb_strtolower($sn)]))
            ->get();

        if ($rows->isEmpty()) {
            ActivityLogger::log('scan', "Scan SN {$sn} REJECTED on {$deliveryOrder->do_number} (not in picking list)", $deliveryOrder, ['serial_number' => $sn, 'ditolak' => true]);

            return back()->withInput()->with('error', "SN {$sn} is not in this DO picking list (incorrect item or unreserved unit).");
        }

        $row = $rows->firstWhere('scanned_at', null);
        if (! $row) {
            return back()->with('error', "SN {$sn} has already been scanned.");
        }

        $row->update(['scanned_at' => now(), 'scanned_by' => $request->user()->id]);
        ActivityLogger::log('scan', "Scan SN {$sn} ({$row->item->item_name}) on {$deliveryOrder->do_number}", $deliveryOrder, ['serial_number' => $sn, 'item_id' => $row->item->item_id]);

        return back()->with('success', "SN {$sn} ({$row->item->item_name}) OK.");
    }

    /** For units without SN: marked as picked manually. */
    public function pick(Request $request, DeliveryOrder $deliveryOrder, DeliveryOrderItem $doItem)
    {
        abort_unless($doItem->delivery_order_id === $deliveryOrder->id, 404);

        if ($deliveryOrder->status !== 'picking') {
            return back()->with('error', 'This DO is no longer in the picking stage.');
        }

        if (filled($doItem->item->serial_number)) {
            return back()->with('error', 'This unit has a Serial Number and must be scanned.');
        }

        $doItem->update(['scanned_at' => now(), 'scanned_by' => $request->user()->id]);
        ActivityLogger::log('scan', "Marked as picked (without SN) {$doItem->item->item_id} on {$deliveryOrder->do_number}", $deliveryOrder, ['item_id' => $doItem->item->item_id]);

        return back()->with('success', "{$doItem->item->item_id} marked as picked.");
    }

    public function ship(Request $request, DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'picking') {
            return back()->with('error', 'This DO is no longer in the picking stage.');
        }

        $deliveryOrder->load('items.item');
        $left = $deliveryOrder->items->whereNull('scanned_at')->count();
        if ($left > 0) {
            return back()->with('error', "There are still {$left} unit(s) that have not been scanned or picked.");
        }

        DB::transaction(function () use ($deliveryOrder, $request) {
            foreach ($deliveryOrder->items as $row) {
                $item = $row->item;

                $item->update([
                    'status' => 'Sold Out',
                    'stuff_out' => now()->toDateString(),
                    'reserved_sales_order_item_id' => null,
                    'reserved_work_order_item_id' => null,
                    'reserved_at' => null,
                ]);

                if (filled($item->serial_number)) {
                    SerialScan::create([
                        'item_id' => $item->id,
                        'serial_number' => $item->serial_number,
                        'direction' => 'out',
                        'location_id' => $item->location_id,
                        'scanned_by' => $request->user()->id,
                        'notes' => "Delivery {$deliveryOrder->do_number}",
                        'scanned_at' => now(),
                    ]);
                }
            }

            $deliveryOrder->update(['status' => 'delivered', 'delivered_at' => now(), 'delivered_by' => $request->user()->id]);

            if ($deliveryOrder->workOrder) {
                $wo = $deliveryOrder->workOrder;
                $wo->load('items');
                $allWoDelivered = $wo->items->every(fn ($l) => $l->delivered_qty >= $l->qty);
                if ($allWoDelivered) {
                    $wo->update(['status' => 'delivered']);
                }
            }

            $this->refreshSalesOrderStatus($deliveryOrder->salesOrder);
        });

        return back()->with('success', "DO {$deliveryOrder->do_number} delivered. Unit stock has been reduced.");
    }

    public function cancel(DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'picking') {
            return back()->with('error', 'Only DOs in picking stage can be cancelled.');
        }

        DB::transaction(function () use ($deliveryOrder) {
            Item::whereIn('id', $deliveryOrder->items()->pluck('item_id'))
                ->update(['reserved_sales_order_item_id' => null, 'reserved_work_order_item_id' => null, 'reserved_at' => null]);

            $deliveryOrder->update(['status' => 'cancelled']);

            $this->refreshSalesOrderStatus($deliveryOrder->salesOrder);
        });

        return back()->with('success', "DO {$deliveryOrder->do_number} cancelled, reserved units have been released.");
    }

    /** Fully delivered -> delivered; partially delivered/locked -> processing; none -> approved. */
    private function refreshSalesOrderStatus(?SalesOrder $so): void
    {
        if (! $so) {
            return;   // DO created from a Work Order has no Sales Order
        }

        $so->load('items');

        $allDelivered = $so->items->every(fn ($l) => $l->delivered_qty >= $l->qty);
        $anyProgress = $so->items->contains(fn ($l) => $l->delivered_qty > 0 || $l->reserved_qty > 0);

        $so->update(['status' => $allDelivered ? 'delivered' : ($anyProgress ? 'processing' : 'approved')]);
    }

    public function printPdf(DeliveryOrder $deliveryOrder, PdfRenderService $pdf)
    {
        $deliveryOrder->load('items.item.type', 'items.workOrderItem', 'items.salesOrderItem', 'salesOrder.customer', 'workOrder');

        ActivityLogger::log('print', "Print DO (PDF) {$deliveryOrder->do_number}", $deliveryOrder, ['format' => 'PDF']);

        return $pdf->render('pdf.delivery-order', ['do' => $deliveryOrder], "{$deliveryOrder->do_number}.pdf", archiveFor: $deliveryOrder);
    }

    public function print(DeliveryOrder $deliveryOrder, DocxTemplateService $docx)
    {
        $deliveryOrder->load('items.item.type', 'items.workOrderItem', 'items.salesOrderItem', 'salesOrder.customer', 'workOrder');
        $do = $deliveryOrder;
        $so = $do->salesOrder;
        $wo = $do->workOrder;

        $values = [
            'do_number' => $do->do_number,
            'shipping_date' => optional($do->delivered_at ?? now())->format('d F Y'),
            'received_date' => '',
            'customer_po' => $so?->payment_terms ?: ($wo?->ref_number ?: '-'),
            'project_name' => $so?->notes ?: ($wo?->subject ?: '-'),
            'ship_to' => $so?->delivery_address ?: ($so?->customer->address ?? ($wo?->cust_address ?: '-')),
        ];

        $rows = $do->items->map(fn ($row, $idx) => [
            'no' => $idx + 1,
            'type' => $row->item->type->name ?? '-',
            'description' => trim($row->item->item_name . ' ' . $row->item->merek),
            'sn' => $row->item->serial_number ?: '-',
            'qty' => 1,
            'remarks' => '',
        ])->all();

        $path = $docx->render(resource_path('doc-templates/do_template.docx'), $values, $rows);

        ActivityLogger::log('print', "Print DO (Word) {$do->do_number}", $do, ['format' => 'Word']);

        DocumentArchive::storeFile($path, "{$do->do_number}.docx", $do);

        return response()->download($path, "{$do->do_number}.docx")->deleteFileAfterSend();
    }
}
