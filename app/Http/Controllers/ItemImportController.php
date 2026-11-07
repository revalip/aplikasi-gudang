<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\Rack;
use App\Models\SerialScan;
use App\Services\ActivityLogger;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Excel / CSV import in two directions:
 *  - in  : add stock to the Warehouse (Part Number based, creates the Inventory entry and Rack when new)
 *  - out : items leaving the warehouse, matched by Serial Number (or Item ID), recorded in the SN history
 */
class ItemImportController extends Controller
{
    private const MAX_ROWS = 5000;

    /** Stock In columns. Matches the Export Excel columns so an exported file can be re-imported. */
    public const COLUMNS_IN = [
        'item_id' => 'Item ID', 'part_number' => 'Part Number', 'item_name' => 'Item Name', 'type' => 'Type',
        'serial_number' => 'Serial Number', 'merek' => 'Brand', 'status' => 'Status', 'condition' => 'Condition',
        'stuff_in' => 'Stuff In', 'stuff_out' => 'Stuff Out', 'no_wo' => 'No WO', 'no_po_spr' => 'No PO/SPR',
        'warranty' => 'Warranty (months)', 'notes' => 'Notes', 'location' => 'Location', 'rack' => 'Rack', 'shelf' => 'Shelf',
    ];

    public const COLUMNS_OUT = [
        'serial_number' => 'Serial Number', 'item_id' => 'Item ID', 'stuff_out' => 'Stuff Out', 'status' => 'Status',
        'no_wo' => 'No WO', 'notes' => 'Notes',
    ];

    private const REQUIRED_IN = ['part_number', 'location'];

    /** Header name aliases recognized (after lowercase & stripped of symbols/spaces). */
    private const ALIASES = [
        'itemid' => 'item_id', 'kodebarang' => 'item_id', 'partnumber' => 'part_number', 'partno' => 'part_number', 'pn' => 'part_number',
        'itemname' => 'item_name', 'namabarang' => 'item_name',
        'type' => 'type', 'tipe' => 'type', 'kategori' => 'type', 'serialnumber' => 'serial_number', 'sn' => 'serial_number',
        'serialnumbermacid' => 'serial_number', 'macid' => 'serial_number', 'merek' => 'merek', 'brand' => 'merek',
        'status' => 'status', 'condition' => 'condition', 'kondisi' => 'condition', 'stuffin' => 'stuff_in',
        'stuffout' => 'stuff_out', 'nowo' => 'no_wo', 'nopospr' => 'no_po_spr', 'nopo' => 'no_po_spr',
        'nospr' => 'no_po_spr', 'warranty' => 'warranty', 'warrantymonths' => 'warranty', 'garansi' => 'warranty',
        'garansibulan' => 'warranty', 'notes' => 'notes', 'catatan' => 'notes', 'keterangan' => 'notes',
        'location' => 'location', 'lokasi' => 'location', 'warehouse' => 'location', 'rack' => 'rack', 'rak' => 'rack',
    ];

    public function form()
    {
        return view('items.import', [
            'columnsIn' => self::COLUMNS_IN,
            'columnsOut' => self::COLUMNS_OUT,
            'requiredIn' => self::REQUIRED_IN,
            'options' => config('item_options'),
            'locations' => Location::orderBy('id')->pluck('name'),
        ]);
    }

