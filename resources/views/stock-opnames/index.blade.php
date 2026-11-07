@extends('layouts.app')
@section('title', 'Stock Opname')

@section('content')
<x-page-header title="Stock Opname" icon="clipboard-check">
    <x-slot:subtitle>
        <span class="text-muted">Export stock per floor before counting, import actual physical count, and reconcile variances.</span>
    </x-slot:subtitle>
    <x-slot:actions>
        @if ($tab === 'opname')
            <a href="{{ route('stock-opnames.export-template', ['location_id' => $currentLocation?->id, 'type_id' => request('type_id'), 'brand' => request('brand'), 'q' => request('q')]) }}"
               class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-canvas shadow-sm"
               title="Export system stock of this floor to Excel before counting">
                <i data-lucide="download" class="h-4 w-4 text-brand"></i>Export Stock Before
            </a>

            @can('warehouse.create')
            <button type="button" onclick="document.getElementById('modal-import-real').showModal()"
                    class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-canvas shadow-sm"
                    title="Import actual physical stock counted in warehouse">
                <i data-lucide="upload" class="h-4 w-4 text-emerald-600"></i>Import Real Count
            </button>
            @endcan

            <a href="{{ route('stock-opnames.index', ['tab' => 'list']) }}"
               class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-bold text-white hover:bg-slate-800 shadow-sm">
                <i data-lucide="history" class="h-4 w-4"></i>Opname History &amp; BASO
            </a>
        @else
            <a href="{{ route('stock-opnames.index', ['tab' => 'opname', 'location_id' => request('location_id')]) }}"
               class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark shadow-sm">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to Audit Workspace
            </a>
        @endif
    </x-slot:actions>
</x-page-header>

