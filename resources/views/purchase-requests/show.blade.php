@extends('layouts.app')
@section('title', 'PR Details ' . $pr->pr_number)

@section('content')
<x-page-header :title="$pr->pr_number" icon="clipboard-list">
    <x-slot:subtitle>
        @if ($pr->salesOrder)
            <span>From SO:
                @can('sales')<a href="{{ route('sales-orders.show', $pr->salesOrder) }}" class="font-bold text-brand hover:underline">{{ $pr->salesOrder->so_number }}</a>
                @else <b>{{ $pr->salesOrder->so_number }}</b>@endcan
            </span>
            <span>&middot; {{ $pr->salesOrder->customer->name ?? '-' }}</span>
        @endif
        <span>&middot; Created by {{ $pr->createdBy->name ?? '-' }}, {{ $pr->created_at->format('d/m/Y H:i') }}</span>
    </x-slot:subtitle>
    <x-slot:actions>
        <x-pr-status-badge :status="$pr->status" class="!px-3 !py-1.5 !text-sm" />
        @if ($pr->status === 'open')
            <a href="{{ route('purchasing.create', ['pr' => $pr->id]) }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
                <i data-lucide="shopping-cart" class="h-4 w-4"></i>Create PO from this PR
            </a>
        @endif
        @if (in_array($pr->status, ['submitted', 'open']))
            <form method="POST" action="{{ route('purchase-requests.cancel', $pr) }}" onsubmit="return confirm('Cancel this PR?')">
                @csrf @method('PATCH')
                <button class="inline-flex h-10 items-center gap-2 rounded-xl border border-accent/30 bg-white px-4 text-sm font-bold text-accent-dark hover:bg-accent-tint">
                    <i data-lucide="x" class="h-4 w-4"></i>Cancel
                </button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="grid gap-4 lg:grid-cols-3">
    <section class="rounded-2xl bg-white p-5 text-sm shadow-sm lg:col-span-2">
        <h2 class="mb-3 font-extrabold">PR Information</h2>
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt class="text-xs font-bold text-muted">Notes</dt><dd>{{ $pr->notes ?: '-' }}</dd></div>
            <div><dt class="text-xs font-bold text-muted">Created by</dt><dd>{{ $pr->createdBy->name ?? '-' }}</dd></div>
        </dl>
        @if ($pr->status === 'submitted')
            <p class="mt-3 text-xs text-muted">A PO can only be created after this PR is approved.</p>
        @endif
    </section>
    <x-approval-panel :doc="$pr" pending="submitted" :approve-url="route('purchase-requests.approve', $pr)" :reject-url="route('purchase-requests.reject', $pr)" />
</div>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
            <tr><th class="px-4 py-2.5">Item Name</th><th class="px-3 py-2.5">Type</th><th class="px-3 py-2.5">Brand</th><th class="px-4 py-2.5 text-right">Required Qty</th></tr>
        </thead>
        <tbody>
            @foreach ($pr->items as $i)
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="px-4 py-3 font-bold">{{ $i->item_name }}</td>
                    <td class="px-3 py-3"><span class="flex items-center gap-2"><x-type-icon :type="$i->type?->name" />{{ $i->type->name ?? '-' }}</span></td>
                    <td class="px-3 py-3 text-muted">{{ $i->merek ?: '-' }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ $i->qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>

@if ($pr->purchaseOrders->isNotEmpty())
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-extrabold">Purchase Orders from this PR</h2>
        @foreach ($pr->purchaseOrders as $po)
            <div class="flex items-center justify-between border-t border-slate-100 py-2 text-sm">
                <a href="{{ route('purchasing.show', $po) }}" class="font-mono font-bold text-brand hover:underline">{{ $po->po_number }}</a>
                <x-po-status-badge :status="$po->status" />
            </div>
        @endforeach
    </section>
@endif

<a href="{{ route('purchase-requests.index') }}" class="text-sm font-semibold text-muted hover:underline">&larr; Back to PR list</a>
@endsection
