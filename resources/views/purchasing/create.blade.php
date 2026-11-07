@extends('layouts.app')
@section('title', 'Create Purchase Order')

@section('content')
<x-page-header title="Create Purchase Order" icon="shopping-cart">
    <x-slot:subtitle><span class="text-muted">Order items and inventory units from suppliers.</span></x-slot:subtitle>
</x-page-header>

@php
    $input = 'mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm focus:border-brand';
    $initialRows = old('items', $prefill->all());
    if (empty($initialRows)) {
        $initialRows = [[]];
    }
@endphp

<form method="POST" action="{{ route('purchasing.store') }}" class="max-w-7xl space-y-4">
    @csrf
    @if ($pr)
        <input type="hidden" name="purchase_request_id" value="{{ $pr->id }}">
        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            This PO is created from Purchase Request <b>{{ $pr->pr_number }}</b>@if ($pr->salesOrder) (for Sales Order {{ $pr->salesOrder->so_number }})@endif. Items and quantities are pre-filled.
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm text-accent-dark">
            <div class="font-bold">Please correct the following errors:</div>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- SECTION 1: PO INFORMATION --}}
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-4 font-extrabold text-ink">Purchase Order Information</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="po_number" class="text-sm font-bold">PO Number <span class="text-accent">*</span></label>
                <input id="po_number" type="text" name="po_number" value="{{ old('po_number', $nextPoNumber) }}" required class="{{ $input }} font-mono font-bold">
            </div>

            <div>
                <label for="supplier_name" class="text-sm font-bold">Supplier <span class="text-accent">*</span></label>
                <input id="supplier_name" type="text" name="supplier_name"
                       value="{{ old('supplier_name', $suppliers->firstWhere('id', old('supplier_id'))?->name ?? '') }}"
                       list="supplier-list" autocomplete="off" required class="{{ $input }}">
                <input type="hidden" id="supplier_id" name="supplier_id" value="{{ old('supplier_id') }}">
                @can('suppliers.view')
                    <a href="{{ route('suppliers.index') }}" class="mt-1 inline-block text-xs font-bold text-brand hover:underline">+ Add new supplier</a>
                @endcan
            </div>

            <div>
                <label for="order_date" class="text-sm font-bold">Order Date <span class="text-accent">*</span></label>
                <input id="order_date" type="date" name="order_date" value="{{ old('order_date', now()->format('Y-m-d')) }}" required class="{{ $input }}">
            </div>

            <div>
                <label for="expected_date" class="text-sm font-bold">Expected Date</label>
                <input id="expected_date" type="date" name="expected_date" value="{{ old('expected_date') }}" class="{{ $input }}">
            </div>
        </div>

        <div class="mt-4">
            <label for="notes" class="text-sm font-bold">Notes</label>
            <textarea id="notes" name="notes" rows="2" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
        </div>
    </section>

    {{-- SECTION 2: ORDERED ITEMS --}}
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-extrabold text-ink">Ordered Items</h2>
                <p class="text-xs text-muted">Select Part Number to auto-fill Item Name (model), Description, Type, Brand, and Warranty from Inventory.</p>
            </div>
            <button type="button" id="add-row" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-tint px-3 text-sm font-bold text-brand hover:bg-brand hover:text-white">
                <i data-lucide="plus" class="h-4 w-4"></i>Add Row
            </button>
        </div>

        <div id="po-rows" class="space-y-3"></div>

        <template id="row-template">
            <div class="po-row rounded-xl border border-line p-3 transition-colors hover:border-slate-300">
                <div class="grid gap-3 sm:grid-cols-12 items-start">
                    {{-- Part Number (2 col) --}}
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-muted truncate" title="Part Number">Part Number</label>
                        <input type="text" name="items[__i__][part_number]" list="inventory-part-list" autocomplete="off"
                               class="row-part-number mt-1 h-10 w-full rounded-lg border border-slate-300 px-2.5 font-mono text-sm uppercase focus:border-brand">
                        <input type="hidden" name="items[__i__][inventory_id]" class="row-inventory-id">
                    </div>

                    {{-- Item Name (2 col) --}}
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-muted truncate" title="Item Name">Item Name <span class="text-accent">*</span></label>
                        <input type="text" name="items[__i__][item_name]" required
                               class="row-item-name mt-1 h-10 w-full rounded-lg border border-slate-300 px-2.5 text-sm font-semibold focus:border-brand">
                    </div>

                    {{-- Description (1 col) --}}
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-muted truncate" title="Description">Description</label>
                        <input type="text" name="items[__i__][description]"
                               class="row-description mt-1 h-10 w-full rounded-lg border border-slate-300 px-2.5 text-sm focus:border-brand">
                    </div>

                    {{-- Type (1 col) --}}
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-muted truncate" title="Type">Type <span class="text-accent">*</span></label>
                        <input type="text" name="items[__i__][type_name]" list="item-type-list" required autocomplete="off"
                               class="row-type-name mt-1 h-10 w-full rounded-lg border border-slate-300 px-2.5 text-sm focus:border-brand">
                        <input type="hidden" name="items[__i__][type_id]" class="row-type-id">
                    </div>

                    {{-- Brand (1 col) --}}
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-muted truncate" title="Brand">Brand</label>
                        <input type="text" name="items[__i__][merek]"
                               class="row-merek mt-1 h-10 w-full rounded-lg border border-slate-300 px-2.5 text-sm focus:border-brand">
                    </div>

                    {{-- Warranty (1 col) --}}
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-muted truncate" title="Warranty">Warranty (mo)</label>
                        <input type="number" min="0" max="240" step="1" inputmode="numeric" placeholder="mo" name="items[__i__][warranty]"
                               class="row-warranty mt-1 h-10 w-full rounded-lg border border-slate-300 px-2 text-sm focus:border-brand">
                    </div>

                    {{-- Qty (1 col) --}}
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-muted truncate" title="Quantity">Qty <span class="text-accent">*</span></label>
                        <input type="number" name="items[__i__][qty_ordered]" min="1" value="1" required
                               class="row-qty mt-1 h-10 w-full rounded-lg border border-slate-300 px-2 text-sm text-center focus:border-brand">
                    </div>

                    {{-- Unit (1 col) --}}
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-muted truncate" title="Unit">Unit</label>
                        <select name="items[__i__][unit]" class="row-unit mt-1 h-10 w-full rounded-lg border border-slate-300 px-1 text-xs font-bold text-slate-700 focus:border-brand">
                            <option value="pcs">pcs</option>
                            <option value="unit">unit</option>
                            <option value="box">box</option>
                            <option value="roll">roll</option>
                            <option value="pack">pack</option>
                            <option value="meter">meter</option>
                            <option value="set">set</option>
                        </select>
                    </div>

                    {{-- Unit Price (1 col) --}}
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-muted truncate" title="Price">Price</label>
                        <input type="number" name="items[__i__][unit_price]" min="0" step="0.01"
                               class="row-price mt-1 h-10 w-full rounded-lg border border-slate-300 px-2 text-sm focus:border-brand">
                    </div>

                    {{-- Action (1 col) --}}
                    <div class="flex flex-col justify-end sm:col-span-1">
                        <span class="block text-xs font-bold text-transparent select-none truncate">Del</span>
                        <div class="mt-1 flex h-10 items-center justify-center">
                            <button type="button" class="remove-row inline-flex h-9 w-9 items-center justify-center rounded-lg text-accent hover:bg-accent-tint" title="Remove row">
                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </section>

    <div class="flex gap-2">
        <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-6 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="check" class="h-4 w-4"></i>Save Purchase Order
        </button>
        <a href="{{ route('purchasing.index') }}" class="inline-flex h-11 items-center px-5 text-sm font-bold text-muted hover:text-ink">Cancel</a>
    </div>
