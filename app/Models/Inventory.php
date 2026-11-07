<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use LogsActivity;

    protected string $activityName = 'Inventory';

    protected $fillable = [
        'part_number',
        'brand',
        'type_id',
        'is_serialized',
        'warranty',
        'description',
    ];

    protected $casts = [
        'is_serialized' => 'boolean',
    ];

    public function type()
    {
        return $this->belongsTo(ItemType::class, 'type_id');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}

