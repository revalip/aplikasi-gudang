@extends('layouts.app')
@section('title', $pn !== '' ? $pn : ($name ?: 'Search Warehouse Units'))

@section('content')
@php
    $canEdit = auth()->user()->can('warehouse.update');
    $canDelete = auth()->user()->can('warehouse.delete');
    $canManage = $canEdit || $canDelete;
    $backUrl = route('items.index', array_filter(['location' => $location?->id]));
    $activeFiltersCount = collect([$sn, $condition, $status, $location, ($name && $pn ? $name : null)])->filter()->count();
@endphp
<x-page-header :title="$pn !== '' ? $pn : ($name ?: 'Warehouse Unit Search')" icon="boxes">
    <x-slot:subtitle>
        @if ($name)
            <span class="text-slate-700 font-semibold">{{ $name }}</span>
            <span class="text-muted">{{ $type?->name ?? '-' }} &bull; {{ $brand ?: '-' }}</span>
        @endif
        <span>{{ $units->total() }} {{ $units->total() == 1 ? 'unit' : 'units' }}{{ $location ? ' in ' . $location->name : ' in all warehouses' }}</span>
        @if ($hasSerial)
            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700 border border-blue-200">
                <i data-lucide="scan-line" class="h-3 w-3"></i> Serialized
            </span>
        @else
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 border border-amber-200">
                <i data-lucide="layers" class="h-3 w-3"></i> Non-Serialized (Qty)
            </span>
        @endif
    </x-slot:subtitle>
    <x-slot:actions>
        <a href="{{ $backUrl }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to Warehouse
        </a>
    </x-slot:actions>
</x-page-header>

{{-- SEARCH & FILTER BAR --}}
<section class="mb-4 rounded-2xl bg-white p-4 shadow-sm border border-line">
    <form method="GET" action="{{ route('items.part') }}" class="space-y-3">
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
            {{-- Filter Serial Number / SN --}}
            <div>
                <label for="filter-sn" class="block text-xs font-bold text-slate-700 mb-1">Serial Number (SN)</label>
                <div class="relative">
                    <i data-lucide="scan" class="absolute left-2.5 top-2.5 h-4 w-4 text-muted"></i>
                    <input id="filter-sn" type="text" name="sn" value="{{ $sn }}" placeholder="Search SN..."
                           class="h-9 w-full rounded-xl border border-slate-300 bg-white pl-8 pr-2.5 text-xs font-mono focus:border-brand">
                </div>
            </div>

            {{-- Filter Part Number --}}
            <div>
                <label for="filter-pn" class="block text-xs font-bold text-slate-700 mb-1">Part Number</label>
                <input id="filter-pn" type="text" name="pn" value="{{ $pn }}" placeholder="e.g. CCR2216..."
                       class="h-9 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs font-mono font-semibold focus:border-brand">
            </div>

            {{-- Filter Item Name --}}
            <div>
                <label for="filter-name" class="block text-xs font-bold text-slate-700 mb-1">Item Name</label>
                <input id="filter-name" type="text" name="name" value="{{ $name }}" placeholder="Search name..."
                       class="h-9 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs focus:border-brand">
            </div>

            {{-- Filter Condition Dropdown --}}
            <div>
                <label for="filter-condition" class="block text-xs font-bold text-slate-700 mb-1">Condition</label>
                <select id="filter-condition" name="condition" class="h-9 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs font-semibold focus:border-brand">
                    <option value="">All Conditions</option>
                    @foreach ($conditions as $c)
                        <option value="{{ $c }}" @selected($condition === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Status Dropdown --}}
            <div>
                <label for="filter-status" class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                <select id="filter-status" name="status" class="h-9 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs font-semibold focus:border-brand">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
                    @endforeach
                    <option value="Sold Out" @selected($status === 'Sold Out')>Sold Out (Out of warehouse)</option>
                </select>
            </div>

            {{-- Filter Warehouse Location --}}
            <div>
                <label for="filter-location" class="block text-xs font-bold text-slate-700 mb-1">Warehouse</label>
                <select id="filter-location" name="location" class="h-9 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs font-semibold focus:border-brand">
                    <option value="">All Warehouses</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected($location?->id == $loc->id)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-slate-100 pt-3">
            <span class="text-xs text-muted">
                Showing {{ $units->total() }} matching result(s)
            </span>
            <div class="flex items-center gap-2">
                @if ($activeFiltersCount > 0 || $pn || $name)
                    <a href="{{ route('items.part', ['pn' => $pn]) }}" class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-600 hover:bg-canvas">
                        <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Reset
                    </a>
                @endif
                <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-brand px-4 text-xs font-bold text-white hover:bg-brand-dark">
                    <i data-lucide="search" class="h-3.5 w-3.5"></i> Apply Filter
                </button>
            </div>
        </div>
    </form>
