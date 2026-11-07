@extends('layouts.app')
@section('title', 'Permissions')

@section('content')
<x-page-header title="Permissions" icon="key-round">
    <x-slot:subtitle><span>Each permission is one allowed action (for example <b>inventory.create</b>). Tick which roles have it. Superadmin always has everything.</span></x-slot:subtitle>
</x-page-header>

<form method="POST" action="{{ route('permissions.update') }}" class="space-y-4">
    @csrf @method('PUT')
    <section class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr>
                    <th class="px-5 py-3">Permission</th>
                    @foreach ($roles as $role)
                        <th class="px-3 py-3 text-center"><x-role-badge :role="$role->name" /></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    $rows = [];
                    foreach ($crud as $module => $def) {
                        $rows[] = ['group' => $def['label'], 'items' => collect($def['perms'])->map(fn ($key, $action) => [$key, config('roles.actions')[$action]])->values()->all()];
                    }
                    $rows[] = ['group' => 'Workflow', 'items' => collect($workflow)->map(fn ($p, $key) => [$key, $p['label']])->values()->all()];
                @endphp
                @foreach ($rows as $row)
                    <tr class="border-y border-line bg-canvas">
                        <td colspan="{{ 1 + $roles->count() }}" class="px-5 py-2 text-xs font-extrabold uppercase tracking-wider text-muted">{{ $row['group'] }}</td>
                    </tr>
                    @foreach ($row['items'] as [$key, $label])
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-5 py-2.5">
                                <span class="font-semibold">{{ $label }}</span>
                                <code class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{{ $key }}</code>
                            </td>
                            @foreach ($roles as $role)
                                <td class="px-3 py-2.5 text-center">
                                    <input type="checkbox" name="perm[{{ $role->name }}][]" value="{{ $key }}" class="h-4 w-4 accent-brand"
                                           aria-label="{{ $role->label }} - {{ $key }}" @checked(in_array($key, $role->permissions ?? [], true))>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </section>
    <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-6 font-bold text-white hover:bg-brand-dark"><i data-lucide="save" class="h-4 w-4"></i>Save Permissions</button>
</form>
@endsection
