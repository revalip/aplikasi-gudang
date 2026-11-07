<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rack extends Model
{
    protected $fillable = ['location_id', 'name'];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
