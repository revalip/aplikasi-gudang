@extends('layouts.app')
@section('title', 'DO Details ' . $do->do_number)

@section('content')
@php
    $total = $do->items->count();
    $scanned = $do->items->whereNotNull('scanned_at')->count();
    $allScanned = $total > 0 && $scanned === $total;
    $picking = $do->status === 'picking';
@endphp
<x-page-header :title="$do->do_number" icon="truck">
    <x-slot:subtitle>
        @if ($do->salesOrder)
            <span>SO:
                @can('sales')<a href="{{ route('sales-orders.show', $do->salesOrder) }}" class="font-bold text-brand hover:underline">{{ $do->salesOrder->so_number }}</a>
                @else <b>{{ $do->salesOrder->so_number }}</b>@endcan
            </span>
        @endif
        @if ($do->workOrder)
            <span>WO:
                @can('work-orders')<a href="{{ route('work-orders.show', $do->workOrder) }}" class="font-bold text-brand hover:underline">{{ $do->workOrder->wo_number }}</a>
                @else <b>{{ $do->workOrder->wo_number }}</b>@endcan
            </span>
        @endif
        <span>&middot; {{ $do->customerName() }}</span>
    </x-slot:subtitle>
    <x-slot:actions>
        <details class="relative">
            <summary class="inline-flex h-10 cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas [&::-webkit-details-marker]:hidden">
                <i data-lucide="printer" class="h-4 w-4"></i>Print
            </summary>
            <div class="absolute right-0 z-20 mt-1 w-40 overflow-hidden rounded-xl border border-line bg-white shadow-lg">
                <a href="{{ route('delivery-orders.print', $do) }}" class="block px-4 py-2 text-sm font-semibold hover:bg-canvas">Word (.docx)</a>
                <a href="{{ route('delivery-orders.print-pdf', $do) }}" target="_blank" class="block px-4 py-2 text-sm font-semibold hover:bg-canvas">PDF</a>
            </div>
        </details>
        <x-do-status-badge :status="$do->status" class="!px-3 !py-1.5 !text-sm" />
        @if ($picking)
            <form method="POST" action="{{ route('delivery-orders.cancel', $do) }}" onsubmit="return confirm('Cancel this DO? Locked units will be released.')">
                @csrf @method('PATCH')
                <button class="inline-flex h-10 items-center gap-2 rounded-xl border border-accent/30 bg-white px-4 text-sm font-bold text-accent-dark hover:bg-accent-tint">
                    <i data-lucide="x" class="h-4 w-4"></i>Cancel
                </button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

@if (session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-2.5 text-sm font-semibold text-accent-dark">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-2.5 text-sm font-semibold text-accent-dark">{{ $errors->first() }}</div>
@endif

@if ($picking)
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-extrabold">Scan Serial Number</h2>
            <span class="text-sm font-bold {{ $allScanned ? 'text-emerald-700' : 'text-muted' }}">{{ $scanned }} / {{ $total }} unit(s)</span>
        </div>
        <form method="POST" action="{{ route('delivery-orders.scan', $do) }}" class="flex flex-col gap-2 sm:flex-row">
            @csrf
            <input id="sn" name="serial_number" autofocus autocomplete="off" required placeholder="Scan / type SN of picked unit..."
                   class="h-11 flex-1 rounded-xl border border-slate-300 px-3 font-mono text-sm">
            <x-camera-scan-button target="#sn" mode="set" :submit="true" />
            <button class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand px-5 text-sm font-bold text-white hover:bg-brand-dark">
                <i data-lucide="scan-line" class="h-4 w-4"></i>Check
            </button>
        </form>
        <p class="mt-2 text-xs text-muted">Direct scanner to this field, Enter is automatic. Only units in the list below are accepted.</p>
    </section>
@endif

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr>
                    <th class="px-4 py-2.5">Part Number</th>
                    <th class="px-3 py-2.5">Serial Number</th>
                    <th class="px-3 py-2.5">Brand</th>
                    <th class="px-3 py-2.5">Unit</th>
                    <th class="px-3 py-2.5">Location / Rack</th>
                    <th class="px-4 py-2.5">Picking</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($do->items as $row)
                    <tr class="border-b border-slate-100 last:border-0 {{ $row->scanned_at ? 'bg-emerald-50/40' : '' }}">
                        <td class="px-4 py-3 font-mono text-xs font-bold text-brand">{{ $row->item->part_number ?: $row->item->item_name }}</td>
                        <td class="px-3 py-3 font-mono text-xs font-bold">{{ $row->item->serial_number ?: '-' }}</td>
                        <td class="px-3 py-3 text-muted">{{ $row->item->merek ?: '-' }}</td>
                        <td class="px-3 py-3 text-xs font-semibold text-slate-700">{{ $row->item->unit ?: 'pcs' }}</td>
                        <td class="px-3 py-3">{{ $row->item->location->name ?? '-' }}{{ $row->item->rack ? ' / ' . $row->item->rack : '' }}</td>
                        <td class="px-4 py-3">
                            @if ($row->scanned_at)
                                <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700"><i data-lucide="check-circle-2" class="h-4 w-4"></i>{{ $row->scanned_at->format('d/m H:i') }}</span>
                            @elseif ($picking && blank($row->item->serial_number))
                                <form method="POST" action="{{ route('delivery-orders.pick', [$do, $row]) }}">
                                    @csrf @method('PATCH')
                                    <button class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-bold hover:bg-canvas">Mark as picked (without SN)</button>
                                </form>
                            @else
                                <span class="text-xs text-muted">{{ $picking ? 'Not scanned yet' : '-' }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

@if ($picking)
    <form method="POST" action="{{ route('delivery-orders.ship', $do) }}" onsubmit="return confirm('Ship items? Unit stock will be reduced and status updated to Sold Out.')">
        @csrf @method('PATCH')
        <button {{ $allScanned ? '' : 'disabled' }} class="inline-flex h-11 items-center gap-2 rounded-xl bg-emerald-600 px-6 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40">
            <i data-lucide="send" class="h-4 w-4"></i>Ship Items
        </button>
        @unless ($allScanned)<span class="ml-2 text-xs text-muted">Active after all units are scanned.</span>@endunless
    </form>
@elseif ($do->status === 'delivered')
    <p class="text-sm text-muted">Delivered {{ $do->delivered_at->format('d/m/Y H:i') }} by {{ $do->deliveredBy->name ?? '-' }}.</p>
@endif

<a href="{{ route('delivery-orders.index') }}" class="text-sm font-semibold text-muted hover:underline">&larr; Back to DO list</a>
@endsection
