@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<x-page-header title="Dashboard" icon="layout-dashboard">
    <x-slot:subtitle><span class="text-muted">{{ $today }}</span></x-slot:subtitle>
    <x-slot:actions>
        <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
            <label for="loc-filter" class="sr-only">Filter location</label>
            <select id="loc-filter" name="location" onchange="this.form.submit()" class="h-10 rounded-xl border border-line bg-white px-3 text-sm font-semibold">
                <option value="">All locations</option>
                @foreach ($allLocations as $loc)
                    <option value="{{ $loc->id }}" @selected($filterLocation?->id === $loc->id)>{{ $loc->name }}</option>
                @endforeach
            </select>
            @if ($filterLocation)
                <a href="{{ route('dashboard') }}" class="text-sm font-bold text-brand hover:underline">Reset</a>
            @endif
        </form>
        <form method="GET" action="{{ route('items.part') }}" class="hidden sm:block">
            <input type="search" name="q" placeholder="Search SN, PN, item..." aria-label="Search items"
                   class="h-10 w-60 rounded-xl border border-line bg-canvas px-3 text-sm">
        </form>
        @can('warehouse.create')
        <a href="{{ route('items.create') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="plus" class="h-4 w-4"></i>Add Item
        </a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-4">
    @foreach ($stats as $s)
        <a href="{{ $s['url'] }}" class="flex flex-col gap-3 rounded-2xl bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:flex-row sm:items-center">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" style="background: {{ $s['bg'] }}; color: {{ $s['fg'] }};">
                <i data-lucide="{{ $s['icon'] }}" class="h-5 w-5"></i>
            </div>
            <div>
                <div class="text-sm text-muted">{{ $s['label'] }}</div>
                <div class="text-2xl font-extrabold" @if ($loop->last && $s['value'] > 0) style="color: #B0104A;" @endif>
                    {{ number_format($s['value'], 0, ',', '.') }} <span class="text-sm font-normal text-muted">units</span>
                </div>
            </div>
        </a>
    @endforeach
</div>

@if (count($actions))
    <section aria-label="Action Required">
        <h2 class="mb-2 text-sm font-extrabold uppercase tracking-wide text-muted">Action Required</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($actions as $a)
                @php
                    $tone = $a['count'] > 0
                        ? ($a['tone'] === 'amber' ? ['bg' => '#FFF4DB', 'fg' => '#8A5A00'] : ['bg' => '#E6F0F7', 'fg' => '#0066A0'])
                        : ['bg' => '#EEF2F6', 'fg' => '#7A8B9C'];
                @endphp
                <a href="{{ $a['url'] }}" class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $a['count'] > 0 ? '' : 'opacity-70' }}">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" style="background: {{ $tone['bg'] }}; color: {{ $tone['fg'] }};">
                        <i data-lucide="{{ $a['icon'] }}" class="h-5 w-5"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-bold">{{ $a['label'] }}</div>
                        <div class="truncate text-xs text-muted">{{ $a['hint'] }}</div>
                    </div>
                    <div class="text-2xl font-extrabold" style="color: {{ $tone['fg'] }};">{{ $a['count'] }}</div>
                </a>
            @endforeach
        </div>
    </section>
@endif

