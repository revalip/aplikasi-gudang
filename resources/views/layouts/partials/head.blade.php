<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0066A0">
<link rel="icon" type="image/png" href="{{ asset('images/icon-mjg.png') }}">
{{-- PWA: bisa di-install ke HP / desktop (manifest + service worker di public/) --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="apple-touch-icon" href="{{ asset('images/pwa-192.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="MJG WMS">

<script>
    /* Theme initializer (anti-flash) */
    (function() {
        try {
            var theme = localStorage.getItem('mjg_theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            if (localStorage.getItem('mjg_sidebar_collapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
    })();

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {}));
    }
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                fontFamily: { sans: ['Nunito', 'Segoe UI', 'system-ui', 'sans-serif'], mono: ['Consolas', 'Cascadia Mono', 'monospace'] },
                colors: {
                    brand: { DEFAULT: '#0066A0', dark: '#004E7A', tint: '#E6F0F7', soft: '#3FB6C9' },
                    accent: { DEFAULT: '#D4145A', dark: '#B0104A', tint: '#FCE8EF' },
                    ink: '#0F2233',
                    muted: '#526477',
                    line: '#E3E9EF',
                    canvas: '#F4F7FA',
                },
            },
        },
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0/dist/umd/lucide.min.js"></script>
<script>
    window.toggleTheme = function() {
        var isDark = document.documentElement.classList.toggle('dark');
        try {
            localStorage.setItem('mjg_theme', isDark ? 'dark' : 'light');
        } catch (e) {}
        if (window.lucide) {
            window.lucide.createIcons();
        }
    };
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<style>
    input, select, textarea { transition: border-color .15s ease, box-shadow .15s ease; }
    input:focus, select:focus, textarea:focus { outline: none; border-color: #0066A0 !important; box-shadow: 0 0 0 3px rgba(0,102,160,.18); }

    /* Collapsible Sidebar transitions */
    #main-sidebar {
        transition: margin-left 0.25s ease-in-out;
    }
    .sidebar-reopen-btn {
        display: flex;
    }
    @media (min-width: 768px) {
        html.sidebar-collapsed #main-sidebar {
            margin-left: -15rem !important; /* -240px */
        }
        .sidebar-reopen-btn {
            display: none !important;
        }
        html.sidebar-collapsed .sidebar-reopen-btn {
            display: flex !important;
        }
    }

    /* Dark Mode Global Styles & Overrides */
    html.dark {
        color-scheme: dark;
    }
    html.dark body {
        background-color: #0B1120 !important;
        color: #E2E8F0 !important;
    }
    html.dark .bg-white {
        background-color: #1E293B !important;
    }
    html.dark .bg-canvas {
        background-color: #0B1120 !important;
    }
    html.dark .bg-slate-50,
    html.dark .bg-slate-50\/50,
    html.dark .bg-slate-50\/60,
    html.dark .bg-slate-50\/70,
    html.dark .bg-slate-50\/75 {
        background-color: #172033 !important;
    }
    html.dark .bg-slate-100 {
        background-color: #27354A !important;
        color: #CBD5E1 !important;
    }
    html.dark .border-line,
    html.dark .border-slate-100,
    html.dark .border-slate-200,
    html.dark .border-slate-300,
    html.dark .divide-line,
    html.dark .divide-slate-100,
    html.dark .divide-slate-200 {
        border-color: #334155 !important;
    }
    html.dark .text-slate-800,
    html.dark .text-slate-900,
    html.dark .text-ink {
        color: #F8FAFC !important;
    }
    html.dark .text-slate-700,
    html.dark .text-slate-600 {
        color: #CBD5E1 !important;
    }
    html.dark .text-slate-500,
    html.dark .text-slate-400,
    html.dark .text-muted {
        color: #94A3B8 !important;
    }
    html.dark input,
    html.dark select,
    html.dark textarea {
        background-color: #1E293B !important;
        border-color: #475569 !important;
        color: #F8FAFC !important;
    }
    html.dark select option {
        background-color: #1E293B !important;
        color: #F8FAFC !important;
    }
    html.dark input::placeholder,
    html.dark textarea::placeholder {
        color: #64748B !important;
    }
    html.dark input:focus,
    html.dark select:focus,
    html.dark textarea:focus {
        border-color: #38BDF8 !important;
        box-shadow: 0 0 0 3px rgba(56,189,248,.25) !important;
    }

    /* Select2 Dark Mode Fix */
    html.dark .select2-container--default .select2-selection--single,
    html.dark .select2-container--default .select2-selection--multiple {
        background-color: #1E293B !important;
        border-color: #475569 !important;
        color: #F8FAFC !important;
    }
    html.dark .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #F8FAFC !important;
    }
    html.dark .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #94A3B8 !important;
    }
    html.dark .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #94A3B8 transparent transparent transparent !important;
    }
    html.dark .select2-dropdown {
        background-color: #1E293B !important;
        border-color: #475569 !important;
        color: #F8FAFC !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5) !important;
    }
    html.dark .select2-search--dropdown {
        background-color: #1E293B !important;
        padding: 6px !important;
    }
    html.dark .select2-search--dropdown .select2-search__field {
        background-color: #0F172A !important;
        border-color: #475569 !important;
        color: #F8FAFC !important;
    }
    html.dark .select2-results {
        background-color: #1E293B !important;
    }
    html.dark .select2-results__options,
    html.dark .select2-container--default .select2-results > .select2-results__options {
        background-color: #1E293B !important;
        color: #F8FAFC !important;
    }
    html.dark .select2-results__option,
    html.dark .select2-container--default .select2-results__option {
        background-color: #1E293B !important;
        color: #F8FAFC !important;
    }
    html.dark .select2-container--default .select2-results__option--highlighted[aria-selected],
    html.dark .select2-container--default .select2-results__option--selectable:hover {
        background-color: #0066A0 !important;
        color: #FFFFFF !important;
    }
    html.dark .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: #26334D !important;
        color: #38BDF8 !important;
        font-weight: bold;
    }
    html.dark .select2-container--default .select2-results__option--disabled {
        background-color: #1E293B !important;
        color: #64748B !important;
    }
    html.dark .select2-results__message {
        background-color: #1E293B !important;
        color: #94A3B8 !important;
    }

    /* Dialog Modals in Dark Mode */
    html.dark dialog {
        background-color: #1E293B !important;
        color: #F8FAFC !important;
        border-color: #334155 !important;
    }
    html.dark dialog::backdrop {
        background-color: rgba(11, 17, 32, 0.75) !important;
    }
    html.dark .hover\:bg-canvas:hover,
    html.dark .hover\:bg-slate-50:hover,
    html.dark .hover\:bg-slate-50\/50:hover,
    html.dark .hover\:bg-slate-50\/60:hover {
        background-color: #26334D !important;
    }
    html.dark .bg-brand-tint {
        background-color: rgba(0, 102, 160, 0.28) !important;
    }
    html.dark aside,
    html.dark header,
    html.dark nav {
        background-color: #131D2E !important;
        border-color: #253347 !important;
    }
    html.dark #mobile-menu-sheet > div:last-child {
        background-color: #131D2E !important;
    }
</style>
