@extends('layouts.app')
@section('title', 'SO Details ' . $so->so_number)

@section('content')
@php
    $canApprove = (auth()->user()->can('sales.approve') && $so->created_by !== auth()->id()) || auth()->user()->isSuperadmin();
    $shortageTotal = $so->items->sum('shortage');
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
@endphp
<x-page-header :title="$so->so_number" icon="file-text">
    <x-slot:subtitle>
        <span>{{ $so->customer->name }}</span>
        <span>&middot; Date: {{ $so->order_date->format('d/m/Y') }}</span>
        @if ($so->payment_terms)<span>&middot; Terms: {{ $so->payment_terms }}</span>@endif
    </x-slot:subtitle>
    <x-slot:actions>
        <details class="relative">
            <summary class="inline-flex h-10 cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas [&::-webkit-details-marker]:hidden">
                <i data-lucide="printer" class="h-4 w-4"></i>Print
            </summary>
            <div class="absolute right-0 z-20 mt-1 w-40 overflow-hidden rounded-xl border border-line bg-white shadow-lg">
                <a href="{{ route('sales-orders.print', $so) }}" class="block px-4 py-2 text-sm font-semibold hover:bg-canvas">Word (.docx)</a>
                <a href="{{ route('sales-orders.print-pdf', $so) }}" target="_blank" class="block px-4 py-2 text-sm font-semibold hover:bg-canvas">PDF</a>
            </div>
        </details>
        <x-so-status-badge :status="$so->status" class="!px-3 !py-1.5 !text-sm" />
        @if ($so->status === 'draft')
            <form method="POST" action="{{ route('sales-orders.submit', $so) }}">
                @csrf @method('PATCH')
                <button class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
                    <i data-lucide="send" class="h-4 w-4"></i>Submit
                </button>
            </form>
        @endif
        @if (in_array($so->status, ['draft', 'submitted'], true))
            <form method="POST" action="{{ route('sales-orders.cancel', $so) }}" onsubmit="return confirm('Cancel this SO?')">
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