</form>

{{-- DATALISTS FOR AUTOCOMPLETE --}}
<datalist id="supplier-list">
    @foreach ($suppliers as $s)
        <option value="{{ $s->name }}" data-id="{{ $s->id }}"></option>
    @endforeach
</datalist>

<datalist id="inventory-part-list">
    @foreach ($inventories as $inv)
        <option value="{{ $inv->part_number }}">
            {{ $inv->brand ? $inv->brand . ' · ' : '' }}{{ $inv->type?->name ?? 'Part' }}
        </option>
    @endforeach
</datalist>

<datalist id="item-type-list">
    @foreach ($itemTypes as $t)
        <option value="{{ $t->name }}" data-id="{{ $t->id }}"></option>
    @endforeach
</datalist>


@endsection

@push('scripts')
<script>
    (function () {
        // --- DATA MAPS SAFELY ENCODED AS JSON ---
        const suppliersMap = @json($suppliersMap ?? []);
        const typesMap = @json($typesMap ?? []);
        const inventoryMap = @json($inventoryMap ?? []);

        // Helper: Extract front part of Part Number (e.g. "RB921UAGS-5SHPacD-NM" -> "RB921UAGS")
        function getFrontPartNumber(pn) {
            if (!pn) return '';
            const trimmed = String(pn).trim();
            if (trimmed.includes('-')) {
                return trimmed.split('-')[0].trim();
            }
            if (trimmed.includes(' ')) {
                return trimmed.split(' ')[0].trim();
            }
            return trimmed;
        }

        // --- SUPPLIER AUTO-ID RESOLUTION ---
        const supplierInput = document.getElementById('supplier_name');
        const supplierIdInput = document.getElementById('supplier_id');

        function resolveSupplierId() {
            if (!supplierInput || !supplierIdInput) return;
            const val = supplierInput.value.trim().toLowerCase();
            supplierIdInput.value = suppliersMap[val] || '';
        }

        if (supplierInput) {
            supplierInput.addEventListener('input', resolveSupplierId);
            supplierInput.addEventListener('change', resolveSupplierId);
            resolveSupplierId();
        }

        // --- DYNAMIC ROWS ---
        const container = document.getElementById('po-rows');
        const template = document.getElementById('row-template');
        let rowIndex = 0;

        const initialRows = @json($initialRows);

        function attachRowHandlers(rowEl) {
            const partInput = rowEl.querySelector('.row-part-number');
            const invIdInput = rowEl.querySelector('.row-inventory-id');
            const nameInput = rowEl.querySelector('.row-item-name');
            const descInput = rowEl.querySelector('.row-description');
            const typeInput = rowEl.querySelector('.row-type-name');
            const typeIdInput = rowEl.querySelector('.row-type-id');
            const merekInput = rowEl.querySelector('.row-merek');
            const warrantyInput = rowEl.querySelector('.row-warranty');

            // Resolve Type ID when type name changes
            if (typeInput && typeIdInput) {
                const resolveTypeId = () => {
                    const key = typeInput.value.trim().toLowerCase();
                    typeIdInput.value = typesMap[key] || '';
                };
                typeInput.addEventListener('input', resolveTypeId);
                typeInput.addEventListener('change', resolveTypeId);
            }

            // Autofill when Part Number changes
            async function handlePartNumberChange() {
                if (!partInput) return;
                const rawPn = partInput.value.trim();
                const pn = rawPn.toUpperCase();
                if (!pn) {
                    if (invIdInput) invIdInput.value = '';
                    return;
                }

                let data = inventoryMap[pn];

                // Fallback to API if not in pre-rendered map
                if (!data) {
                    try {
                        const res = await fetch(`{{ url('/inventories/data') }}/${encodeURIComponent(pn)}`);
                        if (res.ok) {
                            data = await res.json();
                        }
                    } catch (err) {
                        console.error(err);
                    }
                }

                if (data) {
                    if (invIdInput) invIdInput.value = data.id;

                    // Item Name: Take front part of Part Number (e.g. RB921UAGS)
                    if (nameInput) {
                        nameInput.value = getFrontPartNumber(data.part_number);
                    }

                    // Description: Auto-fill description/spec from Inventory
                    if (descInput) {
                        descInput.value = data.description || '';
                    }

                    // Type
                    if (typeInput && data.type_name) {
                        typeInput.value = data.type_name;
                        if (typeIdInput) typeIdInput.value = data.type_id || typesMap[data.type_name.toLowerCase()] || '';
                    }

                    // Brand
                    if (merekInput && data.brand && !merekInput.value) {
                        merekInput.value = data.brand;
                    }

                    // Warranty
                    if (warrantyInput && data.warranty && !warrantyInput.value) {
                        warrantyInput.value = data.warranty;
                    }
                } else {
                    // Even if not in inventory yet, auto-populate Item Name from front part of what user typed
                    if (nameInput && (!nameInput.value || nameInput.value.trim() === '')) {
                        nameInput.value = getFrontPartNumber(rawPn);
                    }
                }
            }

            if (partInput) {
                partInput.addEventListener('change', handlePartNumberChange);
                partInput.addEventListener('blur', handlePartNumberChange);
            }
        }

        function addRow(values = null) {
            if (!template || !container) return;
            const clone = template.content.cloneNode(true);
            const rowEl = clone.querySelector('.po-row') || clone.firstElementChild;
            if (!rowEl) return;

            rowEl.querySelectorAll('[name]').forEach((el) => {
                el.name = el.name.replace('__i__', rowIndex);
                const match = el.name.match(/\[(\w+)\]$/);
                if (match && values && values[match[1]] !== undefined && values[match[1]] !== null) {
                    el.value = values[match[1]];
                }
            });

            // If prefill has type_id, set type_name from typesMap reverse
            if (values && values.type_id && !values.type_name) {
                const typeInput = rowEl.querySelector('.row-type-name');
                const opt = document.querySelector(`#item-type-list option[data-id="${values.type_id}"]`);
                if (typeInput && opt) {
                    typeInput.value = opt.value;
                }
            }

            attachRowHandlers(rowEl);
            container.appendChild(rowEl);
            rowIndex++;

            try {
                if (window.lucide && typeof lucide.createIcons === 'function') {
                    lucide.createIcons();
                }
            } catch (e) {
                // ignore
            }
        }

        const addBtn = document.getElementById('add-row');
        if (addBtn) {
            addBtn.addEventListener('click', () => addRow());
        }

        container.addEventListener('click', (e) => {
            const btn = e.target.closest('.remove-row');
            if (btn) {
                const allRows = container.querySelectorAll('.po-row');
                if (allRows.length <= 1) {
                    // Reset single row instead of deleting
                    const singleRow = allRows[0];
                    singleRow.querySelectorAll('input').forEach((input) => {
                        if (input.name.includes('[qty_ordered]')) {
                            input.value = '1';
                        } else {
                            input.value = '';
                        }
                    });
                    return;
                }
                btn.closest('.po-row').remove();
            }
        });

        // Initialize rows
        if (Array.isArray(initialRows) && initialRows.length > 0) {
            initialRows.forEach((row) => addRow(row));
        } else {
            addRow();
        }
    })();
</script>
@endpush
