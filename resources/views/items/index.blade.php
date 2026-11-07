@extends('layouts.app')
@section('title', 'Warehouse')

@section('content')
<x-page-header title="Warehouse" icon="warehouse">
    <x-slot:subtitle>
        @foreach ($summary as $label => $value)
            @php
                $statusParam = match($label) {
                    'Available' => 'Available',
                    'Under Service' => 'Under Service',
                    'Damaged' => 'Damaged',
                    'Rented' => 'Rented',
                    default => null,
                };
                $isActive = request('status') === $statusParam;
            @endphp
            @if ($statusParam)
                <a href="{{ route('items.index', array_filter(['location' => $current?->id, 'status' => $isActive ? null : $statusParam, 'q' => request('q')])) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg px-2 py-0.5 transition {{ $isActive ? 'bg-brand text-white font-bold' : ($label === 'Damaged' ? 'text-accent-dark hover:bg-slate-100' : 'hover:bg-slate-100') }}"
                   title="Filter {{ $label }}">
                    <span>{{ $label }}:</span> <b>{{ number_format($value, 0, ',', '.') }}</b>
                </a>
            @else
                <span>{{ $label }}: <b>{{ number_format($value, 0, ',', '.') }}</b></span>
            @endif
        @endforeach
    </x-slot:subtitle>
    <x-slot:actions>
        <a href="{{ route('items.export', array_filter(['location_id' => $current?->id, 'status' => request('status'), 'q' => request('q')])) }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas">
            <i data-lucide="download" class="h-4 w-4"></i>Export Excel
        </a>
        @can('warehouse.create')
        <a href="{{ route('items.import') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas">
            <i data-lucide="upload" class="h-4 w-4"></i>Import
        </a>
        <a href="{{ route('items.create', array_filter(['location' => $current?->id])) }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="plus" class="h-4 w-4"></i>Add Item
        </a>
        @endcan
    </x-slot:actions>
