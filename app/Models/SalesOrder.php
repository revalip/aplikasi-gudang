<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    use LogsActivity;

    protected string $activityName = 'Sales Order';

    protected $fillable = [
        'so_number', 'customer_id', 'order_date', 'delivery_address', 'payment_terms',
        'status', 'notes', 'created_by', 'submitted_at', 'approved_by', 'approved_at', 'approval_note',
    ];

    protected $casts = [
        'order_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function deliveryOrders()
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function purchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function hasAcceptedWorkOrder(): bool
    {
        return $this->workOrders()->where('status', 'accepted')->exists();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->items->sum(fn (SalesOrderItem $i) => $i->subtotal);
    }

    /** Nomor otomatis format SO-2026-00001 (urut per tahun). */
    public static function nextNumber(): string
    {
        $prefix = 'SO-' . now()->format('Y') . '-';
        $last = static::where('so_number', 'like', $prefix . '%')->orderByDesc('so_number')->value('so_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
