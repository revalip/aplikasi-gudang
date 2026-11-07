@extends('layouts.app')
@section('title', 'Roles')

@section('content')
<x-page-header title="Roles" icon="shield-check">
    <x-slot:subtitle><span>Total: <b>{{ $roles->count() }}</b> · A role is a set of module permissions. Assign a role to each user.</span></x-slot:subtitle>
    <x-slot:actions>
        <a href="{{ route('roles.create') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="plus" class="h-4 w-4"></i>Add Role
        </a>
    </x-slot:actions>
</x-page-header>

<section class="space-y-3">
    @foreach ($roles as $role)
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <x-role-badge :role="$role->name" />
                <span class="text-sm text-muted">{{ $role->users_count }} {{ $role->users_count == 1 ? 'user' : 'users' }}</span>
                <div class="ml-auto flex gap-1">
                    @unless ($role->isSuperadminRole())
                        <a href="{{ route('roles.edit', $role) }}" class="rounded-lg p-1.5 text-muted hover:bg-brand-tint hover:text-brand" aria-label="Edit {{ $role->label }}" title="Edit"><i data-lucide="pencil" class="h-4 w-4"></i></a>
                    @endunless
                    @unless ($role->is_system)
                        <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm({{ Js::from("Delete role {$role->label}?") }})">
                            @csrf @method('DELETE')
                            <button class="rounded-lg p-1.5 text-accent hover:bg-accent-tint" aria-label="Delete {{ $role->label }}" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                        </form>
                    @endunless
                </div>
            </div>
            @if ($role->description)<p class="mt-2 text-sm text-muted">{{ $role->description }}</p>@endif
            <div class="mt-3 flex flex-wrap gap-1.5">
                @if ($role->isSuperadminRole())
                    <span class="rounded-md bg-accent-tint px-2 py-0.5 text-xs font-semibold text-accent-dark">All modules, all actions</span>
                @else
                    @php
                        $held = $role->permissions ?? [];
                        $chips = [];
                        foreach ($crud as $module => $def) {
                            $acts = collect($def['perms'])->filter(fn ($key) => in_array($key, $held, true))->keys()->map(fn ($a) => config('roles.actions')[$a])->all();
                            if ($acts) { $chips[] = $def['label'] . ' (' . implode(', ', $acts) . ')'; }
                        }
                        foreach ($workflow as $key => $p) {
                            if (in_array($key, $held, true)) { $chips[] = $p['label']; }
                        }
                    @endphp
                    @forelse ($chips as $chip)
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $chip }}</span>
                    @empty
                        <span class="text-xs text-muted">No permissions</span>
                    @endforelse
                @endif
            </div>
        </div>
    @endforeach
</section>
@endsection
