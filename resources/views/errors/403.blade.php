<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <title>Access Denied - Mitra Jaringan Global</title>
</head>
<body class="flex min-h-screen items-center justify-center bg-canvas p-6 font-sans text-ink antialiased">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-sm">
        <img src="{{ asset('images/logo-mjg.png') }}" alt="Mitra Jaringan Global" class="mx-auto h-auto w-48">
        <div class="mx-auto mt-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-accent-tint text-accent">
            <i data-lucide="lock" class="h-7 w-7"></i>
        </div>
        <h1 class="mt-4 text-2xl font-extrabold">Access Denied</h1>
        <p class="mt-2 text-muted">
            Your account role{{ auth()->check() ? ' (' . auth()->user()->roleLabel() . ')' : '' }} does not have permission to access this page.
            Contact a Superadmin to update your access permissions if needed.
        </p>
        <a href="{{ url('/dashboard') }}" class="mt-6 inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-6 font-bold text-white hover:bg-brand-dark">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>Back to Dashboard
        </a>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
