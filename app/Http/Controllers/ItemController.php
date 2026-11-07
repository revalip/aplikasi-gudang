<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsXlsx;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Rack;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    use ExportsXlsx;

    public function index(Request $request)
    {
        $locations = Location::withCount('items')->orderBy('id')->get();
        $totalItemsCount = Item::count();
        $locationParam = $request->query('location');
        $current = ($locationParam && $locationParam !== 'all')
            ? $locations->firstWhere('id', (int) $locationParam)
            : null;

        $unitQuery = fn () => Item::query()
            ->when($current, fn ($q) => $q->where('location_id', $current->id))
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = $request->status;
                if ($status === 'Available') {
                    $query->whereIn('status', ['Available', 'In Stock']);
                } elseif ($status === 'Damaged') {
                    $query->whereIn('status', ['Damaged', 'Broken']);
                } elseif ($status === 'Rented') {
                    $query->whereIn('status', ['Rented', 'Borrow']);
                } elseif ($status === 'Under Service') {
                    $query->whereIn('status', ['Under Service', 'Has not been tested', 'Retur device to supplier', 'Temporarity unavailable']);
                } elseif ($status === 'Reserved') {
                    $query->whereIn('status', ['Reserved', 'Instalation']);
                } elseif ($status === 'Lost') {
                    $query->whereIn('status', ['Lost', 'Sold Out', 'Re-purchase needed']);
                } else {
                    $query->whereIn('status', explode(',', $status));
                }
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->q . '%';
                $query->where(fn ($q) => $q->where('item_name', 'like', $term)
                    ->orWhere('item_id', 'like', $term)
                    ->orWhere('serial_number', 'like', $term)
                    ->orWhere('merek', 'like', $term)
                    ->orWhere('part_number', 'like', $term)
                    ->orWhere('rack', 'like', $term)
                    ->orWhere('shelf', 'like', $term));
            });

        // One row per Part Number (units of the same part number are grouped; click a row to see the units).
        $groups = $unitQuery()
            ->selectRaw("coalesce(part_number, '') as pn, min(item_name) as item_name, min(type_id) as type_id, min(merek) as merek, count(*) as qty,
                sum(case when status in ('Available', 'In Stock') then 1 else 0 end) as in_stock,
                sum(case when status in ('Available', 'In Stock') and `condition` = 'New' then 1 else 0 end) as new_qty,
                sum(case
                    when status in ('Sold Out', 'Lost') or stuff_out is not null then 1
                    when status in ('Rented', 'Borrow', 'Instalation') then 1
                    when status in ('Available', 'In Stock') and (`condition` != 'New' or `condition` is null) then 1
                    else 0
                end) as used_qty")
            ->groupByRaw("coalesce(part_number, ''), case when part_number is null or part_number = '' then item_name else '' end")
            ->orderByRaw("coalesce(part_number, '')")
            ->paginate(15)
            ->withQueryString();

        $types = ItemType::pluck('name', 'id');
        $groups->getCollection()->transform(function ($g) use ($types) {
            $g->type_name = $types[$g->type_id] ?? null;

            return $g;
        });

        $perStatus = Item::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $availableCount = ($perStatus['Available'] ?? 0) + ($perStatus['In Stock'] ?? 0);
        $damagedCount = ($perStatus['Damaged'] ?? 0) + ($perStatus['Broken'] ?? 0);
        $serviceCount = ($perStatus['Under Service'] ?? 0) + ($perStatus['Has not been tested'] ?? 0) + ($perStatus['Retur device to supplier'] ?? 0) + ($perStatus['Temporarity unavailable'] ?? 0);
        $rentedCount = ($perStatus['Rented'] ?? 0) + ($perStatus['Borrow'] ?? 0);

        return view('items.index', [
            'locations' => $locations,
            'totalItemsCount' => $totalItemsCount,
            'current' => $current,
            'groups' => $groups,
            'summary' => [
                'All locations' => $perStatus->sum(),
                'Available' => $availableCount,
                'Under Service' => $serviceCount,
                'Damaged' => $damagedCount,
                'Rented' => $rentedCount,
            ],
        ]);
    }

    /** All units of one Part Number with flexible filters (SN, Name, PN, Condition, Status). */
    public function part(Request $request)
    {
        $pn = trim((string) $request->query('pn', $request->query('part_number', '')));
        $name = trim((string) $request->query('name', $request->query('item_name', '')));
        $sn = trim((string) $request->query('sn', $request->query('serial_number', '')));
        $condition = trim((string) $request->query('condition', ''));
        $status = trim((string) $request->query('status', ''));
        $qTerm = trim((string) $request->query('q', ''));
        $locationId = $request->query('location', $request->query('location_id'));
        $location = $locationId ? Location::find((int) $locationId) : null;

        $query = Item::query()->with(['type', 'location', 'inventory']);

        // Global keyword search if supplied (e.g. from top nav search)
        if ($qTerm !== '') {
            $query->where(function ($w) use ($qTerm) {
                $w->where('serial_number', 'like', "%{$qTerm}%")
                    ->orWhere('part_number', 'like', "%{$qTerm}%")
                    ->orWhere('item_name', 'like', "%{$qTerm}%");
            });
        }

        // Specific field filters
        if ($pn !== '') {
            $query->where('part_number', 'like', "%{$pn}%");
        }
        if ($name !== '') {
            $query->where('item_name', 'like', "%{$name}%");
        }
        if ($sn !== '') {
            $query->where('serial_number', 'like', "%{$sn}%");
        }
        if ($condition !== '' && $condition !== 'all') {
            $query->where('condition', $condition);
        }
        if ($status !== '' && $status !== 'all') {
            if ($status === 'Available') {
                $query->whereIn('status', ['Available', 'In Stock']);
            } elseif ($status === 'Damaged') {
                $query->whereIn('status', ['Damaged', 'Broken']);
            } elseif ($status === 'Rented') {
                $query->whereIn('status', ['Rented', 'Borrow']);
            } elseif ($status === 'Under Service') {
                $query->whereIn('status', ['Under Service', 'Has not been tested', 'Retur device to supplier', 'Temporarity unavailable']);
            } elseif ($status === 'Sold Out') {
                $query->where(fn ($w) => $w->where('status', 'Sold Out')->orWhereNotNull('stuff_out'));
            } else {
                $query->whereIn('status', explode(',', $status));
            }
        }
        if ($location) {
            $query->where('location_id', $location->id);
        }

        $units = $query->orderBy('item_id')->paginate(25)->withQueryString();

        // Check if viewing serialized or non-serialized (quantity-based) items
        $hasSerial = true;
        if ($pn !== '') {
            $inv = Inventory::where('part_number', $pn)->first();
            if ($inv && $inv->is_serialized === false) {
                $hasSerial = false;
            } elseif ($units->isNotEmpty() && ! $units->contains(fn ($u) => filled($u->serial_number))) {
                $hasSerial = false;
            }
        } elseif ($units->isNotEmpty() && ! $units->contains(fn ($u) => filled($u->serial_number))) {
            $hasSerial = false;
        }

        $first = $units->first() ?: ($pn !== '' ? Item::where('part_number', $pn)->first() : null);

        return view('items.part', [
            'pn' => $pn,
            'name' => $first?->item_name ?: $name,
            'type' => $first?->type,
            'brand' => $first?->merek,
            'units' => $units,
            'location' => $location,
            'locations' => Location::orderBy('name')->get(),
            'status' => $status,
            'condition' => $condition,
            'sn' => $sn,
            'q' => $qTerm,
            'hasSerial' => $hasSerial,
            'conditions' => config('item_options.conditions'),
            'statuses' => config('item_options.statuses'),
            'totalAll' => $units->total(),
        ]);
    }

    public function show(Item $item)
    {
        $item->load(['type', 'location']);

        $logs = \App\Models\ActivityLog::where('subject_type', $item->activityName())
            ->where('subject_id', $item->id)->latest('created_at')->latest('id')->get();
        $createdLog = $logs->where('event', 'created')->last();
        if (! $createdLog && filled($item->batch_code)) {
            // Imported units: the single import summary log carries the batch code.
            $createdLog = \App\Models\ActivityLog::where('event', 'import')->latest('id')->limit(500)->get()
                ->first(fn ($l) => ($l->properties['batch_code'] ?? null) === $item->batch_code);
        }

        $po = filled($item->no_po_spr)
            ? PurchaseOrder::with('supplier')->where('po_number', $item->no_po_spr)->first()
            : null;

        // Units entered together with this one (same submit / PO receipt / import).
        $batchMates = Item::query()->where('id', '!=', $item->id)
            ->when(filled($item->batch_code),
                fn ($q) => $q->where('batch_code', $item->batch_code),
                fn ($q) => $q->whereNotNull('no_po_spr')->where('no_po_spr', $item->no_po_spr)
                    ->where('part_number', $item->part_number)
                    ->whereBetween('created_at', [$item->created_at?->copy()->subMinute(), $item->created_at?->copy()->addMinute()]))
            ->orderBy('item_id')->get(['id', 'item_id', 'serial_number']);

        // Same part number by purchase order: shows whether this unit is new stock or older stock.
        $stockByPo = Item::query()
            ->when(filled($item->part_number),
                fn ($q) => $q->where('part_number', $item->part_number),
                fn ($q) => $q->where(fn ($w) => $w->whereNull('part_number')->orWhere('part_number', ''))->where('item_name', $item->item_name))
            ->selectRaw("coalesce(no_po_spr, '') as po, count(*) as qty, min(created_at) as first_in")
            ->groupBy(DB::raw("coalesce(no_po_spr, '')"))->orderBy('first_in')->get();
        $pos = PurchaseOrder::with('supplier')->whereIn('po_number', $stockByPo->pluck('po')->filter()->all())->get()->keyBy('po_number');

        // New vs old stock: is this unit part of the most recent batch/PO entered for its part number?
        $latest = Item::query()
            ->when(filled($item->part_number),
                fn ($q) => $q->where('part_number', $item->part_number),
                fn ($q) => $q->where(fn ($w) => $w->whereNull('part_number')->orWhere('part_number', ''))->where('item_name', $item->item_name))
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        $isNewStock = false;
        if ($latest) {
            $hasA = filled($item->batch_code);
            $hasB = filled($latest->batch_code);
            if ($latest->id === $item->id) {
                $isNewStock = true;
            } elseif ($hasA && $hasB) {
                $isNewStock = $item->batch_code === $latest->batch_code;
            } elseif ($hasA || $hasB) {
                $isNewStock = false;
            } elseif (filled($item->no_po_spr) && filled($latest->no_po_spr)) {
                $isNewStock = $item->no_po_spr === $latest->no_po_spr;
            } else {
                $isNewStock = abs($latest->created_at->diffInSeconds($item->created_at)) <= 60;
            }
        }

        return view('items.show', [
            'isNewStock' => $isNewStock,
            'item' => $item, 'logs' => $logs, 'scans' => $item->serialScans()->with(['location', 'scannedBy'])->get(),
            'po' => $po, 'createdLog' => $createdLog, 'batchMates' => $batchMates,
            'stockByPo' => $stockByPo, 'pos' => $pos,
        ]);
    }

    public function search(Request $request)
    {
        $filters = $request->only(['q', 'item_name', 'part_number', 'type_id', 'merek', 'status']);

        $query = Item::query()
            ->selectRaw("coalesce(part_number, '') as part_number,
                         min(item_name) as item_name,
                         min(type_id) as type_id,
                         min(merek) as merek,
                         count(*) as total_units,
                         sum(case when status in ('Available', 'In Stock') and reserved_sales_order_item_id is null and reserved_work_order_item_id is null then 1 else 0 end) as in_stock_units,
                         sum(case when reserved_sales_order_item_id is not null or reserved_work_order_item_id is not null then 1 else 0 end) as reserved_units,
                         sum(case when status in ('Lost', 'Sold Out') or stuff_out is not null then 1 else 0 end) as sold_units,
                         case
                             when sum(case when status in ('Available', 'In Stock') and reserved_sales_order_item_id is null and reserved_work_order_item_id is null then 1 else 0 end) > 0 then 'Available'
                             when sum(case when reserved_sales_order_item_id is not null or reserved_work_order_item_id is not null then 1 else 0 end) > 0 then 'Reserved'
                             when sum(case when status in ('Lost', 'Sold Out') or stuff_out is not null then 1 else 0 end) > 0 then 'Sold Out'
                             else min(status)
                         end as status")
            ->with('type')
            ->when($filters['q'] ?? null, function ($q, $term) {
                $t = "%{$term}%";
                $q->where(fn ($sub) => $sub->where('part_number', 'like', $t)
                    ->orWhere('item_name', 'like', $t)
                    ->orWhere('merek', 'like', $t)
                    ->orWhere('serial_number', 'like', $t));
            })
            ->when($filters['part_number'] ?? null, fn ($q, $v) => $q->where('part_number', 'like', "%{$v}%"))
            ->when($filters['item_name'] ?? null, fn ($q, $v) => $q->where('item_name', 'like', "%{$v}%"))
            ->when($filters['type_id'] ?? null, fn ($q, $v) => $q->where('type_id', $v))
            ->when($filters['merek'] ?? null, fn ($q, $v) => $q->where('merek', $v))
            ->when($filters['status'] ?? null, function ($q, $v) {
                if ($v === 'Available') {
                    $q->whereIn('status', ['Available', 'In Stock']);
                } else {
                    $q->where('status', $v);
                }
            })
            ->groupByRaw("coalesce(part_number, ''), case when part_number is null or part_number = '' then item_name else '' end")
            ->orderByRaw("min(item_name)")
            ->orderByDesc('total_units');

        if ($request->boolean('export')) {
            return $this->downloadXlsx(
                'items-search-' . now()->format('Ymd_His') . '.xlsx',
                'Search Results',
                ['Part Number' => 24, 'Item Name' => 28, 'Type' => 18, 'Brand' => 16, 'Status' => 20, 'Available Stock' => 16, 'Reserved' => 12, 'Sold Out' => 12, 'Total Records' => 12],
                fn ($write) => $query->get()->each(fn ($r) => $write([
                    $r->part_number ?: '-',
                    $r->item_name,
                    $r->type->name ?? '-',
                    $r->merek ?: '-',
                    $r->status,
                    (int) $r->in_stock_units,
                    (int) $r->reserved_units,
                    (int) $r->sold_units,
                    (int) $r->total_units,
                ]))
            );
        }

        $rows = $query->get();

        return view('items.search', [
            'rows' => $rows,
            'filters' => $filters,
            'itemTypes' => ItemType::orderBy('name')->get(),
            'brands' => Item::whereNotNull('merek')->where('merek', '!=', '')->distinct()->orderBy('merek')->pluck('merek'),
            'statuses' => config('item_options.statuses'),
        ]);
    }

    public function create(Request $request)
    {
        return view('items.form', $this->formData(new Item(['location_id' => $request->query('location')])));
    }

    public function store(Request $request)
    {
        $options = config('item_options');
        $validStatuses = array_merge($options['statuses'], array_keys($options['legacy_statuses'] ?? []));

        $validated = $request->validate([
            'item_id' => 'nullable|string|max:255|unique:items,item_id',
            'item_name' => 'nullable|string|max:255',
            'inventory_id' => 'nullable|exists:inventories,id',
            'part_number' => 'nullable|string|max:255',
            'type_id' => 'required|exists:item_types,id',
            'serial_number' => 'nullable|string|max:255',
            'serial_numbers_text' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1|max:10000',
            'unit' => 'nullable|string|max:20',
            'merek' => 'nullable|string|max:255',
            'status' => ['required', Rule::in($validStatuses)],
            'condition' => ['required', Rule::in($options['conditions'])],
            'stuff_in' => 'nullable|date',
            'stuff_out' => 'nullable|date|after_or_equal:stuff_in',
            'no_wo' => 'nullable|string|max:255',
            'no_po_spr' => 'nullable|string|max:255',
            'warranty' => 'nullable|integer|min:0|max:240',
            'notes' => 'nullable|string',
            'location_id' => 'required|exists:locations,id',
            'rack' => 'nullable|string|max:255',
            'shelf' => 'nullable|string|max:255',
        ], [
            'required' => ':attribute is required.',
            'unique' => ':attribute has already been taken.',
            'exists' => ':attribute not found.',
            'in' => ':attribute is invalid.',
            'date' => ':attribute must be a valid date.',
            'after_or_equal' => 'Stuff Out cannot be before Stuff In.',
        ], [
            'item_id' => 'Item ID', 'item_name' => 'Item Name', 'type_id' => 'Type', 'status' => 'Status',
            'condition' => 'Condition', 'location_id' => 'Location', 'stuff_in' => 'Stuff In', 'stuff_out' => 'Stuff Out',
            'warranty' => 'Warranty (months)',
        ]);

        $resolvedItemName = trim($validated['item_name'] ?? '') ?: (trim($validated['part_number'] ?? '') ?: 'Item');
        $validated['status'] = $options['legacy_statuses'][$validated['status']] ?? $validated['status'];
        $unit = trim($validated['unit'] ?? '') ?: 'pcs';
        $type = ItemType::find($validated['type_id']);

        // Split serial numbers from textarea (1 line = 1 SN = 1 unit)
        $rawSns = collect(preg_split('/\r\n|\r|\n/', (string) ($request->serial_numbers_text ?? '')))
            ->map(fn ($s) => trim($s))
            ->filter(fn ($s) => $s !== '')
            ->values();

        // If textarea is empty but single serial_number is supplied
        if ($rawSns->isEmpty() && filled($request->serial_number)) {
            $rawSns = collect([trim($request->serial_number)]);
        }

        $trackingMode = $request->input('tracking_mode', 'serialized');
        $inventory = !empty($validated['inventory_id'])
            ? Inventory::find($validated['inventory_id'])
            : (!empty($validated['part_number']) ? Inventory::where('part_number', $validated['part_number'])->first() : null);

        $isSerialized = $inventory ? ($inventory->is_serialized ?? true) : ($trackingMode === 'serialized');

        // If tracking mode is explicitly set to qty or inventory is non-serialized
        if ($trackingMode === 'qty' || ! $isSerialized) {
            $rawSns = collect();
        } else {
            // Serialized mode: cannot input without serial number
            if ($rawSns->isEmpty()) {
                return back()->withInput()->withErrors([
                    'serial_numbers_text' => 'Part Number bertipe Serialized wajib memasukkan Serial Number (SN). Silakan scan atau masukkan Serial Number.',
                ]);
            }
        }

        // Check for duplicate SNs in input and database
        if ($rawSns->isNotEmpty()) {
            $duplicates = $rawSns->duplicates();
            if ($duplicates->isNotEmpty()) {
                return back()->withInput()->withErrors([
                    'serial_numbers_text' => 'Duplicate Serial Number found in input: ' . $duplicates->unique()->implode(', '),
                ]);
            }

            $alreadyTaken = Item::whereIn('serial_number', $rawSns)->pluck('serial_number');
            if ($alreadyTaken->isNotEmpty()) {
                return back()->withInput()->withErrors([
                    'serial_numbers_text' => 'Serial Number already registered in system: ' . $alreadyTaken->implode(', '),
                ]);
            }
        }

        // No SN provided: create `quantity` units without serial number (default 1)
        $snsToProcess = $rawSns->isNotEmpty()
            ? $rawSns
            : collect(array_fill(0, (int) ($validated['quantity'] ?? 1) ?: 1, null));

        $this->registerRack($validated['location_id'], $validated['rack'] ?? null);

        $createdCount = 0;
        $firstLocationId = $validated['location_id'];

        $batchCode = (string) \Illuminate\Support\Str::uuid();
        DB::transaction(function () use ($snsToProcess, $validated, $resolvedItemName, $unit, $type, &$createdCount, $batchCode) {
            foreach ($snsToProcess as $sn) {
                $itemId = ($snsToProcess->count() === 1 && filled($validated['item_id'] ?? null))
                    ? $validated['item_id']
                    : Item::nextItemId($type?->name);

                Item::create([
                    'item_id' => $itemId,
                    'item_name' => $resolvedItemName,
                    'inventory_id' => $validated['inventory_id'] ?? null,
                    'part_number' => $validated['part_number'] ?? null,
                    'type_id' => $validated['type_id'],
                    'serial_number' => $sn ?: null,
                    'merek' => $validated['merek'] ?? null,
                    'status' => $validated['status'],
                    'condition' => $validated['condition'],
                    'unit' => $unit,
                    'stuff_in' => $validated['stuff_in'] ?? now()->toDateString(),
                    'stuff_out' => $validated['stuff_out'] ?? null,
                    'no_wo' => $validated['no_wo'] ?? null,
                    'no_po_spr' => $validated['no_po_spr'] ?? null,
                    'warranty' => $validated['warranty'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'location_id' => $validated['location_id'],
                    'rack' => $validated['rack'] ?? null,
                    'shelf' => $validated['shelf'] ?? null,
                    'batch_code' => $batchCode,
                ]);

                $createdCount++;
            }
        });

        $msg = $createdCount > 1
            ? "Successfully added {$createdCount} item units ({$resolvedItemName})."
            : "Item {$resolvedItemName} successfully added.";

        return redirect()->route('items.index', ['location' => $firstLocationId])
            ->with('success', $msg);
    }

    public function edit(Item $item)
    {
        return view('items.form', $this->formData($item));
    }

    public function update(Request $request, Item $item)
    {
        $options = config('item_options');
        $validStatuses = array_merge($options['statuses'], array_keys($options['legacy_statuses'] ?? []));

        $validated = $request->validate([
            'item_id' => ['nullable', 'string', 'max:255', Rule::unique('items', 'item_id')->ignore($item->id)],
            'item_name' => 'nullable|string|max:255',
            'inventory_id' => 'nullable|exists:inventories,id',
            'part_number' => 'nullable|string|max:255',
            'type_id' => 'required|exists:item_types,id',
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('items', 'serial_number')->ignore($item->id)],
            'merek' => 'nullable|string|max:255',
            'status' => ['required', Rule::in($validStatuses)],
            'condition' => ['required', Rule::in($options['conditions'])],
            'unit' => 'nullable|string|max:20',
            'stuff_in' => 'nullable|date',
            'stuff_out' => 'nullable|date|after_or_equal:stuff_in',
            'no_wo' => 'nullable|string|max:255',
            'no_po_spr' => 'nullable|string|max:255',
            'warranty' => 'nullable|integer|min:0|max:240',
            'notes' => 'nullable|string',
            'location_id' => 'required|exists:locations,id',
            'rack' => 'nullable|string|max:255',
            'shelf' => 'nullable|string|max:255',
        ], [
            'required' => ':attribute is required.',
            'unique' => ':attribute has already been taken.',
            'exists' => ':attribute not found.',
            'in' => ':attribute is invalid.',
            'date' => ':attribute must be a valid date.',
            'after_or_equal' => 'Stuff Out cannot be before Stuff In.',
        ], [
            'item_id' => 'Item ID', 'item_name' => 'Item Name', 'type_id' => 'Type', 'status' => 'Status',
            'condition' => 'Condition', 'location_id' => 'Location', 'stuff_in' => 'Stuff In', 'stuff_out' => 'Stuff Out',
            'warranty' => 'Warranty (months)',
        ]);

        $validated['item_name'] = trim($validated['item_name'] ?? '') ?: (trim($validated['part_number'] ?? '') ?: $item->item_name);
        $validated['status'] = $options['legacy_statuses'][$validated['status']] ?? $validated['status'];
        $validated['unit'] = trim($validated['unit'] ?? '') ?: ($item->unit ?: 'pcs');

        $inventory = $item->inventory ?: (filled($validated['part_number'] ?? null) ? Inventory::where('part_number', $validated['part_number'])->first() : null);
        $isSerialized = $inventory ? ($inventory->is_serialized ?? true) : filled($item->serial_number);
        if ($isSerialized && blank($validated['serial_number'] ?? null)) {
            return back()->withInput()->withErrors([
                'serial_number' => 'Serial Number (SN) wajib diisi untuk barang bertipe Serialized.',
            ]);
        }

        $this->registerRack($validated['location_id'], $validated['rack'] ?? null);
        $item->update($validated);

        return redirect()->route('items.index', ['location' => $item->location_id])
            ->with('success', "Item {$item->item_name} successfully updated.");
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return back()->with('success', "Item {$item->item_name} ({$item->item_id}) deleted.");
    }

    public function export(Request $request)
    {
        $locations = Location::orderBy('id')->get();
        $itemTypes = ItemType::withCount('items')->orderBy('name')->get();
        $statuses = config('item_options.statuses', []);
        $brands = Item::whereNotNull('merek')->where('merek', '!=', '')->distinct()->orderBy('merek')->pluck('merek');

        // Multi-select or single filters with backward compatibility
        $selectedTypes = (array) $request->input('types', []);
        if ($request->filled('type_id') && $request->type_id !== 'all') {
            $selectedTypes[] = $request->type_id;
        }
        $selectedTypes = array_values(array_unique(array_filter($selectedTypes, fn ($v) => $v !== '' && $v !== 'all')));

        $selectedBrands = (array) $request->input('brands', []);
        if ($request->filled('brand') && $request->brand !== 'all') {
            $selectedBrands[] = $request->brand;
        }
        $selectedBrands = array_values(array_unique(array_filter($selectedBrands, fn ($v) => $v !== '' && $v !== 'all')));

        $selectedStatuses = (array) $request->input('statuses', []);
        if ($request->filled('status') && $request->status !== 'all') {
            $selectedStatuses = array_merge($selectedStatuses, explode(',', $request->status));
        }
        $selectedStatuses = array_values(array_unique(array_filter($selectedStatuses, fn ($v) => $v !== '' && $v !== 'all')));

        $selectedLocations = (array) $request->input('locations', []);
        if ($request->filled('location_id') && $request->location_id !== 'all') {
            $selectedLocations[] = $request->location_id;
        }
        $selectedLocations = array_values(array_unique(array_filter($selectedLocations, fn ($v) => $v !== '' && $v !== 'all')));

        $query = Item::with(['location', 'type'])
            ->when(!empty($selectedLocations), fn ($q) => $q->whereIn('location_id', $selectedLocations))
            ->when(!empty($selectedStatuses), fn ($q) => $q->whereIn('status', $selectedStatuses))
            ->when(!empty($selectedTypes), fn ($q) => $q->whereIn('type_id', $selectedTypes))
            ->when(!empty($selectedBrands), fn ($q) => $q->whereIn('merek', $selectedBrands))
            ->when($request->filled('condition') && $request->condition !== 'all', fn ($q) => $q->where('condition', $request->condition))
            ->when($request->filled('stuff_in_from'), fn ($q) => $q->whereDate('stuff_in', '>=', $request->stuff_in_from))
            ->when($request->filled('stuff_in_to'), fn ($q) => $q->whereDate('stuff_in', '<=', $request->stuff_in_to))
            ->when($request->filled('stuff_out_from'), fn ($q) => $q->whereDate('stuff_out', '>=', $request->stuff_out_from))
            ->when($request->filled('stuff_out_to'), fn ($q) => $q->whereDate('stuff_out', '<=', $request->stuff_out_to))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->q . '%';
                $query->where(fn ($q) => $q->where('item_name', 'like', $term)
                    ->orWhere('item_id', 'like', $term)
                    ->orWhere('serial_number', 'like', $term)
                    ->orWhere('merek', 'like', $term)
                    ->orWhere('part_number', 'like', $term)
                    ->orWhere('rack', 'like', $term)
                    ->orWhere('shelf', 'like', $term));
            });

        if (! $request->boolean('download')) {
            $totalMatched = (clone $query)->count();
            $statusCounts = (clone $query)->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
            $previewItems = (clone $query)->latest('id')->take(10)->get();

            return view('reports.export', [
                'locations' => $locations,
                'itemTypes' => $itemTypes,
                'statuses' => $statuses,
                'brands' => $brands,
                'selectedTypes' => $selectedTypes,
                'selectedBrands' => $selectedBrands,
                'selectedStatuses' => $selectedStatuses,
                'selectedLocations' => $selectedLocations,
                'totalMatched' => $totalMatched,
                'statusCounts' => $statusCounts,
                'previewItems' => $previewItems,
            ]);
        }

        ActivityLogger::log('export', 'Export filtered items data (Excel)');

        $date = fn ($value) => $value ? new \DateTimeImmutable($value) : null;

        return $this->downloadXlsx(
            'laporan-barang-warehouse-' . now()->format('Ymd_His') . '.xlsx',
            'Warehouse Items',
            [
                'Item ID' => 16, 'Part Number' => 22, 'Item Name' => 26, 'Type' => 16, 'Serial Number' => 18, 'Brand' => 14,
                'Status' => 22, 'Condition' => 24, 'Stuff In' => 12, 'Stuff Out' => 12, 'No WO' => 12,
                'No PO/SPR' => 14, 'Warranty (months)' => 16, 'Notes' => 30, 'Location' => 18, 'Rack' => 14, 'Shelf' => 14,
            ],
            function ($write) use ($query, $date) {
                $query->orderBy('location_id')->orderBy('item_id')->chunk(200, function ($items) use ($write, $date) {
                    foreach ($items as $item) {
                        $write([
                            $item->item_id, $item->part_number, $item->item_name, $item->type->name ?? '-', $item->serial_number,
                            $item->merek, $item->status, $item->condition, $date($item->stuff_in),
                            $date($item->stuff_out), $item->no_wo, $item->no_po_spr, $item->warranty,
                            $item->notes, $item->location->name ?? '-', $item->rack, $item->shelf,
                        ]);
                    }
                });
            }
        );
    }

    private function formData(Item $item): array
    {
        return [
            'item' => $item,
            'itemTypes' => ItemType::orderBy('name')->get(),
            'locations' => Location::orderBy('id')->get(),
            'racksByLocation' => Rack::orderBy('name')->get()->groupBy('location_id')
                ->map(fn ($g) => $g->pluck('name')->values()),
            'options' => config('item_options'),
            'inventories' => Inventory::with('type')->orderBy('part_number')->get(),
            'purchaseOrders' => PurchaseOrder::with(['supplier', 'items'])->latest('order_date')->get(),
        ];
    }

    private function registerRack($locationId, ?string $rack): void
    {
        $rack = trim((string) $rack);
        if ($rack !== '') {
            Rack::firstOrCreate(['location_id' => $locationId, 'name' => $rack]);
        }
    }
}
