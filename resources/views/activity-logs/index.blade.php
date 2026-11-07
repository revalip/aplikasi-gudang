@extends('layouts.app')
@section('title', 'Activity Log')

@section('content')
@php
    $badge = [
        'emerald' => 'bg-emerald-50 text-emerald-700', 'amber' => 'bg-amber-50 text-amber-800',
        'brand' => 'bg-brand-tint text-brand', 'accent' => 'bg-accent-tint text-accent-dark', 'slate' => 'bg-slate-100 text-slate-600',
    ];
    $val = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_null($v) ? '—' : (string) $v);
@endphp
<x-page-header title="Activity Log" icon="history">
    <x-slot:subtitle><span>Total <b>{{ number_format($logs->total(), 0, ',', '.') }} activities</b> recorded (login, create, update, delete, print, export, import). Records cannot be edited or deleted from the application.</span></x-slot:subtitle>
    <x-slot:actions>
        <a href="{{ route('activity-logs.export', request()->query()) }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas">
            <i data-lucide="download" class="h-4 w-4"></i>Export CSV
        </a>
    </x-slot:actions>
</x-page-header>

<form method="GET" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search reference / description..." class="h-10 rounded-xl border border-slate-300 px-3 text-sm lg:col-span-2">
    <select name="user_id" class="h-10 rounded-xl border border-slate-300 px-3 text-sm">
        <option value="">All users</option>
        @foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach
    </select>
    <select name="event" class="h-10 rounded-xl border border-slate-300 px-3 text-sm">
        <option value="">All actions</option>
        @foreach ($events as $key => [$label])<option value="{{ $key }}" @selected(request('event') === $key)>{{ $label }}</option>@endforeach
    </select>
    <input type="date" name="date_from" value="{{ request('date_from') }}" class="h-10 rounded-xl border border-slate-300 px-3 text-sm" title="From date">
    <input type="date" name="date_to" value="{{ request('date_to') }}" class="h-10 rounded-xl border border-slate-300 px-3 text-sm" title="To date">
    <div class="flex gap-2 sm:col-span-2 lg:col-span-6">
        <button class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-bold text-white hover:bg-brand-dark"><i data-lucide="search" class="h-4 w-4"></i>Filter</button>
        @if (request()->query())<a href="{{ route('activity-logs.index') }}" class="inline-flex h-10 items-center px-4 text-sm font-bold text-muted hover:underline">Reset</a>@endif
    </div>
</form>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr><th class="px-4 py-2.5">Timestamp</th><th class="px-3 py-2.5">User</th><th class="px-3 py-2.5">Action</th><th class="px-3 py-2.5">Description</th><th class="px-4 py-2.5">IP</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php [$label, $color] = $events[$log->event] ?? [$log->event, 'slate']; $p = $log->properties; @endphp
                    <tr class="border-b border-slate-100 align-top last:border-0">
                        <td class="whitespace-nowrap px-4 py-3 text-muted">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-3 py-3 font-semibold">{{ $log->user_name ?? 'System' }}</td>
                        <td class="px-3 py-3"><span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold {{ $badge[$color] }}">{{ $label }}</span></td>
                        <td class="px-3 py-3">
                            {{ $log->description }}
                            @if ($p)
                                <details class="mt-1.5">
                                    <summary class="cursor-pointer text-xs font-bold text-brand">View details</summary>
                                    <div class="mt-1.5 overflow-x-auto rounded-lg bg-slate-50 p-2.5 text-xs">
                                        @if (isset($p['old']) && isset($p['new']))
                                            <table class="w-full"><thead><tr class="text-left text-muted"><th class="pr-3">Field</th><th class="pr-3">Before</th><th>After</th></tr></thead>
                                            <tbody>@foreach ($p['new'] as $f => $v)<tr class="align-top"><td class="pr-3 font-mono font-bold">{{ $f }}</td><td class="pr-3 text-accent-dark">{{ $val($p['old'][$f] ?? null) }}</td><td class="text-emerald-700">{{ $val($v) }}</td></tr>@endforeach</tbody></table>
                                        @else
                                            @foreach (($p['new'] ?? $p['old'] ?? $p) as $f => $v)
                                                <div><span class="font-mono font-bold">{{ $f }}</span>: {{ $val($v) }}</div>
                                            @endforeach
                                        @endif
                                    </div>
                                </details>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-muted">{{ $log->ip_address ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-muted">No matching activity found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($logs->hasPages())<div class="border-t border-line px-4 py-3">{{ $logs->links() }}</div>@endif
</section>
@endsection
