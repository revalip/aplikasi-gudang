@extends('layouts.app')
@section('title', 'Create Work Order')

@section('content')
<x-page-header title="Create Work Order" icon="file-signature">
    <x-slot:subtitle><span class="text-muted">Fill in according to the WO document format. User Contact is optional (defaults to "-").</span></x-slot:subtitle>
</x-page-header>

@php $input = 'mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm'; $p = $prefill; @endphp

@if ($errors->any())
    <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm text-accent-dark">
        <div class="font-bold">Please correct the following errors:</div>
        <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('work-orders.store') }}" class="max-w-5xl space-y-4">
    @csrf
    <input type="hidden" name="sales_order_id" value="{{ old('sales_order_id') }}">

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-4 font-extrabold text-base">Document Information</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="text-sm font-bold">WO Date <span class="text-accent">*</span></label>
                <input type="date" name="wo_date" value="{{ old('wo_date', now()->format('Y-m-d')) }}" required class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-bold">Subject <span class="text-accent">*</span></label>
                <input type="text" name="subject" value="{{ old('subject') }}" required placeholder="e.g. Pengadaan dan Jasa Pemasangan Tiang Radio & CCTV" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-bold">Work Type</label>
                <input type="text" name="work_type" value="{{ old('work_type', 'DELIVERY') }}" placeholder="DELIVERY" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-bold">Status Barang di Warehouse <span class="text-accent">*</span></label>
                <select name="target_item_status" class="{{ $input }}">
                    <option value="Reserved" @selected(old('target_item_status', 'Reserved') === 'Reserved')>Reserved (Alokasi WO)</option>
                    <option value="In Transit" @selected(old('target_item_status') === 'In Transit')>In Transit (Dalam Pengiriman)</option>
                    <option value="Rented" @selected(old('target_item_status') === 'Rented')>Rented (Disewa / Pinjam)</option>
                    <option value="Under Service" @selected(old('target_item_status') === 'Under Service')>Under Service (Perbaikan / Setup)</option>
                </select>
                <p class="mt-1 text-xs text-muted">Status barang di gudang akan otomatis mengikuti pilihan ini.</p>
            </div>

            <div>
                <label class="text-sm font-bold">Customer Reference No.</label>
                <input type="text" name="ref_number" value="{{ old('ref_number') }}" placeholder="e.g. STA/PO/2026/10/08/070" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-bold">Reference Company Name</label>
                <input type="text" name="ref_company" value="{{ old('ref_company') }}" placeholder="e.g. STA" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-bold">Business Type</label>
                <input type="text" name="business_type" value="{{ old('business_type', 'NEW-PO') }}" placeholder="NEW-PO" class="{{ $input }}">
            </div>

            <div>
                <label class="text-sm font-bold">PO Date</label>
                <input type="date" name="po_date" value="{{ old('po_date') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-bold">RFS Date</label>
                <input type="date" name="rfs_date" value="{{ old('rfs_date') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-bold">Contract End Date</label>
                <input type="date" name="contract_end_date" value="{{ old('contract_end_date') }}" class="{{ $input }}">
            </div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2">
        <section class="rounded-2xl bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-extrabold text-base text-brand">Customer Contact</h2>
            @if (!empty($customers) && $customers->isNotEmpty())
                <div class="mb-3">
                    <label class="text-sm font-bold">Pick Registered Customer <span class="text-xs font-normal text-muted">(Autofills contact)</span></label>
                    <select id="select-customer" name="customer_id" class="{{ $input }}">
                        <option value="">-- Choose or type manual --</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}"
                                    data-name="{{ $c->name }}"
                                    data-phone="{{ $c->phone }}"
                                    data-email="{{ $c->email }}"
                                    data-address="{{ $c->address }}"
                                    @selected(old('customer_id') == $c->id)>
                                {{ $c->name }}{{ $c->phone ? " ({$c->phone})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            @foreach (['cust_name' => 'Name', 'cust_position' => 'Position', 'cust_phone' => 'Phone', 'cust_email' => 'Email'] as $f => $l)
                <div class="mb-3">
                    <label class="text-sm font-bold">{{ $l }}</label>
                    <input type="text" name="{{ $f }}" value="{{ old($f, $p[$f] ?? '') }}" placeholder="-" class="{{ $input }}">
                </div>
            @endforeach
            <div>
                <label class="text-sm font-bold">Address</label>
                <textarea name="cust_address" rows="2" placeholder="-" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('cust_address', $p['cust_address'] ?? '') }}</textarea>
            </div>
        </section>

        <section class="rounded-2xl bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-extrabold text-base text-accent">User Contact <span class="text-xs font-normal text-muted">(Optional - defaults to "-")</span></h2>
            @foreach (['user_name' => 'Name', 'user_position' => 'Position', 'user_phone' => 'Phone', 'user_email' => 'Email'] as $f => $l)
                <div class="mb-3">
                    <label class="text-sm font-bold">{{ $l }}</label>
                    <input type="text" name="{{ $f }}" value="{{ old($f, $p[$f] ?? '') }}" placeholder="-" class="{{ $input }}">
                </div>
            @endforeach
            <div>
                <label class="text-sm font-bold">Address</label>
                <textarea name="user_address" rows="2" placeholder="-" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('user_address', $p['user_address'] ?? '') }}</textarea>
            </div>
        </section>
    </div>

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="font-extrabold text-base">The Information Table</h2>
                <p class="text-xs text-muted">Input item specifications or technical services. Qty can be entered manually or auto-counted from Serial Numbers.</p>
            </div>
            <button type="button" id="add-row" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-tint px-3 text-sm font-bold text-brand hover:bg-brand hover:text-white">
                <i data-lucide="plus" class="h-4 w-4"></i>Add Row
            </button>
        </div>
        <div id="wo-rows" class="space-y-3"></div>
        <template id="row-template">
            <div class="wo-row rounded-xl border border-line bg-slate-50/50 p-4">
                <div class="grid gap-3 sm:grid-cols-12">
                    <div class="sm:col-span-3">
                        <label class="text-xs font-bold text-muted">Type Name</label>
                        <input name="items[__i__][type_name]" value="DELIVERY" placeholder="DELIVERY" class="row-type mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-sm">
                    </div>
                    <div class="sm:col-span-5">
                        <label class="text-xs font-bold text-muted">Part Number <span class="text-accent">*</span></label>
                        <select name="items[__i__][part_number]" required class="row-part mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-2.5 text-sm">
                            <option value="">-- Select or type part number --</option>
                            @foreach ($inventories as $inv)
                                <option value="{{ $inv->part_number }}"
                                        data-stock="{{ $inv->available_stock ?? 0 }}"
                                        data-name="{{ str_contains($inv->part_number, '-') ? trim(explode('-', $inv->part_number)[0]) : (str_contains($inv->part_number, ' ') ? trim(explode(' ', $inv->part_number)[0]) : $inv->part_number) }}"
                                        data-type="{{ $inv->type?->name }}"
                                        data-brand="{{ $inv->brand }}"
                                        data-desc="{{ $inv->description }}">
                                    {{ $inv->part_number }}{{ $inv->brand ? ' · ' . $inv->brand : '' }} (Stock: {{ $inv->available_stock ?? 0 }})
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="items[__i__][item_name]" class="row-name">
                        <input type="hidden" name="items[__i__][brand]" class="row-brand">
                        <input type="hidden" name="items[__i__][description]" class="row-desc">
                    </div>
                    <div class="sm:col-span-3">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-muted">Qty &amp; Unit <span class="text-accent">*</span></label>
                            <span class="row-stock-info text-[11px] font-semibold text-muted">Stock: -</span>
                        </div>
                        <div class="mt-1 flex gap-1.5">
                            <input type="number" name="items[__i__][qty]" min="1" value="1" required class="row-qty h-10 w-24 rounded-lg border border-slate-300 bg-white px-2.5 text-sm">
                            <select name="items[__i__][unit]" class="row-unit h-10 flex-1 rounded-lg border border-slate-300 bg-white px-1 text-xs font-bold text-slate-700">
                                <option value="pcs">pcs</option>
                                <option value="unit">unit</option>
                                <option value="box">box</option>
                                <option value="roll">roll</option>
                                <option value="pack">pack</option>
                                <option value="meter">meter</option>
                                <option value="set">set</option>
                            </select>
                        </div>
                        <p class="row-qty-note mt-1 hidden text-xs text-muted">Counted from SN lines</p>
                        <p class="row-stock-warn mt-1 hidden text-xs font-bold text-accent-dark"></p>
                    </div>
                    <div class="flex items-end sm:col-span-1">
                        <button type="button" class="remove-row inline-flex h-10 w-full items-center justify-center rounded-lg text-accent hover:bg-accent-tint" aria-label="Remove row"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="text-xs font-bold text-muted">SOW Explanation</label>
                    <textarea name="items[__i__][sow_explanation]" rows="2" placeholder="Explanation of scope of work, specifications, or notes..." class="row-sow mt-1 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm"></textarea>
                </div>

                <details class="mt-2 text-xs">
                    <summary class="cursor-pointer text-muted font-bold hover:text-brand flex items-center gap-1">
                        <i data-lucide="scan-barcode" class="h-3.5 w-3.5"></i>
                        <span>Optional: Serial Numbers (Scan / Paste)</span>
                    </summary>
                    <div class="mt-2 bg-white p-3 rounded-lg border border-slate-200">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-muted">One Serial Number per line</span>
                            <button type="button" data-camera-scan="closest:.wo-row|.row-sn" data-camera-mode="append-line" class="inline-flex items-center gap-1 text-xs font-bold text-brand hover:underline">
                                <i data-lucide="camera" class="h-3.5 w-3.5"></i>Camera Scan
                            </button>
                        </div>
                        <textarea name="items[__i__][serial_numbers]" rows="2" placeholder="Scan or paste Serial Numbers..." class="row-sn w-full rounded-lg border border-slate-300 px-2.5 py-1.5 font-mono text-xs"></textarea>
                    </div>
                </details>
            </div>
        </template>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-4 font-extrabold text-base">Signatories</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="text-sm font-bold">Sales Name</label>
                <input name="sales_name" value="{{ old('sales_name', $p['sales_name'] ?? auth()->user()->name) }}" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-bold">Position</label>
                <input name="sales_title" value="{{ old('sales_title', $p['sales_title'] ?? 'SALES') }}" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-bold">Approver Name</label>
                <input name="approver_name" value="{{ old('approver_name') }}" placeholder="e.g. Achmad Bagir" class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-bold">Approver Position</label>
                <input name="approver_title" value="{{ old('approver_title', $p['approver_title'] ?? 'OPERATIONAL MANAGER') }}" class="{{ $input }}">
            </div>
        </div>
    </section>

    <div class="flex gap-2">
        <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-6 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="save" class="h-4 w-4"></i>Save Work Order
        </button>
        <a href="{{ route('work-orders.index') }}" class="inline-flex h-11 items-center px-5 text-sm font-bold text-muted hover:text-ink">Cancel</a>
    </div>
