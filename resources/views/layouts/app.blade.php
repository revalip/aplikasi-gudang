<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head')
    <title>@yield('title', 'Dashboard') - Mitra Jaringan Global</title>
    @stack('head')
</head>
<body class="bg-canvas font-sans text-ink antialiased">
@php
    $menu = [
        ['label' => 'Dashboard', 'short' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'dashboard', 'active' => 'dashboard'],
        ['label' => 'Warehouse', 'short' => 'Warehouse', 'icon' => 'boxes', 'route' => 'items.index', 'active' => ['items.index', 'items.create', 'items.edit', 'items.show', 'items.part'], 'can' => 'warehouse.view'],
        ['label' => 'Laporan', 'short' => 'Laporan', 'icon' => 'download', 'route' => 'items.export', 'active' => 'items.export', 'can' => 'warehouse.view'],
        ['label' => 'Data Barang', 'short' => 'Barang', 'icon' => 'barcode', 'route' => 'inventories.index', 'active' => 'inventories.*', 'can' => 'inventory.view'],
        ['label' => 'Rack', 'short' => 'Rack', 'icon' => 'server', 'route' => 'racks.index', 'active' => 'racks.*', 'can' => 'racks.view'],
        ['label' => 'Kategori', 'short' => 'Kategori', 'icon' => 'tags', 'route' => 'item-types.index', 'active' => 'item-types.*', 'can' => 'item-types.view'],
        ['label' => 'Purchase Order', 'short' => 'PO', 'icon' => 'shopping-cart', 'route' => 'purchasing.index', 'active' => ['purchasing.index', 'purchasing.create', 'purchasing.show'], 'can' => 'purchasing'],
        ['label' => 'Suppliers', 'short' => 'Suppliers', 'icon' => 'truck', 'route' => 'suppliers.index', 'active' => 'suppliers.index', 'can' => 'suppliers.view'],
    ];
    // Menu accessibility filtered by user permissions.
    $menu = array_values(array_filter($menu, fn ($m) => empty($m['can']) || auth()->user()->can($m['can'])));

    // Mobile: 4 primary menus in bottom bar, everything else in the grouped "Menu" sheet.
    $bottomRoutes = collect(['dashboard', 'items.index', 'purchasing.index', 'racks.index'])
        ->filter(fn ($r) => collect($menu)->contains('route', $r))->take(4)->all();
    $bottomItems = array_values(array_filter($menu, fn ($m) => in_array($m['route'], $bottomRoutes)));
    $accountItems = array_values(array_filter($menu, fn ($m) => ! in_array($m['route'], $bottomRoutes)));

    $logisticRoutes = ['items.index', 'items.export', 'inventories.index', 'racks.index', 'item-types.index'];
    $purchasingRoutes = ['purchasing.index', 'suppliers.index'];
    $salesRoutes = [];
    $adminRoutes = [];
    $userMgmtRoutes = [];
    $logisticItems = array_values(array_filter($menu, fn ($m) => in_array($m['route'], $logisticRoutes)));
    $purchasingItems = array_values(array_filter($menu, fn ($m) => in_array($m['route'], $purchasingRoutes)));
    $salesItems = array_values(array_filter($menu, fn ($m) => in_array($m['route'], $salesRoutes)));
    $adminItems = array_values(array_filter($menu, fn ($m) => in_array($m['route'], $adminRoutes)));
    $userMgmtItems = array_values(array_filter($menu, fn ($m) => in_array($m['route'], $userMgmtRoutes)));
    $userMgmtOpen = collect($userMgmtItems)->contains(fn ($m) => request()->routeIs(...(array) $m['active']));
    $topItems = array_values(array_filter($menu, fn ($m) => !in_array($m['route'], [...$logisticRoutes, ...$purchasingRoutes, ...$salesRoutes, ...$adminRoutes, ...$userMgmtRoutes])));
    $logisticOpen = collect($logisticItems)->contains(fn ($m) => request()->routeIs(...(array) $m['active']));
    $purchasingOpen = collect($purchasingItems)->contains(fn ($m) => request()->routeIs(...(array) $m['active']));
    $salesOpen = collect($salesItems)->contains(fn ($m) => request()->routeIs(...(array) $m['active']));
@endphp

