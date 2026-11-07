<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DecidesApproval;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SerialScan;
use App\Models\Supplier;
use App\Services\DocxTemplateService;
use App\Services\PdfRenderService;
use App\Services\ActivityLogger;
use App\Services\DocumentArchive;
use App\Services\NotifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    use DecidesApproval;

    public function index(Request $request)
    {
        $query = PurchaseOrder::with('supplier')->withCount('items');

        if ($request->filled('status')) {
            $query->whereIn('status', explode(',', $request->status));
        }
        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('po_number', 'like', '%' . $request->q . '%')
                  ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%' . $request->q . '%'));
            });
        }

        $orders = $query->latest('order_date')->paginate(15)->withQueryString();
        $statuses = config('purchasing_options.statuses');

        return view('purchasing.index', compact('orders', 'statuses'));
    }

    public function create(Request $request)
    {
        // Dari Purchase Request: isi baris barang otomatis dari PR yang masih menunggu.
        $pr = $request->filled('pr')
            ? PurchaseRequest::with('items', 'salesOrder')->where('status', 'open')->find($request->pr)
            : null;
        $prefill = $pr ? $pr->items->map(fn ($i) => [
            'item_name' => $i->item_name, 'type_id' => $i->type_id, 'merek' => $i->merek, 'qty_ordered' => $i->qty,
        ])->values() : collect();

        $suppliers = Supplier::orderBy('name')->get();
        $itemTypes = ItemType::orderBy('name')->get();
        $inventories = Inventory::with(['type'])->orderBy('part_number')->get();
        $nextPoNumber = 'PO-' . now()->format('Ym') . '-' . str_pad((PurchaseOrder::whereYear('created_at', now()->year)->count() + 1), 4, '0', STR_PAD_LEFT);

        $inventoryMap = $inventories->mapWithKeys(fn ($inv) => [
            mb_strtoupper((string) $inv->part_number) => [
                'id' => $inv->id,
                'part_number' => $inv->part_number,
                'brand' => $inv->brand ?? '',
                'type_id' => $inv->type_id,
                'type_name' => $inv->type?->name ?? '',
                'warranty' => $inv->warranty ?? '',
                'description' => $inv->description ?? '',
            ],
        ]);

        $typesMap = $itemTypes->mapWithKeys(fn ($t) => [
            mb_strtolower((string) $t->name) => $t->id,
        ]);

        $suppliersMap = $suppliers->mapWithKeys(fn ($s) => [
            mb_strtolower((string) $s->name) => $s->id,
        ]);

        return view('purchasing.create', compact(
            'suppliers', 'itemTypes', 'inventories', 'nextPoNumber',
            'pr', 'prefill', 'inventoryMap', 'typesMap', 'suppliersMap'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'po_number' => 'required|unique:purchase_orders,po_number',
            'purchase_request_id' => 'nullable|exists:purchase_requests,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'nullable|string|max:255',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.part_number' => 'nullable|string|max:255',
            'items.*.inventory_id' => 'nullable|exists:inventories,id',
            'items.*.item_name' => 'required|string',
            'items.*.description' => 'nullable|string',
            'items.*.type_id' => 'required_without:items.*.type_name|nullable|exists:item_types,id',
            'items.*.type_name' => 'nullable|string|max:255',
            'items.*.merek' => 'nullable|string|max:255',
            'items.*.warranty' => 'nullable|integer|min:0|max:240',
            'items.*.qty_ordered' => 'required|integer|min:1',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ], [
            'supplier_name.required' => 'Supplier is required.',
            'items.*.type_id.required_without' => 'Item Type is required for each item row.',
        ]);

        // Resolve supplier dari ID atau input teks nama supplier
        $supplierId = $validated['supplier_id'] ?? null;
        if (! $supplierId && ! empty($validated['supplier_name'])) {
            $supplier = Supplier::firstOrCreate(['name' => trim($validated['supplier_name'])]);
            $supplierId = $supplier->id;
        }

        if (! $supplierId) {
            throw ValidationException::withMessages([
                'supplier_name' => 'Supplier is required.',
            ]);
        }

        $po = DB::transaction(function () use ($validated, $supplierId, $request) {
            $po = PurchaseOrder::create([
                'po_number' => $validated['po_number'],
                'supplier_id' => $supplierId,
                'purchase_request_id' => $validated['purchase_request_id'] ?? null,
                'order_date' => $validated['order_date'],
                'expected_date' => $validated['expected_date'] ?? null,
                'status' => 'draft', // awaiting approval
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $line) {
                // Resolve type
                $typeId = $line['type_id'] ?? null;
                if (! empty($line['type_name'])) {
                    $type = ItemType::firstOrCreate(['name' => trim($line['type_name'])]);
                    $typeId = $type->id;
                }

                $partNumber = trim($line['part_number'] ?? '') ?: null;
                $inventoryId = $line['inventory_id'] ?? null;
                if (! $inventoryId && $partNumber) {
                    $inv = Inventory::where('part_number', $partNumber)->first();
                    if ($inv) {
                        $inventoryId = $inv->id;
                        $typeId ??= $inv->type_id;
                    }
                }

                $po->items()->create([
                    'inventory_id' => $inventoryId,
                    'part_number' => $partNumber,
                    'item_name' => $line['item_name'],
                    'description' => $line['description'] ?? null,
                    'type_id' => $typeId,
                    'merek' => $line['merek'] ?? null,
                    'warranty' => $line['warranty'] ?? null,
                    'qty_ordered' => $line['qty_ordered'],
                    'unit' => trim($line['unit'] ?? '') ?: 'pcs',
                    'unit_price' => $line['unit_price'] ?? null,
                ]);
            }

            // PR yang jadi dasar PO ini otomatis berstatus "Sudah jadi PO".
            if (! empty($validated['purchase_request_id'])) {
                PurchaseRequest::where('id', $validated['purchase_request_id'])->where('status', 'open')->update(['status' => 'ordered']);
            }

            return $po;
        });

        NotifyService::toPermission('purchasing.approve', "PO {$po->po_number} awaiting approval",
            "Supplier: {$po->supplier->name}", route('purchasing.show', $po), $request->user()->id);

        return redirect()->route('purchasing.show', $po)->with('success', "PO {$po->po_number} created and awaiting approval.");
    }

    public function approve(Request $request, PurchaseOrder $purchasing)
    {
        $po = $purchasing;
        $error = $this->decide($request, $po, true, 'draft', 'ordered', "PO {$po->po_number}", route('purchasing.show', $po));

        return $error ? back()->with('error', $error) : back()->with('success', "PO {$po->po_number} approved. Items can be received upon arrival.");
    }

    public function reject(Request $request, PurchaseOrder $purchasing)
    {
        $po = $purchasing;
        $error = $this->decide($request, $po, false, 'draft', 'ordered', "PO {$po->po_number}", route('purchasing.show', $po));
        if ($error) {
            return back()->with('error', $error);
        }

        $po->releasePurchaseRequest();

        return back()->with('success', "PO {$po->po_number} rejected.");
    }

    public function show(PurchaseOrder $purchasing)
    {
        $purchasing->load('supplier', 'items.type', 'items.inventory', 'purchaseRequest.salesOrder', 'units.type', 'units.location', 'createdBy', 'approvedBy');
        $locations = Location::orderBy('id')->get();
        $defaultLocation = $locations->firstWhere('name', 'Stuff In') ?? $locations->first();

        return view('purchasing.show', ['po' => $purchasing, 'locations' => $locations, 'defaultLocation' => $defaultLocation]);
    }

    public function print(PurchaseOrder $purchasing, DocxTemplateService $docx)
    {
        $purchasing->load('supplier', 'items.type', 'purchaseRequest', 'createdBy');
        $po = $purchasing;
        $grand = $po->items->sum(fn ($l) => $l->qty_ordered * $l->unit_price);

        $values = [
            'po_number' => $po->po_number,
            'supplier_name' => $po->supplier->name, 'supplier_phone' => $po->supplier->phone,
            'supplier_address' => $po->supplier->address,
            'order_date' => $po->order_date->format('d F Y'),
            'expected_date' => $po->expected_date?->format('d F Y') ?: '-',
            'status_label' => config('purchasing_options.status_labels.' . $po->status),
            'pr_number' => $po->purchaseRequest->pr_number ?? '-',
            'grand_total' => 'Rp ' . number_format($grand, 0, ',', '.'),
            'notes' => $po->notes ?: '-',
            'buyer_name' => $po->createdBy->name ?? '',
        ];
        $rows = $po->items->map(fn ($l, $i) => [
            'no' => $i + 1, 'name' => $l->item_name,
            'type_merek' => trim(($l->type->name ?? '-') . ($l->merek ? " · {$l->merek}" : '')),
            'qty' => $l->qty_ordered, 'price' => 'Rp ' . number_format($l->unit_price, 0, ',', '.'),
            'subtotal' => 'Rp ' . number_format($l->qty_ordered * $l->unit_price, 0, ',', '.'),
        ])->all();

        $path = $docx->render(resource_path('doc-templates/po_template.docx'), $values, $rows);

        ActivityLogger::log('print', "Print PO (Word) {$po->po_number}", $po, ['format' => 'Word']);

        DocumentArchive::storeFile($path, "{$po->po_number}.docx", $po);

        return response()->download($path, "{$po->po_number}.docx")->deleteFileAfterSend();
    }

    public function printPdf(PurchaseOrder $purchasing, PdfRenderService $pdf)
    {
        $purchasing->load('supplier', 'items.type', 'purchaseRequest', 'createdBy');

        ActivityLogger::log('print', "Print PO (PDF) {$purchasing->po_number}", $purchasing, ['format' => 'PDF']);

        return $pdf->render('pdf.purchase-order', ['po' => $purchasing], "{$purchasing->po_number}.pdf", archiveFor: $purchasing);
    }

    /**
     * Terima barang: qty diterima dicatat di PO, dan tiap unit langsung dibuatkan di Data Master
     * (In Stock, lokasi yang dipilih, No. PO/SPR = nomor PO ini). SN opsional, 1 baris = 1 unit.
     */
    public function receive(Request $request, PurchaseOrder $purchasing)
    {
        if (! $purchasing->canReceive()) {
            return back()->with('error', 'Items can only be received from approved and unfulfilled Purchase Orders.');
        }

        $validated = $request->validate([
            'receive' => 'required|array',
            'receive.*' => 'nullable|integer|min:0',
            'serials' => 'nullable|array',
            'serials.*' => 'nullable|string',
            'location_id' => 'required|exists:locations,id',
        ], [
            'location_id.required' => 'Warehouse storage location is required.',
        ]);

        // Cek semua baris dulu sebelum ada yang disimpan, biar gak ada penerimaan setengah jadi.
        $plan = [];
        $errors = [];
        $seen = [];
        foreach ($validated['receive'] as $lineId => $qty) {
            $line = $qty ? $purchasing->items()->with(['type', 'inventory'])->find($lineId) : null;
            $qty = $line ? min((int) $qty, $line->qty_outstanding) : 0;
            if ($qty <= 0) {
                continue;
            }

            $serials = collect(preg_split('/[\r\n,]+/', $validated['serials'][$lineId] ?? ''))
                ->map(fn ($sn) => trim($sn))->filter()->values();
            $key = "receive.{$lineId}";

            if (! $line->type) {
                $errors[$key] = "{$line->item_name}: Type is not set in PO, units cannot be created in Inventory.";
            } elseif ($serials->count() > $qty) {
                $errors[$key] = "{$line->item_name}: {$serials->count()} Serial Numbers provided, but only {$qty} units being received.";
            } elseif ($dupe = $serials->first(fn ($sn, $i) => isset($seen[mb_strtolower($sn)]) || $serials->search(fn ($o) => strcasecmp($o, $sn) === 0) !== $i)) {
                $errors[$key] = "{$line->item_name}: Duplicate Serial Number {$dupe} in input.";
            } elseif ($taken = Item::whereIn('serial_number', $serials)->pluck('serial_number')->implode(', ')) {
                $errors[$key] = "{$line->item_name}: Serial Number already registered in system ({$taken}).";
            }

            $serials->each(function ($sn) use (&$seen) { $seen[mb_strtolower($sn)] = true; });
            $plan[] = [$line, $qty, $serials];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if (! $plan) {
            return back()->with('error', 'Please specify received quantity for at least one item.');
        }

        $location = Location::find($validated['location_id']);
        $units = DB::transaction(function () use ($plan, $purchasing, $location, $request) {
            $units = collect();
            foreach ($plan as [$line, $qty, $serials]) {
                $line->update(['qty_received' => $line->qty_received + $qty]);
                $batchCode = (string) \Illuminate\Support\Str::uuid();

                for ($i = 0; $i < $qty; $i++) {
                    $unit = Item::create([
                        'item_id' => Item::nextItemId($line->type->name),
                        'item_name' => $line->item_name,
                        'inventory_id' => $line->inventory_id,
                        'part_number' => $line->part_number,
                        'type_id' => $line->type_id,
                        'merek' => $line->merek,
                        'warranty' => $line->warranty,
                        'serial_number' => $serials[$i] ?? null,
                        'status' => 'Available',
                        'condition' => 'New',
                        'unit' => $line->unit ?: 'pcs',
                        'stuff_in' => now()->toDateString(),
                        'no_po_spr' => $purchasing->po_number,
                        'location_id' => $location->id,
                        'rack' => null,
                        'notes' => $line->description ?: "Received from {$purchasing->po_number}",
                        'batch_code' => $batchCode,
                    ]);

                    // Unit ber-SN langsung tercatat "masuk" di histori Scan Serial Number.
                    if ($unit->serial_number) {
                        SerialScan::create([
                            'item_id' => $unit->id, 'serial_number' => $unit->serial_number, 'direction' => 'in',
                            'location_id' => $location->id, 'scanned_by' => $request->user()->id,
                            'notes' => "Inbound receipt {$purchasing->po_number}", 'scanned_at' => now(),
                        ]);
                    }
                    $units->push($unit);
                }
            }
            $purchasing->refreshStatus();

            return $units;
        });

        $withoutSn = $units->whereNull('serial_number')->count();

        return back()->with('success', "Inbound receipt recorded: {$units->count()} units added to Warehouse Inventory (Available, {$location->name})."
            . ($withoutSn ? " {$withoutSn} units missing Serial Number, can be updated via Item Edit." : ''));
    }

    public function cancel(PurchaseOrder $purchasing)
    {
        if (! in_array($purchasing->status, ['draft', 'ordered', 'partial'], true)) {
            return back()->with('error', 'This PO is completed or rejected, cannot be cancelled.');
        }

        $purchasing->update(['status' => 'cancelled']);
        $purchasing->releasePurchaseRequest();

        return back()->with('success', 'PO cancelled.');
    }
}
