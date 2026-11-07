<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /** Matrix of modules (rows) by roles (columns). */
    public function index()
    {
        return view('permissions.index', [
            'roles' => Role::where('name', '!=', 'superadmin')->orderBy('id')->get(),
            'crud' => PermissionCatalog::crud(),
            'workflow' => PermissionCatalog::workflow(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate(['perm' => 'nullable|array', 'perm.*' => 'array', 'perm.*.*' => 'string']);

        $allowed = array_keys($this->modules());
        $submitted = $request->input('perm', []);

        foreach (Role::where('name', '!=', 'superadmin')->get() as $role) {
            $role->update(['permissions' => array_values(array_intersect($allowed, (array) ($submitted[$role->name] ?? [])))]);
        }

        return back()->with('success', 'Permissions updated.');
    }

    private function modules(): array
    {
        return PermissionCatalog::grantable();
    }
}
