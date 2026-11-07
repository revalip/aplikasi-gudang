<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $fillable = ['sales_order_id', 'type_id', 'item_name', 'merek', 'qty', 'unit_price'];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function type()
    {
        return $this->belongsTo(ItemType::class, 'type_id');
    }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->qty * (float) $this->unit_price;
    }

    /** Query unit fisik di Data Master yang cocok dengan baris ini (nama + type + merek). */
    public function matchingUnits()
    {
        return Item::where('item_name', $this->item_name)
            ->where('type_id', $this->type_id)
            ->when($this->merek, fn ($q) => $q->where('merek', $this->merek));
    }

    /** Qty yang sudah benar-benar keluar (DO berstatus Terkirim). */
    public function getDeliveredQtyAttribute(): int
    {
        return DeliveryOrderItem::where('sales_order_item_id', $this->id)
            ->whereHas('deliveryOrder', fn ($q) => $q->where('status', 'delivered'))
            ->count();
    }

    /** Qty yang unitnya sudah dikunci (masuk picking list, belum dikirim). */
    public function getReservedQtyAttribute(): int
    {
        return Item::where('reserved_sales_order_item_id', $this->id)->count();
    }

    /** Qty yang belum terkirim & belum dikunci. */
    public function getRemainingAttribute(): int
    {
        return max(0, $this->qty - $this->delivered_qty - $this->reserved_qty);
    }

    /** Stok bebas: In Stock dan tidak sedang dikunci SO lain. */
    public function getAvailableAttribute(): int
    {
        return $this->matchingUnits()
            ->whereIn('status', ['Available', 'In Stock'])
            ->whereNull('reserved_sales_order_item_id')
            ->whereNull('reserved_work_order_item_id')
            ->count();
    }

    public function getShortageAttribute(): int
    {
        return max(0, $this->remaining - $this->available);
    }
}
