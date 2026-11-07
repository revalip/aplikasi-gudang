@extends('layouts.app')
@php $editing = $role->exists; $checked = old('permissions', $role->permissions ?? []); @endphp
@section('title', $editing ? 'Edit Role' : 'Add Role')

@section('content')
<x-page-header :title="$editing ? 'Edit Role' : 'Add Role'" icon="shield-check">
    <x-slot:subtitle><span class="text-muted">Tick what this role is allowed to do. Anything not ticked is not allowed.</span></x-slot:subtitle>
</x-page-header>

@php $input = 'mt-1.5 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm'; @endphp

<form method="POST" action="{{ $editing ? route('roles.update', $role) : route('roles.store') }}" class="max-w-4xl space-y-4">
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-xl border border-accent/30 bg-accent-tint px-4 py-3 text-sm text-accent-dark">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="label" class="text-sm font-bold">Role name <span class="text-accent">*</span></label>
                <input id="label" name="label" value="{{ old('label', $role->label) }}" required @readonly($editing && $role->is_system) class="{{ $input }}">
            </div>
            <div>
                <label for="description" class="text-sm font-bold">Description</label>
                <input id="description" name="description" value="{{ old('description', $role->description) }}" class="{{ $input }}">
            </div>
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="mb-1 font-extrabold">Permissions</h2>
        <p class="mb-4 text-sm text-muted">Dashboard is always accessible. Everything else follows the ticks below.</p>
        <x-permission-grid :crud="$crud" :workflow="$workflow" :checked="$checked" />
    </section>

    <div class="flex gap-2">
        <button class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-6 font-bold text-white hover:bg-brand-dark"><i data-lucide="save" class="h-4 w-4"></i>{{ $editing ? 'Save Changes' : 'Save Role' }}</button>
        <a href="{{ route('roles.index') }}" class="flex h-11 items-center rounded-xl px-5 font-semibold text-muted hover:bg-white">Cancel</a>
    </div>
</form>
@endsection
