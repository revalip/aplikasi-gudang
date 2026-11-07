@extends('layouts.app')
@section('title', 'Manage Customers')

@section('content')
<x-page-header title="Manage Customers" icon="contact">
    <x-slot:subtitle><span>Total <b>{{ $customers->count() }} customers</b></span></x-slot:subtitle>
</x-page-header>

@if (session('error'))
    <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm font-semibold text-accent-dark">{{ session('error') }}</div>
@endif

<div class="grid gap-4 lg:grid-cols-[1fr_1.4fr] lg:items-start">
    @can('customers.create')
    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="font-extrabold">Add New Customer</h2>
        <form method="POST" action="{{ route('customers.store') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="text-sm font-bold">Name <span class="text-accent">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="text-sm font-bold">Phone</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="text-sm font-bold">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="mt-1.5 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="text-sm font-bold">Address</label>
                <textarea name="address" rows="2" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('address') }}</textarea>
            </div>
            <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white hover:bg-brand-dark">
                <i data-lucide="plus" class="h-4 w-4"></i>Add Customer
            </button>
            @foreach ($errors->all() as $e)<p class="text-sm font-semibold text-accent-dark">{{ $e }}</p>@endforeach
        </form>
    </section>
    @endcan

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs font-bold text-muted">
                <tr>
                    <th class="px-5 py-2.5">Customer Name</th>
                    <th class="px-3 py-2.5">Contact</th>
                    <th class="px-3 py-2.5">Address</th>
                    <th class="px-3 py-2.5 text-center">WO Count</th>
                    <th class="px-3 py-2.5 text-center">DO Count</th>
                    <th class="px-5 py-2.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $c)
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="px-5 py-2.5 font-bold">{{ $c->name }}</td>
                        <td class="px-3 py-2.5 text-muted">{{ $c->phone ?: '-' }}</td>
                        <td class="px-3 py-2.5 text-muted text-xs max-w-xs truncate" title="{{ $c->address }}">{{ $c->address ?: '-' }}</td>
                        <td class="px-3 py-2.5 text-center font-bold text-brand">{{ $c->work_orders_count }}</td>
                        <td class="px-3 py-2.5 text-center font-bold text-emerald-700">{{ $c->delivery_orders_count }}</td>
                        <td class="px-5 py-2.5 text-right">
                            @can('customers.delete')<form method="POST" action="{{ route('customers.destroy', $c) }}" onsubmit="return confirm({{ Js::from('Delete customer ' . $c->name . '?') }})">
                                @csrf @method('DELETE')
                                <button class="rounded-lg p-1.5 text-accent hover:bg-accent-tint" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                            </form>@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-muted">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection
