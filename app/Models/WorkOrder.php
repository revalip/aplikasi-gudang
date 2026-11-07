<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    use LogsActivity;

    protected string $activityName = 'Work Order';

    protected $fillable = [
        'wo_number', 'sales_order_id', 'customer_id', 'wo_date', 'ref_number', 'ref_company', 'work_type',
        'po_date', 'rfs_date', 'contract_end_date', 'business_type', 'subject',
        'cust_name', 'cust_position', 'cust_phone', 'cust_email', 'cust_address',
        'user_name', 'user_position', 'user_phone', 'user_email', 'user_address',
        'sales_name', 'sales_title', 'approver_name', 'approver_title', 'created_by', 'status', 'accepted_by', 'accepted_at',
    ];

    protected $casts = [
        'wo_date' => 'date', 'po_date' => 'date', 'rfs_date' => 'date', 'contract_end_date' => 'date',
        'accepted_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items()
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function deliveryOrders()
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public static function nextNumber(): string
    {
        $prefix = 'WO-' . now()->format('Y') . '-';
        $last = static::where('wo_number', 'like', $prefix . '%')->orderByDesc('wo_number')->value('wo_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
