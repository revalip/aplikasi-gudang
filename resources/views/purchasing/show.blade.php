@extends('layouts.app')
@section('title', 'PO Details ' . $po->po_number)

@section('content')
<x-page-header :title="$po->po_number" icon="shopping-cart">
    <x-slot:subtitle>
        <span>{{ $po->supplier->name }}</span>
        <span>&middot; Order: {{ $po->order_date->format('d/m/Y') }}</span>
        @if ($po->expected_date)<span>&middot; Expected: {{ $po->expected_date->format('d/m/Y') }}</span>@endif
    </x-slot:subtitle>
    <x-slot:actions>
        <details class="relative">
            <summary class="inline-flex h-10 cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas [&::-webkit-details-marker]:hidden">
                <i data-lucide="printer" class="h-4 w-4"></i>Print
            </summary>
            <div class="absolute right-0 z-20 mt-1 w-40 overflow-hidden rounded-xl border border-line bg-white shadow-lg">
                <a href="{{ route('purchasing.print', $po) }}" class="block px-4 py-2 text-sm font-semibold hover:bg-canvas">Word (.docx)</a>
                <a href="{{ route('purchasing.print-pdf', $po) }}" target="_blank" class="block px-4 py-2 text-sm font-semibold hover:bg-canvas">PDF</a>
            </div>
        </details>
        <x-po-status-badge :status="$po->status" class="!px-3 !py-1.5 !text-sm" />
        @if (in_array($po->status, ['draft', 'ordered', 'partial']))
            <form method="POST" action="{{ route('purchasing.cancel', $po) }}" onsubmit="return confirm('Are you sure you want to cancel this PO?')">
                @csrf @method('PATCH')
                <button class="inline-flex h-10 items-center gap-2 rounded-xl border border-accent/30 bg-white px-4 text-sm font-bold text-accent-dark hover:bg-accent-tint">
                    <i data-lucide="x" class="h-4 w-4"></i>Cancel PO
                </button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="grid gap-4 lg:grid-cols-3">
    <section class="rounded-2xl bg-white p-5 text-sm shadow-sm lg:col-span-2">
        <h2 class="mb-3 font-extrabold">PO Information</h2>
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt class="text-xs font-bold text-muted">Source PR</dt><dd>
                @if ($po->purchaseRequest)
                    <span class="font-mono font-bold">{{ $po->purchaseRequest->pr_number }}</span>
                @else - @endif
            </dd></div>
            <div><dt class="text-xs font-bold text-muted">Notes</dt><dd>{{ $po->notes ?: '-' }}</dd></div>
            <div><dt class="text-xs font-bold text-muted">Created By</dt><dd>{{ $po->createdBy->name ?? '-' }}</dd></div>
            <div><dt class="text-xs font-bold text-muted">Created At</dt><dd>{{ $po->created_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
        </dl>
    </section>
    <x-approval-panel :doc="$po" pending="draft" :approve-url="route('purchasing.approve', $po)" :reject-url="route('purchasing.reject', $po)" />
</div>

@php $receiving = $po->canReceive(); @endphp

@if ($errors->any())
    <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm text-accent-dark">
        <div class="font-bold">Please correct the following errors:</div>
        <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('purchasing.receive', $po) }}">
    @csrf @method('PATCH')
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full {{ $receiving ? 'min-w-[1080px]' : 'min-w-[820px]' }} text-sm">
                <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                    <tr>
                        <th class="px-4 py-2.5">Item Name</th>
                        <th class="px-3 py-2.5">Part Number</th>
                        <th class="px-3 py-2.5">Description</th>
                        <th class="px-3 py-2.5">Type</th>
                        <th class="px-3 py-2.5">Brand</th>
                        <th class="px-3 py-2.5">Warranty</th>
                        <th class="px-3 py-2.5 text-center">Qty Ordered</th>
                        <th class="px-3 py-2.5 text-center">Received</th>
                        <th class="px-3 py-2.5 text-center">Outstanding</th>
                        @if ($receiving)
                            <th class="px-3 py-2.5">Receive Now</th>
                            <th class="px-4 py-2.5">Serial Numbers <span class="font-normal">(optional, 1 line = 1 unit)</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($po->items as $line)
                        <tr class="border-b border-slate-100 align-top last:border-0">
                            <td class="px-4 py-3 font-bold">{{ $line->item_name }}</td>
                            <td class="px-3 py-3 font-mono text-xs uppercase font-bold text-slate-700">{{ $line->part_number ?: '-' }}</td>
                            <td class="px-3 py-3 text-xs text-slate-600 max-w-[200px] truncate" title="{{ $line->description }}">{{ $line->description ?: '-' }}</td>
                            <td class="px-3 py-3"><span class="flex items-center gap-2"><x-type-icon :type="$line->type?->name" />{{ $line->type->name ?? '-' }}</span></td>
                            <td class="px-3 py-3 text-muted">{{ $line->merek ?: '-' }}</td>
                            <td class="px-3 py-3 text-xs">{{ \App\Support\Warranty::label($line->warranty) }}</td>
                            <td class="px-3 py-3 text-center font-semibold">{{ $line->qty_ordered }} <span class="text-xs font-normal text-muted">{{ $line->unit ?: 'pcs' }}</span></td>
                            <td class="px-3 py-3 text-center text-emerald-700 font-semibold">{{ $line->qty_received }} <span class="text-xs font-normal text-muted">{{ $line->unit ?: 'pcs' }}</span></td>
                            <td class="px-3 py-3 text-center {{ $line->qty_outstanding > 0 ? 'font-bold text-accent-dark' : 'text-muted' }}">{{ $line->qty_outstanding }} <span class="text-xs font-normal text-muted">{{ $line->unit ?: 'pcs' }}</span></td>
                            @if ($receiving)
                                @if ($line->qty_outstanding > 0)
                                    <td class="px-3 py-3">
                                        <input type="number" name="receive[{{ $line->id }}]" value="{{ old("receive.{$line->id}") }}" min="0" max="{{ $line->qty_outstanding }}" placeholder="0"
                                               class="h-10 w-24 rounded-lg border px-2 text-sm @error("receive.{$line->id}") border-accent @else border-slate-300 @enderror">
                                    </td>
                                    <td class="px-4 py-3">
                                        <textarea name="serials[{{ $line->id }}]" rows="2" placeholder="Scan/enter SN (leave empty for non-SN)"
                                                  class="w-full min-w-[220px] rounded-lg border border-slate-300 px-2.5 py-2 font-mono text-xs">{{ old("serials.{$line->id}") }}</textarea>
                                    </td>
                                @else
                                    <td class="px-3 py-3" colspan="2"><span class="text-xs text-muted">completed</span></td>
                                @endif
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($receiving)
            <div class="flex flex-wrap items-end gap-3 border-t border-line p-4">
                <div>
                    <label for="location_id" class="text-xs font-bold text-muted">Store in Location <span class="text-accent">*</span></label>
                    <select id="location_id" name="location_id" required class="mt-1 block h-11 w-56 rounded-xl border border-slate-300 bg-white px-3 text-sm">
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}" @selected(old('location_id', $defaultLocation?->id) == $loc->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-bold text-white hover:bg-brand-dark">
                    <i data-lucide="package-check" class="h-4 w-4"></i>Record Inbound Receipt
                </button>
                <p class="w-full text-xs text-muted">Each received unit is automatically added to Warehouse Inventory (status <b>In Stock</b>, condition <b>Baru</b>, PO Number <b class="font-mono">{{ $po->po_number }}</b>) and available for Sales Orders. Missing serial numbers can be completed later via Item Edit.</p>
            </div>
        @elseif ($po->status === 'draft')
            <div class="flex items-center gap-2 border-t border-line p-4 text-sm text-muted">
                <i data-lucide="lock" class="h-4 w-4"></i>Item receiving will open after this PO is approved.
            </div>
        @endif
    </section>
