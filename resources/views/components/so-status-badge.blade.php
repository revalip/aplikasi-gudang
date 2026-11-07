@props(['status'])
@php
    $colors = config('sales_options.status_colors');
    $labels = config('sales_options.status_labels');
    [$bg, $fg] = $colors[$status] ?? $colors['default'];
    $label = $labels[$status] ?? $status;
@endphp
<span {{ $attributes->merge(['class' => 'inline-block whitespace-nowrap rounded-md px-2.5 py-0.5 text-xs font-bold']) }}
      style="background: {{ $bg }}; color: {{ $fg }};">{{ $label }}</span>
