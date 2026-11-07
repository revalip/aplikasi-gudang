<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use LogsActivity;

    protected string $activityName = 'Customer';

    protected $fillable = ['name', 'phone', 'email', 'address', 'notes'];

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function deliveryOrders()
    {
        return $this->hasManyThrough(DeliveryOrder::class, WorkOrder::class);
    }
}
