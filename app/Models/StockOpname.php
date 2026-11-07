<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use LogsActivity;

    protected string $activityName = 'Stock Opname';

    protected $fillable = [
        'opname_number',
        'opname_date',
        'location_id',
        'status',
        'notes',
        'total_system_qty',
        'total_physical_qty',
        'total_variance',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'opname_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public static function nextNumber(): string
    {
        $prefix = 'OPN-' . now()->format('Ym') . '-';
        $last = static::where('opname_number', 'like', $prefix . '%')->orderByDesc('opname_number')->value('opname_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function recalculateTotals(): void
    {
        $this->load('items');
        $this->update([
            'total_system_qty' => $this->items->sum('system_qty'),
            'total_physical_qty' => $this->items->sum('physical_qty'),
            'total_variance' => $this->items->sum('variance'),
        ]);
    }
}

