<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrderItem extends Model
{
    protected $fillable = ['delivery_order_id', 'item_id', 'sales_order_item_id', 'work_order_item_id', 'scanned_at', 'scanned_by'];

    protected $casts = ['scanned_at' => 'datetime'];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function salesOrderItem()
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function workOrderItem()
    {
        return $this->belongsTo(WorkOrderItem::class);
    }

    public function scannedBy()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