<div class="grid gap-4 lg:grid-cols-3">
    <section class="rounded-2xl bg-white p-5 text-sm shadow-sm lg:col-span-2">
        <h2 class="mb-3 font-extrabold">Delivery &amp; Shipping Info</h2>
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt class="text-xs font-bold text-muted">Delivery Address</dt><dd>{{ $so->delivery_address ?: ($so->customer->address ?: '-') }}</dd></div>
            <div><dt class="text-xs font-bold text-muted">Notes</dt><dd>{{ $so->notes ?: '-' }}</dd></div>
            <div><dt class="text-xs font-bold text-muted">Created by</dt><dd>{{ $so->createdBy->name ?? '-' }}</dd></div>
            <div><dt class="text-xs font-bold text-muted">Submitted</dt><dd>{{ $so->submitted_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
        </dl>
    </section>

    <section class="rounded-2xl bg-white p-5 text-sm shadow-sm">
        <h2 class="mb-3 font-extrabold">Approval</h2>
        @if ($so->approved_at)
            <p><b>{{ $so->status === 'approved' ? 'Approved' : 'Rejected' }}</b> by {{ $so->approvedBy->name ?? '-' }}</p>
            <p class="text-xs text-muted">{{ $so->approved_at->format('d/m/Y H:i') }}</p>
            @if ($so->approval_note)<p class="mt-2 rounded-lg bg-slate-50 p-2.5">{{ $so->approval_note }}</p>@endif
        @elseif ($so->status === 'submitted' && $canApprove)
            <form method="POST" class="space-y-2">
                @csrf @method('PATCH')
                <textarea name="note" rows="2" placeholder="Note (required when rejecting)" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
                <div class="flex gap-2">
                    <button formaction="{{ route('sales-orders.approve', $so) }}" class="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 text-sm font-bold text-white hover:bg-emerald-700">
                        <i data-lucide="check" class="h-4 w-4"></i>Approve
                    </button>
                    <button formaction="{{ route('sales-orders.reject', $so) }}" class="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-accent/30 bg-white px-3 text-sm font-bold text-accent-dark hover:bg-accent-tint">
                        <i data-lucide="x" class="h-4 w-4"></i>Reject
                    </button>
                </div>
            </form>
        @elseif ($so->status === 'submitted')
            <p class="text-muted">Pending approval from a user with Sales Order Approval permission.</p>
        @else
            <p class="text-muted">Not submitted yet.</p>
        @endif
    </section>
</div>

@if (in_array($so->status, ['approved', 'processing'], true) && $shortageTotal > 0)
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <b>Stock shortage of {{ $shortageTotal }} units.</b> Remaining units must be ordered via Purchase Order before shipment.
    </div>
@endif

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr>
                    <th class="px-4 py-2.5">Item</th><th class="px-3 py-2.5">Type</th><th class="px-3 py-2.5">Brand</th>
                    <th class="px-3 py-2.5 text-right">Qty</th><th class="px-3 py-2.5 text-right">Price</th><th class="px-3 py-2.5 text-right">Subtotal</th>
                    <th class="px-4 py-2.5">Stock Check</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($so->items as $line)
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="px-4 py-3 font-bold">{{ $line->item_name }}</td>
                        <td class="px-3 py-3"><span class="flex items-center gap-2"><x-type-icon :type="$line->type?->name" />{{ $line->type->name ?? '-' }}</span></td>
                        <td class="px-3 py-3 text-muted">{{ $line->merek ?: '-' }}</td>
                        <td class="px-3 py-3 text-right">{{ $line->qty }}
                            @if ($line->delivered_qty || $line->reserved_qty)
                                <div class="text-xs font-normal text-muted">delivered {{ $line->delivered_qty }} &middot; locked {{ $line->reserved_qty }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right">{{ $rp($line->unit_price) }}</td>
                        <td class="px-3 py-3 text-right font-semibold">{{ $rp($line->subtotal) }}</td>
                        <td class="px-4 py-3">
                            @if ($line->shortage === 0)
                                <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">Sufficient ({{ $line->available }} ready)</span>
                            @else
                                <span class="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-800">Shortage {{ $line->shortage }} ({{ $line->available }} ready)</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-line bg-slate-50">
                    <td colspan="5" class="px-4 py-3 text-right font-bold">Total</td>
                    <td class="px-3 py-3 text-right text-base font-extrabold">{{ $rp($so->total) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

@php
    $canCreatePr = in_array($so->status, ['approved', 'processing'], true) && $shortageTotal > 0
        && ! $so->purchaseRequests->contains(fn ($p) => $p->status === 'open'
            || ($p->status === 'ordered' && $p->purchaseOrders->contains(fn ($po) => in_array($po->status, ['draft', 'ordered', 'partial'], true))));
@endphp
@if ($so->purchaseRequests->isNotEmpty() || $canCreatePr)
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-extrabold">Purchase Request (Stock Shortage)</h2>
            @if ($canCreatePr)
                <form method="POST" action="{{ route('purchase-requests.store', $so) }}">
                    @csrf
                    <button class="inline-flex h-10 items-center gap-2 rounded-xl bg-amber-500 px-4 text-sm font-bold text-white hover:bg-amber-600">
                        <i data-lucide="clipboard-list" class="h-4 w-4"></i>Create Purchase Request
                    </button>
                </form>
            @endif
        </div>
        @forelse ($so->purchaseRequests as $p)
            <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm">
                <span class="font-mono font-bold">
                    @can('purchasing')<a href="{{ route('purchase-requests.show', $p) }}" class="text-brand hover:underline">{{ $p->pr_number }}</a>@else{{ $p->pr_number }}@endcan
                </span>
                <x-pr-status-badge :status="$p->status" />
            </div>
        @empty
            <p class="text-sm text-muted">No PR yet. Click the button above to request Purchasing to order missing items.</p>
        @endforelse
    </section>
@endif

@php
    $acceptedWo = $so->workOrders->firstWhere('status', 'accepted');
    $pendingWo = $so->workOrders->firstWhere('status', 'issued');
    $canCreateWo = in_array($so->status, ['approved', 'processing'], true) && ! $acceptedWo && ! $pendingWo;
@endphp
@if ($so->workOrders->isNotEmpty() || ($canCreateWo && auth()->user()->can('work-orders')))
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-extrabold">Work Order</h2>
            @can('work-orders')
                @if ($canCreateWo)
                    <a href="{{ route('work-orders.create') }}?so={{ $so->id }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
                        <i data-lucide="file-signature" class="h-4 w-4"></i>Create WO
                    </a>
                @endif
            @endcan
        </div>
        @forelse ($so->workOrders as $w)
            <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm">
                <span class="font-mono font-bold">
                    @can('work-orders')<a href="{{ route('work-orders.show', $w) }}" class="text-brand hover:underline">{{ $w->wo_number }}</a>@else{{ $w->wo_number }}@endcan
                </span>
                <span class="rounded-md px-2.5 py-0.5 text-xs font-bold {{ $w->status === 'accepted' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $w->status === 'accepted' ? 'Accepted' : 'Pending Acceptance' }}</span>
            </div>
        @empty
            <p class="text-sm text-muted">No WO yet. Create a WO first and wait for it to be accepted by Operations/Warehouse before processing delivery.</p>
        @endforelse
    </section>
@endif

@php
    $activePicking = $so->deliveryOrders->firstWhere('status', 'picking');
    $canCreateDo = in_array($so->status, ['approved', 'processing'], true) && ! $activePicking && $acceptedWo
        && $so->items->contains(fn ($l) => $l->remaining > 0);
@endphp
@if ($so->deliveryOrders->isNotEmpty() || (auth()->user()->can('delivery') && in_array($so->status, ['approved', 'processing'], true)))
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-extrabold">Delivery Order</h2>
            @can('delivery')
                @if ($canCreateDo)
                    <form method="POST" action="{{ route('delivery-orders.store', $so) }}">
                        @csrf
                        <button class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
                            <i data-lucide="truck" class="h-4 w-4"></i>Process Delivery
                        </button>
                    </form>
                @elseif (! $acceptedWo && ! $activePicking && $so->items->contains(fn ($l) => $l->remaining > 0))
                    <span class="text-xs font-semibold text-amber-700">Waiting for WO to be accepted first</span>
                @endif
            @endcan
        </div>
        @forelse ($so->deliveryOrders as $d)
            <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm">
                <span class="font-mono font-bold">
                    @can('delivery')<a href="{{ route('delivery-orders.show', $d) }}" class="text-brand hover:underline">{{ $d->do_number }}</a>@else{{ $d->do_number }}@endcan
                </span>
                <x-do-status-badge :status="$d->status" />
            </div>
        @empty
            <p class="text-sm text-muted">No DO yet. Click "Process Delivery" to lock units and begin picking.</p>
        @endforelse
    </section>
@endif

<a href="{{ route('sales-orders.index') }}" class="text-sm font-semibold text-muted hover:underline">&larr; Back to SO list</a>
@endsection
