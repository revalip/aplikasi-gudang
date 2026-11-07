@props(['openUp' => false, 'alignLeft' => false])
@php
    $notifs = auth()->user()->notifications()->limit(10)->get();
    $unread = $notifs->whereNull('read_at')->count();
    $panelPos = ($openUp ? 'bottom-full mb-2' : 'mt-2') . ' ' . ($alignLeft ? 'left-0' : 'right-0');
@endphp
<details class="relative">
    <summary class="relative flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-full text-slate-600 hover:bg-canvas [&::-webkit-details-marker]:hidden" aria-label="Notifications">
        <i data-lucide="bell" class="h-[18px] w-[18px]"></i>
        @if ($unread > 0)
            <span class="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-bold text-white">{{ $unread }}</span>
        @endif
    </summary>
    <div class="absolute {{ $panelPos }} z-30 w-[min(20rem,90vw)] overflow-hidden rounded-2xl border border-line bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-line px-4 py-2.5">
            <span class="font-bold">Notifications</span>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="text-xs font-bold text-brand hover:underline">Mark all as read</button>
                </form>
            @endif
        </div>
        <div class="max-h-96 overflow-y-auto">
            @forelse ($notifs as $n)
                <a href="{{ $n->link ?: '#' }}" class="block border-b border-slate-100 px-4 py-2.5 text-sm last:border-0 hover:bg-canvas {{ $n->read_at ? '' : 'bg-brand-tint/40' }}">
                    <div class="font-bold">{{ $n->title }}</div>
                    @if ($n->body)<div class="text-xs text-muted">{{ $n->body }}</div>@endif
                    <div class="mt-0.5 text-[10px] text-muted">{{ $n->created_at->diffForHumans() }}</div>
                </a>
            @empty
                <p class="px-4 py-6 text-center text-sm text-muted">No notifications yet.</p>
            @endforelse
        </div>
    </div>
</details>
