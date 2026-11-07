<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>DELIVERY ORDER - {{ $do->do_number }}</title>
<style>
    @page {
        margin: 28px 36px 28px 36px;
        size: a4 portrait;
    }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 8.5pt;
        color: #000;
        line-height: 1.35;
        margin: 0;
        padding: 0;
    }
    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4px;
    }
    .company-title {
        color: #C62828;
        font-weight: bold;
        font-size: 11pt;
        letter-spacing: 0.3px;
        margin-top: 4px;
    }
    .company-tagline {
        color: #455A64;
        font-size: 8.5pt;
        font-style: italic;
        margin-top: 1px;
    }
    .company-address {
        font-size: 8pt;
        line-height: 1.35;
        color: #333333;
        text-align: right;
    }
    .doc-title-container {
        text-align: center;
        margin-top: 22px;
        margin-bottom: 18px;
    }
    .doc-title {
        font-size: 13pt;
        font-weight: bold;
        letter-spacing: 1px;
    }
    .doc-number {
        font-size: 9.5pt;
        margin-top: 3px;
    }
    .meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
        font-size: 8.5pt;
    }
    .meta-table td {
        padding: 2.5px 0;
        vertical-align: top;
    }
    .meta-label {
        width: 125px;
    }
    .meta-colon {
        width: 15px;
    }
    .meta-val {
        line-height: 1.35;
    }
    table.do-items {
        width: 100%;
        border-collapse: collapse;
        border: 1.5px solid #000;
        font-size: 8.5pt;
    }
    table.do-items th, table.do-items td {
        border: 1px solid #000;
        padding: 6px 8px;
        vertical-align: top;
    }
    table.do-items th {
        background-color: #F8F9FA;
        font-weight: bold;
        text-align: center;
        font-size: 9pt;
    }
    .sign-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 40px;
        font-size: 8.5pt;
    }
    .sign-table td {
        width: 50%;
        text-align: center;
        vertical-align: top;
    }
    .sign-space {
        height: 65px;
    }
    .footer-notes {
        margin-top: 30px;
        font-size: 8pt;
        font-family: 'DejaVu Sans Mono', monospace;
        line-height: 1.4;
    }
    .footer-page {
        margin-top: 25px;
        font-size: 8.5pt;
    }
</style>
</head>
<body>
@php
    $so = $do->salesOrder;
    $wo = $do->workOrder;
@endphp

{{-- Header Kop Surat --}}
<table class="header-table">
    <tr>
        <td style="width: 55%; vertical-align: top;">
            @if (!empty($logoBase64))
                <img src="{{ $logoBase64 }}" style="height: 48px; width: auto;" alt="Logo"><br>
            @endif
            <div class="company-title">PT. MITRA JARINGAN GLOBAL</div>
            <div class="company-tagline">Manage Your Network Problem</div>
        </td>
        <td style="width: 45%; vertical-align: top;">
            <div class="company-address">
                Jl. Bahagia Raya, No. 3-4, RT. 006/003<br>
                Abadijaya, Sukmajaya, Depok<br>
                Jawa Barat, 16417<br>
                +6221-77831954<br>
                www.mjg.co.id
            </div>
        </td>
    </tr>
</table>

{{-- Title & Number --}}
<div class="doc-title-container">
    <div class="doc-title">DELIVERY ORDER</div>
    <div class="doc-number">No. {{ $do->do_number }}</div>
</div>

{{-- Metadata --}}
<table class="meta-table">
    <tr>
        <td class="meta-label">Shipping Date</td>
        <td class="meta-colon">:</td>
        <td class="meta-val">{{ optional($do->delivered_at)->format('d F Y') ?: '' }}</td>
    </tr>
    <tr>
        <td class="meta-label">Received Date</td>
        <td class="meta-colon">:</td>
        <td class="meta-val"></td>
    </tr>
    <tr>
        <td class="meta-label">Customer P.O</td>
        <td class="meta-colon">:</td>
        <td class="meta-val">{{ $wo?->ref_number ?: ($so?->payment_terms ?: '-') }}</td>
    </tr>
    <tr>
        <td class="meta-label">Project Name</td>
        <td class="meta-colon">:</td>
        <td class="meta-val">{{ $wo?->subject ?: ($so?->notes ?: '-') }}</td>
    </tr>
    <tr>
        <td class="meta-label">Ship To</td>
        <td class="meta-colon">:</td>
        <td class="meta-val">
            <div style="font-weight: bold;">{{ $wo?->ref_company ?: ($wo?->cust_name ?: ($so?->customer->name ?? '-')) }}</div>
            @php
                $shipAddress = $wo?->cust_address ?: ($so?->delivery_address ?: ($so?->customer->address ?? ''));
            @endphp
            @if ($shipAddress && $shipAddress !== '-')
                <div style="white-space: pre-line; line-height: 1.35; margin-top: 2px;">{{ $shipAddress }}</div>
            @endif
        </td>
    </tr>
