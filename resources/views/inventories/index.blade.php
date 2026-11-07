@extends('layouts.app')
@section('title', 'Part Number')

@section('content')
<x-page-header title="Part Number" icon="barcode">
    <x-slot:subtitle>
        <span class="text-muted">Master specifications catalog by Part Number, brand, item type, and warranty.</span>
    </x-slot:subtitle>
    <x-slot:actions>
        @can('inventory.create')
        <button type="button" onclick="document.getElementById('import-modal').classList.remove('hidden')" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas">
            <i data-lucide="upload" class="h-4 w-4"></i>Import Excel
        </button>
        <a href="{{ route('inventories.create') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="plus" class="h-4 w-4"></i>Add Part Number
        </a>
        @endcan
    </x-slot:actions>
</x-page-header>

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="border-b border-line p-4">
        <form method="GET" action="{{ route('inventories.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-[240px] flex-1">
                <i data-lucide="search" class="absolute left-3 top-3 h-4 w-4 text-muted"></i>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search part number, brand, type, specs..."
                       class="h-10 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 text-sm focus:border-brand">
            </div>

            <select name="type_id" onchange="this.form.submit()" class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm">
                <option value="">All Types</option>
                @foreach ($itemTypes as $t)
                    <option value="{{ $t->id }}" @selected(request('type_id') == $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>

            @if (request()->hasAny(['q', 'type_id']))
                <a href="{{ route('inventories.index') }}" class="inline-flex h-10 items-center rounded-xl px-3 text-sm font-semibold text-muted hover:bg-canvas">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-line bg-canvas text-xs font-bold uppercase tracking-wider text-muted">
                <tr>
                    <th class="px-4 py-3">Part Number</th>
                    <th class="px-4 py-3">Tracking</th>
                    <th class="px-4 py-3">Brand</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Default Warranty</th>
                    <th class="px-4 py-3">Description / Specs</th>
                    @canany(['inventory.update', 'inventory.delete'])
                    <th class="px-4 py-3 text-right">Actions</th>
                    @endcanany
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($inventories as $inv)
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <td class="whitespace-nowrap px-4 py-3 font-mono font-bold text-brand">
                            {{ $inv->part_number }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($inv->is_serialized ?? true)
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700 border border-blue-200">
                                    <i data-lucide="scan-line" class="h-3 w-3"></i> Serialized (SN)
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 border border-amber-200">
                                    <i data-lucide="layers" class="h-3 w-3"></i> Non-Serial (Qty)
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-semibold text-slate-800">
                            {{ $inv->brand ?: '-' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($inv->type)
                                <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                    {{ $inv->type->name }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-semibold text-slate-700">
                            {{ \App\Support\Warranty::label($inv->warranty) }}
                        </td>
                        <td class="max-w-xs truncate px-4 py-3 text-slate-600" title="{{ $inv->description }}">
                            {{ $inv->description ?: '-' }}
                        </td>
                        @canany(['inventory.update', 'inventory.delete'])
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @can('inventory.update')
                                <a href="{{ route('inventories.edit', $inv) }}" class="rounded-lg p-1.5 text-muted hover:bg-canvas hover:text-brand" title="Edit">
                                    <i data-lucide="pencil" class="h-4 w-4"></i>
                                </a>
                                @endcan
                                @can('inventory.delete')
                                <form method="POST" action="{{ route('inventories.destroy', $inv) }}" onsubmit="return confirm('Delete Part Number {{ $inv->part_number }} from inventory?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg p-1.5 text-muted hover:bg-accent-tint hover:text-accent-dark" title="Delete">
                                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                        @endcanany
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-muted">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <i data-lucide="boxes" class="h-8 w-8 text-slate-300"></i>
                                <span class="font-semibold">No inventory items found.</span>
                                @can('inventory.create')
                                <a href="{{ route('inventories.create') }}" class="mt-1 text-xs font-bold text-brand hover:underline">
                                    + Add First Part Number
                                </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($inventories->hasPages())
        <div class="border-t border-line p-4">
            {{ $inventories->links() }}
        </div>
    @endif
</section>

{{-- Import Modal --}}
<div id="import-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 backdrop-blur-sm p-4 hidden">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-line pb-3">
            <h3 class="font-extrabold text-lg flex items-center gap-2">
                <i data-lucide="upload" class="h-5 w-5 text-brand"></i> Import Part Numbers Catalog
            </h3>
            <button type="button" onclick="document.getElementById('import-modal').classList.add('hidden')" class="rounded-lg p-1 text-muted hover:bg-canvas">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <p class="text-xs text-muted leading-relaxed">
            Upload spreadsheet (.xlsx or .csv) containing Part Number master specifications. Physical stock quantities are maintained in the Warehouse module.
        </p>

        <div class="rounded-xl border border-line bg-canvas p-3 flex items-center justify-between">
            <div class="text-xs">
                <div class="font-bold text-slate-800">Use Standard Template</div>
                <div class="text-muted">Headers: Part Number, Brand, Type, Default Warranty (months), Description / Specs</div>
            </div>
            <a href="{{ route('inventories.import.template') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-brand hover:bg-slate-50">
                <i data-lucide="download" class="h-3.5 w-3.5"></i> Download Template
            </a>
        </div>

        <form method="POST" action="{{ route('inventories.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-muted mb-1.5">Choose File (.xlsx / .csv) <span class="text-accent">*</span></label>
                <input type="file" name="file" accept=".xlsx,.csv" required
                       class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand-tint file:text-brand hover:file:bg-brand hover:file:text-white file:cursor-pointer border border-slate-300 rounded-xl p-1 bg-white">
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-line">
                <button type="button" onclick="document.getElementById('import-modal').classList.add('hidden')" class="px-4 py-2 text-sm font-semibold text-muted hover:bg-canvas rounded-xl">
                    Cancel
                </button>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-2 text-sm font-bold text-white hover:bg-brand-dark">
                    <i data-lucide="upload" class="h-4 w-4"></i> Upload &amp; Import
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
