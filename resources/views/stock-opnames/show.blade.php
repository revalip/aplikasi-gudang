@extends('layouts.app')
@section('title', 'Stock Opname ' . $opname->opname_number)

@section('content')
<div class="space-y-4">
    {{-- Page Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('stock-opnames.index', ['tab' => 'list']) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-line bg-white text-slate-600 hover:bg-slate-50" title="Back to Opname List">
                <i data-lucide="arrow-left" class="h-5 w-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black text-ink font-mono">{{ $opname->opname_number }}</h1>
                    @if ($opname->status === 'completed')
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">
                            <i data-lucide="check" class="h-3 w-3"></i>Completed (Reconciled)
                        </span>
                    @elseif ($opname->status === 'draft')
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 border border-amber-200 animate-pulse">
                            Draft (Counting)
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-500 border border-slate-200">
                            Cancelled
                        </span>
                    @endif
                </div>
                <div class="text-xs text-slate-500">
                    Warehouse: <b class="text-slate-700">{{ $opname->location->name ?? '-' }}</b> &bull;
                    Date: {{ $opname->opname_date->format('d F Y') }} &bull;
                    Auditor: {{ $opname->createdBy->name ?? '-' }}
                </div>
            </div>
        </div>

        {{-- Top Actions --}}
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('stock-opnames.print-pdf', $opname) }}" target="_blank" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-bold text-slate-700 hover:bg-slate-50 shadow-sm" title="Print BASO / Opname PDF">
                <i data-lucide="printer" class="h-4 w-4 text-brand"></i>BASO PDF
            </a>

            <a href="{{ route('stock-opnames.export', $opname) }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-bold text-slate-700 hover:bg-slate-50 shadow-sm" title="Export Excel Summary">
                <i data-lucide="download" class="h-4 w-4 text-emerald-600"></i>Export Excel
            </a>
        </div>
    </div>

    {{-- Metric Summary Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-500">Total Products</span>
            <div class="mt-1 text-2xl font-black text-slate-800">{{ $items->total() }}</div>
            <div class="text-xs text-slate-400">Part numbers audited</div>
        </div>

        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <span class="text-xs font-bold uppercase text-slate-500">System Qty (Book)</span>
            <div class="mt-1 text-2xl font-black text-slate-800">{{ number_format($opname->total_system_qty, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-400">Units in DB snapshot</div>
        </div>

        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <span class="text-xs font-bold uppercase text-brand">Real Qty (Physical)</span>
            <div class="mt-1 text-2xl font-black text-brand">{{ number_format($opname->total_physical_qty, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-400">Units physically counted</div>
        </div>

        <div class="rounded-2xl border p-4 shadow-sm {{ $opname->total_variance != 0 ? 'bg-red-50/50 border-red-200' : 'bg-emerald-50/50 border-emerald-200' }}">
            <span class="text-xs font-bold uppercase {{ $opname->total_variance != 0 ? 'text-red-700' : 'text-emerald-700' }}">Total Variance</span>
            <div class="mt-1 text-2xl font-black {{ $opname->total_variance > 0 ? 'text-blue-700' : ($opname->total_variance < 0 ? 'text-red-700' : 'text-emerald-700') }}">
                {{ $opname->total_variance > 0 ? '+' : '' }}{{ number_format($opname->total_variance, 0, ',', '.') }}
            </div>
            <div class="text-xs {{ $opname->total_variance != 0 ? 'text-red-600' : 'text-emerald-600' }}">
                @if ($opname->total_variance == 0) 100% Stock Match @elseif ($opname->total_variance > 0) Physical Surplus @else Physical Deficit @endif
            </div>
        </div>
    </div>

    {{-- Filter Tabs & Search --}}
    <section class="rounded-2xl bg-white shadow-sm border border-line overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-line px-4 py-2 sm:flex-row sm:items-center sm:justify-between bg-slate-50/70">
            <div class="flex flex-wrap gap-1 text-xs font-bold">
                @php $curFilter = request('filter', 'all'); @endphp
                <a href="{{ route('stock-opnames.show', ['stockOpname' => $opname, 'q' => request('q')]) }}" class="rounded-lg px-3 py-1.5 transition-colors {{ $curFilter === 'all' ? 'bg-brand text-white' : 'text-slate-600 hover:bg-white' }}">
                    All Items
                </a>
                <a href="{{ route('stock-opnames.show', ['stockOpname' => $opname, 'filter' => 'variance', 'q' => request('q')]) }}" class="rounded-lg px-3 py-1.5 transition-colors {{ $curFilter === 'variance' ? 'bg-brand text-white' : 'text-slate-600 hover:bg-white' }}">
                    Variances Only
                </a>
                <a href="{{ route('stock-opnames.show', ['stockOpname' => $opname, 'filter' => 'surplus', 'q' => request('q')]) }}" class="rounded-lg px-3 py-1.5 transition-colors {{ $curFilter === 'surplus' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-white' }}">
                    Surplus (+)
                </a>
                <a href="{{ route('stock-opnames.show', ['stockOpname' => $opname, 'filter' => 'deficit', 'q' => request('q')]) }}" class="rounded-lg px-3 py-1.5 transition-colors {{ $curFilter === 'deficit' ? 'bg-red-600 text-white' : 'text-slate-600 hover:bg-white' }}">
                    Deficit (-)
                </a>
                <a href="{{ route('stock-opnames.show', ['stockOpname' => $opname, 'filter' => 'match', 'q' => request('q')]) }}" class="rounded-lg px-3 py-1.5 transition-colors {{ $curFilter === 'match' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-white' }}">
                    Matched (0)
                </a>
            </div>

            <form method="GET" class="flex items-center gap-2">
                @if (request('filter')) <input type="hidden" name="filter" value="{{ request('filter') }}"> @endif
                <div class="relative w-full sm:w-64">
                    <i data-lucide="search" class="absolute left-3 top-2.5 h-4 w-4 text-slate-400"></i>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search Part No / Name..." class="h-9 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-xs focus:border-brand focus:ring-1 focus:ring-brand">
                </div>
            </form>
        </div>

        {{-- Items Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" id="opname-items-table">
                <thead class="bg-slate-50 border-b border-line text-xs uppercase text-slate-500 font-bold">
                    <tr>
                        <th class="px-4 py-3">Part Number</th>
                        <th class="px-4 py-3">Product Name</th>
                        <th class="px-4 py-3 text-right">System Qty</th>
                        <th class="px-4 py-3 text-center">Real (Physical)</th>
                        <th class="px-4 py-3 text-right">Variance</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3">Notes &amp; Serials</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($items as $item)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-4 py-3 font-mono font-bold text-xs text-slate-800">
                                {{ $item->part_number ?: '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900">{{ $item->item_name }}</div>
                                @if ($item->inventory)
                                    <div class="text-xs text-slate-400">{{ $item->inventory->brand }} &bull; {{ $item->inventory->type->name ?? '-' }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-600">
                                {{ $item->system_qty }}
                            </td>
                            <td class="px-4 py-3 text-center font-extrabold text-slate-900">
                                {{ $item->physical_qty }}
                            </td>
                            <td class="px-4 py-3 text-right font-extrabold">
                                @if ($item->variance == 0)
                                    <span class="text-emerald-600">0</span>
                                @elseif ($item->variance > 0)
                                    <span class="text-blue-600">+{{ $item->variance }}</span>
                                @else
                                    <span class="text-red-600">{{ $item->variance }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($item->variance == 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200">Match</span>
                                @elseif ($item->variance > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-bold text-blue-700 border border-blue-200">+Surplus</span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-bold text-red-700 border border-red-200">-Deficit</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                @if ($item->scanned_serials)
                                    <details class="cursor-pointer group">
                                        <summary class="font-semibold text-brand hover:underline flex items-center gap-1">
                                            <span>{{ count($item->serialList()) }} Serials</span>
                                            <i data-lucide="chevron-down" class="h-3 w-3 group-open:rotate-180 transition-transform"></i>
                                        </summary>
                                        <div class="mt-1 rounded bg-slate-100 p-1.5 font-mono text-[11px] text-slate-700 max-h-24 overflow-y-auto">
                                            {{ implode(', ', $item->serialList()) }}
                                        </div>
                                    </details>
                                @endif
                                @if ($item->notes)
                                    <div class="text-slate-600 italic mt-0.5">{{ $item->notes }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                No items found matching the selected filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="p-4 border-t border-line">
                {{ $items->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
