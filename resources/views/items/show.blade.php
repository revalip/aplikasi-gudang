@extends('layouts.app')
@section('title', 'Item History')

@section('content')
@php
    $partUrl = route('items.part', ['pn' => (string) $item->part_number, 'name' => $item->item_name, 'location' => $item->location_id]);
    $date = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : null;

    $months = \App\Support\Warranty::normalize($item->warranty === null ? null : (string) $item->warranty);
    $warrantyBase = $item->stuff_in ?: $item->created_at;
    $warrantyText = $months === null ? null : \App\Support\Warranty::label($months);
    if ($months !== null && (int) $months > 0 && $warrantyBase) {
        $until = \Illuminate\Support\Carbon::parse($warrantyBase)->addMonths((int) $months);
        $warrantyText .= ' · until ' . $until->format('d/m/Y') . ($until->isPast() ? ' (expired)' : '');
    }

    $details = [
        'Part Number' => $item->part_number ?: $item->item_name,
        'Type' => $item->type?->name,
        'Brand' => $item->merek,
        'Serial Number' => $item->serial_number ?: '-',
        'Status' => $item->status,
        'Condition' => $item->condition,
        'Unit' => $item->unit ?: 'pcs',
        'Warehouse' => $item->location?->name,
        'Rack' => $item->rack,
        'Shelf / Bin' => $item->shelf,
        'Location Format' => $item->formatted_location,
        'Stuff Out' => $date($item->stuff_out),
    ];
    $source = [
        'PO Number' => $item->no_po_spr,
        'Supplier' => $po?->supplier?->name,
        'Purchased (PO date)' => $date($po?->order_date),
        'Entered by' => $createdLog?->user_name,
        'Entered on' => $item->created_at?->format('d/m/Y H:i'),
        'Received (Stuff In)' => $date($item->stuff_in),
        'Warranty' => $warrantyText,
    ];
@endphp
<x-page-header title="Item History" icon="history">
    <x-slot:subtitle>
        <span class="font-mono font-bold">{{ $item->serial_number ?: '-' }}</span>
        <span class="text-muted">{{ $item->part_number ?: $item->item_name }}</span>
    </x-slot:subtitle>
    <x-slot:actions>
        <a href="{{ $partUrl }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>Back
        </a>
        @can('warehouse.update')
        <a href="{{ route('items.edit', $item) }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="pencil" class="h-4 w-4"></i>Edit
        </a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="grid gap-4 lg:grid-cols-2 lg:items-start">
    <div class="space-y-4">
        <section class="rounded-2xl bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-extrabold">Unit Details</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                @foreach ($details as $label => $value)
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="text-right font-semibold">{{ filled($value) ? $value : '-' }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($item->notes)
                <p class="mt-3 rounded-xl bg-canvas p-3 text-sm text-slate-700">{{ $item->notes }}</p>
            @endif
        </section>

        <section class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center gap-2">
                <h2 class="font-extrabold">Purchase &amp; Entry</h2>
                @if ($isNewStock)
                    <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-extrabold text-emerald-800">New Stock (latest batch)</span>
                @else
                    <span class="rounded-md bg-slate-200 px-2 py-0.5 text-xs font-extrabold text-slate-700">Old Stock (earlier batch)</span>
                @endif
            </div>
            <dl class="divide-y divide-slate-100 text-sm">
                @foreach ($source as $label => $value)
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="text-right font-semibold">{{ filled($value) ? $value : '-' }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($po)
                @can('purchasing')
                <a href="{{ route('purchasing.show', $po) }}" class="mt-3 inline-flex items-center gap-1 text-sm font-bold text-brand hover:underline">
                    Open {{ $po->po_number }} <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
                @endcan
            @endif
        </section>
    </div>

    <div class="space-y-4">
        <section class="rounded-2xl bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-extrabold">History</h2>
            @php
                $timeline = collect();
                foreach ($logs as $log) {
                    $desc = $log->description;
                    if ($item->item_id) {
                        $desc = str_replace($item->item_id, $item->part_number ?: $item->item_name, $desc);
                    }
                    $timeline->push(['at' => $log->created_at, 'who' => $log->user_name, 'text' => $desc, 'props' => $log->properties]);
                }
                foreach ($scans as $scan) {
                    $timeline->push(['at' => $scan->scanned_at, 'who' => $scan->scannedBy?->name, 'text' => 'Serial scan ' . strtoupper((string) $scan->direction) . ($scan->location ? ' · ' . $scan->location->name : '') . ($scan->notes ? ' · ' . $scan->notes : ''), 'props' => null]);
                }
                $timeline = $timeline->sortByDesc('at')->values();
            @endphp
            @forelse ($timeline as $row)
                <div class="relative border-l-2 border-line pb-4 pl-4 last:pb-0">
                    <span class="absolute -left-[5px] top-1.5 h-2 w-2 rounded-full bg-brand"></span>
                    <div class="text-xs text-muted">{{ $row['at']?->format('d/m/Y H:i') }}{{ $row['who'] ? ' · ' . $row['who'] : '' }}</div>
                    <div class="text-sm font-semibold">{{ $row['text'] }}</div>
                    @if (! empty($row['props']['old']) && ! empty($row['props']['new']))
                        <ul class="mt-1 space-y-0.5 text-xs text-slate-600">
                            @foreach ($row['props']['new'] as $field => $new)
                                <li><span class="font-bold">{{ $field }}</span>: {{ $row['props']['old'][$field] ?? '-' }} → {{ $new ?? '-' }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <p class="text-sm text-muted">No history recorded for this unit yet.</p>
            @endforelse
        </section>
    </div>
</div>
@endsection
