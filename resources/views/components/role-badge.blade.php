@props(['role'])
@php
    $found = \App\Models\Role::map()[$role] ?? null;
    [$bg, $fg] = ['superadmin' => ['#FCE8EF', '#B0104A'], 'user' => ['#E6F0F7', '#004E7A']][$role] ?? ['#EEF2F6', '#46586A'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-block whitespace-nowrap rounded-md px-2.5 py-0.5 text-xs font-bold']) }}
      style="background: {{ $bg }}; color: {{ $fg }};">{{ $found?->label ?? $role }}</span>
