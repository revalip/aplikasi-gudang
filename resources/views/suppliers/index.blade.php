@extends('layouts.app')
@section('title', 'Manage Suppliers')

@section('content')
<x-page-header title="Manage Suppliers" icon="truck">
    <x-slot:subtitle><span>Total <b>{{ $suppliers->count() }} suppliers</b></span></x-slot:subtitle>
</x-page-header>

@if (session('error'))
    <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm font-semibold text-accent-dark">{{ session('error') }}</div>
@endif

<div class="grid gap-4 lg:grid-cols-[340px_1fr] lg:items-start">
    @can('suppliers.create')
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="font-extrabold">Add New Supplier</h2>
        <form method="POST" action="{{ route('suppliers.store') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="text-sm font-bold">Name <span class="text-accent">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="text-sm font-bold">Phone</label>
                <input type="text" name="phone" value="{{ old('phone') }}"
                       class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="text-sm font-bold">Email</label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="text-sm font-bold">Address</label>
                <textarea name="address" rows="2" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('address') }}</textarea>
            </div>
            <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
                <i data-lucide="plus" class="h-4 w-4"></i>Add Supplier
            </button>
            @error('name')<p class="text-sm font-semibold text-accent-dark">{{ $message }}</p>@enderror
        </form>
    </section>
    @endcan

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr><th class="px-5 py-2.5">Name</th><th class="px-3 py-2.5">Contact</th><th class="px-3 py-2.5">Address</th><th class="px-3 py-2.5">PO Count</th><th class="px-5 py-2.5 text-right">Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $s)
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="px-5 py-2.5 font-bold">{{ $s->name }}</td>
                        <td class="px-3 py-2.5 text-muted">
                            <div>{{ $s->phone ?: '-' }}</div>
                            @if ($s->email)<div class="text-xs text-slate-400">{{ $s->email }}</div>@endif
                        </td>
                        <td class="px-3 py-2.5 text-xs text-muted max-w-xs leading-relaxed">{{ $s->address ?: '-' }}</td>
                        <td class="px-3 py-2.5">{{ $s->purchase_orders_count }}</td>
                        <td class="px-5 py-2.5 text-right">
                            @can('suppliers.delete')<form method="POST" action="{{ route('suppliers.destroy', $s) }}" onsubmit="return confirm({{ Js::from('Delete supplier ' . $s->name . '?') }})">
                                @csrf @method('DELETE')
                                <button class="rounded-lg p-1.5 text-accent hover:bg-accent-tint" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                            </form>@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-muted">No suppliers found.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </section>
</div>
@endsection
