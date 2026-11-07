<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Arsip file hasil cetak / export. Dibuat lewat App\Services\DocumentArchive, bukan input manual. */
class Document extends Model
{
    public const FORMATS = ['docx' => 'Word', 'pdf' => 'PDF', 'xlsx' => 'Excel'];

    protected $fillable = [
        'category', 'reference', 'documentable_type', 'documentable_id',
        'format', 'file_name', 'path', 'size', 'created_by',
    ];

    public function documentable()
    {
        return $this->morphTo();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Link ke halaman dokumen sumbernya (kalau user punya akses modul itu). */
    public function sourceUrl(): ?string
    {
        [$route, $permission] = match ($this->documentable_type) {
            SalesOrder::class => ['sales-orders.show', 'sales'],
            WorkOrder::class => ['work-orders.show', 'work-orders'],
            DeliveryOrder::class => ['delivery-orders.show', 'delivery'],
            PurchaseOrder::class => ['purchasing.show', 'purchasing'],
            default => [null, null],
        };

        return $route && $this->documentable_id && auth()->user()?->can($permission)
            ? route($route, $this->documentable_id)
            : null;
    }

    public function getFormatLabelAttribute(): string
    {
        return self::FORMATS[$this->format] ?? strtoupper($this->format);
    }

    public function getSizeLabelAttribute(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1, ',', '.') . ' MB'
            : max(1, (int) round($this->size / 1024)) . ' KB';
    }
}
