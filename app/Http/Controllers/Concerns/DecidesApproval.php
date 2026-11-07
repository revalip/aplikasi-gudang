<?php

namespace App\Http\Controllers\Concerns;

use App\Services\NotifyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Single-level approval for PR & PO — rules follow SO approval. */
trait DecidesApproval
{
    /**
     * Approve or reject a document pending approval. Document creator is notified of the result.
     *
     * @return string|null error message, or null on success
     */
    protected function decide(Request $request, Model $doc, bool $approve, string $pendingStatus, string $approvedStatus, string $label, string $link): ?string
    {
        if ($doc->status !== $pendingStatus) {
            return "Only {$label} pending approval can be " . ($approve ? 'approved.' : 'rejected.');
        }
        if ($doc->created_by === $request->user()->id && ! $request->user()->isSuperadmin()) {
            return "Cannot " . ($approve ? 'approve' : 'reject') . " your own {$label}. Maker-checker requires a different approver.";
        }

        $request->validate(
            ['note' => ($approve ? 'nullable' : 'required') . '|string|max:500'],
            ['note.required' => 'Rejection reason is required.'],
        );

        $doc->update([
            'status' => $approve ? $approvedStatus : 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_note' => $request->input('note'),
        ]);

        if ($doc->created_by) {
            NotifyService::toUser(
                $doc->created_by,
                "{$label} " . ($approve ? 'approved' : 'rejected'),
                "By {$request->user()->name}" . ($request->filled('note') ? ": {$request->input('note')}" : '.'),
                $link,
            );
        }

        return null;
    }
}
