@extends('layouts.app')
@section('title', 'Create Sales Order')

@section('content')
<x-page-header title="Create Sales Order" icon="file-text">
    <x-slot:subtitle><span class="text-muted">SO Number is generated automatically. Select items from available stock.</span></x-slot:subtitle>
</x-page-header>

@php $input = 'mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm'; @endphp

<form method="POST" action="{{ route('sales-orders.store') }}" class="max-w-5xl space-y-4">
    @csrf

    @if ($errors->any())
        <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm text-accent-dark">
            <div class="font-bold">Please correct the following errors:</div>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-4 font-extrabold">SO Information</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="text-sm font-bold">Customer <span class="text-accent">*</span></label>
                <select name="customer_id" required class="{{ $input }}">
                    <option value="">Select customer...</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                @can('customers.view')
                    <a href="{{ route('customers.index') }}" class="mt-1 inline-block text-xs font-bold text-brand hover:underline">+ Add new customer</a>
                @endcan
            </div>
            <div>
                <label class="text-sm font-bold">Order Date <span class="text-accent">*</span></label>
                <input type="date" name="order_date" value="{{ old('order_date', now()->format('Y-m-d')) }}" required class="{{ $input }}">
            </div>
            <div>
                <label class="text-sm font-bold">Payment Terms</label>
                <input type="text" name="payment_terms" value="{{ old('payment_terms') }}" placeholder="e.g. Bank Transfer, NET 14" class="{{ $input }}">
            </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-sm font-bold">Delivery Address</label>
                <textarea name="delivery_address" rows="2" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('delivery_address') }}</textarea>
            </div>
            <div>
                <label class="text-sm font-bold">Notes</label>
                <textarea name="notes" rows="2" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
            </div>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-extrabold">Ordered Items</h2>
            <button type="button" id="add-row" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-tint px-3 text-sm font-bold text-brand hover:bg-brand hover:text-white">
                <i data-lucide="plus" class="h-4 w-4"></i>Add Row
            </button>
        </div>

        @if ($catalog->isEmpty())
            <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">No items available in Warehouse. Please add items to Warehouse first.</p>
        @endif

        <div id="so-rows" class="space-y-3"></div>

        <template id="row-template">
            <div class="so-row grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12">
                <div class="sm:col-span-6">
                    <label class="text-xs font-bold text-muted">Item (from stock) <span class="text-accent">*</span></label>
                    <select class="pick mt-1 h-10 w-full rounded-lg border border-slate-300 px-2.5 text-sm" required>
                        <option value="">Select item...</option>
                    </select>
                    <input type="hidden" name="items[__i__][item_name]" class="f-name">
                    <input type="hidden" name="items[__i__][type_id]" class="f-type">
                    <input type="hidden" name="items[__i__][merek]" class="f-merek">
                    <p class="ready-info mt-1 text-xs text-muted"></p>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-bold text-muted">Qty <span class="text-accent">*</span></label>
                    <input type="number" name="items[__i__][qty]" min="1" value="1" required class="qty mt-1 h-10 w-full rounded-lg border border-slate-300 px-2.5 text-sm">
                </div>
                <div class="sm:col-span-3">
                    <label class="text-xs font-bold text-muted">Unit Price (Rp) <span class="text-accent">*</span></label>
                    <input type="number" name="items[__i__][unit_price]" min="0" step="0.01" value="0" required class="price mt-1 h-10 w-full rounded-lg border border-slate-300 px-2.5 text-sm">
                </div>
                <div class="flex items-end sm:col-span-1">
                    <button type="button" class="remove-row inline-flex h-10 w-10 items-center justify-center rounded-lg text-accent hover:bg-accent-tint" title="Delete row">
                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                </div>
            </div>
        </template>

        <div class="mt-4 text-right text-sm text-muted">Total: <b id="grand-total" class="text-base text-ink">Rp 0</b></div>
    </section>

    <div class="flex flex-wrap gap-2">
        <button name="submit_now" value="1" class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-bold text-white hover:bg-brand-dark">
            <i data-lucide="send" class="h-4 w-4"></i>Save &amp; Submit
        </button>
        <button name="submit_now" value="0" class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 text-sm font-bold hover:bg-canvas">
            <i data-lucide="save" class="h-4 w-4"></i>Save Draft
        </button>
        <a href="{{ route('sales-orders.index') }}" class="inline-flex h-11 items-center px-5 text-sm font-bold text-muted">Cancel</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
    (function () {
        const catalog = @json($catalog);
        const container = document.getElementById('so-rows');
        const template = document.getElementById('row-template');
        const totalEl = document.getElementById('grand-total');
        let i = 0;

        const rupiah = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');

        function recalc() {
            let sum = 0;
            container.querySelectorAll('.so-row').forEach((row) => {
                sum += (parseFloat(row.querySelector('.qty').value) || 0) * (parseFloat(row.querySelector('.price').value) || 0);
            });
            totalEl.textContent = rupiah(sum);
        }

        function fillOptions(select) {
            catalog.forEach((c, idx) => {
                const o = document.createElement('option');
                o.value = idx;
                o.textContent = [c.item_name, c.type, c.merek].filter(Boolean).join(' · ') + ' — ' + c.ready + ' ready';
                select.appendChild(o);
            });
        }

        function addRow() {
            const clone = template.content.cloneNode(true);
            clone.querySelectorAll('[name]').forEach((el) => { el.name = el.name.replace('__i__', i); });
            const row = clone.querySelector('.so-row');
            fillOptions(row.querySelector('.pick'));
            container.appendChild(clone);
            i++;
            if (window.lucide) lucide.createIcons();
        }

        container.addEventListener('change', (e) => {
            const row = e.target.closest('.so-row');
            if (!row) return;
            if (e.target.classList.contains('pick')) {
                const c = catalog[e.target.value];
                row.querySelector('.f-name').value = c ? c.item_name : '';
                row.querySelector('.f-type').value = c && c.type_id ? c.type_id : '';
                row.querySelector('.f-merek').value = c && c.merek ? c.merek : '';
                row.querySelector('.ready-info').textContent = c ? 'Available stock (In Stock): ' + c.ready + ' units' : '';
            }
            recalc();
        });
        container.addEventListener('input', recalc);
        container.addEventListener('click', (e) => {
            const btn = e.target.closest('.remove-row');
            if (btn) { btn.closest('.so-row').remove(); recalc(); }
        });
        document.getElementById('add-row').addEventListener('click', addRow);

        addRow();
    })();
</script>
@endpush
