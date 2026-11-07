<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderItem extends Model
{
    protected $fillable = ['work_order_id', 'type_name', 'part_number', 'item_name', 'brand', 'description', 'qty', 'unit', 'serial_numbers', 'sow_explanation'];

    /** @return \Illuminate\Support\Collection<int,string> */
    public function serialList()
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->serial_numbers))->map(fn ($s) => trim($s))->filter()->values();
    }

    /** Units already shipped for this row (delivered Delivery Orders). */
    public function getDeliveredQtyAttribute(): int
    {
        return DeliveryOrderItem::where('work_order_item_id', $this->id)
            ->whereHas('deliveryOrder', fn ($q) => $q->where('status', 'delivered'))
            ->count();
    }

    /** Units locked in a DO that is still in picking. */
    public function getReservedQtyAttribute(): int
    {
        return DeliveryOrderItem::where('work_order_item_id', $this->id)
            ->whereHas('deliveryOrder', fn ($q) => $q->where('status', 'picking'))
            ->count();
    }

    public function getRemainingAttribute(): int
    {
        return max(0, $this->qty - $this->delivered_qty - $this->reserved_qty);
    }

    /** In Stock or pre-reserved units that match this row. */
    public function matchingUnits()
    {
        return Item::query()
            ->whereNull('stuff_out')
            ->whereNotIn('status', ['Sold Out', 'Lost'])
            ->where(function ($q) {
                $q->where('reserved_work_order_item_id', $this->id)
                    ->orWhere(function ($sub) {
                        $sub->whereIn('status', ['Available', 'In Stock'])
                            ->whereNull('reserved_sales_order_item_id')
                            ->whereNull('reserved_work_order_item_id');
                    });
            })
            ->whereNotIn('id', function ($sub) {
                $sub->select('item_id')
                    ->from('delivery_order_items')
                    ->join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_items.delivery_order_id')
                    ->whereIn('delivery_orders.status', ['picking', 'delivered']);
            })
            ->when(
                filled($this->part_number),
                fn ($q) => $q->whereRaw('lower(part_number) = ?', [mb_strtolower((string) $this->part_number)]),
                fn ($q) => $q->where('item_name', $this->item_name),
            );
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
