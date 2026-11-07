@extends('layouts.app')
@section('title', 'Stock Movements')

@section('content')
<x-page-header title="Stock Movements" icon="arrow-left-right">
    <x-slot:subtitle>
        <span class="text-muted">Audit trail and ledger of physical inventory movements (Inbound, Outbound, Internal Transfers, and Opname Adjustments).</span>
    </x-slot:subtitle>
    <x-slot:actions>
        <a href="{{ route('serial-scans.index', array_merge($filters, ['export' => 1])) }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas">
            <i data-lucide="download" class="h-4 w-4"></i>Export Excel
        </a>
    </x-slot:actions>
</x-page-header>

{{-- Summary Cards --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-4">
    <div class="rounded-2xl bg-white p-4 shadow-sm border border-slate-100">
        <div class="text-xs font-bold text-muted uppercase">Total Movements</div>
        <div class="text-2xl font-extrabold text-slate-800 mt-1">{{ number_format($stats['total'], 0, ',', '.') }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm border border-emerald-100">
        <div class="text-xs font-bold text-emerald-700 uppercase">Stock In (IN)</div>
        <div class="text-2xl font-extrabold text-emerald-600 mt-1">{{ number_format($stats['in'], 0, ',', '.') }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm border border-rose-100">
        <div class="text-xs font-bold text-rose-700 uppercase">Stock Out (OUT)</div>
        <div class="text-2xl font-extrabold text-rose-600 mt-1">{{ number_format($stats['out'], 0, ',', '.') }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm border border-blue-100">
        <div class="text-xs font-bold text-blue-700 uppercase">Transfers &amp; Opname</div>
        <div class="text-2xl font-extrabold text-blue-600 mt-1">{{ number_format($stats['transfer'] + $stats['adjustment'], 0, ',', '.') }}</div>
    </div>
</div>

@if ($errors->any())
    <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm text-accent-dark mb-4">
        <div class="font-bold">Notice:</div>
        <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- Quick Scan / Input Form --}}
<section class="rounded-2xl bg-white p-5 shadow-sm mb-4">
    <h2 class="font-extrabold text-sm mb-3 flex items-center gap-2">
        <i data-lucide="scan-line" class="h-4 w-4 text-brand"></i> Quick Scan Stock Movement
    </h2>
    <form method="POST" action="{{ route('serial-scans.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
        @csrf
        <div>
            <label for="serial_number" class="text-xs font-bold text-slate-700">Serial Number / Barcode <span class="text-accent">*</span></label>
            <div class="mt-1 flex gap-2">
                <input id="serial_number" name="serial_number" value="{{ old('serial_number') }}" autofocus autocomplete="off" required placeholder="Scan / type SN..."
                       class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-mono focus:border-brand focus:ring-2 focus:ring-brand/20">
                <x-camera-scan-button target="#serial_number" mode="set" />
            </div>
        </div>

        <div>
            <label for="direction" class="text-xs font-bold text-slate-700">Movement Direction <span class="text-accent">*</span></label>
            <select id="direction" name="direction" required class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-sm">
                <option value="in" @selected(old('direction') === 'in')>IN - Stock In / Customer Return</option>
                <option value="out" @selected(old('direction') === 'out')>OUT - Stock Out / Delivery</option>
                <option value="transfer" @selected(old('direction') === 'transfer')>TRANSFER - Relocate Warehouse / Rack</option>
            </select>
        </div>

        <div>
            <label for="location_id" class="text-xs font-bold text-slate-700">Target Warehouse / Floor</label>
            <select id="location_id" name="location_id" class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-2.5 text-sm">
                <option value="">- Keep current location -</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}" @selected(old('location_id') == $loc->id)>{{ $loc->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="notes" class="text-xs font-bold text-slate-700">Reference No. / Notes</label>
            <div class="mt-1 flex gap-2">
                <input id="notes" name="notes" value="{{ old('notes') }}" placeholder="DO / PO / WO Ref..."
                       class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm">
                <button type="submit" class="inline-flex h-10 items-center gap-1.5 shrink-0 rounded-xl bg-brand px-4 text-xs font-bold text-white hover:bg-brand-dark">
                    <i data-lucide="check" class="h-4 w-4"></i> Record
                </button>
            </div>
        </div>
    </form>
</section>

{{-- Filters & Ledger Table --}}
<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="border-b border-line p-4">
        <form method="GET" action="{{ route('serial-scans.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr_1fr_auto] lg:items-end">
            <div>
                <label class="text-xs font-bold text-muted">Search SN / Part Number / Ref</label>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Type serial number or keyword..."
                       class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>

            <div>
                <label class="text-xs font-bold text-muted">Movement Type</label>
                <select name="direction" class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-2 text-sm">
                    <option value="">All Movement Types</option>
                    <option value="in" @selected(($filters['direction'] ?? '') === 'in')>IN (Inbound)</option>
                    <option value="out" @selected(($filters['direction'] ?? '') === 'out')>OUT (Outbound)</option>
                    <option value="transfer" @selected(($filters['direction'] ?? '') === 'transfer')>TRANSFER (Relocation)</option>
                    <option value="adjustment" @selected(($filters['direction'] ?? '') === 'adjustment')>ADJUSTMENT (Opname)</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-bold text-muted">Warehouse</label>
                <select name="location_id" class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-2 text-sm">
                    <option value="">All Warehouses</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(($filters['location_id'] ?? '') == $loc->id)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs font-bold text-muted">From Date</label>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                       class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-2.5 text-sm">
            </div>

            <div>
                <label class="text-xs font-bold text-muted">To Date</label>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                       class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-2.5 text-sm">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="h-10 rounded-xl bg-brand px-4 text-xs font-bold text-white hover:bg-brand-dark">Filter</button>
                @if (array_filter($filters))
                    <a href="{{ route('serial-scans.index') }}" class="inline-flex h-10 items-center rounded-xl px-3 text-xs font-semibold text-muted hover:bg-canvas">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr>
                    <th class="px-5 py-3">Timestamp</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Serial Number</th>
                    <th class="px-4 py-3">Part Number &amp; Item Name</th>
                    <th class="px-4 py-3">Warehouse Location</th>
                    <th class="px-4 py-3">Operator</th>
                    <th class="px-5 py-3">Reference / Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/50">
                        <td class="whitespace-nowrap px-5 py-3 text-muted text-xs">
                            {{ $log->scanned_at ? $log->scanned_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($log->direction === 'in')
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-extrabold text-emerald-700">IN (Inbound)</span>
                            @elseif ($log->direction === 'out')
                                <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-extrabold text-rose-700">OUT (Outbound)</span>
                            @elseif ($log->direction === 'transfer')
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-extrabold text-blue-700">TRANSFER</span>
                            @elseif ($log->direction === 'adjustment')
                                <span class="rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-extrabold text-purple-700">OPNAME</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-700">{{ strtoupper($log->direction) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono font-bold text-xs text-brand">{{ $log->serial_number }}</td>
                        <td class="px-4 py-3">
                            @if ($log->item)
                                <div class="font-mono text-xs font-bold text-slate-800">{{ $log->item->part_number ?: '-' }}</div>
                                <div class="text-xs text-muted">{{ $log->item->item_name }}</div>
                            @else
                                <span class="italic text-xs text-muted">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs font-semibold text-slate-700">{{ $log->location->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-xs text-muted">{{ $log->scannedBy->name ?? 'System' }}</td>
                        <td class="px-5 py-3 text-xs text-slate-600 max-w-xs truncate" title="{{ $log->notes }}">{{ $log->notes ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-muted">No stock movement records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($logs->hasPages())
        <div class="border-t border-line px-5 py-3">{{ $logs->links() }}</div>
    @endif
</section>
@endsection
