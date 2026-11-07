<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Services\DocxTemplateService;
use App\Services\PdfRenderService;
use App\Services\ActivityLogger;
use App\Services\DocumentArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = SalesOrder::with(['customer', 'items']);

        if ($request->filled('status')) {
            $query->whereIn('status', explode(',', $request->status));
        }
        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('so_number', 'like', '%' . $request->q . '%')
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%' . $request->q . '%'));
            });
        }

        return view('sales-orders.index', [
            'orders' => $query->latest('order_date')->latest('id')->paginate(15)->withQueryString(),
            'statuses' => config('sales_options.statuses'),
        ]);
    }

    public function create()
    {
        // Stock item catalog: 1 line = 1 combination of name + type + brand, with In Stock count.
        $catalog = Item::with('type')
            ->selectRaw("item_name, type_id, merek, count(*) as total, sum(case when status in ('Available', 'In Stock') and reserved_sales_order_item_id is null then 1 else 0 end) as ready")
            ->groupBy('item_name', 'type_id', 'merek')
            ->orderBy('item_name')
            ->get()
            ->map(fn ($i) => [
                'item_name' => $i->item_name,
                'type_id' => $i->type_id,
                'type' => $i->type?->name,
                'merek' => $i->merek,
                'ready' => (int) $i->ready,
            ])->values();

        return view('sales-orders.create', [
            'customers' => Customer::orderBy('name')->get(),
            'catalog' => $catalog,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_date' => 'required|date',
            'delivery_address' => 'nullable|string',
            'payment_terms' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string',
            'items.*.type_id' => 'nullable|exists:item_types,id',
            'items.*.merek' => 'nullable|string',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ], [
            'required' => ':attribute is required.',
            'items.required' => 'Please add at least 1 item.',
            'items.*.item_name.required' => 'Select an item for each row.',
            'items.*.qty.required' => 'Quantity is required.',
            'items.*.unit_price.required' => 'Unit price is required.',
        ], [
            'customer_id' => 'Customer', 'order_date' => 'Order Date',
        ]);

        $submitNow = $request->boolean('submit_now');

        $order = DB::transaction(function () use ($validated, $request, $submitNow) {
            $order = SalesOrder::create([
                'so_number' => SalesOrder::nextNumber(),
                'customer_id' => $validated['customer_id'],
                'order_date' => $validated['order_date'],
                'delivery_address' => $validated['delivery_address'] ?? null,
                'payment_terms' => $validated['payment_terms'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => $submitNow ? 'submitted' : 'draft',
                'submitted_at' => $submitNow ? now() : null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $line) {
                $order->items()->create($line);
            }

            return $order;
        });

        return redirect()->route('sales-orders.show', $order)
            ->with('success', $submitNow ? "SO {$order->so_number} created and submitted for approval." : "SO {$order->so_number} saved as draft.");
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load('customer', 'items.type', 'createdBy', 'approvedBy', 'deliveryOrders', 'purchaseRequests.purchaseOrders', 'workOrders');

        return view('sales-orders.show', ['so' => $salesOrder]);
    }

    public function print(SalesOrder $salesOrder, DocxTemplateService $docx)
    {
        $salesOrder->load('customer', 'items.type', 'createdBy');
        $so = $salesOrder;

        $values = [
            'so_number' => $so->so_number, 'customer_name' => $so->customer->name,
            'customer_phone' => $so->customer->phone, 'delivery_address' => $so->delivery_address ?: $so->customer->address,
            'order_date' => $so->order_date->format('d F Y'), 'payment_terms' => $so->payment_terms ?: '-',
            'status_label' => config('sales_options.status_labels.' . $so->status),
            'grand_total' => 'Rp ' . number_format($so->total, 0, ',', '.'),
            'notes' => $so->notes ?: '-', 'sales_name' => $so->createdBy->name ?? '',
        ];
        $rows = $so->items->map(fn ($l, $i) => [
            'no' => $i + 1, 'name' => $l->item_name,
            'type_merek' => trim(($l->type->name ?? '-') . ($l->merek ? " · {$l->merek}" : '')),
            'qty' => $l->qty, 'price' => 'Rp ' . number_format($l->unit_price, 0, ',', '.'),
            'subtotal' => 'Rp ' . number_format($l->subtotal, 0, ',', '.'),
        ])->all();

        $path = $docx->render(resource_path('doc-templates/so_template.docx'), $values, $rows);

        ActivityLogger::log('print', "Print SO (Word) {$so->so_number}", $so, ['format' => 'Word']);

        DocumentArchive::storeFile($path, "{$so->so_number}.docx", $so);

        return response()->download($path, "{$so->so_number}.docx")->deleteFileAfterSend();
    }

    public function printPdf(SalesOrder $salesOrder, PdfRenderService $pdf)
    {
        $salesOrder->load('customer', 'items.type', 'createdBy');

        ActivityLogger::log('print', "Print SO (PDF) {$salesOrder->so_number}", $salesOrder, ['format' => 'PDF']);

        return $pdf->render('pdf.sales-order', ['so' => $salesOrder], "{$salesOrder->so_number}.pdf", archiveFor: $salesOrder);
    }

    public function submit(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== 'draft') {
            return back()->with('error', 'Only Draft SOs can be submitted.');
        }

        $salesOrder->update(['status' => 'submitted', 'submitted_at' => now()]);

        return back()->with('success', "SO {$salesOrder->so_number} submitted, awaiting approval.");
    }

    public function cancel(SalesOrder $salesOrder)
    {
        if (! in_array($salesOrder->status, ['draft', 'submitted'], true)) {
            return back()->with('error', 'This SO has already been processed and cannot be cancelled.');
        }

        $salesOrder->update(['status' => 'cancelled']);

        return back()->with('success', "SO {$salesOrder->so_number} cancelled.");
    }

    public function approve(Request $request, SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== 'submitted') {
            return back()->with('error', 'Only SOs pending approval can be approved.');
        }
        if ($salesOrder->created_by === $request->user()->id && ! $request->user()->isSuperadmin()) {
            return back()->with('error', 'Cannot approve your own SO. Maker-checker requires a different approver.');
        }

        $request->validate(['note' => 'nullable|string|max:500']);

        $salesOrder->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_note' => $request->input('note'),
        ]);

        return back()->with('success', "SO {$salesOrder->so_number} approved.");
    }

    public function reject(Request $request, SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== 'submitted') {
            return back()->with('error', 'Only SOs pending approval can be rejected.');
        }
        if ($salesOrder->created_by === $request->user()->id && ! $request->user()->isSuperadmin()) {
            return back()->with('error', 'Cannot reject your own SO. Maker-checker requires a different approver.');
        }

        $request->validate(['note' => 'required|string|max:500'], ['note.required' => 'Rejection reason is required.']);

        $salesOrder->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_note' => $request->input('note'),
        ]);

        return back()->with('success', "SO {$salesOrder->so_number} rejected.");
    }
}
