<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catatan aktivitas. Append-only: tidak ada fitur edit/hapus dari aplikasi. */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'user_name', 'event', 'subject_type', 'subject_id', 'subject_label',
        'description', 'properties', 'ip_address', 'user_agent',
    ];

    protected $casts = ['properties' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
