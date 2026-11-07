@extends('layouts.app')
@section('title', 'User Management')

@section('content')
@php $counts = $users->countBy('role'); @endphp
<x-page-header title="User Management" icon="users">
    <x-slot:subtitle>
        <span>Total: <b>{{ $users->count() }}</b></span>
        @foreach ($roles as $role)
            <span>{{ $role->label }}: <b>{{ $counts[$role->name] ?? 0 }}</b></span>
        @endforeach
    </x-slot:subtitle>
    <x-slot:actions>
        <a href="{{ route('users.create') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="user-plus" class="h-4 w-4"></i>Add User
        </a>
    </x-slot:actions>
</x-page-header>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    {{-- Desktop: table --}}
    <div class="hidden overflow-x-auto md:block">
        <table class="w-full text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr><th class="px-5 py-2.5">Name</th><th class="px-3 py-2.5">Email</th><th class="px-3 py-2.5">Access</th><th class="px-3 py-2.5">Created</th><th class="px-5 py-2.5 text-right">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($users as $u)
                    @php $isMe = $u->is(auth()->user()); @endphp
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="px-5 py-3">
                            <span class="flex items-center gap-3">
                                <x-avatar :user="$u" />
                                <span><span class="font-bold">{{ $u->name }}@if ($isMe) <span class="font-normal text-muted">(you)</span>@endif</span>
                                @if ($u->division)<span class="block text-xs text-muted">{{ $u->division }}</span>@endif</span>
                            </span>
                        </td>
                        <td class="px-3 py-3 text-muted">{{ $u->email }}@if ($u->phone)<span class="block text-xs">{{ $u->phone }}</span>@endif</td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap items-center gap-1">
                                <x-role-badge :role="$u->role" />
                                @unless ($u->isSuperadmin())
                                    @forelse ($u->permissions ?? [] as $perm)
                                        @isset($modules[$perm])
                                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $modules[$perm]['label'] }}</span>
                                        @endisset
                                    @empty
                                        <span class="text-xs text-muted">view only</span>
                                    @endforelse
                                @endunless
                            </div>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap text-muted">{{ $u->created_at?->format('d/m/Y') }}</td>
                        <td class="px-5 py-3">
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('users.edit', $u) }}" class="rounded-lg p-1.5 text-muted hover:bg-brand-tint hover:text-brand" aria-label="Edit {{ $u->name }}" title="Edit">
                                    <i data-lucide="pencil" class="h-4 w-4"></i>
                                </a>
                                @unless ($isMe)
                                    <form method="POST" action="{{ route('users.destroy', $u) }}" onsubmit="return confirm({{ Js::from("Delete user {$u->name} ({$u->email})?") }})">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg p-1.5 text-accent hover:bg-accent-tint" aria-label="Delete {{ $u->name }}" title="Delete">
                                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Mobile: cards --}}
    <div class="divide-y divide-slate-100 md:hidden">
        @foreach ($users as $u)
            <div class="flex items-center gap-3 px-4 py-3">
                <x-avatar :user="$u" size="h-11 w-11" />
                <div class="min-w-0 flex-1">
                    <div class="truncate font-bold">{{ $u->name }}@if ($u->is(auth()->user())) <span class="font-normal text-muted">(you)</span>@endif</div>
                    <div class="truncate text-xs text-muted">{{ $u->email }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-1">
                        <x-role-badge :role="$u->role" />
                        @unless ($u->isSuperadmin())
                            @forelse ($u->permissions ?? [] as $perm)
                                @isset($modules[$perm])
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $modules[$perm]['label'] }}</span>
                                @endisset
                            @empty
                                <span class="text-xs text-muted">view only</span>
                            @endforelse
                        @endunless
                    </div>
                </div>
                <a href="{{ route('users.edit', $u) }}" class="rounded-lg p-2 text-muted" aria-label="Edit {{ $u->name }}"><i data-lucide="pencil" class="h-5 w-5"></i></a>
            </div>
        @endforeach
    </div>
</section>
@endsection
