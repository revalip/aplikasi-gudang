@props(['user', 'size' => 'h-9 w-9'])
@if ($user->photo_path)
    <img src="{{ route('users.photo', $user) }}" alt="{{ $user->name }}" loading="lazy"
         {{ $attributes->merge(['class' => "{$size} shrink-0 rounded-full object-cover"]) }}>
@else
    <span {{ $attributes->merge(['class' => "flex {$size} shrink-0 items-center justify-center rounded-full bg-brand-tint font-extrabold text-brand"]) }}>{{ strtoupper(substr($user->name ?? 'A', 0, 1)) }}</span>
@endif
