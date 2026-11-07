<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

/** Notifikasi dalam-aplikasi saja (bel di header) — tidak ada email/WA, sengaja di luar scope. */
class NotifyService
{
    /** Kirim ke semua user yang punya permission tertentu (lewat centang modul, atau superadmin). */
    public static function toPermission(string $permission, string $title, ?string $body = null, ?string $link = null, ?int $excludeUserId = null): void
    {
        User::all()
            ->filter(fn (User $u) => $u->id !== $excludeUserId && $u->hasPermission($permission))
            ->each(fn (User $u) => static::toUser($u->id, $title, $body, $link));
    }

    public static function toUser(int $userId, string $title, ?string $body = null, ?string $link = null): void
    {
        AppNotification::create(['user_id' => $userId, 'title' => $title, 'body' => $body, 'link' => $link]);
    }
}
