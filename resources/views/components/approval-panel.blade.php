{{-- PR/PO approval panel, matches design of approval panel on SO show page. --}}
@props(['doc', 'pending', 'approveUrl', 'rejectUrl'])
@php $canApprove = (auth()->user()->can('purchasing.approve') && $doc->created_by !== auth()->id()) || auth()->user()->isSuperadmin(); @endphp
<section {{ $attributes->merge(['class' => 'rounded-2xl bg-white p-5 text-sm shadow-sm']) }}>
    <h2 class="mb-3 font-extrabold">Approval</h2>
    @if ($doc->approved_at)
        <p><b>{{ $doc->status === 'rejected' ? 'Rejected' : 'Approved' }}</b> by {{ $doc->approvedBy->name ?? '-' }}</p>
        <p class="text-xs text-muted">{{ $doc->approved_at->format('d/m/Y H:i') }}</p>
        @if ($doc->approval_note)<p class="mt-2 rounded-lg bg-slate-50 p-2.5">{{ $doc->approval_note }}</p>@endif
    @elseif ($doc->status === $pending && $canApprove)
        <form method="POST" class="space-y-2">
            @csrf @method('PATCH')
            <textarea name="note" rows="2" placeholder="Notes (required if rejecting)" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
            @error('note')<p class="text-xs font-semibold text-accent-dark">{{ $message }}</p>@enderror
            <div class="flex gap-2">
                <button formaction="{{ $approveUrl }}" class="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 text-sm font-bold text-white hover:bg-emerald-700">
                    <i data-lucide="check" class="h-4 w-4"></i>Approve
                </button>
                <button formaction="{{ $rejectUrl }}" class="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-accent/30 bg-white px-3 text-sm font-bold text-accent-dark hover:bg-accent-tint">
                    <i data-lucide="x" class="h-4 w-4"></i>Reject
                </button>
            </div>
        </form>
    @elseif ($doc->status === $pending)
        <p class="text-muted">Waiting for approval from a user with Approval PR &amp; PO access{{ ($doc->created_by === auth()->id() && !auth()->user()->isSuperadmin()) ? ' (other than the creator)' : '' }}.</p>
    @else
        <p class="text-muted">Created prior to approval requirement.</p>
    @endif
</section>
