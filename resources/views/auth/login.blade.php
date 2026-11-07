<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <title>Login - Mitra Jaringan Global</title>
</head>
<body class="bg-white font-sans text-ink antialiased">
<div class="flex min-h-screen">
    <div class="flex w-full flex-col px-6 py-8 sm:px-12 lg:w-[46%] lg:px-16">
        <img src="{{ asset('images/logo-mjg.png') }}" alt="Mitra Jaringan Global" class="h-auto w-56">

        <form method="POST" action="{{ route('login.attempt') }}" class="my-auto w-full max-w-sm space-y-5 py-10">
            @csrf
            <div>
                <h1 class="text-3xl font-extrabold">Welcome</h1>
                <p class="mt-1.5 text-muted">Sign in to manage warehouse inventory.</p>
            </div>

            @if ($errors->any())
                <div class="flex items-center gap-2 rounded-xl border border-accent/30 bg-accent-tint px-3 py-2.5 text-sm font-semibold text-accent-dark">
                    <i data-lucide="circle-alert" class="h-4 w-4"></i>{{ $errors->first() }}
                </div>
            @endif

            <div class="space-y-1.5">
                <label for="email" class="text-sm font-bold">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="h-12 w-full rounded-xl border border-slate-300 px-4">
            </div>
            <div class="space-y-1.5">
                <label for="password" class="text-sm font-bold">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                       class="h-12 w-full rounded-xl border border-slate-300 px-4">
            </div>
            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="remember" class="h-4 w-4 accent-brand"> Remember me
            </label>
            <button type="submit" class="h-12 w-full rounded-xl bg-brand font-bold text-white transition-colors hover:bg-brand-dark active:scale-[.99]">
                Sign In
            </button>
        </form>

        <p class="text-sm text-muted">&copy; {{ date('Y') }} Mitra Jaringan Global</p>
    </div>

    <div class="hidden flex-1 flex-col justify-center gap-9 bg-brand px-16 py-16 lg:flex">
        <div>
            <h2 class="text-4xl font-extrabold leading-tight text-white">All warehouse inventory,<br>in one place.</h2>
            <ul class="mt-6 space-y-3 text-brand-tint">
                <li class="flex items-center gap-3"><i data-lucide="check" class="h-5 w-5 text-white"></i>Track inbound &amp; outbound items by location</li>
                <li class="flex items-center gap-3"><i data-lucide="check" class="h-5 w-5 text-white"></i>Search inventory by name, type, brand, status</li>
                <li class="flex items-center gap-3"><i data-lucide="check" class="h-5 w-5 text-white"></i>Export reports to Excel</li>
            </ul>
        </div>
        <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl shadow-black/20">
            <div class="mb-3 font-extrabold">Storage Locations</div>
            @foreach (['Lantai 1', 'Lantai 2', 'Lantai 3', 'Stuff In', 'Rak Barang Rusak'] as $loc)
                <div class="flex items-center gap-3 border-b border-line py-2 text-sm last:border-0">
                    <i data-lucide="{{ $loc === 'Rak Barang Rusak' ? 'triangle-alert' : ($loc === 'Stuff In' ? 'truck' : 'layers') }}"
                       class="h-4 w-4 {{ $loc === 'Rak Barang Rusak' ? 'text-accent' : 'text-brand' }}"></i>{{ $loc }}
                </div>
            @endforeach
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
