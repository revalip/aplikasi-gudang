<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Expand the old coarse permissions into CRUD permissions and keep "view" access for existing roles. */
    public function up(): void
    {
        $legacy = collect(config('roles.permissions'))->filter(fn ($p) => $p['legacy'] ?? false);

        $expand = function (array $perms) use ($legacy): array {
            $out = [];
            foreach ($perms as $perm) {
                if ($legacy->has($perm)) {
                    array_push($out, ...$legacy[$perm]['implies']);
                } else {
                    $out[] = $perm;
                }
            }

            return array_values(array_unique($out));
        };

        foreach (DB::table('roles')->get() as $role) {
            if ($role->name === 'superadmin') {
                continue;
            }
            $perms = $expand(json_decode($role->permissions ?: '[]', true) ?: []);
            // "View" used to be open to everyone, so every existing role keeps it.
            $perms = array_values(array_unique(array_merge($perms, ['inventory.view', 'warehouse.view'])));
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($perms)]);
        }

        foreach (DB::table('users')->whereNotNull('permissions')->get(['id', 'permissions']) as $user) {
            $perms = json_decode($user->permissions ?: '[]', true) ?: [];
            if (array_intersect($perms, $legacy->keys()->all())) {
                DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($expand($perms))]);
            }
        }
    }

    public function down(): void
    {
        // Not reversible: granular permissions cannot be folded back reliably.
    }
};