<div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
    <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ($donuts as $i => $d)
            <div class="flex items-center gap-4 rounded-2xl bg-white p-4 shadow-sm">
                <div class="relative h-24 w-24 shrink-0">
                    <canvas class="block max-w-full" id="donut-{{ $i }}" aria-label="Status composition in {{ $d['name'] }}" role="img"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-lg font-extrabold">{{ $d['total'] }}</div>
                </div>
                <div class="min-w-0 flex-1 space-y-1 text-[13px]">
                    <a href="{{ route('items.index', ['location' => $d['id']]) }}" class="mb-1 block text-base font-extrabold hover:text-brand hover:underline">{{ $d['name'] }}</a>
                    <a href="{{ route('items.index', ['location' => $d['id'], 'status' => 'In Stock']) }}" class="flex items-center gap-2 rounded hover:bg-canvas"><span class="h-2.5 w-2.5 rounded-full bg-brand"></span>In Stock<b class="ml-auto">{{ $d['in_stock'] }}</b></a>
                    <a href="{{ route('items.index', ['location' => $d['id'], 'status' => 'Borrow,Instalation']) }}" class="flex items-center gap-2 rounded hover:bg-canvas"><span class="h-2.5 w-2.5 rounded-full bg-brand-soft"></span>In Use<b class="ml-auto">{{ $d['used'] }}</b></a>
                    <a href="{{ route('items.index', ['location' => $d['id'], 'status' => 'Broken']) }}" class="flex items-center gap-2 rounded hover:bg-canvas"><span class="h-2.5 w-2.5 rounded-full bg-accent"></span>Broken<b class="ml-auto">{{ $d['broken'] }}</b></a>
                    <a href="{{ route('items.index', ['location' => $d['id']]) }}" class="flex items-center gap-2 rounded text-muted hover:bg-canvas"><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>Other<b class="ml-auto text-ink">{{ $d['other'] }}</b></a>
                </div>
            </div>
        @endforeach
    </div>

    <section class="flex min-w-0 flex-col rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="text-lg font-extrabold">Item Distribution by Location</h2>
        <p class="text-sm text-muted">Percentage of total {{ number_format($totalItems, 0, ',', '.') }} units</p>
        <div class="flex flex-1 flex-col items-center gap-5 pt-3 sm:flex-row">
            <div class="h-48 w-48 shrink-0"><canvas class="block max-w-full" id="share-rings" aria-label="Item distribution by location" role="img"></canvas></div>
            <div class="w-full space-y-3">
                @foreach ($share->take(3) as $i => $loc)
                    <a href="{{ route('items.index', ['location' => $loc['id']]) }}" class="flex items-center gap-3 rounded-lg hover:bg-canvas">
                        <span class="h-3 w-3 rounded-full" style="background: {{ ['#0066A0', '#3FB6C9', '#7FA8C4'][$i] }};"></span>
                        <span class="w-20">{{ $loc['name'] }}</span>
                        <b class="text-3xl font-extrabold leading-none">{{ $totalItems ? round($loc['total'] / $totalItems * 100) : 0 }}%</b>
                    </a>
                @endforeach
                <div class="border-t border-line pt-3 text-sm text-muted">
                    @foreach ($share->slice(3) as $loc)
                        <a href="{{ route('items.index', ['location' => $loc['id']]) }}" class="hover:underline {{ $loc['name'] === 'Rak Barang Rusak' ? 'text-accent-dark' : '' }}">{{ $loc['name'] }}: <b class="{{ $loc['name'] === 'Rak Barang Rusak' ? '' : 'text-ink' }}">{{ $loc['total'] }} units</b></a>@if (! $loop->last) &middot; @endif
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <section class="min-w-0 rounded-2xl bg-white p-5 shadow-sm">
        <div class="flex items-baseline justify-between">
            <h2 class="text-lg font-extrabold">Inbound &amp; Outbound Items</h2>
            <span class="text-xs text-muted">Last 6 months</span>
        </div>
        <div class="mt-3 h-60"><canvas class="block max-w-full" id="flow-chart" aria-label="Inbound and outbound items per month" role="img"></canvas></div>
    </section>
    <section class="min-w-0 rounded-2xl bg-white p-5 shadow-sm">
        <div class="flex items-baseline justify-between">
            <h2 class="text-lg font-extrabold">Total Stock Trend</h2>
            <span class="text-xs text-muted">stock at end of month</span>
        </div>
        <div class="mt-3 h-60"><canvas class="block max-w-full" id="stock-chart" aria-label="Total stock trend" role="img"></canvas></div>
    </section>