    public function template(Request $request)
    {
        $out = $request->query('direction') === 'out';

        $path = tempnam(sys_get_temp_dir(), 'mjg-tpl');
        $writer = new XlsxWriter();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($out ? 'Stock Out' : 'Stock In');

        $header = new Style(fontBold: true, fontColor: Color::WHITE, backgroundColor: '0066A0');
        $writer->addRow(Row::fromValuesWithStyle(array_values($out ? self::COLUMNS_OUT : self::COLUMNS_IN), $header));
        $writer->addRow(Row::fromValues($out
            ? ['ABC123456789', '', now()->format('d/m/Y'), 'Sold Out', 'WO-2026-00001', 'Sample row, delete before importing']
            : ['', 'RB951Ui-2nD', 'RB951Ui', 'Router', 'ABC123456789', 'Mikrotik', 'Available', 'New',
                now()->format('d/m/Y'), '', '', 'PO-2026-001', '12', 'Sample row, delete before importing', 'Lantai 1', 'A-01', 'SHELF-01']));
        $writer->close();

        return response()->download($path, $out ? 'template-stock-out.xlsx' : 'template-stock-in.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv'],
            'direction' => ['nullable', 'in:in,out'],
            'mode' => ['nullable', 'in:skip,update'],
        ], [
            'file.required' => 'Please select an Excel (.xlsx) or CSV file first.',
            'file.extensions' => 'The file must be in .xlsx or .csv format.',
            'file.max' => 'Maximum file size is 10 MB.',
        ]);

        $out = $request->input('direction') === 'out';

        // Bulk import: mute per-item logs, replaced with a single summary log (it also carries the batch code for the SN history).
        $response = ActivityLogger::muted(fn () => $out ? $this->processOut($request) : $this->processIn($request));

        if ($result = session('import_result')) {
            $name = $request->file('file')->getClientOriginalName();
            ActivityLogger::log(
                'import',
                ($out ? 'Import stock OUT' : 'Import stock IN') . " from {$name}: {$result['created']} added, {$result['updated']} updated, {$result['skipped']} skipped, " . count($result['errors']) . ' failed',
                null,
                ['file' => $name, 'direction' => $out ? 'out' : 'in', 'mode' => $request->input('mode'), 'batch_code' => $result['batch'] ?? null,
                    'created' => $result['created'], 'updated' => $result['updated'], 'skipped' => $result['skipped'],
                    'failed' => count($result['errors']), 'new_types' => $result['newTypes']],
            );
        }

        return $response;
    }

    // ------------------------------------------------------------------ Stock In

    private function processIn(Request $request)
    {
        $file = $request->file('file');
        $update = $request->input('mode') === 'update';
        $options = config('item_options');

        $locations = Location::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
        $types = ItemType::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
        $locationNames = Location::orderBy('id')->pluck('name')->implode(', ');
        $inventories = Inventory::with('type')->get()->keyBy(fn ($i) => mb_strtolower($i->part_number))->all();

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'newTypes' => [], 'batch' => (string) Str::uuid(), 'direction' => 'in'];
        $map = null;
        $seenIds = [];
        $seenSn = [];
        $total = 0;

        DB::beginTransaction();
        try {
            foreach ($this->readRows($file) as $rowNo => $cells) {
                if ($map === null) {
                    $found = $this->mapHeader($cells);
                    if (! $found) {
                        continue;
                    }
                    $map = $found;
                    $missing = array_diff(self::REQUIRED_IN, array_values($map));
                    if ($missing) {
                        DB::rollBack();

                        return back()->with('import_fatal', 'Required column header not found: '
                            . implode(', ', array_map(fn ($k) => self::COLUMNS_IN[$k], $missing))
                            . '. The first row must contain column headers (see template).');
                    }

                    continue;
                }

                $data = $this->rowData($map, $cells);
                if ($this->isBlank($data)) {
                    continue;
                }
                if (++$total > self::MAX_ROWS) {
                    $result['errors'][] = ['row' => $rowNo, 'msg' => 'Exceeded limit of ' . self::MAX_ROWS . ' rows per import. Remaining rows were skipped; split the file and import again.'];
                    break;
                }

                $errors = [];
                $clean = $this->cleanRowIn($data, $options, $locations, $locationNames, $inventories, $errors);

                // Match an existing unit: Item ID first, then Serial Number.
                $existing = null;
                if ($clean && $clean['item_id']) {
                    $existing = Item::where('item_id', $clean['item_id'])->first();
                } elseif ($clean && $clean['serial_number']) {
                    $existing = Item::where('serial_number', $clean['serial_number'])->first();
                }

                if ($clean && $clean['serial_number']) {
                    $snKey = mb_strtolower($clean['serial_number']);
                    if (isset($seenSn[$snKey])) {
                        $errors[] = "Serial Number {$clean['serial_number']} appears twice in this file";
                    } elseif (! $existing && Item::where('serial_number', $clean['serial_number'])->exists()) {
                        $errors[] = "Serial Number {$clean['serial_number']} is already registered in the system";
                    } elseif ($existing && $update && Item::where('serial_number', $clean['serial_number'])->where('id', '!=', $existing->id)->exists()) {
                        $errors[] = "Serial Number {$clean['serial_number']} belongs to another item";
                    }
                }

                if ($errors || ! $clean) {
                    $result['errors'][] = ['row' => $rowNo, 'msg' => implode('; ', $errors)];

                    continue;
                }

                if ($clean['item_id'] && isset($seenIds[mb_strtolower($clean['item_id'])]) && ! $update) {
                    $result['skipped']++;

                    continue;
                }
                if ($clean['item_id']) {
                    $seenIds[mb_strtolower($clean['item_id'])] = true;
                }
                if ($clean['serial_number']) {
                    $seenSn[mb_strtolower($clean['serial_number'])] = true;
                }

                if ($existing && ! $update) {
                    $result['skipped']++;

                    continue;
                }

                // Type: new types are created automatically.
                $typeKey = mb_strtolower($clean['type']);
                if (! isset($types[$typeKey])) {
                    $types[$typeKey] = ItemType::create(['name' => $clean['type']])->id;
                    $result['newTypes'][] = $clean['type'];
                }

                // Part Number: add to the Inventory catalog when new, so the catalog stays complete.
                $pnKey = mb_strtolower($clean['part_number']);
                if (! isset($inventories[$pnKey])) {
                    $inventories[$pnKey] = Inventory::create([
                        'part_number' => $clean['part_number'], 'brand' => $clean['merek'], 'type_id' => $types[$typeKey], 'warranty' => $clean['warranty'],
                    ]);
                }

                if ($clean['rack']) {
                    Rack::firstOrCreate(['location_id' => $clean['location_id'], 'name' => $clean['rack']]);
                }

                $attrs = [
                    'part_number' => $clean['part_number'], 'inventory_id' => $inventories[$pnKey]->id,
                    'item_name' => $clean['item_name'], 'type_id' => $types[$typeKey],
                    'serial_number' => $clean['serial_number'], 'merek' => $clean['merek'],
                    'status' => $clean['status'], 'condition' => $clean['condition'],
                    'stuff_in' => $clean['stuff_in'], 'stuff_out' => $clean['stuff_out'],
                    'no_wo' => $clean['no_wo'], 'no_po_spr' => $clean['no_po_spr'],
                    'warranty' => $clean['warranty'], 'notes' => $clean['notes'],
                    'location_id' => $clean['location_id'], 'rack' => $clean['rack'],
                ];

                if ($existing) {
                    $existing->update($attrs);
                    $result['updated']++;
                } else {
                    Item::create([
                        'item_id' => $clean['item_id'] ?: Item::nextItemId(ItemType::find($types[$typeKey])?->name),
                        'batch_code' => $result['batch'],
                    ] + array_merge($attrs, ['stuff_in' => $attrs['stuff_in'] ?? now()->toDateString()]));
                    $result['created']++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('import_fatal', 'File is unreadable or corrupted. Make sure it is a valid .xlsx or .csv format. (' . class_basename($e) . ')');
        }

        if ($map === null) {
            return back()->with('import_fatal', 'Column header row not found. Please use the template (Part Number, Item Name, Type, ..., Location, Rack) or an exported Excel file.');
        }

        $result['errors'] = array_slice($result['errors'], 0, 100);

        return redirect()->route('items.import')->with('import_result', $result);
    }

    private function cleanRowIn(array $d, array $options, array $locations, string $locationNames, array $inventories, array &$errors): ?array
    {
        $pn = $this->text($d['part_number'] ?? null);
        $loc = $this->text($d['location'] ?? null);

        if ($pn === null) {
            $errors[] = 'Part Number is required';
        }
        $inv = $pn !== null ? ($inventories[mb_strtolower($pn)] ?? null) : null;

        $type = $this->text($d['type'] ?? null) ?? $inv?->type?->name;
        if ($pn !== null && $type === null) {
            $errors[] = 'Type is required for a new Part Number';
        }

        $locationId = null;
        if ($loc === null) {
            $errors[] = 'Location is required';
        } elseif (! isset($locations[mb_strtolower($loc)])) {
            $errors[] = "Location \"{$loc}\" is unrecognized (valid: {$locationNames})";
        } else {
            $locationId = $locations[mb_strtolower($loc)];
        }

        $name = $this->text($d['item_name'] ?? null);
        if ($name === null && $pn !== null) {
            $name = $this->frontPartNumber($pn);
        }

        $clean = [
            'item_id' => $this->text($d['item_id'] ?? null),
            'part_number' => $pn, 'item_name' => $name, 'type' => $type, 'location_id' => $locationId,
            'serial_number' => $this->text($d['serial_number'] ?? null),
            'merek' => $this->text($d['merek'] ?? null) ?? $inv?->brand,
            'status' => $this->pick($this->legacyStatus($this->text($d['status'] ?? null)), $options['statuses'], 'Status', 'Available', $errors),
            'condition' => $this->pick($this->legacyCondition($this->text($d['condition'] ?? null)), $options['conditions'], 'Condition', 'Normal', $errors),
            'warranty' => $this->warranty($this->text($d['warranty'] ?? null), $errors) ?? $inv?->warranty,
            'stuff_in' => $this->date($d['stuff_in'] ?? null, 'Stuff In', $errors),
            'stuff_out' => $this->date($d['stuff_out'] ?? null, 'Stuff Out', $errors),
            'no_wo' => $this->text($d['no_wo'] ?? null),
            'no_po_spr' => $this->text($d['no_po_spr'] ?? null),
            'notes' => $this->text($d['notes'] ?? null) ?? $inv?->description,
            'rack' => $this->text($d['rack'] ?? null),
            'shelf' => $this->text($d['shelf'] ?? ($d['bin'] ?? ($d['shelf_bin'] ?? null))),
        ];

        return $clean;
    }

    // ------------------------------------------------------------------ Stock Out

    private function processOut(Request $request)
    {
        $file = $request->file('file');
        $options = config('item_options');

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'newTypes' => [], 'batch' => (string) Str::uuid(), 'direction' => 'out'];
        $map = null;
        $seen = [];
        $total = 0;

        DB::beginTransaction();
        try {
            foreach ($this->readRows($file) as $rowNo => $cells) {
                if ($map === null) {
                    $found = $this->mapHeader($cells);
                    if (! $found) {
                        continue;
                    }
                    $map = $found;
                    if (! array_intersect(['serial_number', 'item_id'], array_values($map))) {
                        DB::rollBack();

                        return back()->with('import_fatal', 'Column header "Serial Number" (or "Item ID") not found. The first row must contain column headers (see the Stock Out template).');
                    }

                    continue;
                }

                $data = $this->rowData($map, $cells);
                if ($this->isBlank($data)) {
                    continue;
                }
                if (++$total > self::MAX_ROWS) {
                    $result['errors'][] = ['row' => $rowNo, 'msg' => 'Exceeded limit of ' . self::MAX_ROWS . ' rows per import. Split the file and import again.'];
                    break;
                }

                $errors = [];
                $sn = $this->text($data['serial_number'] ?? null);
                $itemId = $this->text($data['item_id'] ?? null);
                $item = $sn !== null ? Item::where('serial_number', $sn)->first() : ($itemId !== null ? Item::where('item_id', $itemId)->first() : null);

                if ($sn === null && $itemId === null) {
                    $errors[] = 'Serial Number (or Item ID) is required';
                } elseif (! $item) {
                    $errors[] = 'Serial Number / Item ID "' . ($sn ?? $itemId) . '" not found in Warehouse';
                }

                $status = $this->pick($this->text($data['status'] ?? null), $options['statuses'], 'Status', 'Sold Out', $errors);
                $out = $this->date($data['stuff_out'] ?? null, 'Stuff Out', $errors) ?? now()->toDateString();

                if ($errors) {
                    $result['errors'][] = ['row' => $rowNo, 'msg' => implode('; ', $errors)];

                    continue;
                }

                if (isset($seen[$item->id])) {
                    $result['skipped']++;

                    continue;
                }
                $seen[$item->id] = true;

                if ($item->status === $status && $item->stuff_out) {
                    $result['skipped']++;   // already recorded as out

                    continue;
                }

                $note = $this->text($data['notes'] ?? null);
                $wo = $this->text($data['no_wo'] ?? null);

                $item->update(['status' => $status, 'stuff_out' => $out] + ($wo ? ['no_wo' => $wo] : []));

                SerialScan::create([
                    'item_id' => $item->id, 'serial_number' => $item->serial_number ?: $item->item_id, 'direction' => 'out',
                    'location_id' => $item->location_id, 'scanned_by' => auth()->id(),
                    'notes' => trim('Import stock out' . ($wo ? " · {$wo}" : '') . ($note ? " · {$note}" : '')),
                    'scanned_at' => now(),
                ]);

                $result['updated']++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('import_fatal', 'File is unreadable or corrupted. Make sure it is a valid .xlsx or .csv format. (' . class_basename($e) . ')');
        }

        if ($map === null) {
            return back()->with('import_fatal', 'Column header row not found. Please use the Stock Out template (Serial Number, Stuff Out, Status, ...).');
        }

        $result['errors'] = array_slice($result['errors'], 0, 100);

        return redirect()->route('items.import')->with('import_result', $result);
    }

    // ------------------------------------------------------------------ helpers

    private function rowData(array $map, array $cells): array
    {
        $data = [];
        foreach ($map as $idx => $key) {
            $data[$key] = $cells[$idx] ?? null;
        }

        return $data;
    }

    /** "RB921UAGS-5SHPacD-NM" -> "RB921UAGS" (same rule as the Add Item form). */
    private function frontPartNumber(string $pn): string
    {
        if (str_contains($pn, '-')) {
            return trim(explode('-', $pn)[0]);
        }
        if (str_contains($pn, ' ')) {
            return trim(explode(' ', $pn)[0]);
        }

        return $pn;
    }

    /** @return \Generator<int, array<int, mixed>> row number => cell contents */
    private function readRows(UploadedFile $file): \Generator
    {
        $path = $file->getRealPath();

        if (strtolower($file->getClientOriginalExtension()) === 'csv') {
            $fh = fopen($path, 'r');
            $head = (string) fgets($fh);
            fclose($fh);
            $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($head, $d))->first();
            $reader = new CsvReader(new CsvOptions(SHOULD_PRESERVE_EMPTY_ROWS: true, FIELD_DELIMITER: $delimiter));
        } else {
            $reader = new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true));
        }

        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $n = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    yield ++$n => $row->toArray();
                }
                break;
            }
        } finally {
            $reader->close();
        }
    }

    /** @return array<int, string> column index => field key */
    private function mapHeader(array $cells): array
    {
        $map = [];
        foreach ($cells as $i => $label) {
            $norm = preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $label)));
            if (isset(self::ALIASES[$norm]) && ! in_array(self::ALIASES[$norm], $map, true)) {
                $map[$i] = self::ALIASES[$norm];
            }
        }

        return $map;
    }

    private function isBlank(array $data): bool
    {
        return collect($data)->every(fn ($v) => trim((string) ($v instanceof DateTimeInterface ? 'x' : $v)) === '');
    }

    private function text(mixed $v): ?string
    {
        if ($v instanceof DateTimeInterface) {
            return $v->format('d/m/Y');
        }
        if (is_float($v) && floor($v) === $v) {
            $v = (int) $v;
        }
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    private function date(mixed $v, string $label, array &$errors): ?string
    {
        if ($v instanceof DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        $s = $this->text($v);
        if ($s === null) {
            return null;
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd.m.Y'] as $fmt) {
            $d = \DateTimeImmutable::createFromFormat('!' . $fmt, $s);
            if ($d && $d->format($fmt) === $s) {
                return $d->format('Y-m-d');
            }
        }
        $errors[] = "{$label} \"{$s}\" is not a valid date (use dd/mm/yyyy)";

        return null;
    }

    private function legacyCondition(?string $value): ?string
    {
        return $value === null ? null : (config('item_options.legacy_conditions')[mb_strtolower($value)] ?? $value);
    }

    private function legacyStatus(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $map = config('item_options.legacy_statuses', []);

        return $map[$value] ?? ($map[mb_strtolower($value)] ?? $value);
    }

    private function warranty(?string $value, array &$errors): ?string
    {
        if ($value === null) {
            return null;
        }
        $months = \App\Support\Warranty::normalize($value);
        if ($months === null) {
            $errors[] = "Warranty \"{$value}\" must be a number of months (e.g. 12)";
        }

        return $months;
    }

    private function pick(?string $value, array $allowed, string $label, ?string $default, array &$errors): ?string
    {
        if ($value === null) {
            return $default;
        }
        foreach ($allowed as $opt) {
            if (mb_strtolower($opt) === mb_strtolower($value)) {
                return $opt;
            }
        }
        $errors[] = "{$label} \"{$value}\" is unrecognized";

        return null;
    }
}
