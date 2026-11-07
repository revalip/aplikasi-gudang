<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use LogsActivity;

    protected string $activityName = 'Item';
    protected array $activityIgnore = ['reserved_sales_order_item_id', 'reserved_work_order_item_id', 'reserved_at'];

    protected $fillable = [
        'item_id', 'item_name', 'inventory_id', 'part_number', 'type_id', 'serial_number', 'merek',
        'status', 'condition', 'unit', 'stuff_in', 'stuff_out', 'no_wo',
        'no_po_spr', 'warranty', 'notes', 'location_id', 'rack', 'shelf', 'batch_code',
        'reserved_sales_order_item_id', 'reserved_work_order_item_id', 'reserved_at',
    ];

    /** Format lokasi fisik terpadu sesuai standar dokumen magang: Warehouse / Rack / Shelf */
    public function getFormattedLocationAttribute(): string
    {
        $parts = [];
        if ($this->location) {
            $parts[] = $this->location->name;
        }
        if (filled($this->rack)) {
            $parts[] = str_starts_with(strtoupper($this->rack), 'RACK') ? $this->rack : "RACK-{$this->rack}";
        }
        if (filled($this->shelf)) {
            $parts[] = str_starts_with(strtoupper($this->shelf), 'SHELF') ? $this->shelf : "SHELF-{$this->shelf}";
        }

        return implode(' / ', $parts) ?: '-';
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function type()
    {
        return $this->belongsTo(ItemType::class, 'type_id');
    }

    public function serialScans()
    {
        return $this->hasMany(SerialScan::class)->latest('scanned_at');
    }

    /** Item ID berikutnya untuk type ini, lanjut dari nomor terbesar yang sudah ada (MJG-RTR-0042 → MJG-RTR-0043). */
    public static function nextItemId(?string $typeName): string
    {
        $prefix = config('item_options.id_prefixes')[$typeName] ?? null;
        $prefix ??= strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $typeName), 0, 3)) ?: 'ITM';
        $base = "MJG-{$prefix}-";

        $last = static::where('item_id', 'like', $base . '%')->pluck('item_id')
            ->map(fn ($id) => (int) substr($id, strlen($base)))
            ->max() ?? 0;

        return $base . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }
}
