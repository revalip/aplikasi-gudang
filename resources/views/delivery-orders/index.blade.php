@extends('layouts.app')
@section('title', 'Delivery Orders')

@section('content')
<x-page-header title="Delivery Orders" icon="truck">
    <x-slot:subtitle><span>Total <b>{{ $orders->total() }} DO(s)</b>. Delivery Orders are created from accepted Work Orders.</span></x-slot:subtitle>
    <x-slot:actions>
        @can('delivery')
            @if ($readyWorkOrders->isEmpty())
                <button type="button" disabled class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-slate-100 px-4 text-sm font-bold text-slate-400 cursor-not-allowed opacity-60" title="No accepted Work Orders ready for delivery. An accepted Work Order is required to generate a Delivery Order.">
                    <i data-lucide="plus" class="h-4 w-4"></i>Create DO
                </button>
            @elseif ($readyWorkOrders->count() === 1)
                <form method="POST" action="{{ route('delivery-orders.store-from-wo', $readyWorkOrders->first()) }}">
                    @csrf
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
                        <i data-lucide="plus" class="h-4 w-4"></i>Create DO
                    </button>
                </form>
            @else
                <details class="relative">
                    <summary class="inline-flex h-10 cursor-pointer list-none items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark [&::-webkit-details-marker]:hidden">
                        <i data-lucide="plus" class="h-4 w-4"></i>Create DO ({{ $readyWorkOrders->count() }} Available)
                        <i data-lucide="chevron-down" class="h-3.5 w-3.5"></i>
                    </summary>
                    <div class="absolute right-0 z-20 mt-1 w-72 overflow-hidden rounded-xl border border-line bg-white shadow-lg p-2 space-y-1">
                        <div class="px-2 py-1 text-xs font-bold text-muted uppercase">Select Work Order</div>
                        @foreach ($readyWorkOrders as $w)
                            <form method="POST" action="{{ route('delivery-orders.store-from-wo', $w) }}">
                                @csrf
                                <button type="submit" class="w-full text-left rounded-lg p-2 text-xs hover:bg-brand-tint hover:text-brand transition-colors">
                                    <div class="font-bold">{{ $w->wo_number }}</div>
                                    <div class="text-muted truncate">{{ $w->subject }} &middot; {{ $w->cust_name }}</div>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </details>
            @endif
        @endcan
    </x-slot:actions>
</x-page-header>

@if (session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-2.5 text-sm font-semibold text-accent-dark">{{ session('error') }}</div>
@endif

@if ($readyWorkOrders->isNotEmpty())
<section class="rounded-2xl bg-white p-5 shadow-sm">
    <h2 class="font-extrabold">Work Orders ready for delivery</h2>
    <p class="mb-3 text-sm text-muted">Accepted Work Orders with units still to ship. Create the Delivery Order directly, no Sales Order needed.</p>
    <div class="divide-y divide-slate-100">
        @foreach ($readyWorkOrders as $w)
            <div class="flex flex-wrap items-center gap-3 py-2.5 text-sm">
                <a href="{{ route('work-orders.show', $w) }}" class="font-mono font-bold text-brand hover:underline">{{ $w->wo_number }}</a>
                <span class="font-semibold">{{ $w->subject }}</span>
                <span class="text-muted">{{ $w->cust_name }}</span>
                <span class="rounded-full bg-brand-tint px-2.5 py-0.5 text-xs font-extrabold text-brand">{{ $w->items->sum(fn ($i) => $i->remaining) }} unit(s) to ship</span>
                <form method="POST" action="{{ route('delivery-orders.store-from-wo', $w) }}" class="ml-auto">
                    @csrf
                    <button class="inline-flex h-9 items-center gap-2 rounded-lg bg-brand px-3 text-xs font-bold text-white hover:bg-brand-dark"><i data-lucide="truck" class="h-4 w-4"></i>Create DO</button>
                </form>
            </div>
        @endforeach
    </div>
</section>
@endif

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="flex flex-wrap gap-1 border-b border-line px-4 py-3 text-sm">
        <a href="{{ route('delivery-orders.index') }}" class="rounded-lg px-3 py-1.5 font-semibold {{ !request('status') ? 'bg-brand-tint text-brand' : 'text-slate-600 hover:bg-canvas' }}">All</a>
        @foreach ($statuses as $s)
            <a href="{{ route('delivery-orders.index', ['status' => $s]) }}" class="rounded-lg px-3 py-1.5 font-semibold {{ request('status') === $s ? 'bg-brand-tint text-brand' : 'text-slate-600 hover:bg-canvas' }}">{{ config("sales_options.do_status_labels.$s") }}</a>
        @endforeach
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr>
                    <th class="px-4 py-2.5">DO Number</th><th class="px-3 py-2.5">SO / WO</th><th class="px-3 py-2.5">Customer</th>
                    <th class="px-3 py-2.5">Units</th><th class="px-3 py-2.5">Status</th><th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $do)
                    <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-mono font-bold">{{ $do->do_number }}</td>
                        <td class="px-3 py-3 font-mono">{{ $do->sourceLabel() }}</td>
                        <td class="px-3 py-3">{{ $do->customerName() }}</td>
                        <td class="px-3 py-3">{{ $do->items_count }} unit(s)</td>
                        <td class="px-3 py-3"><x-do-status-badge :status="$do->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('delivery-orders.show', $do) }}" class="rounded-lg p-1.5 text-muted hover:bg-brand-tint hover:text-brand" title="View Details"><i data-lucide="eye" class="h-4 w-4"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-muted">No Delivery Orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($orders->hasPages())
        <div class="border-t border-line px-4 py-3">{{ $orders->links() }}</div>
    @endif
</section>
@endsection
