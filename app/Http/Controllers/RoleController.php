<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Support\PermissionCatalog;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        return view('roles.index', [
            'roles' => Role::withCount('users')->orderBy('id')->get(),
            'crud' => PermissionCatalog::crud(),
            'workflow' => PermissionCatalog::workflow(),
        ]);
    }

    public function create()
    {
        return view('roles.form', ['role' => new Role(['permissions' => []]), 'crud' => PermissionCatalog::crud(), 'workflow' => PermissionCatalog::workflow()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $name = Str::slug($data['label']);

        if ($name === '' || Role::where('name', $name)->exists()) {
            return back()->withInput()->withErrors(['label' => 'A role with this name already exists.']);
        }

        $role = Role::create($data + ['name' => $name, 'is_system' => false]);

        return redirect()->route('roles.index')->with('success', "Role {$role->label} created.");
    }

    public function edit(Role $role)
    {
        if ($role->isSuperadminRole()) {
            return redirect()->route('roles.index')->with('error', 'Superadmin always has full access and cannot be edited.');
        }

        return view('roles.form', ['role' => $role, 'crud' => PermissionCatalog::crud(), 'workflow' => PermissionCatalog::workflow()]);
    }

    public function update(Request $request, Role $role)
    {
        if ($role->isSuperadminRole()) {
            return redirect()->route('roles.index')->with('error', 'Superadmin always has full access and cannot be edited.');
        }

        $data = $this->validated($request);
        if ($role->is_system) {
            unset($data['label']); // built-in role names stay fixed
        }
        $role->update($data);

        return redirect()->route('roles.index')->with('success', "Role {$role->label} updated.");
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'Built-in roles cannot be deleted.');
        }
        if ($role->users()->exists()) {
            return back()->with('error', 'This role is assigned to users. Move those users to another role first.');
        }

        $role->delete();

        return back()->with('success', "Role {$role->label} deleted.");
    }

    private function modules(): array
    {
        return PermissionCatalog::grantable();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', 'in:' . implode(',', array_keys($this->modules()))],
        ], ['required' => ':attribute is required.'], ['label' => 'Role name']);

        $data['permissions'] = array_values($data['permissions'] ?? []);

        return $data;
    }
}
