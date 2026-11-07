<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestItem extends Model
{
    protected $fillable = ['purchase_request_id', 'type_id', 'item_name', 'merek', 'qty'];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function type()
    {
        return $this->belongsTo(ItemType::class, 'type_id');
    }
}
