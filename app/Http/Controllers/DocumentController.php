<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsXlsx;
use App\Models\Document;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Documentation module: archive hub for printed and exported documents, searchable and exportable. */
class DocumentController extends Controller
{
    use ExportsXlsx;

    public function index(Request $request)
    {
        $summary = $this->filtered($request)->reorder()
            ->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');

        return view('documents.index', [
            'documents' => $this->filtered($request)->with('createdBy')->paginate(20)->withQueryString(),
            'summary' => $summary,
            'categories' => Document::distinct()->orderBy('category')->pluck('category'),
            'formats' => Document::FORMATS,
        ]);
    }

    public function download(Document $document)
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404, 'Archived file not found.');

        ActivityLogger::log('export', "Download archive {$document->file_name}", null, ['format' => $document->format_label], $document->reference);

        return Storage::disk('local')->download($document->path, $document->file_name);
    }

    /** Report: list of documents matching active filter, in Excel. */
    public function export(Request $request)
    {
        ActivityLogger::log('export', 'Export Documentation report (Excel)');

        $query = $this->filtered($request)->with('createdBy');

        return $this->downloadXlsx(
            'documentation-report-' . now()->format('Ymd_His') . '.xlsx',
            'Documentation Report',
            ['Date' => 18, 'Category' => 18, 'Document No.' => 22, 'Format' => 10, 'File Name' => 34, 'Size' => 10, 'Created By' => 20],
            fn ($write) => $query->chunk(500, fn ($docs) => $docs->each(fn (Document $d) => $write([
                $d->created_at->toDateTimeImmutable(), $d->category, $d->reference, $d->format_label,
                $d->file_name, $d->size_label, $d->createdBy->name ?? '-',
            ]))),
        );
    }

    private function filtered(Request $request): Builder
    {
        return Document::query()
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn (Builder $w) => $w->where('reference', 'like', $term)->orWhere('file_name', 'like', $term));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('format'), fn ($q) => $q->where('format', $request->format))
            ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', $request->date_from . ' 00:00:00'))
            ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', $request->date_to . ' 23:59:59'))
            ->latest('id');
    }
}
