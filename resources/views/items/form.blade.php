@extends('layouts.app')
@php
    $editing = $item->exists;
    $currentSupplier = $purchaseOrders->firstWhere('po_number', $item->no_po_spr)?->supplier?->name ?? '';
@endphp
@section('title', $editing ? 'Edit Item' : 'Add Item')

@section('content')
<x-page-header :title="$editing ? 'Edit Item' : 'Add Item'" :icon="$editing ? 'pencil' : 'package-plus'">
    <x-slot:subtitle>
        <span class="text-muted">{{ $editing ? (($item->part_number ?: $item->item_name) . ' · ' . ($item->serial_number ?: '-')) : 'Enter incoming warehouse items and scan serial numbers.' }}</span>
    </x-slot:subtitle>
</x-page-header>

@php
    $input = 'mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-brand';
    $val = fn ($field, $default = '') => old($field, $item->{$field} ?? $default);
@endphp

<form id="item-form" method="POST" action="{{ $editing ? route('items.update', $item) : route('items.store') }}" class="max-w-4xl space-y-4">
    @csrf
    @if ($editing) @method('PUT') @endif

    <input type="hidden" id="inventory_id" name="inventory_id" value="{{ $val('inventory_id') }}">
    <input type="hidden" id="item_id" name="item_id" value="{{ $val('item_id') }}">
    <input type="hidden" id="item_name" name="item_name" value="{{ $val('item_name') ?: $val('part_number') }}">

    @if ($errors->any())
        <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm text-accent-dark">
            <div class="font-bold">Please correct the following errors:</div>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- SECTION 1: PRODUCT & PURCHASE ORDER --}}
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-4 font-extrabold text-ink flex items-center gap-2">
            <i data-lucide="package" class="h-5 w-5 text-brand"></i> Product &amp; Purchase Information
        </h2>
        <div class="grid gap-4 sm:grid-cols-3">
            {{-- Part Number --}}
            <div>
                <label for="part_number" class="text-sm font-bold">Part Number <span class="text-accent">*</span></label>
                <select id="part_number" name="part_number" required class="{{ $input }} font-mono font-bold">
                    <option value="">--Select part number--</option>
                    @if ($val('part_number') && ! $inventories->contains('part_number', $val('part_number')))
                        <option value="{{ $val('part_number') }}" selected>{{ $val('part_number') }}</option>
                    @endif
                    @foreach ($inventories as $inv)
                        <option value="{{ $inv->part_number }}" @selected($val('part_number') === $inv->part_number)>
                            {{ $inv->part_number }}{{ $inv->brand ? ' · ' . $inv->brand : '' }}{{ $inv->type ? ' · ' . $inv->type->name : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Type --}}
            <div>
                <label for="type_id" class="text-sm font-bold">Type <span class="text-accent">*</span></label>
                <select id="type_id" name="type_id" required class="{{ $input }}">
                    <option value="">-</option>
                    @foreach ($itemTypes as $type)
                        <option value="{{ $type->id }}" @selected($val('type_id') == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Brand --}}
            <div>
                <label for="merek" class="text-sm font-bold">Brand</label>
                <input id="merek" name="merek" value="{{ $val('merek') }}" class="{{ $input }}">
            </div>

            {{-- PO Number --}}
            <div class="sm:col-span-2">
                <label for="no_po_spr" class="text-sm font-bold">PO Number</label>
                <select id="no_po_spr" name="no_po_spr" class="{{ $input }}">
                    <option value="">--Select PO (Optional)--</option>
                    @if ($val('no_po_spr') && ! $purchaseOrders->contains('po_number', $val('no_po_spr')))
                        <option value="{{ $val('no_po_spr') }}" selected>{{ $val('no_po_spr') }}</option>
                    @endif
                    @foreach ($purchaseOrders as $po)
                        <option value="{{ $po->po_number }}" @selected($val('no_po_spr') === $po->po_number)>
                            {{ $po->po_number }} - {{ $po->supplier?->name ?? 'Unknown Supplier' }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Supplier Name (Auto-resolved from PO) --}}
            <div>
                <label for="supplier_name" class="text-sm font-bold">Supplier</label>
                <input id="supplier_name" type="text" readonly
                       value="{{ old('supplier_name', $currentSupplier) }}"
                       class="{{ $input }} bg-slate-50 font-semibold text-slate-700 cursor-not-allowed">
            </div>
        </div>
    </section>

    {{-- SECTION 2: SERIAL NUMBERS & QUANTITY (BARCODE SCANNER) --}}
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-extrabold text-ink flex items-center gap-2">
                    <i data-lucide="scan-line" class="h-5 w-5 text-brand"></i> Serial Numbers &amp; Quantity (Barcode Scan)
                </h2>
                <p class="text-xs text-muted">Each scanned barcode represents 1 physical unit. Quantity is calculated from total lines of serial numbers.</p>
            </div>
            @if (!$editing)
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-muted">Total Quantity:</span>
                    <span id="qty-counter-badge" class="rounded-full bg-brand px-3 py-1 text-sm font-extrabold text-white">
                        0 units
                    </span>
                </div>
            @endif
        </div>

        @if ($editing)
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="serial_number" class="text-sm font-bold">Serial Number / MAC ID</label>
                    <input id="serial_number" name="serial_number" value="{{ $val('serial_number') }}"
                           placeholder="Leave empty if non-serialized"
                           autocomplete="off" class="{{ $input }} font-mono">
                </div>
                <div>
                    <label for="unit" class="text-sm font-bold">Unit / Satuan</label>
                    <select id="unit" name="unit" class="{{ $input }}">
                        <option value="pcs" @selected($val('unit', 'pcs') === 'pcs')>pcs</option>
                        <option value="unit" @selected($val('unit') === 'unit')>unit</option>
                        <option value="box" @selected($val('unit') === 'box')>box</option>
                        <option value="roll" @selected($val('unit') === 'roll')>roll</option>
                        <option value="pack" @selected($val('unit') === 'pack')>pack</option>
                        <option value="meter" @selected($val('unit') === 'meter')>meter</option>
                        <option value="set" @selected($val('unit') === 'set')>set</option>
                    </select>
                </div>
            </div>
        @else
            {{-- Toggle Mode: Serialized vs Non-Serialized --}}
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider mr-1">Input Mode:</span>
                <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200">
                    <button type="button" id="btn-mode-sn" onclick="switchTrackingMode('serialized')"
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-extrabold transition-all bg-white text-brand shadow-sm">
                        <i data-lucide="scan-line" class="h-4 w-4"></i> Serialized (Scan SN)
                    </button>
                    <button type="button" id="btn-mode-qty" onclick="switchTrackingMode('qty')"
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-extrabold transition-all text-slate-600 hover:text-ink">
                        <i data-lucide="layers" class="h-4 w-4"></i> Non-Serialized (Quantity / Non-SN)
                    </button>
                </div>
                <input type="hidden" name="tracking_mode" id="tracking_mode" value="{{ old('tracking_mode', 'serialized') }}">
            </div>

            <div class="space-y-3">
                {{-- PANEL 1: SERIALIZED (Barcode Continuous Scan + Multi-line list) --}}
                <div id="panel-serialized" class="space-y-3">
                    <div class="rounded-xl border border-line bg-canvas p-3">
                        <label for="barcode-quick-input" class="block text-xs font-bold text-slate-800">
                            Scan Barcode Here (Continuous Scan Mode):
                        </label>
                        <div class="mt-1 flex gap-2">
                            <div class="relative flex-1">
                                <i data-lucide="scan" class="absolute left-3 top-3 h-5 w-5 text-brand"></i>
                                <input id="barcode-quick-input" type="text" autofocus autocomplete="off"
                                       placeholder="Scan barcode SN..."
                                       class="h-11 w-full rounded-xl border border-brand bg-white pl-10 pr-3 font-mono text-sm font-bold text-brand focus:ring-2 focus:ring-brand/20">
                            </div>
                            <x-camera-scan-button target="#barcode-quick-input" mode="enter" data-camera-list="#serial_numbers_text" />
                            <button type="button" id="btn-add-sn" class="inline-flex h-11 items-center gap-1 rounded-xl bg-brand px-4 text-xs font-bold text-white hover:bg-brand-dark">
                                <i data-lucide="plus" class="h-4 w-4"></i> Add
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label for="serial_numbers_text" class="text-xs font-bold text-slate-700">
                                Serial Numbers List (1 line = 1 unit, paste from Excel supported):
                            </label>
                            <button type="button" id="btn-clear-sn" class="text-xs text-muted hover:text-accent font-semibold">
                                Clear list
                            </button>
                        </div>
                        <textarea id="serial_numbers_text" name="serial_numbers_text" rows="5" placeholder="One serial number per line"
                                  class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white p-3 font-mono text-sm leading-relaxed focus:border-brand">{{ old('serial_numbers_text') }}</textarea>
                    </div>
                </div>

                {{-- PANEL 2: NON-SERIALIZED (Quantity & Unit only) --}}
                <div id="panel-qty" class="hidden rounded-xl border border-line bg-canvas p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-tint text-brand">
                            <i data-lucide="layers" class="h-4 w-4"></i>
                        </span>
                        <div>
                            <div class="text-sm font-extrabold text-slate-800">Non-Serialized Bulk Item Input</div>
                            <div class="text-xs text-muted">Barang tanpa serial number (kabel, patch cord, baut, konektor RJ45 dll). Cukup masukkan kuantitas dan satuan.</div>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 pt-2">
                        <div>
                            <label for="quantity" class="text-xs font-bold text-slate-700 block mb-1">Total Quantity <span class="text-accent">*</span></label>
                            <input id="quantity" type="number" name="quantity" min="1" max="10000" value="{{ old('quantity', 1) }}"
                                   class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold focus:border-brand">
                        </div>
                        <div>
                            <label for="unit_manual" class="text-xs font-bold text-slate-700 block mb-1">Satuan / Unit <span class="text-accent">*</span></label>
                            <select id="unit_manual" name="unit" class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-700 focus:border-brand">
                                <option value="pcs" @selected($val('unit', 'pcs') === 'pcs')>pcs</option>
                                <option value="unit" @selected($val('unit') === 'unit')>unit</option>
                                <option value="box" @selected($val('unit') === 'box')>box</option>
                                <option value="roll" @selected($val('unit') === 'roll')>roll</option>
                                <option value="pack" @selected($val('unit') === 'pack')>pack</option>
                                <option value="meter" @selected($val('unit') === 'meter')>meter</option>
                                <option value="set" @selected($val('unit') === 'set')>set</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- SECTION 3: LOCATION & STORAGE --}}
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-4 font-extrabold text-ink flex items-center gap-2">
            <i data-lucide="map-pin" class="h-5 w-5 text-brand"></i> Location &amp; Physical Storage
        </h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="location_id" class="text-sm font-bold">Warehouse <span class="text-accent">*</span></label>
                <select id="location_id" name="location_id" required class="{{ $input }}">
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected($val('location_id') == $loc->id)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rack" class="text-sm font-bold">Rack</label>
                <select id="rack" name="rack" class="{{ $input }}">
                    <option value="">-</option>
                    @if ($val('rack'))
                        <option value="{{ $val('rack') }}" selected>{{ $val('rack') }}</option>
                    @endif
                </select>
                <p class="mt-1 text-xs text-muted">Pick an existing rack or type a new one.</p>
            </div>
            <div>
                <label for="shelf" class="text-sm font-bold">Shelf / Bin Location</label>
                <input id="shelf" type="text" name="shelf" value="{{ $val('shelf') }}" placeholder="e.g. SHELF-01, Bin A, Level 2" class="{{ $input }}">
                <p class="mt-1 text-xs text-muted">Specific shelf level or bin inside the rack.</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 border-t border-slate-100 pt-4">
            <div>
                <label for="status" class="text-sm font-bold">Status <span class="text-accent">*</span></label>
                <select id="status" name="status" required class="{{ $input }}">
                    @foreach ($options['statuses'] as $status)
                        <option value="{{ $status }}" @selected($val('status', 'Available') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="condition" class="text-sm font-bold">Condition <span class="text-accent">*</span></label>
                <select id="condition" name="condition" required class="{{ $input }}">
                    @foreach ($options['conditions'] as $condition)
                        <option value="{{ $condition }}" @selected($val('condition', 'New') === $condition)>{{ $condition }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    {{-- SECTION 4: DATES & NOTES --}}
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-4 font-extrabold text-ink flex items-center gap-2">
            <i data-lucide="file-text" class="h-5 w-5 text-brand"></i> Dates &amp; Additional Details
        </h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="stuff_in" class="text-sm font-bold">Stuff In (Date Received)</label>
                <input id="stuff_in" type="date" name="stuff_in" value="{{ $val('stuff_in', now()->toDateString()) }}" class="{{ $input }}">
            </div>
            <div>
                <label for="stuff_out" class="text-sm font-bold">Stuff Out (Date Dispatched)</label>
                <input id="stuff_out" type="date" name="stuff_out" value="{{ $val('stuff_out') }}" class="{{ $input }}">
            </div>
            <div>
                <label for="warranty" class="text-sm font-bold">Warranty (months)</label>
                <input id="warranty" type="number" name="warranty" min="0" max="240" step="1" inputmode="numeric"
                       value="{{ $val('warranty') }}" placeholder="e.g. 12" class="{{ $input }}">
                <p class="mt-1 text-xs text-muted">In months: 12 = 1 year, 24 = 2 years.</p>
            </div>
            <input type="hidden" id="no_wo" name="no_wo" value="{{ $val('no_wo') }}">
            <div class="sm:col-span-3">
                <label for="notes" class="text-sm font-bold">Description / Specifications / Notes</label>
                <textarea id="notes" name="notes" rows="2"
                          class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ $val('notes') }}</textarea>
            </div>
        </div>
    </section>

    <div class="flex gap-2">
        <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-6 font-bold text-white hover:bg-brand-dark">
            <i data-lucide="save" class="h-4 w-4"></i>{{ $editing ? 'Save Changes' : 'Save Items' }}
        </button>
        <a href="{{ route('items.index', ['location' => $item->location_id]) }}" class="flex h-11 items-center rounded-xl px-5 font-semibold text-muted hover:bg-white">Cancel</a>
    </div>