</div>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="flex items-center justify-between px-5 py-4">
        <h2 class="text-lg font-extrabold">Recent Inbound Items</h2>
        <a href="{{ route('items.index', array_filter(['location' => $filterLocation?->id])) }}" class="text-sm font-bold text-brand hover:underline">View all</a>
    </div>
    <div class="divide-y divide-slate-100 border-t border-line md:hidden">
        @forelse ($recentItems as $item)
            <a href="{{ route('items.index', ['location' => $item->location_id, 'q' => $item->item_id]) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-tint"><x-type-icon :type="$item->type?->name" class="h-5 w-5" /></div>
                <div class="min-w-0 flex-1">
                    <div class="truncate font-bold">{{ $item->item_name }}</div>
                    <div class="truncate text-xs text-muted"><span class="font-mono">{{ $item->item_id }}</span> &middot; {{ $item->location->name ?? '-' }}</div>
                </div>
                <x-status-badge :status="$item->status" />
            </a>
        @empty
            <div class="px-4 py-8 text-center text-muted">No items found.</div>
        @endforelse
    </div>
    <div class="hidden overflow-x-auto md:block">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="border-y border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr><th class="px-5 py-2.5">Item ID</th><th class="px-3 py-2.5">Item Name</th><th class="px-3 py-2.5">Type</th><th class="px-3 py-2.5">Brand</th><th class="px-3 py-2.5">Location</th><th class="px-5 py-2.5">Status</th></tr>
            </thead>
            <tbody>
                @forelse ($recentItems as $item)
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="px-5 py-3 font-mono"><a href="{{ route('items.index', ['location' => $item->location_id, 'q' => $item->item_id]) }}" class="text-brand hover:underline">{{ $item->item_id }}</a></td>
                        <td class="px-3 py-3 font-bold">{{ $item->item_name }}</td>
                        <td class="px-3 py-3"><span class="flex items-center gap-2"><x-type-icon :type="$item->type?->name" />{{ $item->type->name ?? '-' }}</span></td>
                        <td class="px-3 py-3">{{ $item->merek ?: '-' }}</td>
                        <td class="px-3 py-3">{{ $item->location->name ?? '-' }}</td>
                        <td class="px-5 py-3"><x-status-badge :status="$item->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-muted">No items found.@can('warehouse.create') <a href="{{ route('items.create') }}" class="font-bold text-brand">Add first item</a>@endcan</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
    Chart.defaults.font.family = 'Nunito, Segoe UI, sans-serif';
    Chart.defaults.color = '#526477';
    const BRAND = '#0066A0', SOFT = '#3FB6C9', RED = '#D4145A', GREY = '#CBD5E1';

    @foreach ($donuts as $i => $d)
    new Chart(document.getElementById('donut-{{ $i }}'), {
        type: 'doughnut',
        data: {
            labels: ['In Stock', 'In Use', 'Broken', 'Other'],
            datasets: [{!! $d['total']
                ? json_encode(['data' => [$d['in_stock'], $d['used'], $d['broken'], $d['other']], 'backgroundColor' => ['#0066A0', '#3FB6C9', '#D4145A', '#CBD5E1']])
                : json_encode(['data' => [1], 'backgroundColor' => ['#EEF2F6']]) !!}],
        },
        options: { cutout: '72%', borderWidth: 0, plugins: { legend: { display: false }, tooltip: { enabled: {{ $d['total'] ? 'true' : 'false' }} } } },
    });
    @endforeach

    const shares = @json($share->take(3)->map(fn ($l) => $totalItems ? round($l['total'] / $totalItems * 100) : 0)->values());
    new Chart(document.getElementById('share-rings'), {
        type: 'doughnut',
        data: {
            datasets: shares.flatMap((v, i) => [
                ...(i ? [{ data: [100], backgroundColor: ['transparent'], borderWidth: 0, weight: 0.7 }] : []),
                { data: [v, 100 - v], backgroundColor: [[BRAND, SOFT, '#7FA8C4'][i], '#EEF2F6'], borderWidth: 0, borderRadius: 8, weight: 1 },
            ]),
        },
        options: { circumference: 270, rotation: -135, cutout: '42%', plugins: { legend: { display: false }, tooltip: { enabled: false } } },
    });

    const monthly = @json($monthly);
    new Chart(document.getElementById('flow-chart'), {
        type: 'bar',
        data: {
            labels: monthly.labels,
            datasets: [
                { label: 'Inbound', data: monthly.in, backgroundColor: BRAND, borderRadius: 6, stack: 's' },
                { label: 'Outbound', data: monthly.out, backgroundColor: '#A9CFE6', borderRadius: 6, stack: 's' },
            ],
        },
        options: {
            maintainAspectRatio: false,
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#EEF2F6' } } },
            plugins: { legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } } },
        },
    });

    const stockCtx = document.getElementById('stock-chart');
    const fill = stockCtx.getContext('2d').createLinearGradient(0, 0, 0, 240);
    fill.addColorStop(0, 'rgba(0,102,160,.28)');
    fill.addColorStop(1, 'rgba(0,102,160,0)');
    new Chart(stockCtx, {
        type: 'line',
        data: {
            labels: monthly.labels,
            datasets: [{ label: 'Total Stock', data: monthly.stock, borderColor: BRAND, backgroundColor: fill, fill: true, tension: .4, borderWidth: 3, pointBackgroundColor: '#fff', pointBorderWidth: 2, pointRadius: 4 }],
        },
        options: {
            maintainAspectRatio: false,
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#EEF2F6' } } },
            plugins: { legend: { display: false } },
        },
    });
</script>
@endpush
