@props(['title', 'icon' => null])
<div class="flex flex-col gap-3 rounded-2xl bg-white px-5 py-4 shadow-sm md:flex-row md:items-center md:justify-between">
    <div class="min-w-0">
        <div class="flex items-center gap-3">
            @if ($icon)
                <i data-lucide="{{ $icon }}" class="h-6 w-6 text-brand"></i>
            @endif
            <h1 class="text-2xl font-extrabold">{{ $title }}</h1>
        </div>
        @isset($subtitle)
            <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-sm text-brand">{{ $subtitle }}</div>
        @endisset
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
