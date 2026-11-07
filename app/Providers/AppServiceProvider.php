<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Superadmin memiliki akses penuh tanpa batasan apapun
        Gate::before(function (User $user) {
            if ($user->isSuperadmin()) {
                return true;
            }
        });

        // Tiap permission di config/roles.php jadi ability: dipakai via middleware can:... dan @can di view.
        foreach (array_keys(config('roles.permissions')) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // Log Activity: login / logout / gagal login. (Event Login terpicu SEBELUM user dipasang ke guard, jadi user diambil dari event.)
        Event::listen(Login::class, fn (Login $e) => ActivityLogger::logAs($e->user->name, $e->user->id, 'login', "Login: {$e->user->name}"));
        Event::listen(Logout::class, function (Logout $e) {
            if ($e->user) {
                ActivityLogger::logAs($e->user->name, $e->user->id, 'logout', "Logout: {$e->user->name}");
            }
        });
        Event::listen(Failed::class, fn (Failed $e) => ActivityLogger::logAs(
            $e->credentials['email'] ?? null,
            $e->user?->id,
            'login_failed',
            'Gagal login dengan email ' . ($e->credentials['email'] ?? '-'),
        ));
    }
}
