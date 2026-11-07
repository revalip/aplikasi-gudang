@component('pdf.layout', ['docTitle' => 'SALES ORDER', 'docNumber' => $so->so_number])
    <table style="width:100%; margin-bottom:12px;"><tr>
        <td style="width:50%; vertical-align:top;">
            <div class="label">Customer</div>
            <div style="font-weight:bold; font-size:12px;">{{ $so->customer->name }}</div>
            <div class="muted">{{ $so->customer->phone }}</div>
            <div class="muted">{{ $so->delivery_address ?: $so->customer->address }}</div>
        </td>
        <td style="width:50%; vertical-align:top;">
            <table style="width:100%;">
                <tr><td class="label">Date</td><td class="text-right">{{ $so->order_date->format('d F Y') }}</td></tr>
                <tr><td class="label">Payment Terms</td><td class="text-right">{{ $so->payment_terms ?: '-' }}</td></tr>
                <tr><td class="label">Status</td><td class="text-right">{{ config('sales_options.status_labels.' . $so->status) }}</td></tr>
            </table>
        </td>
    </tr></table>

    <table class="items">
        <thead><tr><th>#</th><th>Item</th><th>Type / Brand</th><th class="text-right">Qty</th><th class="text-right">Unit Price</th><th class="text-right">Subtotal</th></tr></thead>
        <tbody>
        @foreach ($so->items as $i => $line)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td style="font-weight:bold;">{{ $line->item_name }}</td>
                <td>{{ $line->type->name ?? '-' }} @if($line->merek) &middot; {{ $line->merek }} @endif</td>
                <td class="text-right">{{ $line->qty }}</td>
                <td class="text-right">Rp {{ number_format($line->unit_price, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($line->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="total-row"><td colspan="5" class="text-right">TOTAL</td><td class="text-right">Rp {{ number_format($so->total, 0, ',', '.') }}</td></tr>
        </tbody>
    </table>

    @if ($so->notes)
        <div class="box" style="margin-top:12px;"><span class="label">Notes</span><br>{{ $so->notes }}</div>
    @endif

    <table class="sign-table">
        <tr>
            <td><span class="name">{{ $so->createdBy->name ?? '(.........................)' }}</span><br><span class="muted">Sales</span></td>
            <td><span class="name">&nbsp;</span><br><span class="muted">Approved by — {{ $so->customer->name }}</span></td>
        </tr>
    </table>
@endcomponent
