<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id',
        'inventory_id',
        'part_number',
        'item_name',
        'system_qty',
        'physical_qty',
        'variance',
        'scanned_serials',
        'notes',
    ];

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function serialList(): array
    {
        return collect(preg_split('/\r?\n|,/', (string) $this->scanned_serials))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values()
            ->all();
    }
}

