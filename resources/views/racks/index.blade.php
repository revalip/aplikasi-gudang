@extends('layouts.app')
@section('title', 'Warehouses & Racks')

@section('content')
<x-page-header title="Warehouses & Racks" icon="warehouse">
    <x-slot:subtitle>
        <span class="text-muted">Manage warehouse locations, storage racks, and shelf compartments. Hierarchy: <b>Warehouse Location &rarr; Rack &rarr; Shelf/Bin</b></span>
    </x-slot:subtitle>
</x-page-header>

<div class="grid gap-6 lg:grid-cols-[1fr_1.4fr] lg:items-start">
    <div class="space-y-6">
        @can('racks.create')
        {{-- Add Warehouse Location Card --}}
        <section class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="map-pin" class="h-5 w-5 text-brand"></i>
                <h2 class="font-extrabold text-base text-slate-800">Add Warehouse Location</h2>
            </div>
            <p class="text-xs text-muted leading-relaxed">
                Add a new warehouse building, floor, or zone (e.g. <i>Lantai 4</i>, <i>Gudang Distribusi B</i>, <i>Transit Zone</i>).
            </p>
            <form method="POST" action="{{ route('locations.store') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="loc_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Warehouse Name <span class="text-accent">*</span></label>
                    <input id="loc_name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Lantai 4" required
                           class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-brand focus:ring-1 focus:ring-brand">
                </div>
                <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-xs font-bold text-white hover:bg-brand-dark transition-colors">
                    <i data-lucide="plus" class="h-4 w-4"></i>Add Warehouse Location
                </button>
            </form>
        </section>

        {{-- Add Rack Card --}}
        <section class="rounded-2xl bg-white p-5 shadow-sm border border-slate-100">
            <div class="flex items-center gap-2 mb-1">
                <i data-lucide="server" class="h-5 w-5 text-brand"></i>
                <h2 class="font-extrabold text-base text-slate-800">Add New Rack</h2>
            </div>
            <p class="text-xs text-muted leading-relaxed">
                Assign a storage rack or shelf under an existing warehouse location.
            </p>
            <form method="POST" action="{{ route('racks.store') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="location_id" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Warehouse Location <span class="text-accent">*</span></label>
                    <select id="location_id" name="location_id" required class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-brand">
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}" @selected(old('location_id') == $loc->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rack_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Rack Name / Code <span class="text-accent">*</span></label>
                    <input id="rack_name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. A-01, B2, RAK-CORE" required
                           class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-brand focus:ring-1 focus:ring-brand">
                </div>
                <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-800 px-4 text-xs font-bold text-white hover:bg-slate-900 transition-colors">
                    <i data-lucide="plus" class="h-4 w-4"></i>Add Rack
                </button>
            </form>
        </section>
        @endcan
    </div>

    {{-- Warehouse & Rack Directory --}}
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-muted">Warehouse Directory</h3>
            <span class="text-xs font-bold text-slate-500">{{ $locations->count() }} Warehouses</span>
        </div>

        @forelse ($locations as $loc)
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm border border-slate-100">
                <div class="flex items-center justify-between border-b border-line bg-slate-50/75 px-5 py-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand/10 text-brand">
                            <i data-lucide="warehouse" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <div class="text-sm font-extrabold text-slate-800">{{ $loc->name }}</div>
                            <div class="text-[11px] text-muted font-medium">
                                {{ $loc->racks->count() }} racks &bull; {{ $loc->items_count }} physical units
                            </div>
                        </div>
                    </div>

                    @can('racks.delete')
                    @if ($loc->racks->count() === 0 && $loc->items_count === 0)
                        <form method="POST" action="{{ route('locations.destroy', $loc) }}"
                              onsubmit="return confirm({{ Js::from("Delete warehouse location '{$loc->name}'? This action cannot be undone.") }})">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1 text-xs font-bold text-accent hover:text-accent-dark bg-accent-tint/40 hover:bg-accent-tint px-2.5 py-1 rounded-lg transition-colors" title="Delete empty warehouse">
                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete Warehouse
                            </button>
                        </form>
                    @endif
                    @endcan
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse ($loc->racks as $rack)
                        <div class="flex items-center justify-between px-5 py-3 text-sm hover:bg-slate-50/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center h-6 w-6 rounded bg-slate-100 text-xs font-mono font-bold text-slate-600">
                                    #
                                </span>
                                <div>
                                    <span class="font-bold text-slate-800">{{ $rack->name }}</span>
                                    <span class="ml-2 text-xs font-medium text-muted">
                                        {{ $counts[$loc->id . '|' . $rack->name] ?? 0 }} units
                                    </span>
                                    @php $rackShelves = $shelves[$loc->id . '|' . $rack->name] ?? collect(); @endphp
                                    @if ($rackShelves->isNotEmpty())
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                                            <span class="text-muted font-medium">Shelves / Bins:</span>
                                            @foreach ($rackShelves as $sh)
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-700">
                                                    {{ $sh->shelf }} <span class="text-slate-400">({{ $sh->count }})</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @can('racks.delete')
                            <form method="POST" action="{{ route('racks.destroy', $rack) }}"
                                  onsubmit="return confirm({{ Js::from("Delete rack '{$rack->name}' from {$loc->name}?") }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg p-1.5 text-slate-400 hover:text-accent hover:bg-accent-tint transition-colors" aria-label="Delete rack {{ $rack->name }}" title="Delete Rack">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                </button>
                            </form>
                            @endcan
                        </div>
                    @empty
                        <div class="px-5 py-4 text-center text-xs text-muted">
                            <i data-lucide="inbox" class="h-5 w-5 mx-auto mb-1 text-slate-300"></i>
                            No racks in this warehouse yet.
                        </div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center text-muted shadow-sm">
                <i data-lucide="warehouse" class="h-8 w-8 mx-auto mb-2 text-slate-300"></i>
                <p class="font-semibold text-sm">No warehouse locations configured yet.</p>
                <p class="text-xs mt-1">Add your first warehouse location using the form on the left.</p>
            </div>
        @endforelse
    </section>
</div>
@endsection
