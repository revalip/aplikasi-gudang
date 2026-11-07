<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DecidesApproval;
use App\Models\PurchaseRequest;
use App\Models\SalesOrder;
use App\Services\NotifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRequestController extends Controller
{
    use DecidesApproval;

    public function index(Request $request)
    {
        if (! config('app.enable_purchase_request', false)) {
            return redirect()->route('purchasing.index')->with('info', 'Purchase Request module is currently disabled.');
        }

        $query = PurchaseRequest::with('salesOrder')->withCount('items')->withSum('items', 'qty');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('purchase-requests.index', [
            'requests' => $query->latest('id')->paginate(15)->withQueryString(),
            'statuses' => array_keys(config('purchasing_options.pr_status_labels')),
        ]);
    }

    /** Created from SO page: shortage rows become PR items. */
    public function store(Request $request, SalesOrder $salesOrder)
    {
        if (! in_array($salesOrder->status, ['approved', 'processing'], true)) {
            return back()->with('error', 'PR can only be created from an approved SO.');
        }
        if ($salesOrder->purchaseRequests()->active()->exists()) {
            return back()->with('error', 'This SO still has an active Purchase Request (not yet fully received).');
        }

        $salesOrder->load('items');
        $short = $salesOrder->items->filter(fn ($l) => $l->shortage > 0);

        if ($short->isEmpty()) {
            return back()->with('error', 'Stock is sufficient for all items in this SO, no PR needed.');
        }

        $pr = DB::transaction(function () use ($salesOrder, $short, $request) {
            $pr = PurchaseRequest::create([
                'pr_number' => PurchaseRequest::nextNumber(),
                'sales_order_id' => $salesOrder->id,
                'status' => 'submitted',
                'notes' => "Stock shortage for {$salesOrder->so_number}",
                'created_by' => $request->user()->id,
            ]);

            foreach ($short as $line) {
                $pr->items()->create([
                    'type_id' => $line->type_id,
                    'item_name' => $line->item_name,
                    'merek' => $line->merek,
                    'qty' => $line->shortage,
                ]);
            }

            return $pr;
        });

        NotifyService::toPermission('purchasing.approve', "PR {$pr->pr_number} pending approval",
            "Stock shortage for {$salesOrder->so_number}", route('purchase-requests.show', $pr), $request->user()->id);

        return back()->with('success', "PR {$pr->pr_number} created ({$short->count()} shortage items) and pending approval.");
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        if (! config('app.enable_purchase_request', false)) {
            return redirect()->route('purchasing.index')->with('info', 'Purchase Request module is currently disabled.');
        }

        $purchaseRequest->load('salesOrder.customer', 'items.type', 'purchaseOrders', 'createdBy', 'approvedBy');

        return view('purchase-requests.show', ['pr' => $purchaseRequest]);
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest)
    {
        $pr = $purchaseRequest;
        $error = $this->decide($request, $pr, true, 'submitted', 'open', "PR {$pr->pr_number}", route('purchase-requests.show', $pr));
        if ($error) {
            return back()->with('error', $error);
        }

        NotifyService::toPermission('purchasing', "PR {$pr->pr_number} approved", 'Ready for Purchase Order creation.',
            route('purchase-requests.show', $pr), $request->user()->id);

        return back()->with('success', "PR {$pr->pr_number} approved, forwarded to Purchasing.");
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        $pr = $purchaseRequest;
        $error = $this->decide($request, $pr, false, 'submitted', 'open', "PR {$pr->pr_number}", route('purchase-requests.show', $pr));

        return $error ? back()->with('error', $error) : back()->with('success', "PR {$pr->pr_number} rejected.");
    }

    public function cancel(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['submitted', 'open'], true)) {
            return back()->with('error', 'Only pending PRs can be cancelled.');
        }

        $purchaseRequest->update(['status' => 'cancelled']);

        return back()->with('success', "PR {$purchaseRequest->pr_number} cancelled.");
    }
}