</form>

@if ($po->units->isNotEmpty())
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <h2 class="font-extrabold">Units Added to Warehouse from this PO</h2>
            <span class="text-sm text-muted">{{ $po->units->count() }} unit(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                    <tr>
                        <th class="px-4 py-2.5">Item ID</th>
                        <th class="px-3 py-2.5">Part Number</th>
                        <th class="px-3 py-2.5">Item Name</th>
                        <th class="px-3 py-2.5">Serial Number</th>
                        <th class="px-3 py-2.5">Location</th>
                        <th class="px-3 py-2.5">Warranty</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">Received Date</th>
                        @can('warehouse.update')<th class="px-4 py-2.5"></th>@endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($po->units->sortBy('item_id') as $unit)
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="px-4 py-3 font-mono font-bold">{{ $unit->item_id }}</td>
                            <td class="px-3 py-3 font-mono text-xs uppercase font-bold text-slate-700">{{ $unit->part_number ?: '-' }}</td>
                            <td class="px-3 py-3">{{ $unit->item_name }}</td>
                            <td class="px-3 py-3 font-mono {{ $unit->serial_number ? '' : 'text-muted' }}">{{ $unit->serial_number ?: '-' }}</td>
                            <td class="px-3 py-3">{{ $unit->location->name ?? '-' }}</td>
                            <td class="px-3 py-3 text-xs">{{ \App\Support\Warranty::label($unit->warranty) }}</td>
                            <td class="px-3 py-3"><x-status-badge :status="$unit->status" /></td>
                            <td class="px-3 py-3 text-muted">{{ $unit->stuff_in ? \Illuminate\Support\Carbon::parse($unit->stuff_in)->format('d/m/Y') : '-' }}</td>
                            @can('warehouse.update')
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('items.edit', $unit) }}" class="rounded-lg p-1.5 text-muted hover:bg-brand-tint hover:text-brand" aria-label="Edit {{ $unit->item_id }}" title="Edit"><i data-lucide="pencil" class="inline h-4 w-4"></i></a>
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