</table>

{{-- Table of Items --}}
<table class="do-items">
    <thead>
        <tr>
            <th style="width: 6%;">No.</th>
            <th style="width: 18%;">Part Number</th>
            <th style="width: 31%;">Description</th>
            <th style="width: 8%;">Qty</th>
            <th style="width: 37%;">Serial Number</th>
        </tr>
    </thead>
    <tbody>
        @php
            $grouped = $do->items->groupBy(function ($item) {
                return $item->item->part_number ?: $item->item->item_name;
            });
        @endphp
        @forelse ($grouped as $pn => $lines)
            @php
                $first = $lines->first();
                $qty = $lines->count();
                $serials = $lines->pluck('item.serial_number')->filter()->values();
                $desc = $first->workOrderItem?->sow_explanation
                    ?: ($first->workOrderItem?->description
                        ?: ($first->item->notes
                            ?: trim(($first->item->type?->name ?? '') . ' ' . $first->item->item_name . ' ' . ($first->item->merek ?? ''))));
            @endphp
            <tr>
                <td style="text-align: center; vertical-align: top;">{{ $loop->iteration }}</td>
                <td style="text-align: center; vertical-align: top; font-weight: bold;">{{ $pn !== '' ? $pn : '-' }}</td>
                <td style="text-align: left; vertical-align: top; line-height: 1.35;">
                    {!! nl2br(e($desc)) !!}
                </td>
                <td style="text-align: center; vertical-align: top;">
                    {{ $qty }}
                    @if ($first->workOrderItem?->unit || $first->item->unit)
                        <div style="font-size: 7pt; color: #666;">{{ $first->workOrderItem?->unit ?: $first->item->unit }}</div>
                    @endif
                </td>
                <td style="vertical-align: top; padding: 4px;">
                    @if ($serials->isEmpty())
                        <div style="text-align: center; color: #888;">-</div>
                    @elseif ($serials->count() > 10)
                        @php
                            $half = ceil($serials->count() / 2);
                            $col1 = $serials->slice(0, $half);
                            $col2 = $serials->slice($half);
                        @endphp
                        <table style="width: 100%; border-collapse: collapse; border: none;">
                            <tr>
                                <td style="width: 50%; border: none; vertical-align: top; padding: 0 3px; font-family: 'DejaVu Sans Mono', monospace; font-size: 7.5pt; line-height: 1.3; text-align: left;">
                                    @foreach ($col1 as $sn)
                                        <div>{{ $sn }}</div>
                                    @endforeach
                                </td>
                                <td style="width: 50%; border: none; vertical-align: top; padding: 0 3px; font-family: 'DejaVu Sans Mono', monospace; font-size: 7.5pt; line-height: 1.3; text-align: left;">
                                    @foreach ($col2 as $sn)
                                        <div>{{ $sn }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        </table>
                    @else
                        <div style="font-family: 'DejaVu Sans Mono', monospace; font-size: 7.5pt; line-height: 1.35; text-align: left; padding: 0 4px;">
                            @foreach ($serials as $sn)
                                <div>{{ $sn }}</div>
                            @endforeach
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="text-align: center; padding: 15px; color: #888;">No items attached to this Delivery Order.</td>
            </tr>
        @endforelse
    </tbody>
</table>

{{-- Signatures --}}
<table class="sign-table">
    <tr>
        <td>
            <div>Mitra Jaringan Global</div>
            <div class="sign-space"></div>
            <div>(____________________)</div>
        </td>
        <td>
            <div>Receiver</div>
            <div class="sign-space"></div>
            <div>(____________________)</div>
        </td>
    </tr>
</table>

{{-- Copies / Notes --}}
<div class="footer-notes">
    <div>Lembar1 : Logistic</div>
    <div>Lembar2 : Customer</div>
    <div>Lembar3 : Operational</div>
</div>

<div class="footer-page">
    Page 1 of 1
</div>

</body>
</html>
