<?php

namespace App\Support;

/** Groups the permission config for the Roles / Permissions / Users screens. */
class PermissionCatalog
{
    /** Permissions that can be granted (no legacy, no superadmin-only). */
    public static function grantable(): array
    {
        return array_filter(config('roles.permissions'), fn ($p) => ! ($p['superadmin_only'] ?? false) && ! ($p['legacy'] ?? false));
    }

    /** CRUD modules: [module => ['label' => ..., 'description' => ..., 'perms' => [action => permission key]]] */
    public static function crud(): array
    {
        $out = [];
        foreach (config('roles.crud') as $module => $def) {
            $out[$module] = ['label' => $def['label'], 'description' => $def['description'] ?? '', 'perms' => []];
            foreach ($def['actions'] as $action) {
                $out[$module]['perms'][$action] = "{$module}.{$action}";
            }
        }

        return $out;
    }

    /** Workflow permissions: [key => config entry] */
    public static function workflow(): array
    {
        return array_filter(self::grantable(), fn ($p) => ($p['group'] ?? null) === 'workflow');
    }
}
