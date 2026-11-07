<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'inventory_id', 'part_number', 'type_id', 'item_name', 'description', 'merek',
        'warranty', 'qty_ordered', 'unit', 'qty_received', 'unit_price', 'notes',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function type()
    {
        return $this->belongsTo(ItemType::class, 'type_id');
    }

    public function getQtyOutstandingAttribute(): int
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }
}
