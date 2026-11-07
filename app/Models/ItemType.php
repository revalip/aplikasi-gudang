<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ItemType extends Model
{
    use LogsActivity;

    protected string $activityName = 'Item Type';

    protected $fillable = ['name'];

    public function items()
    {
        return $this->hasMany(Item::class, 'type_id');
    }
}
