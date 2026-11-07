@extends('layouts.app')
@section('title', 'Export Reports - Warehouse Inventory')

@section('content')
<x-page-header title="Export Reports - Warehouse Inventory" icon="download">
    <x-slot:subtitle>
        <span class="text-muted">Select custom grouping criteria to export warehouse stock data to an Excel spreadsheet (.xlsx).</span>
    </x-slot:subtitle>
</x-page-header>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    {{-- Filter Form --}}
    <div class="lg:col-span-1">
        <form method="GET" action="{{ route('items.export') }}" id="export-form" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm border border-line">
            <div class="border-b border-line pb-3">
                <h2 class="font-bold text-base text-slate-800 flex items-center gap-2">
                    <i data-lucide="sliders-horizontal" class="h-4 w-4 text-brand"></i>Filter &amp; Grouping Criteria
                </h2>
                <p class="text-xs text-muted mt-1 leading-relaxed">
                    Filter by combinations of categories (e.g. Router &amp; Switch, or AP &amp; Connector), warehouses, and statuses.
                </p>
            </div>

            {{-- Multi-Select Item Types (Pengelompokan Jenis Barang) --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-700">
                        Item Types / Category
                    </label>
                    <div class="flex items-center gap-2 text-[11px]">
                        <button type="button" onclick="toggleAllCheckboxes('.type-checkbox', true)" class="text-brand font-bold hover:underline">Select All</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" onclick="toggleAllCheckboxes('.type-checkbox', false)" class="text-muted hover:underline">Clear</button>
                    </div>
                </div>
                <div class="max-h-48 space-y-1 overflow-y-auto rounded-xl border border-slate-300 bg-white p-2">
                    @foreach ($itemTypes as $t)
                        <label class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50 cursor-pointer text-xs">
                            <div class="flex items-center gap-2.5">
                                <input type="checkbox" name="types[]" value="{{ $t->id }}"
                                       @checked(in_array((string)$t->id, array_map('strval', $selectedTypes)))
                                       class="type-checkbox rounded border-slate-300 text-brand focus:ring-brand">
                                <span class="font-bold text-slate-800">{{ $t->name }}</span>
                            </div>
                            <span class="text-[10px] font-bold text-muted bg-slate-100 rounded px-1.5 py-0.5">{{ $t->items_count }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-muted mt-1">Leave all unchecked to include all item types.</p>
            </div>

            {{-- Multi-Select Warehouse Locations --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-700">
                        Warehouse Location
                    </label>
                    <div class="flex items-center gap-2 text-[11px]">
                        <button type="button" onclick="toggleAllCheckboxes('.loc-checkbox', true)" class="text-brand font-bold hover:underline">Select All</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" onclick="toggleAllCheckboxes('.loc-checkbox', false)" class="text-muted hover:underline">Clear</button>
                    </div>
                </div>
                <div class="max-h-36 space-y-1 overflow-y-auto rounded-xl border border-slate-300 bg-white p-2">
                    @foreach ($locations as $loc)
                        <label class="flex items-center gap-2.5 rounded-lg px-2 py-1 hover:bg-slate-50 cursor-pointer text-xs">
                            <input type="checkbox" name="locations[]" value="{{ $loc->id }}"
                                   @checked(in_array((string)$loc->id, array_map('strval', $selectedLocations)))
                                   class="loc-checkbox rounded border-slate-300 text-brand focus:ring-brand">
                            <span class="font-medium text-slate-700">{{ $loc->name }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-muted mt-1">Leave all unchecked to include all warehouses.</p>
            </div>

            {{-- Multi-Select Item Status --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-700">
                        Item Status
                    </label>
                    <div class="flex items-center gap-2 text-[11px]">
                        <button type="button" onclick="toggleAllCheckboxes('.status-checkbox', true)" class="text-brand font-bold hover:underline">Select All</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" onclick="toggleAllCheckboxes('.status-checkbox', false)" class="text-muted hover:underline">Clear</button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-1 rounded-xl border border-slate-300 bg-white p-2 text-xs">
                    @foreach ($statuses as $st)
                        <label class="flex items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="statuses[]" value="{{ $st }}"
                                   @checked(in_array($st, $selectedStatuses))
                                   class="status-checkbox rounded border-slate-300 text-brand focus:ring-brand">
                            <span class="font-medium text-slate-700 truncate" title="{{ $st }}">{{ $st }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-muted mt-1">Leave all unchecked to include all statuses.</p>
            </div>

            {{-- Brand Filter --}}
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Brand</label>
                <select name="brand" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-brand">
                    <option value="all">All Brands</option>
                    @foreach ($brands as $b)
                        <option value="{{ $b }}" {{ (request('brand') === $b || in_array($b, $selectedBrands)) ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Date Ranges --}}
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Stuff In Date Range</label>
                <div class="grid grid-cols-2 gap-2">
                    <input type="date" name="stuff_in_from" value="{{ request('stuff_in_from') }}" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs focus:border-brand" title="From date">
                    <input type="date" name="stuff_in_to" value="{{ request('stuff_in_to') }}" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs focus:border-brand" title="To date">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Stuff Out Date Range</label>
                <div class="grid grid-cols-2 gap-2">
                    <input type="date" name="stuff_out_from" value="{{ request('stuff_out_from') }}" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs focus:border-brand" title="From date">
                    <input type="date" name="stuff_out_to" value="{{ request('stuff_out_to') }}" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-xs focus:border-brand" title="To date">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Search Keywords</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Part Number, SN, Rack, Item Name..."
                       class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-brand">
            </div>

            <div class="flex flex-col gap-2 pt-3 border-t border-line">
                <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white font-bold text-slate-700 hover:bg-canvas transition-colors">
                    <i data-lucide="eye" class="h-4 w-4"></i>Preview Results
                </button>

                <button type="submit" name="download" value="1" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 font-bold text-white hover:bg-emerald-700 shadow-sm transition-colors">
                    <i data-lucide="download" class="h-4 w-4"></i>Download Excel (.xlsx)
                </button>

                @if (request()->hasAny(['location_id', 'locations', 'status', 'statuses', 'type_id', 'types', 'brand', 'brands', 'stuff_in_from', 'stuff_in_to', 'stuff_out_from', 'stuff_out_to', 'q']))
                    <a href="{{ route('items.export') }}" class="text-center text-xs font-bold text-muted hover:text-accent mt-1">
                        Reset all filters
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Preview & Summary Panel --}}
    <div class="space-y-4 lg:col-span-2">
        {{-- Matching Metric Card --}}
        <div class="rounded-2xl border border-line bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-line pb-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-muted">Estimated Matching Records</span>
                    <div class="text-3xl font-black text-brand mt-0.5">
                        {{ number_format($totalMatched, 0, ',', '.') }} <span class="text-sm font-semibold text-muted">Units</span>
                    </div>
                </div>

                <button type="submit" form="export-form" name="download" value="1" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white hover:bg-emerald-700 shadow-sm transition-colors">
                    <i data-lucide="download" class="h-4 w-4"></i>Download {{ number_format($totalMatched, 0, ',', '.') }} Rows (.xlsx)
                </button>
            </div>

            {{-- Breakdown by Selected Types --}}
            @if (!empty($selectedTypes))
                <div class="mt-3 pt-2">
                    <span class="text-xs font-bold uppercase text-muted">Active Grouping Filter:</span>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($itemTypes->whereIn('id', $selectedTypes) as $selectedType)
                            <span class="inline-flex items-center gap-1 rounded-full bg-brand/10 text-brand px-3 py-1 text-xs font-bold">
                                <i data-lucide="check" class="h-3 w-3"></i>{{ $selectedType->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Breakdown by Status --}}
            <div class="mt-4">
                <span class="text-xs font-bold uppercase text-muted">Status Composition:</span>
                <div class="mt-2 flex flex-wrap gap-2">
                    @forelse ($statusCounts as $stName => $stQty)
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                            <b>{{ $stName }}:</b> {{ number_format($stQty, 0, ',', '.') }}
                        </span>
                    @empty
                        <span class="text-xs text-muted">No records match the selected filter criteria.</span>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Preview Sample Table --}}
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm border border-line">
            <div class="border-b border-line px-4 py-3 bg-slate-50/70 flex items-center justify-between">
                <h3 class="font-bold text-sm text-slate-700 flex items-center gap-2">
                    <i data-lucide="table" class="h-4 w-4 text-slate-500"></i>Preview Records (Sample Top 10)
                </h3>
                <span class="text-xs text-muted">Showing {{ min($previewItems->count(), 10) }} of {{ number_format($totalMatched, 0, ',', '.') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-line uppercase text-muted font-bold">
                        <tr>
                            <th class="px-3.5 py-2.5">Item ID</th>
                            <th class="px-3.5 py-2.5">Type</th>
                            <th class="px-3.5 py-2.5">Part Number</th>
                            <th class="px-3.5 py-2.5">Item Name</th>
                            <th class="px-3.5 py-2.5">Serial Number</th>
                            <th class="px-3.5 py-2.5">Status</th>
                            <th class="px-3.5 py-2.5">Location</th>
                            <th class="px-3.5 py-2.5">Rack</th>
                            <th class="px-3.5 py-2.5">Stuff In</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($previewItems as $it)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-3.5 py-2.5 font-mono font-bold text-slate-800">{{ $it->item_id }}</td>
                                <td class="px-3.5 py-2.5 font-semibold text-slate-700">
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-700">
                                        {{ $it->type->name ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2.5 font-mono font-bold text-brand">{{ $it->part_number ?: '-' }}</td>
                                <td class="px-3.5 py-2.5 font-medium text-slate-900">{{ $it->item_name }}</td>
                                <td class="px-3.5 py-2.5 font-mono text-slate-600">{{ $it->serial_number ?: '-' }}</td>
                                <td class="px-3.5 py-2.5 font-semibold">
                                    <span class="rounded px-1.5 py-0.5 text-[11px] {{ $it->status === 'In Stock' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $it->status }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2.5 text-slate-700">{{ $it->location->name ?? '-' }}</td>
                                <td class="px-3.5 py-2.5 text-slate-700">{{ $it->rack ?: '-' }}</td>
                                <td class="px-3.5 py-2.5 text-muted">{{ $it->stuff_in ? \Carbon\Carbon::parse($it->stuff_in)->format('d/m/Y') : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-8 text-center text-muted">
                                    <i data-lucide="inbox" class="h-6 w-6 mx-auto mb-1 text-slate-300"></i>
                                    No items match the selected filter criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($totalMatched > 10)
                <div class="p-3 bg-slate-50 text-center text-xs text-muted border-t border-line">
                    Showing top 10 preview. All <b>{{ number_format($totalMatched, 0, ',', '.') }}</b> matching records will be downloaded in the Excel file.
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    function toggleAllCheckboxes(selector, checked) {
        document.querySelectorAll(selector).forEach(cb => {
            cb.checked = checked;
        });
    }
</script>
@endsection
