@extends('layouts.app')
@section('title', 'Manage Item Types')

@section('content')
<x-page-header title="Manage Item Types" icon="tags">
    <x-slot:subtitle><span>Total <b>{{ $types->count() }} types</b></span></x-slot:subtitle>
</x-page-header>

<div class="grid gap-4 lg:grid-cols-[1fr_1.4fr] lg:items-start">
    @can('item-types.create')
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="font-extrabold">Add New Type</h2>
        <p class="mt-1 text-sm text-muted">New types will appear immediately on the item form.</p>
        <form method="POST" action="{{ route('item-types.store') }}" class="mt-4 flex gap-2">
            @csrf
            <input type="text" name="name" placeholder="e.g. NVR" required aria-label="New type name"
                   class="h-11 min-w-0 flex-1 rounded-xl border border-slate-300 px-3 text-sm">
            <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
                <i data-lucide="plus" class="h-4 w-4"></i>Add
            </button>
        </form>
        @error('name')<p class="mt-2 text-sm font-semibold text-accent-dark">{{ $message }}</p>@enderror
    </section>
    @endcan

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr><th class="px-5 py-2.5">Type</th><th class="px-3 py-2.5">Item Count</th><th class="px-5 py-2.5 text-right">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($types as $type)
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="px-5 py-2.5"><span class="flex items-center gap-2.5 font-bold"><x-type-icon :type="$type->name" />{{ $type->name }}</span></td>
                        <td class="px-3 py-2.5">{{ $type->items_count }} units</td>
                        <td class="px-5 py-2.5 text-right">
                            @can('item-types.delete')<form method="POST" action="{{ route('item-types.destroy', $type) }}" onsubmit="return confirm({{ Js::from("Delete type {$type->name}?") }})">
                                @csrf @method('DELETE')
                                <button class="rounded-lg p-1.5 text-accent hover:bg-accent-tint" aria-label="Delete type {{ $type->name }}" title="Delete">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                </button>
                            </form>@endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</div>
@endsection
