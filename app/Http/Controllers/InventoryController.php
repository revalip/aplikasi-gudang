<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsXlsx;
use App\Models\Inventory;
use App\Models\ItemType;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    use ExportsXlsx;

    public function index(Request $request)
    {
        $query = Inventory::with('type')->withCount('items');

        if ($request->filled('q')) {
            $term = '%' . trim($request->q) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('part_number', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('type', fn ($t) => $t->where('name', 'like', $term));
            });
        }

        if ($request->filled('type_id')) {
            $query->where('type_id', $request->type_id);
        }

        $inventories = $query->latest()->paginate(20)->withQueryString();

        return view('inventories.index', [
            'inventories' => $inventories,
            'itemTypes' => ItemType::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('inventories.form', [
            'inventory' => new Inventory(),
            'itemTypes' => ItemType::orderBy('name')->get(),
            'existingBrands' => Inventory::whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_number' => 'required|string|max:255|unique:inventories,part_number',
            'brand' => 'nullable|string|max:255',
            'type_name' => 'nullable|string|max:255',
            'type_id' => 'nullable|exists:item_types,id',
            'warranty' => 'nullable|integer|min:0|max:240',
            'description' => 'nullable|string',
        ], [
            'part_number.required' => 'Part Number is required.',
            'part_number.unique' => 'Part Number is already registered in Inventory.',
        ]);

        $typeId = $this->resolveType($validated['type_id'] ?? null, $validated['type_name'] ?? null);
        $isSerialized = $request->has('is_serialized') ? $request->boolean('is_serialized') : true;

        $inventoryData = [
            'part_number' => trim($validated['part_number']),
            'brand' => trim($validated['brand'] ?? '') ?: null,
            'type_id' => $typeId,
            'warranty' => trim($validated['warranty'] ?? '') ?: null,
            'description' => trim($validated['description'] ?? '') ?: null,
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('inventories', 'is_serialized')) {
            $inventoryData['is_serialized'] = $isSerialized;
        }

        $inventory = Inventory::create($inventoryData);

        return redirect()->route('inventories.index')
            ->with('success', "Part Number {$inventory->part_number} successfully added to Inventory.");
    }

    public function edit(Inventory $inventory)
    {
        return view('inventories.form', [
            'inventory' => $inventory,
            'itemTypes' => ItemType::orderBy('name')->get(),
            'existingBrands' => Inventory::whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand'),
        ]);
    }

    public function update(Request $request, Inventory $inventory)
    {
        $validated = $request->validate([
            'part_number' => ['required', 'string', 'max:255', Rule::unique('inventories', 'part_number')->ignore($inventory->id)],
            'brand' => 'nullable|string|max:255',
            'type_name' => 'nullable|string|max:255',
            'type_id' => 'nullable|exists:item_types,id',
            'warranty' => 'nullable|integer|min:0|max:240',
            'description' => 'nullable|string',
        ], [
            'part_number.required' => 'Part Number is required.',
            'part_number.unique' => 'Part Number is already registered in Inventory.',
        ]);

        $typeId = $this->resolveType($validated['type_id'] ?? null, $validated['type_name'] ?? null);
        $isSerialized = $request->has('is_serialized') ? $request->boolean('is_serialized') : true;

        $inventoryData = [
            'part_number' => trim($validated['part_number']),
            'brand' => trim($validated['brand'] ?? '') ?: null,
            'type_id' => $typeId,
            'warranty' => trim($validated['warranty'] ?? '') ?: null,
            'description' => trim($validated['description'] ?? '') ?: null,
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('inventories', 'is_serialized')) {
            $inventoryData['is_serialized'] = $isSerialized;
        }

        $inventory->update($inventoryData);

        return redirect()->route('inventories.index')
            ->with('success', "Part Number {$inventory->part_number} successfully updated.");
    }

    public function destroy(Inventory $inventory)
    {
        $pn = $inventory->part_number;
        $inventory->delete();

        return redirect()->route('inventories.index')
            ->with('success', "Part Number {$pn} successfully deleted from Inventory.");
    }

    public function importTemplate()
    {
        return $this->downloadXlsx(
            'template-import-part-number.xlsx',
            'Template Part Number',
            [
                'Part Number' => 24,
                'Brand' => 16,
                'Type' => 18,
                'Tracking Type' => 20,
                'Default Warranty (months)' => 24,
                'Description / Specs' => 45,
            ],
            function ($write) {
                $write(['RB750Gr3 aka hEX', 'Mikrotik', 'Router', 'Serialized', 24, '5x Gigabit Ethernet, Dual Core 880MHz CPU, 256MB RAM, USB, microSD, RouterOS L4']);
                $write(['CRS106-1C-5S', 'Mikrotik', 'Switch', 'Serialized', 12, 'Smart Switch, 1x Combo port (Gigabit Ethernet / SFP), 5x 1.25Gbps SFP cages']);
                $write(['UTP Cat6 305m', 'Belden', 'Kabel', 'Non-Serial', 0, 'Kabel UTP Cat6 305 Meter Grey']);
                $write(['RG-RAP2200(E)', 'Ruijie Reyee', 'Access Point', 'Serialized', 36, 'Wi-Fi 5 AC1300 Ceiling Mount AP, Dual-Band, Gigabit Port, PoE Support']);
            }
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            return back()->with('error', 'Format file harus berupa .xlsx atau .csv');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        if ($ext === 'xlsx') {
            $options = new \OpenSpout\Reader\XLSX\Options();
            $reader = new \OpenSpout\Reader\XLSX\Reader($options);
        } else {
            $options = new \OpenSpout\Reader\CSV\Options();
            $reader = new \OpenSpout\Reader\CSV\Reader($options);
        }

        try {
            $reader->open($file->getRealPath());
            $headerMap = null;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($c) => trim((string) $c), $row->toArray());

                    if ($headerMap === null) {
                        $map = [];
                        foreach ($cells as $idx => $header) {
                            $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $header));
                            if (in_array($norm, ['partnumber', 'pn', 'partno', 'part'], true)) $map['part_number'] = $idx;
                            elseif (in_array($norm, ['brand', 'merek', 'merk'], true)) $map['brand'] = $idx;
                            elseif (in_array($norm, ['type', 'tipe', 'category', 'kategori'], true)) $map['type'] = $idx;
                            elseif (in_array($norm, ['trackingtype', 'isserialized', 'serialized', 'tipepencatatan', 'serial'], true)) $map['is_serialized'] = $idx;
                            elseif (in_array($norm, ['warranty', 'defaultwarranty', 'garansi', 'garansibulan', 'warrantymonths'], true)) $map['warranty'] = $idx;
                            elseif (in_array($norm, ['description', 'descriptionspecs', 'specs', 'deskripsi', 'spesifikasi', 'notes'], true)) $map['description'] = $idx;
                        }
                        if (isset($map['part_number'])) {
                            $headerMap = $map;
                        }
                        continue;
                    }

                    $pn = trim((string) ($cells[$headerMap['part_number']] ?? ''));
                    if ($pn === '') {
                        $skipped++;
                        continue;
                    }

                    $brand = isset($headerMap['brand']) ? trim((string) ($cells[$headerMap['brand']] ?? '')) : null;
                    $typeName = isset($headerMap['type']) ? trim((string) ($cells[$headerMap['type']] ?? '')) : null;
                    $warrantyRaw = isset($headerMap['warranty']) ? trim((string) ($cells[$headerMap['warranty']] ?? '')) : null;
                    $desc = isset($headerMap['description']) ? trim((string) ($cells[$headerMap['description']] ?? '')) : null;

                    $typeId = null;
                    if ($typeName !== '' && $typeName !== null) {
                        $type = ItemType::firstOrCreate(['name' => $typeName]);
                        $typeId = $type->id;
                    }

                    $warranty = is_numeric($warrantyRaw) ? (int) $warrantyRaw : null;

                    $isSerialized = true;
                    if (isset($headerMap['is_serialized'])) {
                        $serNorm = strtolower(trim((string) ($cells[$headerMap['is_serialized']] ?? '')));
                        if (in_array($serNorm, ['0', 'no', 'tidak', 'qty', 'non-serial', 'non-serialized', 'quantity'], true)) {
                            $isSerialized = false;
                        }
                    }

                    $invPayload = [
                        'brand' => $brand,
                        'type_id' => $typeId,
                        'warranty' => $warranty,
                        'description' => $desc,
                    ];
                    if (\Illuminate\Support\Facades\Schema::hasColumn('inventories', 'is_serialized')) {
                        $invPayload['is_serialized'] = $isSerialized;
                    }

                    $existing = Inventory::where('part_number', $pn)->first();
                    if ($existing) {
                        $updateData = array_filter($invPayload, fn ($v) => $v !== null);
                        if (isset($headerMap['is_serialized']) && \Illuminate\Support\Facades\Schema::hasColumn('inventories', 'is_serialized')) {
                            $updateData['is_serialized'] = $isSerialized;
                        }
                        $existing->update($updateData);
                        $updated++;
                    } else {
                        Inventory::create(array_merge(['part_number' => $pn], $invPayload));
                        $created++;
                    }
                }
                break;
            }
            $reader->close();
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal membaca file: ' . $e->getMessage());
        }

        ActivityLogger::log('import', "Import Part Numbers: {$created} added, {$updated} updated.");

        return back()->with('success', "Import berhasil! {$created} Part Number baru ditambahkan, {$updated} diperbarui, {$skipped} baris kosong dilewati.");
    }

    public function getByPartNumber(string $partNumber)
    {
        $inventory = Inventory::with('type')
            ->where('part_number', $partNumber)
            ->first();

        if (! $inventory) {
            return response()->json(['message' => 'Part Number not found'], 404);
        }

        return response()->json([
            'id' => $inventory->id,
            'part_number' => $inventory->part_number,
            'brand' => $inventory->brand,
            'type_id' => $inventory->type_id,
            'type_name' => $inventory->type?->name,
            'is_serialized' => (bool) ($inventory->is_serialized ?? true),
            'warranty' => $inventory->warranty,
            'description' => $inventory->description,
        ]);
    }

    private function resolveType(?string $typeId, ?string $typeName): ?int
    {
        if (filled($typeName)) {
            $type = ItemType::firstOrCreate(['name' => trim($typeName)]);
            return $type->id;
        }

        return $typeId ? (int) $typeId : null;
    }
}
