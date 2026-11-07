<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    use LogsActivity;

    protected string $activityName = 'Delivery Order';

    protected $fillable = ['do_number', 'sales_order_id', 'work_order_id', 'status', 'notes', 'created_by', 'delivered_at', 'delivered_by'];

    protected $casts = ['delivered_at' => 'datetime'];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function items()
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    /** The order this DO fulfils: its Work Order (PO -> WO -> DO flow) or its Sales Order. */
    public function sourceLabel(): string
    {
        return $this->workOrder?->wo_number ?? $this->salesOrder?->so_number ?? '-';
    }

    public function customerName(): string
    {
        return $this->salesOrder?->customer?->name ?? $this->workOrder?->cust_name ?? '-';
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /** Nomor otomatis format DO-2026-00001 (urut per tahun). */
    public static function nextNumber(): string
    {
        $prefix = 'DO-' . now()->format('Y') . '-';
        $last = static::where('do_number', 'like', $prefix . '%')->orderByDesc('do_number')->value('do_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