</form>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<style>
    .select2-container { width: 100% !important; margin-top: .25rem; }
    .select2-container .select2-selection--single { height: 2.5rem; border-radius: .5rem; border-color: #cbd5e1; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 2.4rem; padding-left: .6rem; font-size: .875rem; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 2.4rem; }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
    (function () {
        const custSel = document.getElementById('select-customer');
        if (custSel) {
            custSel.addEventListener('change', () => {
                const opt = custSel.options[custSel.selectedIndex];
                if (!opt || !opt.value) return;
                const name = opt.dataset.name || '';
                const phone = opt.dataset.phone || '';
                const email = opt.dataset.email || '';
                const address = opt.dataset.address || '';
                
                const nameInput = document.querySelector('input[name="cust_name"]');
                const phoneInput = document.querySelector('input[name="cust_phone"]');
                const emailInput = document.querySelector('input[name="cust_email"]');
                const addressInput = document.querySelector('textarea[name="cust_address"]');
                const refCompanyInput = document.querySelector('input[name="ref_company"]');

                if (nameInput) nameInput.value = name;
                if (phoneInput && phone) phoneInput.value = phone;
                if (emailInput && email) emailInput.value = email;
                if (addressInput && address) addressInput.value = address;
                if (refCompanyInput && (!refCompanyInput.value || refCompanyInput.value === '')) refCompanyInput.value = name;
            });
        }

        const container = document.getElementById('wo-rows');
        const template = document.getElementById('row-template');
        let i = 0;

        function snCount(ta) { return (ta.value || '').split(/\r?\n/).map((x) => x.trim()).filter(Boolean).length; }

        function syncQty(row) {
            const ta = row.querySelector('.row-sn'), qty = row.querySelector('.row-qty'), note = row.querySelector('.row-qty-note'), warn = row.querySelector('.row-stock-warn');
            if (!ta || !qty) return;
            const n = snCount(ta);
            qty.readOnly = n > 0;
            if (note) note.classList.toggle('hidden', n === 0);
            if (n > 0) {
                const max = parseInt(qty.max, 10);
                if (!isNaN(max) && max >= 0 && n > max) {
                    if (warn) {
                        warn.textContent = `Scanned SNs (${n}) exceed available stock (${max})!`;
                        warn.classList.remove('hidden');
                    }
                } else if (warn) {
                    warn.classList.add('hidden');
                }
                qty.value = n;
            }
        }

        function bindRow(row) {
            const sel = row.querySelector('.row-part');
            const qtyInput = row.querySelector('.row-qty');
            const stockInfo = row.querySelector('.row-stock-info');
            const stockWarn = row.querySelector('.row-stock-warn');

            const fill = () => {
                const o = sel.options[sel.selectedIndex];
                if (!o || !o.value) return;
                const nameInput = row.querySelector('.row-name');
                const brandInput = row.querySelector('.row-brand');
                const descInput = row.querySelector('.row-desc');
                const sowInput = row.querySelector('.row-sow');
                if (nameInput) nameInput.value = o.dataset.name || o.value;
                if (brandInput) brandInput.value = o.dataset.brand || '';
                if (descInput) descInput.value = o.dataset.desc || '';
                if (sowInput && !sowInput.value && o.dataset.desc) sowInput.value = o.dataset.desc;

                if (o.dataset.stock !== undefined) {
                    const stock = parseInt(o.dataset.stock, 10);
                    if (stockInfo) {
                        stockInfo.textContent = `Stock: ${stock}`;
                        stockInfo.className = stock > 0
                            ? 'row-stock-info text-[11px] font-bold text-emerald-700'
                            : 'row-stock-info text-[11px] font-bold text-accent-dark';
                    }
                    if (stock > 0) {
                        qtyInput.max = stock;
                        if (parseInt(qtyInput.value, 10) > stock) {
                            qtyInput.value = stock;
                        }
                        if (stockWarn) stockWarn.classList.add('hidden');
                    } else {
                        qtyInput.max = 0;
                        qtyInput.value = 0;
                        if (stockWarn) {
                            stockWarn.textContent = 'Stock is 0 in warehouse!';
                            stockWarn.classList.remove('hidden');
                        }
                    }
                }
            };

            if (window.jQuery && jQuery.fn.select2) {
                jQuery(sel).select2({ tags: true }).on('change', fill);
            } else {
                sel.addEventListener('change', fill);
            }

            if (qtyInput) {
                qtyInput.addEventListener('input', () => {
                    const max = parseInt(qtyInput.max, 10);
                    const val = parseInt(qtyInput.value, 10);
                    if (!isNaN(max) && max >= 0 && val > max) {
                        qtyInput.value = max;
                        if (stockWarn) {
                            stockWarn.textContent = `Max available stock is ${max}`;
                            stockWarn.classList.remove('hidden');
                            setTimeout(() => stockWarn.classList.add('hidden'), 2500);
                        }
                    } else if (stockWarn) {
                        stockWarn.classList.add('hidden');
                    }
                });
            }

            const ta = row.querySelector('.row-sn');
            if (ta) ta.addEventListener('input', () => syncQty(row));
        }

        function addRow() {
            const clone = template.content.cloneNode(true);
            clone.querySelectorAll('[name]').forEach((el) => { el.name = el.name.replace('__i__', i); });
            container.appendChild(clone);
            i++;
            bindRow(container.lastElementChild);
            if (window.lucide) lucide.createIcons();
        }
        container.addEventListener('click', (e) => {
            const b = e.target.closest('.remove-row');
            if (b && container.children.length > 1) b.closest('.wo-row').remove();
        });
        document.getElementById('add-row').addEventListener('click', addRow);
        addRow();
    })();
</script>
@endpush
