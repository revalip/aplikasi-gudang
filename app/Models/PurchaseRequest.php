<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use LogsActivity;

    protected string $activityName = 'Purchase Request';

    protected $fillable = ['pr_number', 'sales_order_id', 'status', 'notes', 'created_by', 'approved_by', 'approved_at', 'approval_note'];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** PR yang masih "hidup": menunggu approval / belum diproses, atau sudah jadi PO yang barangnya belum lengkap diterima. */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('status', ['submitted', 'open'])
                ->orWhere(fn ($q2) => $q2->where('status', 'ordered')
                    ->whereHas('purchaseOrders', fn ($po) => $po->whereIn('status', ['draft', 'ordered', 'partial'])));
        });
    }

    /** Nomor otomatis format PR-2026-00001 (urut per tahun). */
    public static function nextNumber(): string
    {
        $prefix = 'PR-' . now()->format('Y') . '-';
        $last = static::where('pr_number', 'like', $prefix . '%')->orderByDesc('pr_number')->value('pr_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