</form>

@push('scripts')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<style>
    .select2-container { width: 100% !important; margin-top: .375rem; }
    .select2-container .select2-selection--single { height: 2.75rem; border-radius: .75rem; border-color: #cbd5e1; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 2.65rem; padding-left: .75rem; font-size: .875rem; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 2.65rem; }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Select2 on all dropdowns
        if (window.jQuery && jQuery.fn.select2) {
            jQuery('#part_number, #no_po_spr, #type_id, #location_id, #status, #condition').select2({ minimumResultsForSearch: 0 });

            // Warehouse -> Rack: rack list follows the selected warehouse; new racks can be typed
            const racksByLocation = @json($racksByLocation);
            const $loc = jQuery('#location_id'), $rack = jQuery('#rack');
            const initRack = $rack.val();
            const fillRacks = (keep) => {
                const names = racksByLocation[$loc.val()] || [];
                const list = keep && !names.includes(keep) ? [...names, keep] : names;
                $rack.empty().append(new Option('-', '', false, false));
                list.forEach(n => $rack.append(new Option(n, n, false, n === keep)));
                $rack.val(keep || '').trigger('change');
            };
            $rack.select2({ tags: true, allowClear: true, placeholder: '-' });
            fillRacks(initRack);
            $loc.on('change', () => fillRacks(''));
        }

        // --- 1. PO SUPPLIER & WARRANTY LOOKUP ---
        const poDataMap = {
            @foreach ($purchaseOrders as $po)
                "{{ addslashes($po->po_number) }}": {
                    supplier: "{{ addslashes($po->supplier?->name ?? 'Unknown Supplier') }}",
                    warranty: "{{ addslashes($po->items->firstWhere('warranty')?->warranty ?? '') }}",
                    firstPartNumber: "{{ addslashes($po->items->firstWhere('part_number')?->part_number ?? '') }}"
                },
            @endforeach
        };

        const poInput = document.getElementById('no_po_spr');
        const supplierInput = document.getElementById('supplier_name');
        const warrantySelect = document.getElementById('warranty');
        const partInput = document.getElementById('part_number');

        function updatePoDetails() {
            if (!poInput) return;
            const val = poInput.value.trim();
            const poData = poDataMap[val];

            if (poData) {
                if (supplierInput) supplierInput.value = poData.supplier;
                if (warrantySelect && !warrantySelect.value && poData.warranty) {
                    setSelect(warrantySelect, poData.warranty);
                }
                if (partInput && !partInput.value && poData.firstPartNumber) {
                    if ([...partInput.options].some(o => o.value === poData.firstPartNumber)) {
                        setSelect(partInput, poData.firstPartNumber);
                    }
                    fetchPartDetails(poData.firstPartNumber);
                }
            } else if (!val && supplierInput) {
                supplierInput.value = '';
            }
        }

        // Select2 fires jQuery 'change' only, so bind through jQuery
        const onChange = (el, fn) => window.jQuery ? jQuery(el).on('change', fn) : el.addEventListener('change', fn);
        const setSelect = (el, v) => { el.value = v; if (window.jQuery) jQuery(el).trigger('change.select2'); };

        if (poInput) {
            onChange(poInput, updatePoDetails);
            if (poInput.value) updatePoDetails();
        }

        // --- 2. AUTOFILL FROM INVENTORY (PART NUMBER) ---
        async function fetchPartDetails(partNumber) {
            if (!partNumber || partNumber.trim() === '') return;
            const pn = encodeURIComponent(partNumber.trim());

            try {
                const response = await fetch(`{{ url('/inventories/data') }}/${pn}`);
                if (!response.ok) return;
                const data = await response.json();

                const invId = document.getElementById('inventory_id');
                const itemName = document.getElementById('item_name');
                const typeSelect = document.getElementById('type_id');
                const brandInput = document.getElementById('merek');
                const notesInput = document.getElementById('notes');

                if (invId) invId.value = data.id;
                if (itemName) {
                    itemName.value = data.part_number;
                }
                if (typeSelect && data.type_id) setSelect(typeSelect, data.type_id);
                if (brandInput && data.brand) brandInput.value = data.brand;
                if (warrantySelect && data.warranty && !warrantySelect.value) setSelect(warrantySelect, data.warranty);
                @if (!$editing)
                if (notesInput && data.description && !notesInput.value.trim()) {
                    notesInput.value = data.description;
                }
                if (data.is_serialized === false) {
                    if (window.switchTrackingMode) window.switchTrackingMode('qty');
                } else if (data.is_serialized === true) {
                    if (window.switchTrackingMode) window.switchTrackingMode('serialized');
                }
                @endif
            } catch (err) {
                console.error(err);
            }
        }

        if (partInput) {
            const handlePartInput = () => {
                fetchPartDetails(partInput.value);
                const itemName = document.getElementById('item_name');
                if (itemName && partInput.value) {
                    itemName.value = partInput.value;
                }
            };
            onChange(partInput, handlePartInput);
        }

        // --- 3. CONTINUOUS BARCODE SCANNER & QTY CALCULATION ---
        const quickInput = document.getElementById('barcode-quick-input');
        const snTextarea = document.getElementById('serial_numbers_text');
        const qtyBadge = document.getElementById('qty-counter-badge');
        const btnAddSn = document.getElementById('btn-add-sn');
        const btnClear = document.getElementById('btn-clear-sn');
        const qtyManual = document.getElementById('quantity');

        window.switchTrackingMode = function(mode) {
            const btnSn = document.getElementById('btn-mode-sn');
            const btnQty = document.getElementById('btn-mode-qty');
            const panelSn = document.getElementById('panel-serialized');
            const panelQty = document.getElementById('panel-qty');
            const modeInput = document.getElementById('tracking_mode');

            if (mode === 'qty') {
                if (panelSn) panelSn.classList.add('hidden');
                if (panelQty) panelQty.classList.remove('hidden');
                if (modeInput) modeInput.value = 'qty';
                if (btnQty) {
                    btnQty.classList.add('bg-white', 'text-brand', 'shadow-sm');
                    btnQty.classList.remove('text-slate-600');
                }
                if (btnSn) {
                    btnSn.classList.remove('bg-white', 'text-brand', 'shadow-sm');
                    btnSn.classList.add('text-slate-600');
                }
            } else {
                if (panelSn) panelSn.classList.remove('hidden');
                if (panelQty) panelQty.classList.add('hidden');
                if (modeInput) modeInput.value = 'serialized';
                if (btnSn) {
                    btnSn.classList.add('bg-white', 'text-brand', 'shadow-sm');
                    btnSn.classList.remove('text-slate-600');
                }
                if (btnQty) {
                    btnQty.classList.remove('bg-white', 'text-brand', 'shadow-sm');
                    btnQty.classList.add('text-slate-600');
                }
            }
            updateQtyCounter();
        };

        function updateQtyCounter() {
            if (!qtyBadge) return;
            const modeInput = document.getElementById('tracking_mode');
            const isQtyMode = modeInput && modeInput.value === 'qty';
            let count = 0;
            if (isQtyMode && qtyManual) {
                count = parseInt(qtyManual.value || '1', 10);
            } else if (snTextarea) {
                const lines = snTextarea.value.split('\n').map(s => s.trim()).filter(s => s.length > 0);
                count = lines.length;
            }
            qtyBadge.textContent = `${count} ${count === 1 ? 'unit' : 'units'}`;
            if (count > 0) {
                qtyBadge.classList.remove('bg-brand');
                qtyBadge.classList.add('bg-emerald-600');
            } else {
                qtyBadge.classList.remove('bg-emerald-600');
                qtyBadge.classList.add('bg-brand');
            }
        }

        if (qtyManual) {
            qtyManual.addEventListener('input', updateQtyCounter);
        }

        function appendScannedSn(val) {
            const sn = (val || '').trim();
            if (!sn) return;

            const existingLines = snTextarea.value.split('\n').map(s => s.trim()).filter(s => s.length > 0);
            if (existingLines.includes(sn)) {
                alert(`Serial Number "${sn}" has already been scanned in this list!`);
                if (quickInput) {
                    quickInput.select();
                    quickInput.classList.add('ring-2', 'ring-accent');
                    setTimeout(() => quickInput.classList.remove('ring-2', 'ring-accent'), 600);
                }
                return;
            }

            existingLines.push(sn);
            snTextarea.value = existingLines.join('\n');
            updateQtyCounter();

            if (quickInput) {
                quickInput.value = '';
                quickInput.classList.add('ring-2', 'ring-emerald-400');
                setTimeout(() => quickInput.classList.remove('ring-2', 'ring-emerald-400'), 300);
                quickInput.focus();
            }
        }

        if (quickInput) {
            // Intercept barcode scanner 'Enter' keystroke
            quickInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    appendScannedSn(quickInput.value);
                }
            });
        }

        if (btnAddSn && quickInput) {
            btnAddSn.addEventListener('click', () => appendScannedSn(quickInput.value));
        }

        if (qtyManual) qtyManual.addEventListener('input', updateQtyCounter);

        if (snTextarea) {
            snTextarea.addEventListener('input', updateQtyCounter);
            snTextarea.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    setTimeout(updateQtyCounter, 50);
                }
            });
            updateQtyCounter();
        }

        if (btnClear && snTextarea) {
            btnClear.addEventListener('click', () => {
                if (confirm('Clear all scanned serial numbers?')) {
                    snTextarea.value = '';
                    updateQtyCounter();
                    if (quickInput) quickInput.focus();
                }
            });
        }

        const itemForm = document.getElementById('item-form');
        if (itemForm && !@json($editing)) {
            itemForm.addEventListener('submit', (e) => {
                const mode = document.getElementById('tracking_mode')?.value;
                if (mode === 'serialized') {
                    // If user typed in quick input but forgot to click Add
                    if (quickInput && quickInput.value.trim() !== '') {
                        appendScannedSn(quickInput.value);
                    }
                    const snVal = snTextarea ? snTextarea.value.trim() : '';
                    if (!snVal) {
                        e.preventDefault();
                        alert('Part Number bertipe Serialized wajib memasukkan setidaknya 1 Serial Number (SN)! Silakan scan barcode atau masukkan SN.');
                        if (quickInput) quickInput.focus();
                    }
                }
            });
        }
    });
</script>
@endpush
@endsection
