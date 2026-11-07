<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsXlsx;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\SerialScan;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\ActivityLogger;
use App\Services\PdfRenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class StockOpnameController extends Controller
{
    use ExportsXlsx;

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'opname');
        $locations = Location::orderBy('id')->get();
        $itemTypes = ItemType::orderBy('name')->get();
        $brands = Item::whereNotNull('merek')->where('merek', '!=', '')->distinct()->orderBy('merek')->pluck('merek');

        // TAB 2: Stock Opname List (History)
        if ($tab === 'list') {
            if (! Schema::hasTable('stock_opnames')) {
                return view('stock-opnames.index', [
                    'tab' => 'list',
                    'opnames' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
                    'locations' => $locations,
                    'summary' => [
                        'total' => 0,
                        'draft' => 0,
                        'completed' => 0,
                        'cancelled' => 0,
                    ],
                ]);
            }

            $statusCounts = StockOpname::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

            $query = StockOpname::with(['location', 'createdBy', 'completedBy'])
                ->when($request->filled('location_id'), fn ($q) => $q->where('location_id', $request->location_id))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->when($request->filled('date_from'), fn ($q) => $q->whereDate('opname_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn ($q) => $q->whereDate('opname_date', '<=', $request->date_to))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = "%{$request->q}%";
                    $q->where(fn ($sub) => $sub->where('opname_number', 'like', $term)->orWhere('notes', 'like', $term));
                });

            $opnames = $query->latest('opname_date')->latest('id')->paginate(15)->withQueryString();

            return view('stock-opnames.index', [
                'tab' => 'list',
                'opnames' => $opnames,
                'locations' => $locations,
                'summary' => [
                    'total' => StockOpname::count(),
                    'draft' => $statusCounts['draft'] ?? 0,
                    'completed' => $statusCounts['completed'] ?? 0,
                    'cancelled' => $statusCounts['cancelled'] ?? 0,
                ],
            ]);
        }

        // TAB 1: Stock Opname (Active Count Workspace)
        $selectedLocationId = $request->query('location_id') ?: ($locations->first()?->id ?? null);
        $currentLocation = $selectedLocationId ? ($locations->firstWhere('id', (int) $selectedLocationId) ?: $locations->first()) : $locations->first();

        // Query in-stock items in this selected warehouse floor
        $query = Item::query()
            ->when($currentLocation, fn ($q) => $q->where('location_id', $currentLocation->id))
            ->whereIn('status', ['Available', 'In Stock'])
            ->when($request->filled('type_id') && $request->type_id !== 'all', fn ($q) => $q->where('type_id', $request->type_id))
            ->when($request->filled('brand') && $request->brand !== 'all', fn ($q) => $q->where('merek', $request->brand))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = "%{$request->q}%";
                $q->where(fn ($sub) => $sub->where('part_number', 'like', $term)
                    ->orWhere('item_name', 'like', $term)
                    ->orWhere('serial_number', 'like', $term));
            });

        $systemRows = $query->selectRaw("coalesce(nullif(part_number, ''), '') as pn, min(item_name) as item_name, min(merek) as brand, min(type_id) as type_id, count(*) as system_qty")
            ->groupByRaw("coalesce(nullif(part_number, ''), ''), case when part_number is null or part_number = '' then item_name else '' end")
            ->orderByRaw("coalesce(nullif(part_number, ''), '')")
            ->get();

        $typesMap = $itemTypes->pluck('name', 'id');
        $systemRows->transform(function ($row) use ($typesMap) {
            $row->type_name = $typesMap[$row->type_id] ?? '-';
            return $row;
        });

        // Retrieve any imported real physical counts from current session
        $importedCounts = session('opname_imported_counts', []);
        $importedFilename = session('opname_imported_filename');

        // Merge physical counts and calculate initial variances
        $items = $systemRows->map(function ($row) use ($importedCounts) {
            $key = mb_strtolower(trim($row->pn ?: $row->item_name));
            $imported = $importedCounts[$key] ?? null;

            $realQty = $imported ? (int) $imported['real_qty'] : (int) $row->system_qty;
            $variance = $realQty - (int) $row->system_qty;

            return (object) [
                'part_number' => $row->pn ?: '-',
                'item_name' => $row->item_name,
                'brand' => $row->brand ?: '-',
                'type_name' => $row->type_name,
                'system_qty' => (int) $row->system_qty,
                'real_qty' => $realQty,
                'variance' => $variance,
                'unit' => 'Unit',
                'notes' => $imported['notes'] ?? '',
                'serials' => $imported['serials'] ?? '',
                'is_imported' => $imported !== null,
            ];
        });

        // Also check if imported file had surplus items not found in current floor snapshot
        if (!empty($importedCounts)) {
            $systemKeys = $systemRows->map(fn ($r) => mb_strtolower(trim($r->pn ?: $r->item_name)))->all();
            foreach ($importedCounts as $key => $imp) {
                if (!in_array($key, $systemKeys, true)) {
                    $items->push((object) [
                        'part_number' => $imp['part_number'] ?: '-',
                        'item_name' => $imp['item_name'] ?: "Surplus Item ({$imp['part_number']})",
                        'brand' => $imp['brand'] ?? '-',
                        'type_name' => $imp['type_name'] ?? '-',
                        'system_qty' => 0,
                        'real_qty' => (int) $imp['real_qty'],
                        'variance' => (int) $imp['real_qty'],
                        'unit' => 'Unit',
                        'notes' => $imp['notes'] ?: 'Physical Surplus (Not in system snapshot)',
                        'serials' => $imp['serials'] ?? '',
                        'is_imported' => true,
                    ]);
                }
            }
        }

        // Apply status filter if present
        $statusFilter = $request->query('status_filter');
        if ($statusFilter === 'match') {
            $items = $items->filter(fn ($it) => $it->variance === 0)->values();
        } elseif ($statusFilter === 'discrepancy') {
            $items = $items->filter(fn ($it) => $it->variance !== 0)->values();
        } elseif ($statusFilter === 'surplus') {
            $items = $items->filter(fn ($it) => $it->variance > 0)->values();
        } elseif ($statusFilter === 'deficit') {
            $items = $items->filter(fn ($it) => $it->variance < 0)->values();
        }

        return view('stock-opnames.index', [
            'tab' => 'opname',
            'locations' => $locations,
            'currentLocation' => $currentLocation,
            'itemTypes' => $itemTypes,
            'brands' => $brands,
            'items' => $items,
            'totalItems' => $items->count(),
            'matchedCount' => $items->where('variance', 0)->count(),
            'discrepancyCount' => $items->where('variance', '!=', 0)->count(),
            'importedFilename' => $importedFilename,
        ]);
    }

    /**
     * Show form to create a new stock opname session.
     */
    public function create()
    {
        $locations = Location::orderBy('name')->get();
        $suggestedNumber = StockOpname::nextNumber();

        return view('stock-opnames.create', [
            'locations' => $locations,
            'suggestedNumber' => $suggestedNumber,
        ]);
    }

    /**
     * Store new stock opname (from create form or workspace save).
     */
    public function store(Request $request)
    {
        if ($request->has('items')) {
            return $this->saveSession($request);
        }

        $validated = $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'opname_date' => ['required', 'date'],
            'count_mode' => ['nullable', 'in:zero,snapshot'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $location = Location::findOrFail($validated['location_id']);
        $countMode = $validated['count_mode'] ?? 'zero';

        $opname = DB::transaction(function () use ($validated, $location, $countMode) {
            $opname = StockOpname::create([
                'opname_number' => StockOpname::nextNumber(),
                'opname_date' => $validated['opname_date'],
                'location_id' => $location->id,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? "Physical inventory audit for {$location->name}",
                'created_by' => auth()->id(),
                'completed_by' => auth()->id(),
                'completed_at' => now(),
            ]);

            $items = Item::where('location_id', $location->id)
                ->whereIn('status', ['Available', 'In Stock'])
                ->selectRaw("coalesce(nullif(part_number, ''), '') as pn, min(item_name) as item_name, count(*) as system_qty")
                ->groupByRaw("coalesce(nullif(part_number, ''), ''), case when part_number is null or part_number = '' then item_name else '' end")
                ->get();

            $inventories = Inventory::pluck('id', 'part_number');

            foreach ($items as $row) {
                $pn = !empty($row->pn) ? $row->pn : null;
                $sysQty = (int) $row->system_qty;
                $physicalQty = ($countMode === 'snapshot') ? $sysQty : 0;
                $variance = $physicalQty - $sysQty;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'inventory_id' => $pn ? ($inventories[$pn] ?? null) : null,
                    'part_number' => $pn,
                    'item_name' => $row->item_name,
                    'system_qty' => $sysQty,
                    'physical_qty' => $physicalQty,
                    'variance' => $variance,
                ]);

                if ($variance > 0) {
                    SerialScan::create([
                        'serial_number' => $pn ?: $row->item_name,
                        'item_id' => null,
                        'direction' => 'in',
                        'location_id' => $location->id,
                        'scanned_by' => auth()->id(),
                        'scanned_at' => now(),
                        'notes' => "Stock Opname {$opname->opname_number} - Physical Surplus (+{$variance} units)",
                    ]);
                } elseif ($variance < 0) {
                    SerialScan::create([
                        'serial_number' => $pn ?: $row->item_name,
                        'item_id' => null,
                        'direction' => 'out',
                        'location_id' => $location->id,
                        'scanned_by' => auth()->id(),
                        'scanned_at' => now(),
                        'notes' => "Stock Opname {$opname->opname_number} - Physical Deficit ({$variance} units)",
                    ]);
                }
            }

            $opname->recalculateTotals();

            ActivityLogger::log('created', "Completed Stock Opname {$opname->opname_number} for {$location->name}", $opname);

            return $opname;
        });

        return redirect()->route('stock-opnames.show', $opname)
            ->with('success', "Stock Opname {$opname->opname_number} successfully saved!");
    }

    /**
     * Export stock before counting (Step 1: Download Stock Before Sheet for selected floor)
     */
    public function exportTemplate(Request $request)
    {
        $locationId = (int) $request->query('location_id', 1);
        $location = Location::find($locationId) ?: Location::first();
        if (! $location) {
            return back()->with('error', 'No warehouse location found.');
        }

        ActivityLogger::log('export', "Export Stock Opname Before Sheet - {$location->name} (Excel)");

        $query = Item::query()
            ->where('location_id', $location->id)
            ->whereIn('status', ['Available', 'In Stock'])
            ->when($request->filled('type_id') && $request->type_id !== 'all', fn ($q) => $q->where('type_id', $request->type_id))
            ->when($request->filled('brand') && $request->brand !== 'all', fn ($q) => $q->where('merek', $request->brand))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = "%{$request->q}%";
                $q->where(fn ($sub) => $sub->where('part_number', 'like', $term)->orWhere('item_name', 'like', $term));
            });

        $groups = $query->selectRaw("coalesce(nullif(part_number, ''), '') as pn, min(item_name) as item_name, min(merek) as brand, min(type_id) as type_id, count(*) as system_qty")
            ->groupByRaw("coalesce(nullif(part_number, ''), ''), case when part_number is null or part_number = '' then item_name else '' end")
            ->orderByRaw("coalesce(nullif(part_number, ''), '')")
            ->get();

        $types = ItemType::pluck('name', 'id');
        $locationSlug = Str::slug($location->name);

        return $this->downloadXlsx(
            "stock-opname-before-{$locationSlug}-" . now()->format('Ymd_His') . ".xlsx",
            "Stock Count " . substr($location->name, 0, 20),
            [
                'Part Number' => 22,
                'Product Name' => 30,
                'Brand' => 16,
                'Type' => 16,
                'System Qty' => 14,
                'Real Qty (Fill in physical count)' => 30,
                'Unit' => 10,
                'Serial Numbers (Optional)' => 32,
                'Notes' => 28,
            ],
            function ($write) use ($groups, $types) {
                foreach ($groups as $row) {
                    $write([
                        $row->pn ?: '-',
                        $row->item_name,
                        $row->brand ?: '-',
                        $types[$row->type_id] ?? '-',
                        (int) $row->system_qty,
                        '', // Empty for user to fill in the physical count!
                        'Unit',
                        '',
                        '',
                    ]);
                }
            }
        );
    }

    /**
     * Import the actual counted stock file (Step 2: Upload Real Stock Count Sheet)
     */
    public function importReal(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv'],
            'location_id' => ['required', 'exists:locations,id'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $reader = ($ext === 'csv') ? new CsvReader() : new XlsxReader();
        $reader->open($file->getRealPath());

        $headerMap = null;
        $importedCounts = [];
        $rowCount = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $cells = array_map(fn ($c) => trim((string) $c), $row->toArray());

                if ($headerMap === null) {
                    $headerMap = [];
                    foreach ($cells as $colIdx => $colName) {
                        $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $colName));
                        if (in_array($normalized, ['partnumber', 'pn', 'partno', 'kodeproduk', 'kodebarang'])) $headerMap['part_number'] = $colIdx;
                        if (in_array($normalized, ['productname', 'itemname', 'product', 'namabarang', 'deskripsi', 'description'])) $headerMap['item_name'] = $colIdx;
                        if (in_array($normalized, ['brand', 'merek'])) $headerMap['brand'] = $colIdx;
                        if (in_array($normalized, ['type', 'tipe', 'kategori'])) $headerMap['type_name'] = $colIdx;
                        if (in_array($normalized, ['realqty', 'real', 'fisik', 'qtyfisik', 'actualqty', 'physicalqty', 'qty'])) $headerMap['real_qty'] = $colIdx;
                        if (in_array($normalized, ['serialnumbers', 'serialnumber', 'sn', 'macid'])) $headerMap['serials'] = $colIdx;
                        if (in_array($normalized, ['notes', 'catatan', 'keterangan'])) $headerMap['notes'] = $colIdx;
                    }
                    continue;
                }

                $pn = isset($headerMap['part_number']) ? ($cells[$headerMap['part_number']] ?? '') : '';
                $name = isset($headerMap['item_name']) ? ($cells[$headerMap['item_name']] ?? '') : '';
                $rawReal = isset($headerMap['real_qty']) ? ($cells[$headerMap['real_qty']] ?? '') : '';
                $serials = isset($headerMap['serials']) ? ($cells[$headerMap['serials']] ?? '') : '';
                $notes = isset($headerMap['notes']) ? ($cells[$headerMap['notes']] ?? '') : '';
                $brand = isset($headerMap['brand']) ? ($cells[$headerMap['brand']] ?? '') : '';
                $typeName = isset($headerMap['type_name']) ? ($cells[$headerMap['type_name']] ?? '') : '';

                if ($pn === '' && $name === '' && $rawReal === '') {
                    continue;
                }

                $realQty = is_numeric($rawReal) ? (int) $rawReal : 0;
                $key = mb_strtolower(trim($pn !== '' && $pn !== '-' ? $pn : $name));

                $importedCounts[$key] = [
                    'part_number' => $pn !== '-' ? $pn : null,
                    'item_name' => $name,
                    'brand' => $brand,
                    'type_name' => $typeName,
                    'real_qty' => $realQty,
                    'serials' => $serials,
                    'notes' => $notes,
                ];
                $rowCount++;
            }
            break; // Read first sheet only
        }

        $reader->close();

        session([
            'opname_imported_counts' => $importedCounts,
            'opname_imported_filename' => $file->getClientOriginalName(),
        ]);

        ActivityLogger::log('import', "Import real stock count file ({$rowCount} rows read) for location ID {$request->location_id}");

        return redirect()->route('stock-opnames.index', ['location_id' => $request->location_id])
            ->with('success', "Real physical count file imported successfully! ({$rowCount} products parsed). Check discrepancies below and click 'Save & Post Opname'.");
    }

    /**
     * Clear imported file data from session
     */
    public function clearImport(Request $request)
    {
        session()->forget(['opname_imported_counts', 'opname_imported_filename']);
        return redirect()->route('stock-opnames.index', ['location_id' => $request->location_id])
            ->with('success', 'Imported data reset. Showing live system stock.');
    }

    /**
     * Save & Post Stock Opname (Step 3: Simpan & Rekonsiliasi Otomatis)
     */
    public function saveSession(Request $request)
    {
        $validated = $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'opname_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_number' => ['nullable', 'string'],
            'items.*.item_name' => ['required', 'string'],
            'items.*.system_qty' => ['required', 'integer', 'min:0'],
            'items.*.real_qty' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.serial_numbers' => ['nullable', 'string'],
        ], [
            'items.required' => 'No products available to save in this stock opname session.',
        ]);

        $location = Location::findOrFail($validated['location_id']);

        $opname = DB::transaction(function () use ($validated, $location) {
            $opname = StockOpname::create([
                'opname_number' => StockOpname::nextNumber(),
                'opname_date' => $validated['opname_date'],
                'location_id' => $location->id,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? "Physical inventory audit for {$location->name}",
                'created_by' => auth()->id(),
                'completed_by' => auth()->id(),
                'completed_at' => now(),
            ]);

            $inventories = Inventory::pluck('id', 'part_number');

            foreach ($validated['items'] as $line) {
                $pn = !empty($line['part_number']) && $line['part_number'] !== '-' ? trim($line['part_number']) : null;
                $sysQty = (int) $line['system_qty'];
                $realQty = (int) $line['real_qty'];
                $variance = $realQty - $sysQty;

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'inventory_id' => $pn ? ($inventories[$pn] ?? null) : null,
                    'part_number' => $pn,
                    'item_name' => $line['item_name'],
                    'system_qty' => $sysQty,
                    'physical_qty' => $realQty,
                    'variance' => $variance,
                    'scanned_serials' => $line['serial_numbers'] ?? null,
                    'notes' => $line['notes'] ?? null,
                ]);

                // Auto-register audit movement in Stock Movements
                if ($variance > 0) {
                    SerialScan::create([
                        'serial_number' => $pn ?: $line['item_name'],
                        'item_id' => null,
                        'direction' => 'in',
                        'location_id' => $location->id,
                        'scanned_by' => auth()->id(),
                        'scanned_at' => now(),
                        'notes' => "Stock Opname {$opname->opname_number} - Physical Surplus (+{$variance} units)",
                    ]);
                } elseif ($variance < 0) {
                    SerialScan::create([
                        'serial_number' => $pn ?: $line['item_name'],
                        'item_id' => null,
                        'direction' => 'out',
                        'location_id' => $location->id,
                        'scanned_by' => auth()->id(),
                        'scanned_at' => now(),
                        'notes' => "Stock Opname {$opname->opname_number} - Physical Deficit ({$variance} units)",
                    ]);
                }
            }

            $opname->recalculateTotals();

            ActivityLogger::log('created', "Completed Stock Opname {$opname->opname_number} for {$location->name} (Audit reconciled)", $opname);

            return $opname;
        });

        // Clear session imported counts
        session()->forget(['opname_imported_counts', 'opname_imported_filename']);

        return redirect()->route('stock-opnames.show', $opname)
            ->with('success', "Stock Opname {$opname->opname_number} successfully saved! Stock variances have been reconciled into Stock Movements.");
    }

    public function show(StockOpname $stockOpname, Request $request)
    {
        $stockOpname->load(['location', 'createdBy', 'completedBy']);

        $query = $stockOpname->items()->with('inventory');

        if ($request->filled('q')) {
            $term = "%{$request->q}%";
            $query->where(fn ($q) => $q->where('item_name', 'like', $term)->orWhere('part_number', 'like', $term));
        }

        if ($request->query('filter') === 'variance') {
            $query->where('variance', '!=', 0);
        } elseif ($request->query('filter') === 'surplus') {
            $query->where('variance', '>', 0);
        } elseif ($request->query('filter') === 'deficit') {
            $query->where('variance', '<', 0);
        } elseif ($request->query('filter') === 'match') {
            $query->where('variance', 0);
        }

        $items = $query->orderByRaw("case when variance != 0 then 0 else 1 end")
            ->orderBy('part_number')
            ->orderBy('item_name')
            ->paginate(30)
            ->withQueryString();

        return view('stock-opnames.show', [
            'opname' => $stockOpname,
            'items' => $items,
        ]);
    }

    public function printPdf(StockOpname $stockOpname, PdfRenderService $pdf)
    {
        $stockOpname->load(['location', 'createdBy', 'completedBy', 'items.inventory']);

        ActivityLogger::log('print', "Print Stock Opname Audit Report (PDF) {$stockOpname->opname_number}", $stockOpname, ['format' => 'PDF']);

        return $pdf->render('pdf.stock-opname', [
            'opname' => $stockOpname,
        ], "BASO-{$stockOpname->opname_number}.pdf", archiveFor: $stockOpname);
    }

    public function export(StockOpname $stockOpname)
    {
        $stockOpname->load(['location', 'createdBy', 'items']);

        ActivityLogger::log('export', "Export Stock Opname Summary {$stockOpname->opname_number} (Excel)");

        return $this->downloadXlsx(
            'stock-opname-' . $stockOpname->opname_number . '.xlsx',
            'Stock Opname Report',
            [
                'Part Number' => 22,
                'Item Name' => 28,
                'System Qty' => 14,
                'Real Qty' => 14,
                'Variance' => 18,
                'Status' => 16,
                'Serial Numbers' => 30,
                'Notes' => 28,
            ],
            function ($write) use ($stockOpname) {
                foreach ($stockOpname->items as $item) {
                    $status = $item->variance == 0 ? 'Match' : ($item->variance > 0 ? "Surplus (+{$item->variance})" : "Deficit ({$item->variance})");
                    $write([
                        $item->part_number ?: '-',
                        $item->item_name,
                        $item->system_qty,
                        $item->physical_qty,
                        $item->variance,
                        $status,
                        $item->scanned_serials ? str_replace("\n", ', ', $item->scanned_serials) : '-',
                        $item->notes ?: '-',
                    ]);
                }
            }
        );
    }
}
