@extends('layouts.app')
@section('title', 'Documentation')

@section('content')
@php $formatColor = ['docx' => 'bg-brand-tint text-brand', 'pdf' => 'bg-accent-tint text-accent-dark', 'xlsx' => 'bg-emerald-50 text-emerald-700']; @endphp
<x-page-header title="Documentation" icon="folder-archive">
    <x-slot:subtitle><span>Total <b>{{ number_format($documents->total(), 0, ',', '.') }} document(s)</b>. All printed SO / WO / DO / PO (Word & PDF) and Excel exports are automatically archived here.</span></x-slot:subtitle>
    <x-slot:actions>
        <a href="{{ route('documents.export', request()->query()) }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold hover:bg-canvas">
            <i data-lucide="file-spreadsheet" class="h-4 w-4"></i>Export Report (Excel)
        </a>
    </x-slot:actions>
</x-page-header>

<form method="GET" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search document number / file name..." class="h-10 rounded-xl border border-slate-300 px-3 text-sm lg:col-span-2">
    <select name="category" class="h-10 rounded-xl border border-slate-300 px-3 text-sm">
        <option value="">All categories</option>
        @foreach ($categories as $c)<option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>@endforeach
    </select>
    <select name="format" class="h-10 rounded-xl border border-slate-300 px-3 text-sm">
        <option value="">All formats</option>
        @foreach ($formats as $key => $label)<option value="{{ $key }}" @selected(request('format') === $key)>{{ $label }}</option>@endforeach
    </select>
    <input type="date" name="date_from" value="{{ request('date_from') }}" class="h-10 rounded-xl border border-slate-300 px-3 text-sm" title="From date">
    <input type="date" name="date_to" value="{{ request('date_to') }}" class="h-10 rounded-xl border border-slate-300 px-3 text-sm" title="To date">
    <div class="flex gap-2 sm:col-span-2 lg:col-span-6">
        <button class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand px-5 text-sm font-bold text-white hover:bg-brand-dark"><i data-lucide="search" class="h-4 w-4"></i>Filter</button>
        @if (request()->query())<a href="{{ route('documents.index') }}" class="inline-flex h-10 items-center px-4 text-sm font-bold text-muted hover:underline">Reset</a>@endif
    </div>
</form>

@if ($summary->isNotEmpty())
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($summary as $category => $total)
            <a href="{{ route('documents.index', array_merge(request()->query(), ['category' => $category, 'page' => null])) }}"
               class="rounded-2xl bg-white p-4 shadow-sm hover:ring-2 hover:ring-brand/20 {{ request('category') === $category ? 'ring-2 ring-brand' : '' }}">
                <div class="text-xs font-bold text-muted">{{ $category }}</div>
                <div class="mt-1 text-2xl font-extrabold">{{ $total }} <span class="text-sm font-semibold text-muted">file(s)</span></div>
            </a>
        @endforeach
    </div>
@endif

<section class="overflow-hidden rounded-2xl bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr>
                    <th class="px-4 py-2.5">Date</th><th class="px-3 py-2.5">Category</th><th class="px-3 py-2.5">Document No.</th>
                    <th class="px-3 py-2.5">Format</th><th class="px-3 py-2.5">Size</th><th class="px-3 py-2.5">Created By</th><th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $doc)
                    <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60">
                        <td class="whitespace-nowrap px-4 py-3 text-muted">{{ $doc->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-3">{{ $doc->category }}</td>
                        <td class="px-3 py-3 font-mono font-bold">
                            @if ($url = $doc->sourceUrl())
                                <a href="{{ $url }}" class="text-brand hover:underline">{{ $doc->reference }}</a>
                            @else
                                {{ $doc->reference ?? '-' }}
                            @endif
                        </td>
                        <td class="px-3 py-3"><span class="rounded-md px-2.5 py-0.5 text-xs font-bold {{ $formatColor[$doc->format] ?? 'bg-slate-100 text-slate-600' }}">{{ $doc->format_label }}</span></td>
                        <td class="px-3 py-3 text-muted">{{ $doc->size_label }}</td>
                        <td class="px-3 py-3">{{ $doc->createdBy->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('documents.download', $doc) }}" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-bold text-brand hover:bg-brand-tint" title="Download {{ $doc->file_name }}">
                                <i data-lucide="download" class="h-4 w-4"></i>Download
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-muted">No documents yet. Generate SO / WO / DO / PO prints or export Excel, and files will automatically appear here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($documents->hasPages())<div class="border-t border-line px-4 py-3">{{ $documents->links() }}</div>@endif
</section>
@endsection
