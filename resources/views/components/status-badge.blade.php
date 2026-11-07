@props(['status'])
@php
    $colors = config('item_options.status_colors');
    [$bg, $fg] = $colors[$status] ?? $colors['default'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-block whitespace-nowrap rounded-md px-2.5 py-0.5 text-xs font-bold']) }}
      style="background: {{ $bg }}; color: {{ $fg }};">{{ $status }}</span>
