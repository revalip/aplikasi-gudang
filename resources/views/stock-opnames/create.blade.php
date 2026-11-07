@extends('layouts.app')
@section('title', 'Mulai Stock Opname Baru')

@section('content')
<div class="mx-auto max-w-2xl">
    <x-page-header title="Mulai Stock Opname Baru" icon="clipboard-plus">
        <x-slot:subtitle>
            Membuat lembar kerja Stock Opname dan mengambil snapshot stok sistem saat ini
        </x-slot:subtitle>
        <x-slot:actions>
            <a href="{{ route('stock-opnames.index') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-canvas">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>Kembali
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="mt-4 rounded-2xl bg-white p-6 shadow-sm border border-line">
        <form method="POST" action="{{ route('stock-opnames.store') }}" class="space-y-5">
            @csrf

            <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-4 text-sm text-blue-900">
                <div class="flex gap-2.5">
                    <i data-lucide="info" class="h-5 w-5 shrink-0 text-blue-600 mt-0.5"></i>
                    <div>
                        <div class="font-bold">Standar Prosedur Stock Opname:</div>
                        <ul class="mt-1 list-disc list-inside space-y-1 text-xs text-blue-800">
                            <li>Sistem akan membuat snapshot jumlah stok buku (Qty Sistem) per Part Number di gudang terpilih.</li>
                            <li>Setelah sesi opname dibuat, Anda dapat menghitung fisik menggunakan barcode scanner, input manual, atau upload Excel count sheet.</li>
                            <li>Perubahan saldo stok aktual di warehouse baru akan dieksekusi setelah Anda menekan tombol <b>Finalisasi</b>.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">
                    Nomor Opname (Auto Generated)
                </label>
                <input type="text" value="{{ $suggestedNumber }}" disabled class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-sm font-mono font-bold text-slate-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">
                    Pilih Gudang / Lokasi <span class="text-accent">*</span>
                </label>
                <select name="location_id" required class="h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm font-medium focus:border-brand focus:ring-1 focus:ring-brand">
                    <option value="">-- Pilih Lokasi Gudang --</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" {{ old('location_id') == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }} ({{ $loc->items()->where('status', 'In Stock')->count() }} unit in stock)
                        </option>
                    @endforeach
                </select>
                @error('location_id')
                    <div class="mt-1 text-xs font-semibold text-accent">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">
                    Tanggal Pelaksanaan Opname <span class="text-accent">*</span>
                </label>
                <input type="date" name="opname_date" value="{{ old('opname_date', now()->format('Y-m-d')) }}" required class="h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm font-medium focus:border-brand focus:ring-1 focus:ring-brand">
                @error('opname_date')
                    <div class="mt-1 text-xs font-semibold text-accent">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">
                    Mode Awal Perhitungan Fisik
                </label>
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-3 hover:bg-slate-50 [&:has(input:checked)]:border-brand [&:has(input:checked)]:bg-brand-tint/30">
                        <input type="radio" name="count_mode" value="zero" checked class="mt-1 text-brand focus:ring-brand">
                        <div>
                            <div class="text-sm font-bold text-slate-800">Hitung dari Nol (Blind Count)</div>
                            <div class="text-xs text-slate-500">Qty fisik diawali 0. Petugas menghitung dan scan barang satu demi satu dari gudang.</div>
                        </div>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-3 hover:bg-slate-50 [&:has(input:checked)]:border-brand [&:has(input:checked)]:bg-brand-tint/30">
                        <input type="radio" name="count_mode" value="snapshot" class="mt-1 text-brand focus:ring-brand">
                        <div>
                            <div class="text-sm font-bold text-slate-800">Salin dari Sistem (Pre-filled)</div>
                            <div class="text-xs text-slate-500">Qty fisik diawali sama dengan Qty sistem. Petugas hanya mengoreksi item yang selisih.</div>
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-600 mb-1.5">
                    Catatan / Keterangan Tambahan
                </label>
                <textarea name="notes" rows="3" placeholder="Contoh: Stock opname berkala akhir bulan Oktober 2026 oleh Tim Logistik." class="w-full rounded-xl border border-slate-300 p-3 text-sm focus:border-brand focus:ring-1 focus:ring-brand">{{ old('notes') }}</textarea>
                @error('notes')
                    <div class="mt-1 text-xs font-semibold text-accent">{{ $message }}</div>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-line">
                <a href="{{ route('stock-opnames.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-700 hover:bg-canvas">
                    Batal
                </a>
                <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand px-6 text-sm font-bold text-white hover:bg-brand-dark shadow-sm">
                    <i data-lucide="play" class="h-4 w-4"></i>Mulai Proses Opname
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