@if ($tab === 'opname')
    {{-- ============================================================== --}}
    {{-- TAB 1: AUDIT WORKSPACE (NATIVE LIGHT CLEAN DESIGN)             --}}
    {{-- ============================================================== --}}

    {{-- Summary Metric Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-4">
        <div class="rounded-2xl bg-white p-4 shadow-sm border border-line">
            <div class="text-xs font-bold text-muted uppercase">Warehouse Floor</div>
            <div class="text-xl font-black text-slate-800 mt-1 truncate">{{ $currentLocation?->name ?? 'All' }}</div>
            <div class="text-xs text-slate-400 mt-0.5">Active audit target</div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm border border-line">
            <div class="text-xs font-bold text-muted uppercase">Total Products</div>
            <div class="text-2xl font-black text-slate-800 mt-1" id="stat-total">{{ $totalItems }}</div>
            <div class="text-xs text-slate-400 mt-0.5">Part numbers recorded</div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm border border-emerald-100">
            <div class="text-xs font-bold text-emerald-700 uppercase">Matched (0 Variance)</div>
            <div class="text-2xl font-black text-emerald-600 mt-1" id="stat-matched">{{ $matchedCount }}</div>
            <div class="text-xs text-emerald-600 mt-0.5">Physical matches system</div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm border {{ $discrepancyCount > 0 ? 'border-rose-200 bg-rose-50/20' : 'border-line' }}">
            <div class="text-xs font-bold {{ $discrepancyCount > 0 ? 'text-rose-700' : 'text-slate-500' }} uppercase">Discrepancies (Variance)</div>
            <div class="text-2xl font-black {{ $discrepancyCount > 0 ? 'text-rose-600' : 'text-slate-700' }} mt-1" id="stat-discrepancy">{{ $discrepancyCount }}</div>
            <div class="text-xs {{ $discrepancyCount > 0 ? 'text-rose-500' : 'text-slate-400' }} mt-0.5">Requires reconciliation</div>
        </div>
    </div>

    {{-- Notification if physical file is imported --}}
    @if ($importedFilename)
        <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 mb-4">
            <div class="flex items-center gap-2">
                <i data-lucide="file-check-2" class="h-5 w-5 text-emerald-600"></i>
                <span>File <b>{{ $importedFilename }}</b> imported! Real physical quantities and variances are loaded below.</span>
            </div>
            <form method="POST" action="{{ route('stock-opnames.clear-import') }}">
                @csrf
                <input type="hidden" name="location_id" value="{{ $currentLocation?->id }}">
                <button type="submit" class="text-xs font-bold text-emerald-800 underline hover:text-accent">
                    Reset Import
                </button>
            </form>
        </div>
    @endif

    {{-- Main Section: Floor Tabs, Filters, and Table --}}
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm border border-line">
        {{-- Warehouse Floor Tabs (Native design like Warehouse module) --}}
        <div class="border-b border-line px-4 pt-2">
            <nav class="-mb-px flex gap-1 overflow-x-auto text-sm" aria-label="Warehouse Floors">
                @foreach ($locations as $loc)
                    @php $on = $currentLocation && $loc->id === $currentLocation->id; @endphp
                    <a href="{{ route('stock-opnames.index', ['tab' => 'opname', 'location_id' => $loc->id]) }}"
                       class="flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-[3px] px-3.5 pb-3 pt-2 text-sm {{ $on ? 'border-brand font-bold text-brand' : 'border-transparent font-semibold text-slate-600 hover:text-ink' }}">
                        <i data-lucide="warehouse" class="h-4 w-4"></i>{{ $loc->name }}
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Filter Row --}}
        <div class="border-b border-line p-4 bg-slate-50/50">
            <form method="GET" action="{{ route('stock-opnames.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1.5fr_1fr_auto] lg:items-end">
                <input type="hidden" name="tab" value="opname">
                <input type="hidden" name="location_id" value="{{ $currentLocation?->id }}">

                <div>
                    <label class="text-xs font-bold text-muted">Item Type</label>
                    <select name="type_id" class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-sm">
                        <option value="all">All Types</option>
                        @foreach ($itemTypes as $t)
                            <option value="{{ $t->id }}" @selected(request('type_id') == $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-xs font-bold text-muted">Brand</label>
                    <select name="brand" class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-sm">
                        <option value="all">All Brands</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b }}" @selected(request('brand') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-xs font-bold text-muted">Product / Part Number</label>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by part no or name..."
                           class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm">
                </div>

                <div>
                    <label class="text-xs font-bold text-muted">Variance Filter</label>
                    <select name="status_filter" class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-sm">
                        <option value="">All Statuses</option>
                        <option value="match" @selected(request('status_filter') === 'match')>Match (0 Variance)</option>
                        <option value="discrepancy" @selected(request('status_filter') === 'discrepancy')>Discrepancies Only</option>
                        <option value="surplus" @selected(request('status_filter') === 'surplus')>Surplus Only (+)</option>
                        <option value="deficit" @selected(request('status_filter') === 'deficit')>Deficit Only (-)</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="h-10 rounded-xl bg-brand px-4 text-xs font-bold text-white hover:bg-brand-dark">Filter</button>
                    @if (request()->hasAny(['type_id', 'brand', 'q', 'status_filter']))
                        <a href="{{ route('stock-opnames.index', ['tab' => 'opname', 'location_id' => $currentLocation?->id]) }}" class="inline-flex h-10 items-center rounded-xl px-3 text-xs font-semibold text-muted hover:bg-canvas">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Form Save & Post Opname --}}
        <form method="POST" action="{{ route('stock-opnames.save-session') }}" id="save-opname-form">
            @csrf
            <input type="hidden" name="location_id" value="{{ $currentLocation?->id }}">
            <input type="hidden" name="opname_date" value="{{ now()->format('Y-m-d') }}">

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" id="opname-table">
                    <thead class="bg-slate-50 border-b border-line text-xs uppercase text-slate-500 font-bold">
                        <tr>
                            <th rowspan="2" class="px-4 py-3 text-center w-12 border-r border-slate-200">No</th>
                            <th rowspan="2" class="px-4 py-3 border-r border-slate-200">Part Number</th>
                            <th rowspan="2" class="px-4 py-3 border-r border-slate-200">Product Name</th>
                            <th colspan="3" class="px-3 py-2 text-center border-b border-r border-slate-200 bg-slate-100/70 text-slate-700">
                                Quantity
                            </th>
                            <th rowspan="2" class="px-3 py-3 text-center w-20 border-r border-slate-200">Unit</th>
                            <th rowspan="2" class="px-3 py-3 text-center w-28 border-r border-slate-200">Status</th>
                            <th rowspan="2" class="px-4 py-3">Notes</th>
                        </tr>
                        <tr class="bg-slate-50/80">
                            <th class="px-3 py-1.5 text-right w-24 border-r border-slate-200">System</th>
                            <th class="px-3 py-1.5 text-center w-28 border-r border-slate-200 text-brand">Real (Physical)</th>
                            <th class="px-3 py-1.5 text-right w-24 border-r border-slate-200">Variance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($items as $idx => $it)
                            <tr class="hover:bg-slate-50/60 transition-colors row-item" data-row-idx="{{ $idx }}">
                                <td class="px-4 py-3 text-center text-slate-400 font-mono border-r border-slate-100">
                                    {{ $idx + 1 }}
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-xs text-slate-800 border-r border-slate-100">
                                    <input type="hidden" name="items[{{ $idx }}][part_number]" value="{{ $it->part_number }}">
                                    {{ $it->part_number }}
                                </td>
                                <td class="px-4 py-3 border-r border-slate-100">
                                    <input type="hidden" name="items[{{ $idx }}][item_name]" value="{{ $it->item_name }}">
                                    <div class="font-bold text-slate-900">{{ $it->item_name }}</div>
                                    <div class="text-xs text-muted">{{ $it->brand }} &bull; {{ $it->type_name }}</div>
                                </td>
                                <td class="px-3 py-3 text-right font-semibold text-slate-600 border-r border-slate-100">
                                    <input type="hidden" name="items[{{ $idx }}][system_qty]" value="{{ $it->system_qty }}">
                                    {{ $it->system_qty }}
                                </td>
                                <td class="px-3 py-2 text-center border-r border-slate-100">
                                    <input type="number" min="0" required
                                           name="items[{{ $idx }}][real_qty]"
                                           value="{{ $it->real_qty }}"
                                           data-sys="{{ $it->system_qty }}"
                                           oninput="calcRowVariance(this, {{ $idx }})"
                                           class="h-8 w-20 rounded-lg border border-slate-300 bg-white px-2 text-center font-extrabold text-slate-900 focus:border-brand focus:ring-1 focus:ring-brand real-input">
                                </td>
                                <td class="px-3 py-3 text-right font-extrabold border-r border-slate-100 var-cell" id="var-cell-{{ $idx }}">
                                    @if ($it->variance === 0)
                                        <span class="text-emerald-600">0</span>
                                    @elseif ($it->variance > 0)
                                        <span class="text-blue-600">+{{ $it->variance }}</span>
                                    @else
                                        <span class="text-rose-600">{{ $it->variance }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center text-slate-500 border-r border-slate-100">
                                    {{ $it->unit }}
                                </td>
                                <td class="px-3 py-3 text-center border-r border-slate-100" id="status-cell-{{ $idx }}">
                                    @if ($it->variance === 0)
                                        <span class="inline-flex rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-xs font-bold text-emerald-700">Match</span>
                                    @elseif ($it->variance > 0)
                                        <span class="inline-flex rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-xs font-bold text-blue-700">+Surplus</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-xs font-bold text-rose-700">-Deficit</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <input type="text" name="items[{{ $idx }}][notes]" value="{{ $it->notes }}" placeholder="Notes or serial numbers..."
                                           class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-brand">
                                    <input type="hidden" name="items[{{ $idx }}][serial_numbers]" value="{{ $it->serials }}">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-10 text-center text-slate-400">
                                    <i data-lucide="package-open" class="mx-auto mb-2 h-10 w-10 text-slate-300"></i>
                                    <div class="font-bold text-slate-600">No products found in {{ $currentLocation?->name ?? 'this warehouse floor' }}.</div>
                                    <div class="text-xs">Select another warehouse floor tab above or clear filters.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Bottom Action & Validation Bar --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 bg-slate-50 border-t border-line">
                <div class="text-xs text-slate-500 font-semibold">
                    * Make sure to save the session before switching warehouse floors or resetting filters.
                </div>

                <div class="flex items-center gap-2.5 self-end sm:self-auto">
                    <button type="button" onclick="validateOpname()"
                            class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-canvas shadow-sm">
                        <i data-lucide="rotate-cw" class="h-3.5 w-3.5"></i>Validate Count
                    </button>

                    @can('warehouse.create')
                    <button type="submit"
                            class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-5 text-xs font-bold text-white hover:bg-brand-dark shadow-sm">
                        <i data-lucide="save" class="h-4 w-4"></i>Save &amp; Post Opname
                    </button>
                    @endcan
                </div>
            </div>
        </form>
    </section>

    {{-- Native Modal: Upload Real Physical Count File --}}
    <dialog id="modal-import-real" class="backdrop:bg-slate-900/40 rounded-2xl border border-line bg-white p-6 shadow-2xl max-w-lg w-full text-ink">
        <div class="flex items-center justify-between pb-3 border-b border-line">
            <h3 class="font-bold text-base text-slate-800 flex items-center gap-2">
                <i data-lucide="upload" class="h-5 w-5 text-brand"></i>Import Real Physical Stock Count
            </h3>
            <button type="button" onclick="document.getElementById('modal-import-real').close()" class="text-slate-400 hover:text-ink">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('stock-opnames.import-real') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="location_id" value="{{ $currentLocation?->id }}">

            <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-3.5 text-xs text-blue-900 space-y-1.5">
                <div class="font-bold flex items-center gap-1.5">
                    <i data-lucide="info" class="h-4 w-4 text-brand"></i>Standard Stock Opname Workflow:
                </div>
                <div>1. Click <b>Export Stock Before</b> above to download the inventory list of <b>{{ $currentLocation?->name }}</b>.</div>
                <div>2. Count physical items on the warehouse floor and enter the numbers into the <b>Real Qty</b> column.</div>
                <div>3. Upload that file here. The system will automatically compute differences (variances) against system stock.</div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">Select Counted File (.xlsx / .csv)</label>
                <input type="file" name="file" accept=".xlsx,.csv" required
                       class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand-tint file:text-brand hover:file:bg-brand/20 cursor-pointer">
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-line">
                <button type="button" onclick="document.getElementById('modal-import-real').close()" class="h-10 px-4 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                    Cancel
                </button>
                <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-5 text-xs font-bold text-white hover:bg-brand-dark shadow-sm">
                    <i data-lucide="upload" class="h-4 w-4"></i>Upload &amp; Calculate
                </button>
            </div>
        </form>
    </dialog>

@else
    {{-- ============================================================== --}}
    {{-- TAB 2: STOCK OPNAME HISTORY & BASO (NATIVE DESIGN)             --}}
    {{-- ============================================================== --}}

    {{-- Metric Summary Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-4">
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-500">Total Audits</span>
            <div class="mt-2 text-2xl font-black text-ink">{{ number_format($summary['total'], 0, ',', '.') }}</div>
            <div class="text-xs text-slate-400 mt-0.5">Historical opnames</div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <span class="text-xs font-bold uppercase text-emerald-600">Completed (Reconciled)</span>
            <div class="mt-2 text-2xl font-black text-emerald-600">{{ number_format($summary['completed'], 0, ',', '.') }}</div>
            <div class="text-xs text-slate-400 mt-0.5">Stock adjustments posted</div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <span class="text-xs font-bold uppercase text-amber-600">Draft (In Progress)</span>
            <div class="mt-2 text-2xl font-black text-amber-600">{{ number_format($summary['draft'], 0, ',', '.') }}</div>
            <div class="text-xs text-slate-400 mt-0.5">Pending completion</div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-500">Cancelled</span>
            <div class="mt-2 text-2xl font-black text-slate-500">{{ number_format($summary['cancelled'], 0, ',', '.') }}</div>
            <div class="text-xs text-slate-400 mt-0.5">Aborted sessions</div>
        </div>
    </div>

    {{-- Filters & Search --}}
    <section class="rounded-2xl bg-white p-4 shadow-sm border border-line mb-4">
        <form method="GET" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 items-end">
            <input type="hidden" name="tab" value="list">
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Warehouse / Location</label>
                <select name="location_id" class="h-10 w-full rounded-xl border border-slate-300 px-3 text-sm">
                    <option value="">All Warehouses</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(request('location_id') == $loc->id)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Status</label>
                <select name="status" class="h-10 w-full rounded-xl border border-slate-300 px-3 text-sm">
                    <option value="">All Statuses</option>
                    <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">From Date</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="h-10 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">To Date</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="h-10 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-bold text-white hover:bg-slate-800">
                    <i data-lucide="filter" class="h-4 w-4"></i>Filter
                </button>
                @if (request()->hasAny(['location_id', 'status', 'date_from', 'date_to', 'q']))
                    <a href="{{ route('stock-opnames.index', ['tab' => 'list']) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-300 px-3 text-sm font-bold text-slate-600 hover:bg-canvas">
                        <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                    </a>
                @endif
            </div>
        </form>
    </section>

    {{-- Historical Opname Table --}}
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm border border-line">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-line text-xs uppercase text-slate-500 font-bold">
                    <tr>
                        <th class="px-4 py-3.5">Opname No.</th>
                        <th class="px-4 py-3.5">Date</th>
                        <th class="px-4 py-3.5">Warehouse</th>
                        <th class="px-4 py-3.5 text-right">System Qty</th>
                        <th class="px-4 py-3.5 text-right">Real Qty</th>
                        <th class="px-4 py-3.5 text-right">Variance</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                        <th class="px-4 py-3.5">Auditor</th>
                        <th class="px-4 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($opnames as $opn)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-4 py-3.5">
                                <a href="{{ route('stock-opnames.show', $opn) }}" class="font-bold text-brand hover:underline font-mono">
                                    {{ $opn->opname_number }}
                                </a>
                                @if ($opn->notes)
                                    <div class="text-xs text-slate-400 truncate max-w-xs">{{ $opn->notes }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-slate-600 font-medium">
                                {{ $opn->opname_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3.5 font-semibold text-slate-700">
                                {{ $opn->location->name ?? '-' }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-medium text-slate-600">
                                {{ number_format($opn->total_system_qty, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-bold text-slate-900">
                                {{ number_format($opn->total_physical_qty, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-extrabold">
                                @if ($opn->total_variance == 0)
                                    <span class="text-emerald-600">0</span>
                                @elseif ($opn->total_variance > 0)
                                    <span class="text-blue-600">+{{ number_format($opn->total_variance, 0, ',', '.') }}</span>
                                @else
                                    <span class="text-rose-600">{{ number_format($opn->total_variance, 0, ',', '.') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                @if ($opn->status === 'completed')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 border border-emerald-200">
                                        <i data-lucide="check" class="h-3 w-3"></i>Completed
                                    </span>
                                @elseif ($opn->status === 'draft')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 border border-amber-200 animate-pulse">
                                        Draft
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500 border border-slate-200">
                                        Cancelled
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-500">
                                <div>{{ $opn->createdBy->name ?? '-' }}</div>
                                @if ($opn->completed_at)
                                    <div class="text-[11px] text-slate-400">{{ $opn->completed_at->format('d/m/Y H:i') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('stock-opnames.show', $opn) }}" class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50" title="View Details">
                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>Details
                                    </a>
                                    <a href="{{ route('stock-opnames.print-pdf', $opn) }}" target="_blank" class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 text-xs font-bold text-slate-700 hover:text-brand hover:bg-slate-50" title="Print BASO PDF">
                                        <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                                    </a>
                                    <a href="{{ route('stock-opnames.export', $opn) }}" class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 text-xs font-bold text-slate-700 hover:text-emerald-600 hover:bg-slate-50" title="Export Excel Report">
                                        <i data-lucide="download" class="h-3.5 w-3.5"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-8 text-center text-slate-400">
                                <i data-lucide="clipboard-x" class="mx-auto mb-2 h-10 w-10 text-slate-300"></i>
                                <div class="font-bold text-slate-600">No Stock Opname audit sessions recorded yet.</div>
                                <div class="text-xs">Click "Back to Audit Workspace" above to conduct a physical stock count.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($opnames->hasPages())
            <div class="p-4 border-t border-line">
                {{ $opnames->links() }}
            </div>
        @endif
    </section>
@endif

@push('scripts')
<script>
    function calcRowVariance(inputEl, rowIdx) {
        const sys = parseInt(inputEl.dataset.sys, 10) || 0;
        const real = parseInt(inputEl.value, 10) || 0;
        const variance = real - sys;

        const varCell = document.getElementById(`var-cell-${rowIdx}`);
        const statusCell = document.getElementById(`status-cell-${rowIdx}`);

        if (varCell) {
            if (variance === 0) {
                varCell.innerHTML = '<span class="text-emerald-600">0</span>';
            } else if (variance > 0) {
                varCell.innerHTML = `<span class="text-blue-600">+${variance}</span>`;
            } else {
                varCell.innerHTML = `<span class="text-rose-600">${variance}</span>`;
            }
        }

        if (statusCell) {
            if (variance === 0) {
                statusCell.innerHTML = '<span class="inline-flex rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-xs font-bold text-emerald-700">Match</span>';
            } else if (variance > 0) {
                statusCell.innerHTML = '<span class="inline-flex rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-xs font-bold text-blue-700">+Surplus</span>';
            } else {
                statusCell.innerHTML = '<span class="inline-flex rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-xs font-bold text-rose-700">-Deficit</span>';
            }
        }

        recalculateTotals();
    }

    function recalculateTotals() {
        const rows = document.querySelectorAll('.row-item');
        let matched = 0;
        let discrepancy = 0;

        rows.forEach(r => {
            const realInput = r.querySelector('.real-input');
            if (realInput) {
                const sys = parseInt(realInput.dataset.sys, 10) || 0;
                const real = parseInt(realInput.value, 10) || 0;
                if (sys === real) {
                    matched++;
                } else {
                    discrepancy++;
                }
            }
        });

        const statMatched = document.getElementById('stat-matched');
        const statDiscrepancy = document.getElementById('stat-discrepancy');
        if (statMatched) statMatched.textContent = matched;
        if (statDiscrepancy) statDiscrepancy.textContent = discrepancy;
    }

    function validateOpname() {
        recalculateTotals();
        const statMatched = document.getElementById('stat-matched')?.textContent || '0';
        const statDiscrepancy = document.getElementById('stat-discrepancy')?.textContent || '0';
        const total = document.getElementById('stat-total')?.textContent || '0';

        if (parseInt(statDiscrepancy, 10) === 0) {
            alert(`Validation Successful!\nAll ${total} products match 100% with physical inventory.`);
        } else {
            alert(`Validation Completed.\nTotal Products: ${total}\nMatched: ${statMatched}\nDiscrepancies: ${statDiscrepancy} product(s) have variances between system and real stock.\n\nYou can click 'Save & Post Opname' to reconcile these records into the system.`);
        }
    }
</script>
@endpush
@endsection
