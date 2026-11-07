@props(['type' => null])
@php
    $icons = config('item_options.type_icons');
    $icon = $icons[$type] ?? $icons['default'];
@endphp
<i data-lucide="{{ $icon }}" {{ $attributes->merge(['class' => 'h-[18px] w-[18px] shrink-0 text-brand']) }}></i>
