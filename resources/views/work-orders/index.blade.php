@extends('layouts.app')
@section('title', 'Work Orders')

@section('content')
<x-page-header title="Work Orders" icon="file-signature">
    <x-slot:subtitle><span>Total <b>{{ $orders->total() }} WO(s)</b></span></x-slot:subtitle>
    <x-slot:actions>
        <a href="{{ route('work-orders.create') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="plus" class="h-4 w-4"></i>Create WO
        </a>
    </x-slot:actions>
</x-page-header>

@if (session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
@endif

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="flex flex-wrap gap-1 border-b border-line px-4 py-3 text-sm">
        <a href="{{ route('work-orders.index') }}" class="rounded-lg px-3 py-1.5 font-semibold {{ !request('status') ? 'bg-brand-tint text-brand' : 'text-slate-600 hover:bg-canvas' }}">All</a>
        <a href="{{ route('work-orders.index', ['status' => 'issued']) }}" class="rounded-lg px-3 py-1.5 font-semibold {{ request('status') === 'issued' ? 'bg-brand-tint text-brand' : 'text-slate-600 hover:bg-canvas' }}">Pending Acceptance</a>
        <a href="{{ route('work-orders.index', ['status' => 'accepted']) }}" class="rounded-lg px-3 py-1.5 font-semibold {{ request('status') === 'accepted' ? 'bg-brand-tint text-brand' : 'text-slate-600 hover:bg-canvas' }}">Accepted</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr><th class="px-4 py-2.5">WO Number</th><th class="px-3 py-2.5">Subject</th><th class="px-3 py-2.5">SO</th><th class="px-3 py-2.5">Date</th><th class="px-3 py-2.5">Items</th><th class="px-3 py-2.5">Status</th><th class="px-4 py-2.5"></th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $wo)
                    <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-mono font-bold">{{ $wo->wo_number }}</td>
                        <td class="px-3 py-3">{{ $wo->subject }}</td>
                        <td class="px-3 py-3 font-mono text-muted">{{ $wo->salesOrder->so_number ?? '-' }}</td>
                        <td class="px-3 py-3 text-muted">{{ $wo->wo_date->format('d/m/Y') }}</td>
                        <td class="px-3 py-3">{{ $wo->items_count }} item(s)</td>
                        <td class="px-3 py-3">
                            @if ($wo->status === 'accepted')
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">Accepted</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800">Pending Acceptance</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('work-orders.show', $wo) }}" class="rounded-lg p-1.5 text-muted hover:bg-brand-tint hover:text-brand" title="View Details"><i data-lucide="eye" class="h-4 w-4"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-muted">No Work Orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())<div class="border-t border-line px-4 py-3">{{ $orders->links() }}</div>@endif
</section>
@endsection
