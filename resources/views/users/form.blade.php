@extends('layouts.app')
@php $editing = $user->exists; @endphp
@section('title', $editing ? 'Edit User' : 'Add User')

@section('content')
<x-page-header :title="$editing ? 'Edit User' : 'Add User'" :icon="$editing ? 'user-pen' : 'user-plus'">
    <x-slot:subtitle>
        <span class="text-muted">{{ $editing ? "{$user->name} · {$user->email}" : 'Create a new login account and select the modules they can access.' }}</span>
    </x-slot:subtitle>
</x-page-header>

@php $input = 'mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm'; @endphp

<form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-4">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm text-accent-dark">
            <div class="font-bold">Please correct the following errors:</div>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-4 font-extrabold">Account Information</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="text-sm font-bold">Name <span class="text-accent">*</span></label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="{{ $input }}">
            </div>
            <div>
                <label for="email" class="text-sm font-bold">Email (used for login) <span class="text-accent">*</span></label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="off" class="{{ $input }}">
            </div>
            <div>
                <label for="phone" class="text-sm font-bold">Phone Number</label>
                <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" inputmode="tel" class="{{ $input }}">
            </div>
            <div>
                <label for="division" class="text-sm font-bold">Division</label>
                <input id="division" name="division" value="{{ old('division', $user->division) }}" placeholder="e.g. Warehouse, Purchasing, Sales" class="{{ $input }}">
            </div>
        </div>
        <div class="mt-4 flex items-center gap-4">
            <x-avatar :user="$user" size="h-16 w-16" />
            <div>
                <label for="photo" class="text-sm font-bold">Profile Photo</label>
                <input id="photo" type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="mt-1.5 block text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-tint file:px-3 file:py-2 file:text-sm file:font-bold file:text-brand">
                <p class="mt-1 text-xs text-muted">JPG, PNG or WEBP, max 2 MB.</p>
                @if ($editing && $user->photo_path)
                    <label class="mt-1 flex items-center gap-2 text-xs font-semibold text-muted"><input type="checkbox" name="remove_photo" value="1" class="accent-brand"> Remove current photo</label>
                @endif
            </div>
        </div>
    </section>

    @php
        $currentRole = old('role', $user->role ?: 'user');
        if (old('is_superadmin')) { $currentRole = 'superadmin'; }
        $isSuper = $currentRole === 'superadmin';
        $checked = old('permissions', $user->permissions ?? []);
    @endphp
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-1 font-extrabold">Role</h2>
        <p class="mb-3 text-sm text-muted">The role decides which modules this user can use. Manage roles under User Management &rarr; Roles.</p>
        <select id="role" name="role" class="{{ $input }}">
            @foreach ($roles as $r)
                <option value="{{ $r->name }}" @selected($currentRole === $r->name)>{{ $r->label }}{{ $r->name === 'superadmin' ? ' (full access)' : '' }}</option>
            @endforeach
        </select>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-1 font-extrabold">Additional Module Access Permissions</h2>
        <p class="mb-4 text-sm text-muted">Optional. Extra permissions for this user on top of the role. Only the Dashboard is always accessible.</p>

        <div id="module-list" class="{{ $isSuper ? 'pointer-events-none opacity-40' : '' }}">
            <x-permission-grid :crud="$crud" :workflow="$workflow" :checked="$checked" :disabled="$isSuper" />
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-1 font-extrabold">Password</h2>
        <p class="mb-4 text-sm text-muted">{{ $editing ? 'Leave blank if you do not want to change the password.' : 'Minimum 8 characters. Provide this to the corresponding user.' }}</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="password" class="text-sm font-bold">Password @unless ($editing)<span class="text-accent">*</span>@endunless</label>
                <input id="password" type="password" name="password" @unless ($editing) required @endunless minlength="8" autocomplete="new-password" class="{{ $input }}">
            </div>
            <div>
                <label for="password_confirmation" class="text-sm font-bold">Confirm Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" class="{{ $input }}">
            </div>
        </div>
    </section>

    <div class="flex gap-2">
        <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-6 font-bold text-white hover:bg-brand-dark">
            <i data-lucide="save" class="h-4 w-4"></i>{{ $editing ? 'Save Changes' : 'Save User' }}
        </button>
        <a href="{{ route('users.index') }}" class="flex h-11 items-center rounded-xl px-5 font-semibold text-muted hover:bg-white">Cancel</a>
    </div>
</form>
@push('scripts')
<script>
    (() => {
        const role = document.getElementById('role');
        const list = document.getElementById('module-list');
        const checks = () => [...document.querySelectorAll('#module-list .perm-check')];
        const sync = () => {
            const sup = role.value === 'superadmin';
            list.classList.toggle('pointer-events-none', sup);
            list.classList.toggle('opacity-40', sup);
            checks().forEach(c => c.disabled = sup);
        };
        role.addEventListener('change', sync);

    })();
</script>
@endpush
@endsection
