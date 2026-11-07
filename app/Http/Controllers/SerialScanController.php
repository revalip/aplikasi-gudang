<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsXlsx;
use App\Models\Item;
use App\Models\Location;
use App\Models\SerialScan;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class SerialScanController extends Controller
{
    use ExportsXlsx;

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'direction', 'location_id', 'date_from', 'date_to']);

        $query = SerialScan::with(['item.type', 'location', 'scannedBy'])
            ->when($filters['q'] ?? null, function ($q, $term) {
                $t = "%{$term}%";
                $q->where(fn ($sub) => $sub->where('serial_number', 'like', $t)
                    ->orWhere('notes', 'like', $t)
                    ->orWhereHas('item', fn ($i) => $i->where('item_name', 'like', $t)->orWhere('part_number', 'like', $t)));
            })
            ->when($filters['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))
            ->when($filters['location_id'] ?? null, fn ($q, $v) => $q->where('location_id', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('scanned_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('scanned_at', '<=', $v));

        if ($request->boolean('export')) {
            ActivityLogger::log('export', 'Export Stock Movements (Excel)');

            return $this->downloadXlsx(
                'stock-movements-' . now()->format('Ymd_His') . '.xlsx',
                'Stock Movements',
                [
                    'Date & Time' => 20,
                    'Movement Type' => 16,
                    'Serial Number' => 20,
                    'Part Number' => 22,
                    'Item Name' => 26,
                    'Warehouse Location' => 18,
                    'Operator' => 18,
                    'Reference / Notes' => 35,
                ],
                fn ($write) => $query->latest('scanned_at')->get()->each(fn ($l) => $write([
                    $l->scanned_at ? $l->scanned_at->format('d/m/Y H:i') : '-',
                    strtoupper($l->direction),
                    $l->serial_number,
                    $l->item?->part_number ?: '-',
                    $l->item?->item_name ?: '-',
                    $l->location?->name ?: '-',
                    $l->scannedBy?->name ?: 'System',
                    $l->notes ?: '-',
                ]))
            );
        }

        $logs = $query->latest('scanned_at')->paginate(25)->withQueryString();

        $stats = [
            'total' => SerialScan::count(),
            'in' => SerialScan::where('direction', 'in')->count(),
            'out' => SerialScan::where('direction', 'out')->count(),
            'transfer' => SerialScan::where('direction', 'transfer')->count(),
            'adjustment' => SerialScan::where('direction', 'adjustment')->count(),
        ];

        return view('serial-scans.index', [
            'locations' => Location::orderBy('id')->get(),
            'logs' => $logs,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'serial_number' => 'required|string|max:255',
            'direction' => 'required|in:in,out,transfer',
            'location_id' => 'nullable|exists:locations,id',
            'notes' => 'nullable|string|max:500',
        ], [
            'required' => ':attribute is required.',
        ], [
            'serial_number' => 'Serial Number',
        ]);

        $sn = trim($data['serial_number']);
        $item = Item::where('serial_number', $sn)->first();

        if (! $item) {
            return back()->withInput()->with('error', "Serial Number '{$sn}' is not registered in the Warehouse Inventory.");
        }

        $isOut = ($item->status === 'Sold Out' || $item->stuff_out !== null);

        // 1. If item is already OUT, it CANNOT be scanned out again!
        if ($data['direction'] === 'out') {
            if ($isOut) {
                $outDate = $item->stuff_out ? \Illuminate\Support\Carbon::parse($item->stuff_out)->format('d/m/Y') : '-';
                return back()->withInput()->with('error', "Item with SN '{$sn}' ({$item->item_name}) is already marked as OUT / Sold Out on {$outDate}. Items already dispatched cannot be scanned OUT again.");
            }

            // Mark as OUT
            $item->update([
                'status' => 'Sold Out',
                'stuff_out' => now()->toDateString(),
            ]);

            $note = $data['notes'] ?: 'Manual outbound scan';
            $label = 'OUT (Outbound)';
        } elseif ($data['direction'] === 'in') {
            // 2. Inbound scan: if already available at the same location, warn the user
            if (in_array($item->status, ['Available', 'In Stock']) && ! $isOut && $item->location_id == ($data['location_id'] ?? $item->location_id)) {
                return back()->withInput()->with('error', "Item with SN '{$sn}' ({$item->item_name}) is already in stock/available at ({$item->location?->name}). Use 'Transfer' to move between warehouses/racks.");
            }

            // Re-stock or return item
            $targetLocation = $data['location_id'] ? (int) $data['location_id'] : $item->location_id;
            $item->update([
                'status' => 'Available',
                'location_id' => $targetLocation,
                'stuff_out' => null,
            ]);

            $note = $data['notes'] ?: ($isOut ? 'Customer Return / Inbound Return' : 'Inbound scan receipt');
            $label = 'IN (Inbound)';
        } elseif ($data['direction'] === 'transfer') {
            // 3. Transfer / Relocate
            if ($isOut) {
                return back()->withInput()->with('error', "Item with SN '{$sn}' is currently marked as OUT and cannot be transferred.");
            }
            if (empty($data['location_id']) || $data['location_id'] == $item->location_id) {
                return back()->withInput()->with('error', 'Please select a different destination warehouse to perform a transfer.');
            }

            $fromLoc = $item->location?->name ?? 'Unknown';
            $toLoc = Location::find($data['location_id'])?->name ?? 'Destination Warehouse';
            $item->update([
                'location_id' => $data['location_id'],
            ]);

            $note = $data['notes'] ?: "Internal transfer from {$fromLoc} to {$toLoc}";
            $label = "TRANSFER ({$fromLoc} -> {$toLoc})";
        }

        SerialScan::create([
            'item_id' => $item->id,
            'serial_number' => $sn,
            'direction' => $data['direction'],
            'location_id' => $item->location_id,
            'scanned_by' => auth()->id(),
            'notes' => $note,
            'scanned_at' => now(),
        ]);

        ActivityLogger::log('scan', "Stock Movement: SN {$sn} ({$item->item_name}) {$label}", $item, [
            'direction' => $data['direction'],
            'location_id' => $item->location_id,
            'notes' => $note,
        ]);

        $itemName = $item->part_number ?: $item->item_name;
        return back()->with('success', "Success! SN {$sn} ({$itemName}) successfully recorded as {$label}.");
    }
}
