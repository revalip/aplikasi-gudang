<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'users' => User::orderBy('name')->get()->sortBy(fn (User $u) => $u->isSuperadmin() ? 0 : 1)->values(),
            'roles' => Role::orderBy('id')->get(),
            'modules' => $this->modules(),
            'catalog' => PermissionCatalog::grantable(),
        ]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => config('roles.default'), 'permissions' => []]), 'crud' => PermissionCatalog::crud(), 'workflow' => PermissionCatalog::workflow(), 'roles' => Role::orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $photo = $this->storePhoto($request);
        $user = User::create($data + ($photo ? ['photo_path' => $photo] : []));

        return redirect()->route('users.index')->with('success', "User {$user->name} ({$user->roleLabel()}) successfully added.");
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user, 'crud' => PermissionCatalog::crud(), 'workflow' => PermissionCatalog::workflow(), 'roles' => Role::orderBy('id')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        if ($user->isSuperadmin() && $data['role'] !== 'superadmin' && $this->isLastSuperadmin($user)) {
            return back()->withInput()->with('error', 'This is the only Superadmin. Create another Superadmin first before revoking access.');
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($photo = $this->storePhoto($request)) {
            $this->deletePhoto($user);
            $data['photo_path'] = $photo;
        } elseif ($request->boolean('remove_photo')) {
            $this->deletePhoto($user);
            $data['photo_path'] = null;
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', "User {$user->name} successfully updated.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete the currently authenticated account.');
        }

        if ($user->isSuperadmin() && $this->isLastSuperadmin($user)) {
            return back()->with('error', 'The last Superadmin cannot be deleted.');
        }

        $this->deletePhoto($user);
        $user->delete();

        return back()->with('success', "User {$user->name} has been deleted.");
    }

    /** Profile photos are private files, shown only to logged-in users through users.photo. */
    public function photo(User $user)
    {
        $path = storage_path('app/private/' . $user->photo_path);
        abort_unless($user->photo_path && file_exists($path), 404);

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];
        $contentType = $mimes[$ext] ?? 'image/jpeg';

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }
        $file = $request->file('photo');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = \Illuminate\Support\Str::random(40) . '.' . $ext;
        $targetDir = storage_path('app/private/avatars');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }
        $file->move($targetDir, $filename);

        return 'avatars/' . $filename;
    }

    private function deletePhoto(User $user): void
    {
        if ($user->photo_path) {
            Storage::disk('local')->delete($user->photo_path);
        }
    }

    private function isLastSuperadmin(User $user): bool
    {
        return ! User::where('role', 'superadmin')->whereKeyNot($user->getKey())->exists();
    }

    /** Selectable modules (excluding superadmin only). */
    private function modules(): array
    {
        return PermissionCatalog::grantable();
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'division' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(array_keys($this->modules()))],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ], [
            'required' => ':attribute is required.',
            'email' => ':attribute must be a valid email address.',
            'unique' => ':attribute is already in use by another user.',
            'in' => 'Selected module is invalid.',
            'min' => ':attribute must be at least :min characters.',
            'confirmed' => 'Password confirmation does not match.',
        ], [
            'name' => 'Name', 'email' => 'Email', 'password' => 'Password',
        ]);

        // Superadmin = full access, extra module checklist is not used.
        $roleNames = array_keys(Role::map());
        $requested = (string) $request->input('role');
        $data['role'] = $request->boolean('is_superadmin') ? 'superadmin' : (in_array($requested, $roleNames, true) ? $requested : 'user');
        $data['permissions'] = $data['role'] === 'superadmin' ? [] : array_values($data['permissions'] ?? []);

        unset($data['photo']);

        return $data;
    }
}
