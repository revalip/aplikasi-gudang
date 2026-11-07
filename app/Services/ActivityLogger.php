<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencatat Log Activity. Satu pintu buat semua log (login, input, ubah, hapus, cetak, export, dst).
 * Kegagalan nyatet log TIDAK boleh bikin aksi user gagal, jadi semua error cuma di-report.
 */
class ActivityLogger
{
    /** Field yang gak pernah masuk log. */
    public const MASKED = ['password', 'remember_token', 'current_password'];

    private static int $muted = 0;

    public static function log(string $event, string $description, ?Model $subject = null, ?array $properties = null, ?string $subjectLabel = null): void
    {
        if (self::$muted > 0) {
            return;
        }

        try {
            $user = auth()->user();
            $request = self::request();

            ActivityLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'event' => $event,
                'subject_type' => $subject ? self::subjectName($subject) : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subjectLabel ?? ($subject ? self::labelOf($subject) : null),
                'description' => mb_substr($description, 0, 250),
                'properties' => $properties ? self::clean($properties) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 250) : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Log tanpa user yang login (misal login gagal) — user_name diisi manual. */
    public static function logAs(?string $userName, ?int $userId, string $event, string $description, ?array $properties = null): void
    {
        if (self::$muted > 0) {
            return;
        }

        try {
            $request = self::request();

            ActivityLog::create([
                'user_id' => $userId,
                'user_name' => $userName,
                'event' => $event,
                'description' => mb_substr($description, 0, 250),
                'properties' => $properties ? self::clean($properties) : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 250) : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Matiin pencatatan per-model sementara (dipakai import massal biar gak 5.000 baris log, diganti 1 ringkasan). */
    public static function muted(callable $callback): mixed
    {
        self::$muted++;

        try {
            return $callback();
        } finally {
            self::$muted--;
        }
    }

    /** Di artisan/seeder gak ada request asli, jadi IP & browser dikosongin. */
    private static function request(): ?\Illuminate\Http\Request
    {
        if (! app()->bound('request') || (app()->runningInConsole() && ! app()->runningUnitTests())) {
            return null;
        }

        return request();
    }

    public static function subjectName(Model $model): string
    {
        return method_exists($model, 'activityName') ? $model->activityName() : class_basename($model);
    }

    public static function labelOf(Model $model): string
    {
        foreach (['so_number', 'po_number', 'pr_number', 'wo_number', 'do_number', 'item_id', 'name', 'email', 'serial_number'] as $attr) {
            $value = $model->getAttribute($attr);
            if (filled($value)) {
                return (string) $value;
            }
        }

        return '#' . $model->getKey();
    }

    /** Buang field sensitif & potong nilai panjang. */
    public static function clean(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (in_array($key, self::MASKED, true)) {
                continue;
            }
            $out[$key] = is_array($value) ? self::clean($value) : (is_string($value) ? mb_substr($value, 0, 200) : $value);
        }

        return $out;
    }
}
