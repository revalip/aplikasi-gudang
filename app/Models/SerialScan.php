<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class SerialScan extends Model
{
    use LogsActivity;

    protected string $activityName = 'Scan SN';

    protected $fillable = [
        'item_id', 'serial_number', 'direction', 'location_id',
        'scanned_by', 'notes', 'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function scannedBy()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
