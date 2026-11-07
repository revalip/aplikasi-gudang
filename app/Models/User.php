<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'division', 'photo_path', 'password', 'role', 'permissions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, LogsActivity;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'division',
        'photo_path',
        'password',
        'role',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected string $activityName = 'User';
    protected array $activityIgnore = ['remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    /** Permissions from the role plus any extra permissions set on the user. */
    public function effectivePermissions(): array
    {
        return array_values(array_unique(array_merge($this->permissions ?? [], Role::permissionsOf($this->role))));
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        // Permission names contain dots (inventory.view), so never use config() dot-notation with them.
        $all = config('roles.permissions');
        if ($all[$permission]['superadmin_only'] ?? false) {
            return false;
        }

        foreach ($this->effectivePermissions() as $held) {
            if ($held === $permission || in_array($permission, $all[$held]['implies'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function notifications()
    {
        return $this->hasMany(AppNotification::class)->latest();
    }

    /** URL of the profile photo (served through an authenticated route), or null. */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? route('users.photo', $this) : null;
    }

    public function roleLabel(): string
    {
        return Role::labelOf($this->role);
    }
}
