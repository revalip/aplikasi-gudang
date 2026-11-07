@extends('layouts.app')
@php $editing = $inventory->exists; @endphp
@section('title', $editing ? 'Edit Part Number' : 'Add Part Number')

@section('content')
<x-page-header :title="$editing ? 'Edit Part Number' : 'Add Part Number'" :icon="$editing ? 'pencil' : 'barcode'">
    <x-slot:subtitle>
        <span class="text-muted">{{ $editing ? "Part Number: {$inventory->part_number}" : 'Register new product part number specifications in the warehouse catalog.' }}</span>
    </x-slot:subtitle>
</x-page-header>

@php
    $input = 'mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-brand';
    $val = fn ($field, $default = '') => old($field, $inventory->{$field} ?? $default);
@endphp

<form method="POST" action="{{ $editing ? route('inventories.update', $inventory) : route('inventories.store') }}" class="max-w-4xl space-y-4">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm text-accent-dark">
            <div class="font-bold">Please correct the following errors:</div>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-4 font-extrabold text-ink">Product / Part Information</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="part_number" class="text-sm font-bold">Part Number <span class="text-accent">*</span></label>
                <input id="part_number" name="part_number" value="{{ $val('part_number') }}" required autofocus
                       class="{{ $input }} font-mono font-bold uppercase">
            </div>

            <div>
                <label for="brand" class="text-sm font-bold">Brand</label>
                <input id="brand" name="brand" value="{{ $val('brand') }}" list="brand-list" class="{{ $input }}">
                <datalist id="brand-list">
                    @foreach ($existingBrands as $b)
                        <option value="{{ $b }}">
                    @endforeach
                </datalist>
            </div>

            <div>
                <label for="type_name" class="text-sm font-bold">Item Type</label>
                <input id="type_name" name="type_name"
                       value="{{ old('type_name', $inventory->type?->name ?? '') }}"
                       list="type-list" class="{{ $input }}">
                <datalist id="type-list">
                    @foreach ($itemTypes as $t)
                        <option value="{{ $t->name }}">
                    @endforeach
                </datalist>
            </div>

            <div>
                <label for="warranty" class="text-sm font-bold">Default Warranty (months)</label>
                <input id="warranty" type="number" name="warranty" min="0" max="240" step="1" inputmode="numeric"
                       value="{{ $val('warranty') }}" placeholder="e.g. 12" class="{{ $input }}">
                <p class="mt-1 text-xs text-muted">In months: 12 = 1 year, 24 = 2 years.</p>
            </div>

            <div class="sm:col-span-2">
                <label class="text-sm font-bold block mb-2">Tracking Type (Tipe Pencatatan Stok) <span class="text-accent">*</span></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-start gap-3 rounded-xl border border-slate-300 p-3.5 cursor-pointer hover:border-brand transition-colors bg-white has-[:checked]:border-brand has-[:checked]:bg-brand-tint/20">
                        <input type="radio" name="is_serialized" value="1" @checked(old('is_serialized', $inventory->is_serialized ?? true)) class="mt-1 h-4 w-4 text-brand focus:ring-brand">
                        <div>
                            <span class="block text-sm font-extrabold text-slate-800">Serialized (Serial Number / SN &amp; MAC ID)</span>
                            <span class="block text-xs text-muted mt-0.5">Setiap unit fisik memiliki SN unik (contoh: Router, Switch, Access Point, Radio, CCTV).</span>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-300 p-3.5 cursor-pointer hover:border-brand transition-colors bg-white has-[:checked]:border-brand has-[:checked]:bg-brand-tint/20">
                        <input type="radio" name="is_serialized" value="0" @checked(!old('is_serialized', $inventory->is_serialized ?? true)) class="mt-1 h-4 w-4 text-brand focus:ring-brand">
                        <div>
                            <span class="block text-sm font-extrabold text-slate-800">Non-Serialized (Quantity / Non-SN)</span>
                            <span class="block text-xs text-muted mt-0.5">Pencatatan berbasis jumlah kuantitas/qty (contoh: Kabel Roll, Patch Cord, Konektor RJ45, Baut).</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="sm:col-span-2">
                <label for="description" class="text-sm font-bold">Description / Specifications</label>
                <textarea id="description" name="description" rows="3"
                          class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand">{{ $val('description') }}</textarea>
            </div>
        </div>
    </section>

    <div class="flex gap-2">
        <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-6 font-bold text-white hover:bg-brand-dark">
            <i data-lucide="save" class="h-4 w-4"></i>{{ $editing ? 'Save Changes' : 'Save Part Number' }}
        </button>
        <a href="{{ route('inventories.index') }}" class="flex h-11 items-center rounded-xl px-5 font-semibold text-muted hover:bg-white">Cancel</a>
    </div>
</form>
@endsection