<div class="flex min-h-screen flex-col">
    {{-- TOP BAR HEADER (Brand, Sidebar Toggle, Theme Switcher, Notifications, User Menu) --}}
    <header class="sticky top-0 z-30 flex h-16 w-full shrink-0 items-center justify-between border-b border-line bg-white px-4 md:px-5">
        <div class="flex items-center gap-3">
            <button type="button" id="sidebar-reopen-btn" onclick="toggleSidebar()"
                    class="sidebar-reopen-btn hidden h-9 w-9 items-center justify-center rounded-xl text-slate-600 hover:bg-canvas transition-colors"
                    aria-label="Toggle Navigation Menu" title="Open Menu (Buka Menu)">
                <i data-lucide="menu" class="h-5 w-5"></i>
            </button>
            <a href="{{ route('dashboard') }}" class="flex items-center">
                <img src="{{ asset('images/logo-mjg.png') }}" alt="Mitra Jaringan Global" class="h-8 md:h-9 w-auto">
            </a>
        </div>

        <div class="flex items-center gap-2 md:gap-3">
            {{-- Dark / Light Mode Toggle --}}
            <button type="button" onclick="toggleTheme()" class="flex h-9 w-9 items-center justify-center rounded-xl text-muted hover:bg-canvas hover:text-brand transition-colors" aria-label="Toggle theme" title="Toggle Dark/Light Mode">
                <i data-lucide="moon" class="dark:hidden h-5 w-5"></i>
                <i data-lucide="sun" class="hidden dark:block h-5 w-5 text-amber-400"></i>
            </button>

            {{-- Notifications Bell (sejajar di kanan) --}}
            <x-notification-bell />

            {{-- User Menu Dropdown --}}
            <details id="user-menu-dropdown" class="relative">
                <summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-full md:rounded-xl p-1 md:py-1 md:px-2 hover:bg-canvas transition-colors [&::-webkit-details-marker]:hidden" aria-label="User menu">
                    <x-avatar :user="auth()->user()" size="h-8 w-8" />
                    <div class="hidden text-left md:block">
                        <div class="truncate text-xs font-bold leading-tight text-ink">{{ auth()->user()->name }}</div>
                        <div class="truncate text-[11px] text-muted leading-tight">{{ auth()->user()->email }}</div>
                    </div>
                    <i data-lucide="chevron-down" class="hidden h-3.5 w-3.5 text-muted md:block"></i>
                </summary>
                <div class="absolute right-0 z-40 mt-2 w-64 overflow-hidden rounded-2xl border border-line bg-white shadow-xl">
                    <div class="border-b border-line px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-avatar :user="auth()->user()" size="h-10 w-10" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-bold text-ink">{{ auth()->user()->name }}</div>
                                <div class="truncate text-xs text-muted">{{ auth()->user()->email }}</div>
                                <span class="mt-1 inline-block rounded-md bg-brand-tint px-2 py-0.5 text-[10px] font-bold text-brand">{{ auth()->user()->roleLabel() }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-1.5">
                        <a href="{{ route('profile.edit') }}" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-canvas hover:text-brand transition-colors">
                            <i data-lucide="settings" class="h-4 w-4"></i>Setting Profile
                        </a>
                    </div>
                    <div class="border-t border-line p-1.5">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-bold text-rose-600 hover:bg-rose-50 transition-colors">
                                <i data-lucide="log-out" class="h-4 w-4"></i>Log Out
                            </button>
                        </form>
                    </div>
                </div>
            </details>
        </div>
    </header>

    {{-- MAIN WRAPPER (Collapsible Sidebar + Content) --}}
    <div class="flex min-h-[calc(100vh-4rem)] flex-1">
        {{-- DESKTOP COLLAPSIBLE SIDEBAR --}}
        <aside id="main-sidebar" class="sticky top-16 hidden h-[calc(100vh-4rem)] w-60 shrink-0 flex-col border-r border-line bg-white md:flex">
            <div class="mx-3 my-3 flex items-center gap-1.5">
                <form method="GET" action="{{ route('items.part') }}" class="min-w-0 flex-1">
                    <label class="flex h-10 items-center gap-2 rounded-xl border border-line px-2.5 text-xs text-muted focus-within:border-brand">
                        <i data-lucide="search" class="h-4 w-4 shrink-0"></i>
                        <input type="search" name="q" placeholder="Search SN, PN, item..." aria-label="Search items"
                               class="w-full border-0 bg-transparent p-0 text-xs text-ink placeholder:text-muted focus:shadow-none">
                    </label>
                </form>
                <button type="button" onclick="toggleSidebar()"
                        class="flex h-10 w-9 shrink-0 items-center justify-center rounded-xl border border-line text-slate-600 hover:bg-canvas hover:text-brand transition-colors"
                        aria-label="Collapse Menu" title="Close Menu (Tutup Menu)">
                    <i data-lucide="menu" class="h-4 w-4"></i>
                </button>
            </div>

            <nav class="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto overscroll-contain px-3 pb-3 text-sm">
                @foreach ($topItems as $m)
                    @php $on = request()->routeIs(...(array) $m['active']); @endphp
                    <a href="{{ route($m['route']) }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $on ? 'bg-brand-tint font-bold text-brand' : 'font-semibold text-slate-600 hover:bg-canvas' }}">
                        <i data-lucide="{{ $m['icon'] }}" class="h-[18px] w-[18px]"></i>{{ $m['label'] }}
                    </a>
                @endforeach

                @if ($logisticItems)
                <details {{ $logisticOpen ? 'open' : '' }} class="group">
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-slate-600 hover:bg-canvas [&::-webkit-details-marker]:hidden {{ $logisticOpen ? 'text-brand font-bold' : '' }}">
                        <i data-lucide="warehouse" class="h-[18px] w-[18px]"></i>Logistic
                        <i data-lucide="chevron-down" class="ml-auto h-4 w-4 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="ml-3 mt-0.5 flex flex-col gap-0.5 border-l border-line pl-3">
                        @foreach ($logisticItems as $m)
                            @if (in_array($m['route'], ['inventories.index', 'racks.index', 'item-types.index'])) @continue @endif
                            @php $on = request()->routeIs(...(array) $m['active']); @endphp
                            <a href="{{ route($m['route']) }}"
                               class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors {{ $on ? 'bg-brand-tint font-bold text-brand' : 'font-semibold text-slate-600 hover:bg-canvas' }}">
                                <i data-lucide="{{ $m['icon'] }}" class="h-4 w-4"></i>{{ $m['label'] }}
                            </a>
                        @endforeach

                        {{-- Submenu Settings: Part Number, Rack, Item Types --}}
                        @php
                            $settingsItems = array_values(array_filter($menu, fn ($m) => in_array($m['route'], ['inventories.index', 'racks.index', 'item-types.index'])));
                            $settingsOpen = collect($settingsItems)->contains(fn ($m) => request()->routeIs(...(array) $m['active']));
                        @endphp
                        @if (!empty($settingsItems))
                        <details {{ $settingsOpen ? 'open' : '' }} class="group/sub my-0.5">
                            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-canvas [&::-webkit-details-marker]:hidden {{ $settingsOpen ? 'text-brand font-bold' : '' }}">
                                <i data-lucide="settings" class="h-4 w-4"></i>Settings
                                <i data-lucide="chevron-down" class="ml-auto h-3.5 w-3.5 transition-transform group-open/sub:rotate-180"></i>
                            </summary>
                            <div class="ml-3 mt-0.5 flex flex-col gap-0.5 border-l-2 border-line/80 pl-2.5">
                                @foreach ($settingsItems as $sm)
                                    @php $sOn = request()->routeIs(...(array) $sm['active']); @endphp
                                    <a href="{{ route($sm['route']) }}"
                                       class="flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-xs transition-colors {{ $sOn ? 'bg-brand-tint font-bold text-brand' : 'font-semibold text-slate-500 hover:text-brand hover:bg-canvas' }}">
                                        <i data-lucide="{{ $sm['icon'] }}" class="h-3.5 w-3.5"></i>{{ $sm['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                        @endif
                    </div>
                </details>
                @endif

                @if ($purchasingItems)
                <details {{ $purchasingOpen ? 'open' : '' }} class="group">
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-slate-600 hover:bg-canvas [&::-webkit-details-marker]:hidden {{ $purchasingOpen ? 'text-brand font-bold' : '' }}">
                        <i data-lucide="shopping-cart" class="h-[18px] w-[18px]"></i>Purchasing
                        <i data-lucide="chevron-down" class="ml-auto h-4 w-4 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="ml-3 mt-0.5 flex flex-col gap-0.5 border-l border-line pl-3">
                        @foreach ($purchasingItems as $m)
                            @php
                                $on = request()->routeIs(...(array) $m['active']);
                                $isPrDisabled = ($m['route'] === 'purchase-requests.index' && !config('app.enable_purchase_request', false));
                            @endphp
                            @if ($isPrDisabled)
                                @continue
                            @endif
                            <a href="{{ route($m['route']) }}"
                               class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors {{ $on ? 'bg-brand-tint font-bold text-brand' : 'font-semibold text-slate-600 hover:bg-canvas' }}">
                                <i data-lucide="{{ $m['icon'] }}" class="h-4 w-4"></i>{{ $m['label'] }}
                            </a>
                        @endforeach
                    </div>
                </details>
                @endif

                @if ($salesItems)
                <details {{ $salesOpen ? 'open' : '' }} class="group">
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-slate-600 hover:bg-canvas [&::-webkit-details-marker]:hidden {{ $salesOpen ? 'text-brand font-bold' : '' }}">
                        <i data-lucide="trending-up" class="h-[18px] w-[18px]"></i>Sales
                        <i data-lucide="chevron-down" class="ml-auto h-4 w-4 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="ml-3 mt-0.5 flex flex-col gap-0.5 border-l border-line pl-3">
                        @foreach ($salesItems as $m)
                            @php
                                $on = request()->routeIs(...(array) $m['active']);
                                $isSoDisabled = ($m['route'] === 'sales-orders.index' && !config('app.enable_sales_order', false));
                            @endphp
                            @if ($isSoDisabled)
                                @continue
                            @endif
                            <a href="{{ route($m['route']) }}"
                               class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors {{ $on ? 'bg-brand-tint font-bold text-brand' : 'font-semibold text-slate-600 hover:bg-canvas' }}">
                                <i data-lucide="{{ $m['icon'] }}" class="h-4 w-4"></i>{{ $m['label'] }}
                            </a>
                        @endforeach
                    </div>
                </details>
                @endif

                @foreach ($adminItems as $m)
                    @php $on = request()->routeIs(...(array) $m['active']); @endphp
                    <a href="{{ route($m['route']) }}"
                       class="{{ $loop->first ? 'mt-2 border-t border-line pt-3' : '' }} flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors {{ $on ? 'bg-brand-tint font-bold text-brand' : 'font-semibold text-slate-600 hover:bg-canvas' }}">
                        <i data-lucide="{{ $m['icon'] }}" class="h-[18px] w-[18px]"></i>{{ $m['label'] }}
                    </a>
                @endforeach

                @if (config('app.enable_user_management', false) && $userMgmtItems)
                <details {{ $userMgmtOpen ? 'open' : '' }} class="group">
                    <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-slate-600 hover:bg-canvas [&::-webkit-details-marker]:hidden {{ $userMgmtOpen ? 'text-brand font-bold' : '' }}">
                        <i data-lucide="users" class="h-[18px] w-[18px]"></i>User Management
                        <i data-lucide="chevron-down" class="ml-auto h-4 w-4 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="ml-3 mt-0.5 flex flex-col gap-0.5 border-l border-line pl-3">
                        @foreach ($userMgmtItems as $m)
                            @php $on = request()->routeIs(...(array) $m['active']); @endphp
                            <a href="{{ route($m['route']) }}"
                               class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors {{ $on ? 'bg-brand-tint font-bold text-brand' : 'font-semibold text-slate-600 hover:bg-canvas' }}">
                                <i data-lucide="{{ $m['icon'] }}" class="h-4 w-4"></i>{{ $m['label'] }}
                            </a>
                        @endforeach
                    </div>
                </details>
                @endif
            </nav>
        </aside>

        {{-- MAIN CONTENT AREA --}}
        <div class="flex min-w-0 flex-1 flex-col overflow-x-clip">
            <main class="flex-1 space-y-4 p-4 pb-24 md:p-6 md:pb-6">
                @if (session('success'))
                    <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800">
                        <i data-lucide="circle-check" class="h-4 w-4"></i>{{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="flex items-center gap-2 rounded-xl border border-accent/30 bg-accent-tint px-4 py-2.5 text-sm font-semibold text-accent-dark">
                        <i data-lucide="circle-alert" class="h-4 w-4"></i>{{ session('error') }}
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</div>

{{-- MOBILE BOTTOM BAR --}}
<nav class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-5 border-t border-line bg-white pb-[env(safe-area-inset-bottom)] md:hidden">
    @foreach ($bottomItems as $m)
        @php $on = request()->routeIs(...(array) $m['active']); @endphp
        <a href="{{ route($m['route']) }}" class="flex flex-col items-center gap-0.5 py-2.5 text-[11px] {{ $on ? 'font-extrabold text-brand' : 'text-muted' }}">
            <i data-lucide="{{ $m['icon'] }}" class="h-[22px] w-[22px]"></i>{{ $m['short'] }}
        </a>
    @endforeach
    <button type="button" id="mobile-menu-btn" class="flex flex-col items-center gap-0.5 py-2.5 text-[11px] text-muted" aria-controls="mobile-menu-sheet" aria-expanded="false">
        <i data-lucide="layout-grid" class="h-[22px] w-[22px]"></i>Menu
    </button>
</nav>

{{-- MOBILE "MENU" SHEET --}}
<div id="mobile-menu-sheet" class="fixed inset-0 z-40 hidden md:hidden">
    <div id="mobile-menu-backdrop" class="absolute inset-0 bg-slate-900/40"></div>
    <div class="absolute inset-x-0 bottom-0 max-h-[80vh] overflow-y-auto overscroll-contain rounded-t-3xl bg-white px-4 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-3 shadow-2xl">
        <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-slate-300"></div>
        @php
            $mobileGroups = [
                ['Dashboard & Others', $topItems],
                ['Logistic', $logisticItems],
                ['Purchasing', $purchasingItems],
                ['Sales', $salesItems],
                ['Admin', $adminItems],
            ];
            if (config('app.enable_user_management', false)) {
                $mobileGroups[] = ['User Management', $userMgmtItems];
            }
        @endphp
        @foreach ($mobileGroups as [$groupLabel, $groupItems])
            @if (count($groupItems))
                <div class="mb-1 mt-3 text-xs font-extrabold uppercase tracking-wider text-muted">{{ $groupLabel }}</div>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($groupItems as $m)
                        @php
                            $on = request()->routeIs(...(array) $m['active']);
                            $isSoDisabled = ($m['route'] === 'sales-orders.index' && !config('app.enable_sales_order', false));
                            $isPrDisabled = ($m['route'] === 'purchase-requests.index' && !config('app.enable_purchase_request', false));
                        @endphp
                        @if ($isSoDisabled || $isPrDisabled)
                            @continue
                        @endif
                        <a href="{{ route($m['route']) }}" class="flex flex-col items-center gap-1.5 rounded-2xl px-1 py-3 text-center text-xs {{ $on ? 'bg-brand-tint font-extrabold text-brand' : 'bg-canvas font-semibold text-slate-700' }}">
                            <i data-lucide="{{ $m['icon'] }}" class="h-5 w-5 text-brand"></i>{{ $m['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>
</div>

@include('layouts.partials.camera-scanner')
@stack('scripts')
<script>
    lucide.createIcons();

    // Sidebar toggle (Desktop collapses/expands sidebar, Mobile opens/closes bottom sheet)
    window.toggleSidebar = function() {
        if (window.innerWidth < 768) {
            const sheet = document.getElementById('mobile-menu-sheet');
            const btn = document.getElementById('mobile-menu-btn');
            if (sheet) {
                const isHidden = sheet.classList.contains('hidden');
                sheet.classList.toggle('hidden', !isHidden);
                if (btn) btn.setAttribute('aria-expanded', isHidden);
                document.body.classList.toggle('overflow-hidden', isHidden);
            }
        } else {
            const isCollapsed = document.documentElement.classList.toggle('sidebar-collapsed');
            try {
                localStorage.setItem('mjg_sidebar_collapsed', isCollapsed ? '1' : '0');
            } catch(e) {}
        }
    };

    // Mobile "Menu" sheet toggle button in bottom bar
    (() => {
        const sheet = document.getElementById('mobile-menu-sheet');
        const btn = document.getElementById('mobile-menu-btn');
        if (!sheet || !btn) return;
        const set = (open) => {
            sheet.classList.toggle('hidden', !open);
            btn.setAttribute('aria-expanded', open);
            document.body.classList.toggle('overflow-hidden', open);
        };
        btn.addEventListener('click', () => set(sheet.classList.contains('hidden')));
        document.getElementById('mobile-menu-backdrop').addEventListener('click', () => set(false));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') set(false); });
    })();

    // Close user menu dropdown when clicked outside
    document.addEventListener('click', (e) => {
        const menu = document.getElementById('user-menu-dropdown');
        if (menu?.open && !menu.contains(e.target)) {
            menu.open = false;
        }
    });
</script>
</body>
</html>