</section>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm border border-line">
    <div class="hidden overflow-x-auto md:block">
        <table class="w-full min-w-[900px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted uppercase tracking-wider">
                <tr>
                    {{-- Requirement #8: Conditionally show SN header --}}
                    @if ($hasSerial)
                        <th class="px-4 py-3">SN / MAC ID</th>
                    @else
                        <th class="px-4 py-3">Qty / Satuan</th>
                    @endif
                    <th class="px-3 py-3">Part Number</th>
                    <th class="px-3 py-3">Brand</th>
                    <th class="px-3 py-3">Condition</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3">Warehouse</th>
                    <th class="px-3 py-3">Rack</th>
                    <th class="px-3 py-3">Shelf / Bin</th>
                    <th class="px-3 py-3">Stuff In</th>
                    @if ($canManage)<th class="px-4 py-3 text-right">Actions</th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($units as $item)
                    @php $url = route('items.show', $item); @endphp
                    <tr class="cursor-pointer hover:bg-brand-tint/40 transition-colors" data-href="{{ $url }}">
                        {{-- Requirement #8: Conditionally show SN cell --}}
                        @if ($hasSerial)
                            <td class="px-4 py-3 font-mono text-xs">
                                <a href="{{ $url }}" class="font-bold text-brand hover:underline" title="View details">
                                    {{ $item->serial_number ?: '-' }}
                                </a>
                            </td>
                        @else
                            <td class="px-4 py-3 font-semibold text-slate-800">
                                <a href="{{ $url }}" class="font-bold text-brand hover:underline" title="View details">
                                    1 {{ $item->unit ?: 'pcs' }}
                                </a>
                            </td>
                        @endif
                        <td class="px-3 py-3 font-mono font-bold text-slate-800">{{ $item->part_number ?: '-' }}</td>
                        <td class="px-3 py-3">{{ $item->merek ?: '-' }}</td>
                        <td class="px-3 py-3 text-muted">{{ $item->condition }}</td>
                        <td class="px-3 py-3"><x-status-badge :status="$item->status" /></td>
                        <td class="px-3 py-3 text-muted">{{ $item->location?->name ?? '-' }}</td>
                        <td class="px-3 py-3 font-semibold text-slate-700">{{ $item->rack ?: '-' }}</td>
                        <td class="px-3 py-3 text-muted font-mono text-xs">{{ $item->shelf ?: '-' }}</td>
                        <td class="px-3 py-3 whitespace-nowrap text-muted">{{ $item->stuff_in ? \Illuminate\Support\Carbon::parse($item->stuff_in)->format('d/m/Y') : '-' }}</td>
                        @if ($canManage)
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                @if ($canEdit)
                                <a href="{{ route('items.edit', $item) }}" class="rounded-lg p-1.5 text-muted hover:bg-brand-tint hover:text-brand" aria-label="Edit" title="Edit">
                                    <i data-lucide="pencil" class="h-4 w-4"></i>
                                </a>
                                @endif
                                @if ($canDelete)
                                <form method="POST" action="{{ route('items.destroy', $item) }}" onsubmit="return confirm({{ Js::from("Delete unit " . ($item->serial_number ?: $item->part_number) . "?") }})">
                                    @csrf @method('DELETE')
                                    <button class="rounded-lg p-1.5 text-accent hover:bg-accent-tint" aria-label="Delete" title="Delete">
                                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $hasSerial ? 10 : 9 }}" class="p-8 text-center text-muted">
                            <i data-lucide="inbox" class="h-8 w-8 mx-auto mb-2 text-slate-300"></i>
                            <div class="font-bold text-slate-700">No warehouse units found matching your search.</div>
                            <div class="text-xs text-muted mt-1">Try adjusting the filter criteria or resetting filters.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile View --}}
    <div class="divide-y divide-slate-100 md:hidden">
        @forelse ($units as $item)
            <a href="{{ route('items.show', $item) }}" class="flex items-center gap-3 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <div class="truncate font-mono text-sm font-bold text-brand">
                        {{ $hasSerial ? ($item->serial_number ?: '-') : ($item->part_number ?: $item->item_name) }}
                        @if (!$hasSerial)
                            <span class="text-xs font-normal text-muted">(1 {{ $item->unit ?: 'pcs' }})</span>
                        @endif
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted"><x-status-badge :status="$item->status" /> {{ $item->condition }}</div>
                    <div class="mt-0.5 text-xs text-muted flex items-center gap-1">
                        <i data-lucide="map-pin" class="h-3 w-3 text-brand"></i>
                        <span>{{ $item->formatted_location }}</span>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="h-4 w-4 text-muted"></i>
            </a>
        @empty
            <div class="p-6 text-center text-xs text-muted">
                No warehouse units found matching your search.
            </div>
        @endforelse
    </div>

    @if ($units->hasPages())
        <div class="border-t border-line px-4 py-3">{{ $units->links() }}</div>
    @endif
</section>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('tr[data-href]').forEach((row) => {
        row.addEventListener('click', (e) => { if (!e.target.closest('a, button, form')) window.location = row.dataset.href; });
    });
</script>
@endpush
