<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /** event => [label, badge color]. Color is used in view. */
    public const EVENTS = [
        'login' => ['Login', 'emerald'],
        'logout' => ['Logout', 'slate'],
        'login_failed' => ['Failed Login', 'accent'],
        'created' => ['Created', 'emerald'],
        'updated' => ['Updated', 'brand'],
        'status_changed' => ['Status Changed', 'amber'],
        'deleted' => ['Deleted', 'accent'],
        'scan' => ['Scan SN', 'brand'],
        'print' => ['Print', 'slate'],
        'export' => ['Export', 'slate'],
        'import' => ['Import', 'amber'],
    ];

    public function index(Request $request)
    {
        return view('activity-logs.index', [
            'logs' => $this->filtered($request)->latest('id')->paginate(30)->withQueryString(),
            'events' => self::EVENTS,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request)
    {
        ActivityLogger::log('export', 'Export Activity Log (CSV)');

        $query = $this->filtered($request)->latest('id')->limit(20000);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, ['Timestamp', 'User', 'Action', 'Object', 'Number/Name', 'Description', 'Change Details', 'IP']);
            $query->cursor()->each(function (ActivityLog $l) use ($out) {
                fputcsv($out, [
                    $l->created_at->format('Y-m-d H:i:s'), $l->user_name ?? 'System',
                    self::EVENTS[$l->event][0] ?? $l->event, $l->subject_type, $l->subject_label,
                    $l->description, $l->properties ? json_encode($l->properties, JSON_UNESCAPED_UNICODE) : '', $l->ip_address,
                ]);
            });
            fclose($out);
        }, 'log-activity-' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request): Builder
    {
        return ActivityLog::query()
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = '%' . $request->q . '%';
                $q->where(fn (Builder $w) => $w->where('description', 'like', $term)
                    ->orWhere('subject_label', 'like', $term)
                    ->orWhere('user_name', 'like', $term));
            })
            ->when($request->filled('user_id'), fn (Builder $q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('event'), fn (Builder $q) => $q->where('event', $request->event))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->where('created_at', '>=', $request->date_from . ' 00:00:00'))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->where('created_at', '<=', $request->date_to . ' 23:59:59'));
    }
}
