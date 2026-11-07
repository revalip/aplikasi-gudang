@extends('layouts.app')
@section('title', 'Search Items')

@section('content')
<x-page-header title="Search Items" icon="search">
    <x-slot:subtitle>
        <span>Found <b>{{ $rows->count() }} result(s)</b> &middot; total <b>{{ number_format($rows->sum('total_units'), 0, ',', '.') }} units recorded</b></span>
    </x-slot:subtitle>
    <x-slot:actions>
        <a href="{{ route('items.search', array_merge($filters, ['export' => 1])) }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas">
            <i data-lucide="download" class="h-4 w-4"></i>Export Excel
        </a>
    </x-slot:actions>
</x-page-header>

<form method="GET" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[1.5fr_1.5fr_1fr_1fr_1fr_auto] lg:items-end">
    <div class="space-y-1.5">
        <label for="part_number" class="text-sm font-bold">Part Number</label>
        <input id="part_number" type="search" name="part_number" value="{{ $filters['part_number'] ?? '' }}" placeholder="Search part number..."
               class="h-10 w-full rounded-xl border border-slate-300 px-3 text-sm">
    </div>
    <div class="space-y-1.5">
        <label for="item_name" class="text-sm font-bold">Item Name / Keyword</label>
        <input id="item_name" type="search" name="item_name" value="{{ $filters['item_name'] ?? '' }}" placeholder="Type item name..."
               class="h-10 w-full rounded-xl border border-slate-300 px-3 text-sm">
    </div>
    <div class="space-y-1.5">
        <label for="type_id" class="text-sm font-bold">Type</label>
        <select id="type_id" name="type_id" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-2 text-sm">
            <option value="">All types</option>
            @foreach ($itemTypes as $type)
                <option value="{{ $type->id }}" @selected(($filters['type_id'] ?? '') == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="space-y-1.5">
        <label for="merek" class="text-sm font-bold">Brand</label>
        <select id="merek" name="merek" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-2 text-sm">
            <option value="">All brands</option>
            @foreach ($brands as $brand)
                <option value="{{ $brand }}" @selected(($filters['merek'] ?? '') === $brand)>{{ $brand }}</option>
            @endforeach
        </select>
    </div>
    <div class="space-y-1.5">
        <label for="status" class="text-sm font-bold">Status</label>
        <select id="status" name="status" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-2 text-sm">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex gap-2 sm:col-span-2 lg:col-span-1">
        <button class="h-10 flex-1 rounded-xl bg-brand px-6 text-sm font-bold text-white hover:bg-brand-dark lg:flex-none">Search</button>
        @if (array_filter($filters))
            <a href="{{ route('items.search') }}" class="flex h-10 items-center rounded-xl px-3 text-sm font-semibold text-muted hover:bg-canvas">Reset</a>
        @endif
    </div>
</form>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="hidden overflow-x-auto md:block">
        <table class="w-full text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-[13px] font-bold text-muted">
                <tr>
                    <th class="px-5 py-3">Part Number</th>
                    <th class="px-4 py-3">Item Name</th>
                    <th class="px-3 py-3">Type</th>
                    <th class="px-3 py-3">Brand</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Available Stock</th>
                    <th class="px-5 py-3 text-right">Total Recorded</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/50">
                        <td class="px-5 py-3 font-mono font-bold text-brand">{{ $r->part_number ?: '-' }}</td>
                        <td class="px-4 py-3 font-bold">{{ $r->item_name }}</td>
                        <td class="px-3 py-3"><span class="flex items-center gap-2"><x-type-icon :type="$r->type?->name" />{{ $r->type->name ?? '-' }}</span></td>
                        <td class="px-3 py-3">{{ $r->merek ?: '-' }}</td>
                        <td class="px-3 py-3"><x-status-badge :status="$r->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @if ($r->in_stock_units > 0)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-extrabold text-emerald-700">
                                    {{ $r->in_stock_units }} available
                                </span>
                                @if ($r->reserved_units > 0)
                                    <span class="block text-[11px] text-amber-600 font-semibold">({{ $r->reserved_units }} in picking)</span>
                                @endif
                            @elseif ($r->status === 'Lost' || $r->status === 'Sold Out' || $r->sold_units > 0)
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-500">
                                    0 available ({{ $r->sold_units }} out)
                                </span>
                            @else
                                <span class="text-xs text-muted font-semibold">0 available</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right font-extrabold text-slate-700">{{ $r->total_units }} units</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-muted">No matching items found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="divide-y divide-slate-100 md:hidden">
        @forelse ($rows as $r)
            <div class="flex items-center gap-3 px-4 py-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-tint"><x-type-icon :type="$r->type?->name" class="h-5 w-5" /></div>
                <div class="min-w-0 flex-1">
                    <div class="truncate font-mono text-xs font-bold text-brand">{{ $r->part_number ?: '-' }}</div>
                    <div class="truncate font-bold">{{ $r->item_name }}</div>
                    <div class="truncate text-sm text-muted">{{ $r->type->name ?? '-' }} &middot; {{ $r->merek ?: '-' }}</div>
                    <div class="mt-1 flex items-center gap-1.5">
                        <x-status-badge :status="$r->status" />
                        @if (in_array($r->status, ['Available', 'In Stock']))
                            <span class="text-xs font-bold text-emerald-700">({{ $r->in_stock_units }} available)</span>
                        @else
                            <span class="text-xs text-muted">(0 available)</span>
                        @endif
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xl font-extrabold leading-none {{ in_array($r->status, ['Available', 'In Stock']) ? 'text-emerald-700' : 'text-slate-500' }}">
                        {{ in_array($r->status, ['Available', 'In Stock']) ? $r->in_stock_units : 0 }}
                    </div>
                    <div class="text-xs text-muted">{{ in_array($r->status, ['Available', 'In Stock']) ? 'Available' : 'Out/Inactive' }}</div>
                </div>
            </div>
        @empty
            <div class="px-4 py-10 text-center text-muted">No matching items found.</div>
        @endforelse
    </div>
</section>
@endsection
