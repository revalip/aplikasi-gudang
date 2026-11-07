<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['name'];

    public function racks()
    {
        return $this->hasMany(Rack::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