</x-page-header>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('items.index') }}" class="flex flex-wrap items-center gap-3">
            @if (request('q')) <input type="hidden" name="q" value="{{ request('q') }}"> @endif
            
            <label for="warehouse-select" class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted">
                <i data-lucide="warehouse" class="h-4 w-4 text-brand"></i> Warehouse:
            </label>
            <div class="relative">
                <select id="warehouse-select" name="location" onchange="this.form.submit()"
                        class="h-10 rounded-xl border border-slate-300 bg-white pl-3.5 pr-8 text-sm font-bold text-ink focus:border-brand focus:outline-none">
                    <option value="" @selected(is_null($current))>All ({{ $totalItemsCount }} items)</option>
                    @foreach ($locations as $index => $loc)
                        <option value="{{ $loc->id }}" @selected($current && $loc->id === $current->id)>
                            Warehouse {{ $index + 1 }} - {{ $loc->name }} ({{ $loc->items_count }})
                        </option>
                    @endforeach
                </select>
            </div>

            <label for="status-select" class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted">
                <i data-lucide="tag" class="h-4 w-4 text-brand"></i> Status:
            </label>
            <div class="relative">
                <select id="status-select" name="status" onchange="this.form.submit()"
                        class="h-10 rounded-xl border border-slate-300 bg-white pl-3.5 pr-8 text-sm font-bold text-ink focus:border-brand focus:outline-none">
                    <option value="">All Statuses</option>
                    @foreach (config('item_options.statuses') as $st)
                        <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>

            @if ($current || request('status') || request('q'))
                <a href="{{ route('items.index') }}"
                   class="text-xs font-bold text-brand hover:underline">Reset</a>
            @endif
        </form>

        <form method="GET" action="{{ route('items.index') }}" class="w-full sm:w-auto">
            @if ($current)<input type="hidden" name="location" value="{{ $current->id }}">@endif
            @if (request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"></i>
                <input type="search" name="q" value="{{ request('q') }}"
                       placeholder="Search in {{ $current ? ('Warehouse ' . (array_search($current->id, $locations->pluck('id')->all()) + 1) . ' - ' . $current->name) : 'All Warehouses' }}..."
                       class="h-10 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm focus:border-brand sm:w-64">
            </div>
        </form>
    </div>

    @if (request('status'))
        <div class="flex flex-wrap items-center gap-2 border-b border-line bg-brand-tint/50 px-4 py-2 text-sm">
            <i data-lucide="filter" class="h-4 w-4 text-brand"></i>
            <span>Status filter: <b>{{ str_replace(',', ' + ', request('status')) }}</b> in {{ $current?->name ?? 'All Warehouses' }}</span>
            <a href="{{ route('items.index', array_filter(['location' => $current?->id])) }}" class="ml-auto font-bold text-brand hover:underline">Clear filter</a>
        </div>
    @endif

    {{-- Desktop: one row per Part Number. Click anywhere on a row to open all stock of that Part Number. --}}
    <div class="hidden overflow-x-auto md:block">
        <table class="w-full min-w-[800px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr>
                    <th class="px-4 py-2.5">Part Number</th><th class="px-3 py-2.5">Item Name</th>
                    <th class="px-3 py-2.5">Type</th><th class="px-3 py-2.5">Brand</th><th class="px-4 py-2.5 text-right">Available Qty</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $g)
                    @php $url = route('items.part', array_filter(['pn' => $g->pn ?: null, 'name' => $g->pn ? null : $g->item_name, 'location' => $current?->id])); @endphp
                    <tr class="cursor-pointer border-b border-slate-100 last:border-0 hover:bg-brand-tint/40" data-href="{{ $url }}">
                        <td class="px-4 py-3 font-mono font-bold text-brand"><a href="{{ $url }}" class="hover:underline">{{ $g->pn !== '' ? $g->pn : '-' }}</a></td>
                        <td class="px-3 py-3 font-bold">{{ $g->item_name }}</td>
                        <td class="px-3 py-3"><span class="flex items-center gap-2"><x-type-icon :type="$g->type_name" />{{ $g->type_name ?? '-' }}</span></td>
                        <td class="px-3 py-3">{{ $g->merek ?: '-' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-tint px-3 py-1 text-xs font-extrabold text-brand">
                                <span>{{ $g->in_stock }}</span>
                                <span class="font-normal text-slate-500">(New {{ $g->new_qty }}, Used {{ $g->used_qty }})</span>
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-muted">
                        No items found in {{ $current?->name ?? 'All Warehouses' }}{{ request('q') ? ' matching "' . request('q') . '"' : '' }}.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile: cards --}}
    <div class="divide-y divide-slate-100 md:hidden">
        @forelse ($groups as $g)
            @php $mobileUrl = route('items.part', array_filter(['pn' => $g->pn ?: null, 'name' => $g->pn ? null : $g->item_name, 'location' => $current?->id])); @endphp
            <a href="{{ $mobileUrl }}" class="flex items-center gap-3 px-4 py-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-tint"><x-type-icon :type="$g->type_name" class="h-5 w-5" /></div>
                <div class="min-w-0 flex-1">
                    <div class="truncate font-mono text-sm font-bold text-brand">{{ $g->pn !== '' ? $g->pn : '-' }}</div>
                    <div class="truncate text-sm font-bold">{{ $g->item_name }}</div>
                    <div class="truncate text-xs text-muted">{{ $g->merek ?: '-' }}</div>
                </div>
                <span class="rounded-full bg-brand-tint px-2.5 py-1 text-xs font-extrabold text-brand">
                    {{ $g->in_stock }} <span class="font-normal text-slate-500">(New {{ $g->new_qty }}, Used {{ $g->used_qty }})</span>
                </span>
                <i data-lucide="chevron-right" class="h-4 w-4 text-muted"></i>
            </a>
        @empty
            <div class="px-4 py-10 text-center text-muted">No items found in {{ $current?->name ?? 'All Warehouses' }}.</div>
        @endforelse
    </div>

    @if ($groups->hasPages())
        <div class="border-t border-line px-4 py-3">{{ $groups->links() }}</div>
    @endif
</section>
@endsection

@push('scripts')
<script>
    document.querySelector('nav[aria-label="Locations"] [aria-current="page"]')?.scrollIntoView({ inline: 'center', block: 'nearest' });
    // Click anywhere on a row to open it
    document.querySelectorAll('tr[data-href]').forEach((row) => {
        row.addEventListener('click', (e) => { if (!e.target.closest('a')) window.location = row.dataset.href; });
    });
</script>
@endpush
