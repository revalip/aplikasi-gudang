<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use LogsActivity;

    protected string $activityName = 'Purchase Order';

    protected $fillable = [
        'po_number', 'supplier_id', 'purchase_request_id', 'order_date', 'expected_date',
        'status', 'notes', 'created_by', 'approved_by', 'approved_at', 'approval_note',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Barang baru boleh diterima setelah PO disetujui. */
    public function canReceive(): bool
    {
        return in_array($this->status, ['ordered', 'partial'], true);
    }

    /** PO batal/ditolak: PR asal dibuka lagi kalau tidak ada PO aktif lain, supaya bisa dibuatkan PO baru. */
    public function releasePurchaseRequest(): void
    {
        $pr = $this->purchaseRequest;
        if ($pr && $pr->status === 'ordered' && ! $pr->purchaseOrders()->whereNotIn('status', ['cancelled', 'rejected'])->exists()) {
            $pr->update(['status' => 'open']);
        }
    }

    /** Unit fisik di Data Master yang masuk lewat PO ini (ditautkan lewat kolom No. PO/SPR). */
    public function units()
    {
        return $this->hasMany(Item::class, 'no_po_spr', 'po_number');
    }

    /** Update status otomatis berdasarkan qty_received tiap baris (dipanggil setelah receive). */
    public function refreshStatus(): void
    {
        $items = $this->items;
        $totalOrdered = $items->sum('qty_ordered');
        $totalReceived = $items->sum('qty_received');

        if ($this->status !== 'cancelled') {
            $this->status = match (true) {
                $totalReceived <= 0 => $this->status === 'draft' ? 'draft' : 'ordered',
                $totalReceived < $totalOrdered => 'partial',
                default => 'received',
            };
            $this->save();
        }
    }
}
