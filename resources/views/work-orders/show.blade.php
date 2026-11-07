@extends('layouts.app')
@section('title', 'WO Details ' . $wo->wo_number)

@section('content')
<x-page-header :title="$wo->wo_number" icon="file-signature">
    <x-slot:subtitle><span>{{ $wo->subject }}</span> @if ($wo->salesOrder)<span>&middot; SO: {{ $wo->salesOrder->so_number }}</span>@endif</x-slot:subtitle>
    <x-slot:actions>
        <span class="rounded-md px-2.5 py-1 text-xs font-bold {{ $wo->status === 'accepted' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $wo->status === 'accepted' ? 'Accepted' : 'Pending Acceptance' }}</span>
        @if ($wo->status !== 'accepted')
            <form method="POST" action="{{ route('work-orders.accept', $wo) }}">
                @csrf @method('PATCH')
                <button class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700">
                    <i data-lucide="check" class="h-4 w-4"></i>Accept WO
                </button>
            </form>
        @endif
        @if ($wo->status === 'accepted')
            @can('delivery')
            @if ($wo->items->sum(fn ($i) => $i->remaining) > 0 && ! $wo->deliveryOrders->contains('status', 'picking'))
                <form method="POST" action="{{ route('delivery-orders.store-from-wo', $wo) }}">
                    @csrf
                    <button class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
                        <i data-lucide="truck" class="h-4 w-4"></i>Create Delivery Order
                    </button>
                </form>
            @endif
            @endcan
        @endif
        <details class="relative">
            <summary class="inline-flex h-10 cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas [&::-webkit-details-marker]:hidden">
                <i data-lucide="printer" class="h-4 w-4"></i>Print
            </summary>
            <div class="absolute right-0 z-20 mt-1 w-40 overflow-hidden rounded-xl border border-line bg-white shadow-lg">
                <a href="{{ route('work-orders.print', $wo) }}" class="block px-4 py-2 text-sm font-semibold hover:bg-canvas">Word (.docx)</a>
                <a href="{{ route('work-orders.print-pdf', $wo) }}" target="_blank" class="block px-4 py-2 text-sm font-semibold hover:bg-canvas">PDF</a>
            </div>
        </details>
    </x-slot:actions>
</x-page-header>

@if (session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-2.5 text-sm font-semibold text-accent-dark">{{ session('error') }}</div>
@endif
@if ($wo->status === 'accepted')
    <p class="text-sm text-muted">Accepted by <b>{{ $wo->acceptedBy->name ?? '-' }}</b>, {{ $wo->accepted_at->format('d/m/Y H:i') }}.</p>
@endif

<div class="grid gap-4 lg:grid-cols-2">
    <section class="rounded-2xl bg-white p-5 text-sm shadow-sm">
        <h2 class="mb-3 font-extrabold">Customer Contact</h2>
        <dl class="space-y-1">
            <div><b>{{ $wo->cust_name }}</b> <span class="text-muted">{{ $wo->cust_position }}</span></div>
            <div class="text-muted">{{ $wo->cust_phone }} &middot; {{ $wo->cust_email }}</div>
            <div class="text-muted">{{ $wo->cust_address }}</div>
        </dl>
    </section>
    <section class="rounded-2xl bg-white p-5 text-sm shadow-sm">
        <h2 class="mb-3 font-extrabold">User Contact</h2>
        <dl class="space-y-1">
            <div><b>{{ $wo->user_name }}</b> <span class="text-muted">{{ $wo->user_position }}</span></div>
            <div class="text-muted">{{ $wo->user_phone }} &middot; {{ $wo->user_email }}</div>
            <div class="text-muted">{{ $wo->user_address }}</div>
        </dl>
    </section>
</div>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
            <tr><th class="px-4 py-2.5">No</th><th class="px-3 py-2.5">Part Number</th><th class="px-3 py-2.5">Item Name</th><th class="px-3 py-2.5">Type</th><th class="px-3 py-2.5">Brand</th><th class="px-3 py-2.5">Qty / Delivered</th><th class="px-3 py-2.5">Serial Numbers</th><th class="px-4 py-2.5">SOW Explanation</th></tr>
        </thead>
        <tbody>
            @foreach ($wo->items as $i => $line)
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-4 py-2.5">{{ $i + 1 }}</td>
                    <td class="px-3 py-2.5 font-mono font-bold text-brand">{{ $line->part_number }}</td>
                    <td class="px-3 py-2.5"><div class="font-bold">{{ $line->part_number ?: $line->item_name }}</div>@if ($line->description)<div class="text-xs text-muted">{{ $line->description }}</div>@endif</td>
                    <td class="px-3 py-2.5">{{ $line->type_name }}</td>
                    <td class="px-3 py-2.5">{{ $line->brand ?: '-' }}</td>
                    <td class="px-3 py-2.5 whitespace-nowrap">{{ $line->qty }} <span class="text-xs font-bold text-slate-700">{{ $line->unit ?: 'pcs' }}</span> <span class="text-xs text-muted">/ {{ $line->delivered_qty }} sent{{ $line->reserved_qty ? ', ' . $line->reserved_qty . ' picking' : '' }}</span></td>
                    <td class="px-3 py-2.5">
                        @forelse ($line->serialList() as $sn)
                            <div class="font-mono text-xs">{{ $sn }}@if (! $knownSn->has(mb_strtolower($sn))) <span class="ml-1 rounded bg-amber-100 px-1.5 text-[10px] font-bold text-amber-800" title="Not found in Warehouse">not in warehouse</span>@endif</div>
                        @empty
                            <span class="text-muted">-</span>
                        @endforelse
                    </td>
                    <td class="px-4 py-2.5">{{ $line->sow_explanation }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>

@if ($wo->deliveryOrders->isNotEmpty())
<section class="rounded-2xl bg-white p-5 shadow-sm">
    <h2 class="mb-3 font-extrabold">Delivery Orders</h2>
    <div class="flex flex-wrap gap-2 text-sm">
        @foreach ($wo->deliveryOrders as $d)
            @can('delivery')<a href="{{ route('delivery-orders.show', $d) }}" class="inline-flex items-center gap-2 rounded-lg border border-line px-3 py-1.5 font-mono font-bold text-brand hover:bg-canvas">{{ $d->do_number }} <x-do-status-badge :status="$d->status" /></a>
            @else <span class="font-mono font-bold">{{ $d->do_number }}</span>@endcan
        @endforeach
    </div>
</section>
@endif

<a href="{{ route('work-orders.index') }}" class="text-sm font-semibold text-muted hover:underline">&larr; Back to WO list</a>
@endsection
