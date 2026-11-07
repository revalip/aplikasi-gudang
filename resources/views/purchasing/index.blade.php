@extends('layouts.app')
@section('title', 'Purchase Orders')

@section('content')
<x-page-header title="Purchase Orders" icon="shopping-cart">
    <x-slot:subtitle><span>Total <b>{{ $orders->total() }} PO(s)</b></span></x-slot:subtitle>
    <x-slot:actions>
        @can('suppliers.view')
            <a href="{{ route('suppliers.index') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas">
                <i data-lucide="truck" class="h-4 w-4"></i>Manage Suppliers
            </a>
        @endcan
        <a href="{{ route('purchasing.create') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="plus" class="h-4 w-4"></i>Create PO
        </a>
    </x-slot:actions>
</x-page-header>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-line px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-1 text-sm">
            <a href="{{ route('purchasing.index') }}" class="rounded-lg px-3 py-1.5 font-semibold {{ !request('status') ? 'bg-brand-tint text-brand' : 'text-slate-600 hover:bg-canvas' }}">All</a>
            @foreach ($statuses as $s)
                <a href="{{ route('purchasing.index', ['status' => $s]) }}" class="rounded-lg px-3 py-1.5 font-semibold {{ request('status') === $s ? 'bg-brand-tint text-brand' : 'text-slate-600 hover:bg-canvas' }}">{{ config("purchasing_options.status_labels.$s") }}</a>
            @endforeach
        </div>
        <form method="GET">
            @if (request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search PO Number / supplier..."
                   class="h-10 w-full rounded-xl border border-slate-300 px-3 text-sm lg:w-64">
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr>
                    <th class="px-4 py-2.5">PO Number</th>
                    <th class="px-3 py-2.5">Supplier</th>
                    <th class="px-3 py-2.5">Order Date</th>
                    <th class="px-3 py-2.5">Expected Date</th>
                    <th class="px-3 py-2.5">Items</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $po)
                    <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-mono font-bold">{{ $po->po_number }}</td>
                        <td class="px-3 py-3">{{ $po->supplier->name ?? '-' }}</td>
                        <td class="px-3 py-3 text-muted">{{ $po->order_date->format('d/m/Y') }}</td>
                        <td class="px-3 py-3 text-muted">{{ $po->expected_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-3 py-3">{{ $po->items_count }} item(s)</td>
                        <td class="px-3 py-3"><x-po-status-badge :status="$po->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('purchasing.show', $po) }}" class="rounded-lg p-1.5 text-muted hover:bg-brand-tint hover:text-brand" title="View Details">
                                <i data-lucide="eye" class="h-4 w-4"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-muted">No Purchase Orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($orders->hasPages())
        <div class="border-t border-line px-4 py-3">{{ $orders->links() }}</div>
    @endif
</section>
@endsection
