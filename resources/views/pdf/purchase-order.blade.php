@component('pdf.layout', ['docTitle' => 'PURCHASE ORDER', 'docNumber' => $po->po_number])
    <table style="width:100%; margin-bottom:12px;"><tr>
        <td style="width:50%; vertical-align:top;">
            <div class="label">Supplier</div>
            <div style="font-weight:bold; font-size:12px;">{{ $po->supplier->name }}</div>
            <div class="muted">{{ $po->supplier->phone }}</div>
            <div class="muted">{{ $po->supplier->address }}</div>
        </td>
        <td style="width:50%; vertical-align:top;">
            <table style="width:100%;">
                <tr><td class="label">PO Date</td><td class="text-right">{{ $po->order_date->format('d F Y') }}</td></tr>
                @if ($po->expected_date)<tr><td class="label">Expected Date</td><td class="text-right">{{ $po->expected_date->format('d F Y') }}</td></tr>@endif
                <tr><td class="label">Status</td><td class="text-right">{{ config('purchasing_options.status_labels.' . $po->status) }}</td></tr>
                @if ($po->purchaseRequest)<tr><td class="label">From PR</td><td class="text-right">{{ $po->purchaseRequest->pr_number }}</td></tr>@endif
            </table>
        </td>
    </tr></table>

    <table class="items">
        <thead><tr><th>#</th><th>Item / Description</th><th>Type / Brand</th><th class="text-right">Qty</th><th class="text-right">Unit Price</th><th class="text-right">Subtotal</th></tr></thead>
        <tbody>
        @php $grand = 0; @endphp
        @foreach ($po->items as $i => $line)
            @php $sub = $line->qty_ordered * $line->unit_price; $grand += $sub; @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    <div style="font-weight:bold;">{{ $line->item_name }}</div>
                    @if ($line->description)<div class="muted" style="font-size:10px;">{{ $line->description }}</div>@endif
                    @if ($line->part_number)<div class="muted" style="font-family:monospace; font-size:10px;">PN: {{ $line->part_number }}</div>@endif
                </td>
                <td>
                    <div>{{ $line->type->name ?? '-' }} @if($line->merek) &middot; {{ $line->merek }} @endif</div>
                    @if ($line->warranty)<div class="muted" style="font-size:10px;">Warranty: {{ \App\Support\Warranty::label($line->warranty) }}</div>@endif
                </td>
                <td class="text-right">{{ $line->qty_ordered }} {{ $line->unit ?: 'pcs' }}</td>
                <td class="text-right">Rp {{ number_format($line->unit_price, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($sub, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="total-row"><td colspan="5" class="text-right">TOTAL</td><td class="text-right">Rp {{ number_format($grand, 0, ',', '.') }}</td></tr>
        </tbody>
    </table>

    @if ($po->notes)
        <div class="box" style="margin-top:12px;"><span class="label">Notes</span><br>{{ $po->notes }}</div>
    @endif

    <table class="sign-table">
        <tr>
            <td><span class="name">{{ $po->createdBy->name ?? '(.........................)' }}</span><br><span class="muted">Purchasing</span></td>
            <td><span class="name">&nbsp;</span><br><span class="muted">{{ $po->supplier->name }}</span></td>
        </tr>
    </table>
@endcomponent
