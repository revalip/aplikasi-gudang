<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * A role is a named set of module permissions. users.role stores the role name (slug).
 * "superadmin" always has full access regardless of its permission list.
 */
class Role extends Model
{
    use LogsActivity;

    protected string $activityName = 'Role';

    protected $fillable = ['name', 'label', 'description', 'permissions', 'is_system'];

    protected $casts = ['permissions' => 'array', 'is_system' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }

    /** All roles keyed by name, memoized for the current request. */
    public static function map(): array
    {
        if (! app()->bound('roles.map')) {
            try {
                $map = static::query()->orderBy('id')->get()->keyBy('name')->all();
            } catch (\Throwable $e) {
                $map = [];
            }
            app()->instance('roles.map', $map);
        }

        return app('roles.map');
    }

    public static function flush(): void
    {
        app()->forgetInstance('roles.map');
    }

    public static function permissionsOf(?string $name): array
    {
        return static::map()[$name]->permissions ?? [];
    }

    public static function labelOf(?string $name): string
    {
        return static::map()[$name]->label ?? config("roles.roles.{$name}.label", ucfirst((string) $name));
    }

    public function isSuperadminRole(): bool
    {
        return $this->name === 'superadmin';
    }

    public function users()
    {
        return $this->hasMany(User::class, 'role', 'name');
    }
}
