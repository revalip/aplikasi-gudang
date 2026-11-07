@extends('layouts.app')
@section('title', 'Sales Orders')

@section('content')
<x-page-header title="Sales Orders" icon="file-text">
    <x-slot:subtitle><span>Total <b>{{ $orders->total() }} SO(s)</b></span></x-slot:subtitle>
    <x-slot:actions>
        @can('customers.view')
            <a href="{{ route('customers.index') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas">
                <i data-lucide="contact" class="h-4 w-4"></i>Manage Customers
            </a>
        @endcan
        <a href="{{ route('sales-orders.create') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="plus" class="h-4 w-4"></i>Create SO
        </a>
    </x-slot:actions>
</x-page-header>

@if (session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
@endif

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-line px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-1 text-sm">
            <a href="{{ route('sales-orders.index') }}" class="rounded-lg px-3 py-1.5 font-semibold {{ !request('status') ? 'bg-brand-tint text-brand' : 'text-slate-600 hover:bg-canvas' }}">All</a>
            @foreach ($statuses as $s)
                <a href="{{ route('sales-orders.index', ['status' => $s]) }}" class="rounded-lg px-3 py-1.5 font-semibold {{ request('status') === $s ? 'bg-brand-tint text-brand' : 'text-slate-600 hover:bg-canvas' }}">{{ config("sales_options.status_labels.$s") }}</a>
            @endforeach
        </div>
        <form method="GET">
            @if (request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search SO Number / customer..." class="h-10 w-full rounded-xl border border-slate-300 px-3 text-sm lg:w-64">
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr>
                    <th class="px-4 py-2.5">SO Number</th><th class="px-3 py-2.5">Customer</th><th class="px-3 py-2.5">Date</th>
                    <th class="px-3 py-2.5">Items</th><th class="px-3 py-2.5 text-right">Total</th><th class="px-3 py-2.5">Status</th><th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $so)
                    <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-mono font-bold">{{ $so->so_number }}</td>
                        <td class="px-3 py-3">{{ $so->customer->name ?? '-' }}</td>
                        <td class="px-3 py-3 text-muted">{{ $so->order_date->format('d/m/Y') }}</td>
                        <td class="px-3 py-3">{{ $so->items->count() }} line(s)</td>
                        <td class="px-3 py-3 text-right font-semibold">Rp {{ number_format($so->total, 0, ',', '.') }}</td>
                        <td class="px-3 py-3"><x-so-status-badge :status="$so->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('sales-orders.show', $so) }}" class="rounded-lg p-1.5 text-muted hover:bg-brand-tint hover:text-brand" title="View Details"><i data-lucide="eye" class="h-4 w-4"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-muted">No Sales Orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($orders->hasPages())
        <div class="border-t border-line px-4 py-3">{{ $orders->links() }}</div>
    @endif
</section>
@endsection
