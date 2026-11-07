<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use LogsActivity;

    protected string $activityName = 'Supplier';

    protected $fillable = ['name', 'phone', 'email', 'address', 'notes'];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
