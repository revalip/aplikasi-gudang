<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Simpan salinan tiap dokumen yang dicetak / di-export ke modul Documentation.
 * Sama kayak ActivityLogger: kalau gagal nyimpen arsip, cetaknya TETAP jalan (error cuma di-report).
 */
class DocumentArchive
{
    /** @param string $contents isi file (bukan path) */
    public static function store(string $contents, string $fileName, ?Model $source = null, ?string $category = null): ?Document
    {
        try {
            $format = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $path = 'documents/' . now()->format('Y/m') . '/' . now()->format('Ymd_His') . '_' . Str::random(6) . '_' . $fileName;
            Storage::disk('local')->put($path, $contents);

            return Document::create([
                'category' => $category ?? ($source ? ActivityLogger::subjectName($source) : 'Laporan'),
                'reference' => $source ? ActivityLogger::labelOf($source) : pathinfo($fileName, PATHINFO_FILENAME),
                'documentable_type' => $source?->getMorphClass(),
                'documentable_id' => $source?->getKey(),
                'format' => $format,
                'file_name' => $fileName,
                'path' => $path,
                'size' => strlen($contents),
                'created_by' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function storeFile(string $localPath, string $fileName, ?Model $source = null, ?string $category = null): ?Document
    {
        $contents = @file_get_contents($localPath);

        return $contents === false ? null : static::store($contents, $fileName, $source, $category);
    }
}
