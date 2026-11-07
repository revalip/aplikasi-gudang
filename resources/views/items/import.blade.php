@extends('layouts.app')
@section('title', 'Import Items')

@section('content')
<x-page-header title="Import Items" icon="upload">
    <x-slot:subtitle><span class="text-muted">Import many items at once from an Excel / CSV file: stock coming in, or stock going out.</span></x-slot:subtitle>
</x-page-header>

@if (session('import_fatal'))
    <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm font-semibold text-accent-dark">{{ session('import_fatal') }}</div>
@endif
@if ($errors->any())
    <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm text-accent-dark">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if ($r = session('import_result'))
    @php $isOut = ($r['direction'] ?? 'in') === 'out'; @endphp
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-extrabold">Import Results ({{ $isOut ? 'Stock Out' : 'Stock In' }})</h2>
        <div class="flex flex-wrap gap-2 text-sm">
            @unless ($isOut)<span class="rounded-lg bg-emerald-50 px-3 py-1.5 font-bold text-emerald-700">Created: {{ $r['created'] }}</span>@endunless
            <span class="rounded-lg bg-sky-50 px-3 py-1.5 font-bold text-sky-700">{{ $isOut ? 'Marked out' : 'Updated' }}: {{ $r['updated'] }}</span>
            <span class="rounded-lg bg-slate-100 px-3 py-1.5 font-bold text-slate-600">Skipped ({{ $isOut ? 'already out / repeated' : 'already exists' }}): {{ $r['skipped'] }}</span>
            <span class="rounded-lg px-3 py-1.5 font-bold {{ count($r['errors']) ? 'bg-accent-tint text-accent-dark' : 'bg-slate-100 text-slate-600' }}">Failed: {{ count($r['errors']) }}</span>
        </div>
        @if ($r['newTypes'])
            <p class="mt-3 text-sm text-muted">New types automatically created: <b>{{ implode(', ', $r['newTypes']) }}</b></p>
        @endif
        @if ($r['errors'])
            <p class="mt-4 text-sm font-bold">Failed rows (fix in the file and re-import; successful rows will be skipped):</p>
            <div class="mt-2 max-h-72 overflow-y-auto rounded-xl border border-line">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-left text-xs font-bold text-muted"><tr><th class="px-4 py-2">Row</th><th class="px-4 py-2">Issue</th></tr></thead>
                    <tbody>
                        @foreach ($r['errors'] as $err)
                            <tr class="border-t border-slate-100"><td class="whitespace-nowrap px-4 py-2 font-mono">{{ $err['row'] }}</td><td class="px-4 py-2">{{ $err['msg'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="mt-3 text-xs text-muted">Every imported unit appears in its SN history (click a serial number in Warehouse).</p>
    </section>
@endif

<form method="POST" action="{{ route('items.import.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-4">
    @csrf
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-extrabold">1. What are you importing?</h2>
        <div class="grid gap-2 sm:grid-cols-2">
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-line p-3 has-[:checked]:border-brand has-[:checked]:bg-brand-tint/40">
                <input type="radio" name="direction" value="in" class="mt-0.5 accent-brand" @checked(old('direction', 'in') === 'in')>
                <span><span class="block text-sm font-bold">Stock In (items coming in)</span><span class="block text-xs text-muted">Adds items to the Warehouse by Part Number, with Rack and PO.</span></span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-line p-3 has-[:checked]:border-brand has-[:checked]:bg-brand-tint/40">
                <input type="radio" name="direction" value="out" class="mt-0.5 accent-brand" @checked(old('direction') === 'out')>
                <span><span class="block text-sm font-bold">Stock Out (items going out)</span><span class="block text-xs text-muted">Marks items as out by Serial Number and records it in their history.</span></span>
            </label>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 text-sm">
            <a data-dir="in" href="{{ route('items.import.template', ['direction' => 'in']) }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 font-bold hover:bg-canvas"><i data-lucide="file-down" class="h-4 w-4"></i>Download Stock In template</a>
            <a data-dir="out" href="{{ route('items.import.template', ['direction' => 'out']) }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 font-bold hover:bg-canvas"><i data-lucide="file-down" class="h-4 w-4"></i>Download Stock Out template</a>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-extrabold">2. Upload file</h2>
        <input type="file" name="file" accept=".xlsx,.csv" required class="block w-full rounded-xl border border-slate-300 bg-white p-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-tint file:px-3 file:py-2 file:font-bold file:text-brand">
        <p class="mt-1.5 text-xs text-muted">Format .xlsx or .csv, maximum 10 MB / 5,000 rows. For Stock In you can also use the Export Excel file.</p>

        <div id="mode-box" class="mt-4 space-y-2">
            <span class="text-sm font-bold">If the Item ID or Serial Number already exists in Warehouse:</span>
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-line p-3 has-[:checked]:border-brand has-[:checked]:bg-brand-tint/40">
                <input type="radio" name="mode" value="skip" class="mt-0.5 accent-brand" checked>
                <span><span class="block text-sm font-bold">Skip</span><span class="block text-xs text-muted">Existing data is not modified. Safe for re-importing.</span></span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 border-line p-3 has-[:checked]:border-brand has-[:checked]:bg-brand-tint/40">
                <input type="radio" name="mode" value="update" class="mt-0.5 accent-brand">
                <span><span class="block text-sm font-bold">Overwrite / Update</span><span class="block text-xs text-muted">Existing data will be replaced with file contents (all columns).</span></span>
            </label>
        </div>

        <button class="mt-5 inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-6 font-bold text-white hover:bg-brand-dark">
            <i data-lucide="upload" class="h-4 w-4"></i>Import Now
        </button>
    </section>
</form>

<section id="rules-in" class="max-w-3xl rounded-2xl bg-white p-5 shadow-sm">
    <h2 class="mb-3 font-extrabold">Stock In: columns</h2>
    <p class="mb-3 text-sm text-muted">First row = column header (any order). Columns marked with <span class="text-accent">*</span> are required.</p>
    <div class="flex flex-wrap gap-1.5 text-xs">
        @foreach ($columnsIn as $key => $label)
            <span class="rounded-md px-2 py-1 font-bold {{ in_array($key, $requiredIn, true) ? 'bg-accent-tint text-accent-dark' : 'bg-slate-100 text-slate-600' }}">{{ $label }}{{ in_array($key, $requiredIn, true) ? ' *' : '' }}</span>
        @endforeach
    </div>
    <ul class="mt-4 list-disc space-y-1.5 pl-5 text-sm text-muted">
        <li><b>Part Number</b> is required. If it is new it is added to Inventory automatically, and Item Name, Type and Brand are filled from Inventory when left empty.</li>
        <li><b>Item ID</b> can be left empty, it is generated automatically.</li>
        <li><b>Location</b> (warehouse) must be one of: {{ $locations->implode(', ') }}. A new <b>Rack</b> is created automatically for that warehouse.</li>
        <li><b>Type</b> that does not exist will be created automatically.</li>
        <li><b>Status</b> (empty = In Stock): {{ implode(', ', $options['statuses']) }}.</li>
        <li><b>Condition</b> (empty = Normal): {{ implode(', ', $options['conditions']) }}.</li>
        <li><b>Warranty</b> (optional): number of months, e.g. 12 = 1 year, 24 = 2 years.</li>
        <li><b>Stuff In / Stuff Out</b> date format: dd/mm/yyyy.</li>
        <li><b>Serial Number</b> long digits: format the column as <b>Text</b> in Excel so it won't convert to scientific notation like 1.23E+15.</li>
        <li>Rows with errors will be skipped and reported; valid rows will be imported.</li>
    </ul>
</section>

<section id="rules-out" class="hidden max-w-3xl rounded-2xl bg-white p-5 shadow-sm">
    <h2 class="mb-3 font-extrabold">Stock Out: columns</h2>
    <p class="mb-3 text-sm text-muted">First row = column header. Provide <b>Serial Number</b> (or <b>Item ID</b> for items without a serial number).</p>
    <div class="flex flex-wrap gap-1.5 text-xs">
        @foreach ($columnsOut as $key => $label)
            <span class="rounded-md px-2 py-1 font-bold {{ in_array($key, ['serial_number'], true) ? 'bg-accent-tint text-accent-dark' : 'bg-slate-100 text-slate-600' }}">{{ $label }}{{ $key === 'serial_number' ? ' *' : '' }}</span>
        @endforeach
    </div>
    <ul class="mt-4 list-disc space-y-1.5 pl-5 text-sm text-muted">
        <li>Each matched item is set to the given <b>Status</b> (empty = Sold Out) with <b>Stuff Out</b> date (empty = today).</li>
        <li><b>No WO</b> and <b>Notes</b> are saved in the item history.</li>
        <li>Each row is recorded as an OUT scan, so it shows in the Serial Number history.</li>
        <li>Serial Numbers not found in Warehouse are reported and skipped.</li>
    </ul>
</section>
@endsection

@push('scripts')
<script>
    (() => {
        const radios = [...document.querySelectorAll('input[name="direction"]')];
        const sync = () => {
            const out = radios.find((r) => r.checked)?.value === 'out';
            document.getElementById('rules-in').classList.toggle('hidden', out);
            document.getElementById('rules-out').classList.toggle('hidden', !out);
            document.getElementById('mode-box').classList.toggle('hidden', out);
        };
        radios.forEach((r) => r.addEventListener('change', sync));
        sync();
    })();
</script>
@endpush
