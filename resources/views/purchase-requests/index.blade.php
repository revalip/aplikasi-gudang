@extends('layouts.app')
@section('title', 'Purchase Request')

@section('content')
<x-page-header title="Purchase Request" icon="clipboard-list">
    <x-slot:subtitle><span>Total <b>{{ $requests->total() }} PR(s)</b>. PR is created automatically from Sales Orders when stock is insufficient.</span></x-slot:subtitle>
</x-page-header>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="flex flex-wrap gap-1 border-b border-line px-4 py-3 text-sm">
        <a href="{{ route('purchase-requests.index') }}" class="rounded-lg px-3 py-1.5 font-semibold {{ !request('status') ? 'bg-brand-tint text-brand' : 'text-slate-600 hover:bg-canvas' }}">All</a>
        @foreach ($statuses as $s)
            <a href="{{ route('purchase-requests.index', ['status' => $s]) }}" class="rounded-lg px-3 py-1.5 font-semibold {{ request('status') === $s ? 'bg-brand-tint text-brand' : 'text-slate-600 hover:bg-canvas' }}">{{ config("purchasing_options.pr_status_labels.$s") }}</a>
        @endforeach
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr>
                    <th class="px-4 py-2.5">PR Number</th><th class="px-3 py-2.5">From SO</th><th class="px-3 py-2.5">Items</th>
                    <th class="px-3 py-2.5">Total Qty</th><th class="px-3 py-2.5">Status</th><th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $pr)
                    <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-mono font-bold">{{ $pr->pr_number }}</td>
                        <td class="px-3 py-3 font-mono">{{ $pr->salesOrder->so_number ?? '-' }}</td>
                        <td class="px-3 py-3">{{ $pr->items_count }} item(s)</td>
                        <td class="px-3 py-3">{{ $pr->items_sum_qty ?? 0 }} units</td>
                        <td class="px-3 py-3"><x-pr-status-badge :status="$pr->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('purchase-requests.show', $pr) }}" class="rounded-lg p-1.5 text-muted hover:bg-brand-tint hover:text-brand" title="View Details"><i data-lucide="eye" class="h-4 w-4"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-muted">No Purchase Requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($requests->hasPages())
        <div class="border-t border-line px-4 py-3">{{ $requests->links() }}</div>
    @endif
</section>
@endsection
