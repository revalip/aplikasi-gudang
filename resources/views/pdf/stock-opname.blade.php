<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>PHYSICAL INVENTORY AUDIT - {{ $opname->opname_number }}</title>
<style>
    @page {
        margin: 28px 34px 45px 34px;
        size: a4 portrait;
    }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 8.5pt;
        color: #1c2b36;
        line-height: 1.35;
        margin: 0;
        padding: 0;
    }
    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        border-bottom: 2px solid #0066A0;
        padding-bottom: 8px;
    }
    .company-name {
        color: #B0104A;
        font-size: 13pt;
        font-weight: bold;
    }
    .company-tag {
        color: #0066A0;
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 0.5px;
    }
    .company-address {
        font-size: 7.5pt;
        color: #555;
        line-height: 1.3;
        margin-top: 3px;
    }
    .doc-title-container {
        text-align: right;
    }
    .doc-title {
        font-size: 13pt;
        font-weight: bold;
        color: #0066A0;
        letter-spacing: 0.5px;
    }
    .doc-number {
        font-size: 8.5pt;
        color: #475569;
        font-family: 'DejaVu Sans Mono', monospace;
        margin-top: 3px;
    }
    .meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        margin-bottom: 12px;
    }
    .meta-table td {
        vertical-align: top;
        padding: 3px 0;
    }
    .meta-label {
        color: #64748b;
        font-size: 8pt;
        font-weight: bold;
        text-transform: uppercase;
        width: 135px;
    }
    .meta-val {
        font-size: 8.5pt;
        font-weight: 600;
        color: #1e293b;
    }
    .summary-box {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
    }
    .summary-card {
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        padding: 6px 10px;
        background-color: #f8fafc;
        text-align: center;
    }
    .summary-card .num {
        font-size: 12pt;
        font-weight: bold;
        color: #0066A0;
    }
    .summary-card .lbl {
        font-size: 7.5pt;
        text-transform: uppercase;
        color: #64748b;
        font-weight: bold;
    }
    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
        margin-bottom: 14px;
    }
    table.items th {
        background-color: #0066A0;
        color: #ffffff;
        border: 1px solid #005585;
        padding: 5px 6px;
        text-align: left;
        font-size: 8pt;
        text-transform: uppercase;
    }
    table.items td {
        border: 1px solid #cbd5e1;
        padding: 5px 6px;
        font-size: 8pt;
        vertical-align: middle;
    }
    table.items tr:nth-child(even) td {
        background-color: #f8fafc;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 7pt;
        font-weight: bold;
        text-transform: uppercase;
    }
    .badge-match { background-color: #dcfce7; color: #166534; }
    .badge-surplus { background-color: #dbeafe; color: #1e40af; }
    .badge-deficit { background-color: #fee2e2; color: #991b1b; }
    .sign-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 24px;
    }
    .sign-table td {
        width: 33.33%;
        text-align: center;
        vertical-align: top;
        font-size: 8pt;
    }
    .sign-role {
        font-weight: bold;
        color: #334155;
        margin-bottom: 50px;
    }
    .sign-name {
        border-top: 1px solid #0f172a;
        display: inline-block;
        min-width: 140px;
        font-weight: bold;
        padding-top: 4px;
    }
    .footer {
        position: fixed;
        bottom: -25px;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 7.5pt;
        color: #94a3b8;
        border-top: 1px solid #e2e8f0;
        padding-top: 4px;
    }
</style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width: 55%; vertical-align: top;">
            @if (!empty($logoBase64))
                <img src="{{ $logoBase64 }}" style="max-height: 42px; margin-bottom: 2px;"><br>
            @else
                <div class="company-name">PT. MITRA JARINGAN GLOBAL</div>
                <div class="company-tag">MANAGE YOUR NETWORK PROBLEM</div>
            @endif
            <div class="company-address">
                Jl. Bahagia Raya No. 3-4, RT. 006/003, Sukmajaya, Depok, Jawa Barat 16417<br>
                Tel: +6221-77831954 | Email: info@mjg.co.id | www.mjg.co.id
            </div>
        </td>
        <td style="width: 45%; vertical-align: top;" class="doc-title-container">
            <div class="doc-title">PHYSICAL INVENTORY AUDIT</div>
            <div style="font-size: 8.5pt; color: #0066A0; font-weight: bold;">(BERITA ACARA STOCK OPNAME)</div>
            <div class="doc-number">No: {{ $opname->opname_number }}</div>
            <div style="margin-top: 4px; font-size: 8pt;">
                Status: <b>{{ strtoupper($opname->status) }}</b>
            </div>
        </td>
    </tr>
</table>

<table class="meta-table">
    <tr>
        <td style="width: 50%;">
            <table style="width: 100%;">
                <tr>
                    <td class="meta-label">Warehouse / Floor:</td>
                    <td class="meta-val">{{ $opname->location->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Audit Date:</td>
                    <td class="meta-val">{{ $opname->opname_date->format('d F Y') }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Audited By:</td>
                    <td class="meta-val">{{ $opname->createdBy->name ?? '-' }}</td>
                </tr>
            </table>
        </td>
        <td style="width: 50%;">
            <table style="width: 100%;">
                <tr>
                    <td class="meta-label">Completed At:</td>
                    <td class="meta-val">{{ $opname->completed_at ? $opname->completed_at->format('d/m/Y H:i') : '-' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Approved By:</td>
                    <td class="meta-val">{{ $opname->completedBy->name ?? ($opname->status === 'completed' ? 'Superadmin' : '-') }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Audit Notes:</td>
                    <td class="meta-val">{{ $opname->notes ?: '-' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="summary-box">
    <tr>
        <td style="width: 25%; padding: 0 4px 0 0;">
            <div class="summary-card">
                <div class="num">{{ count($opname->items) }}</div>
                <div class="lbl">Total Products</div>
            </div>
        </td>
        <td style="width: 25%; padding: 0 4px;">
            <div class="summary-card">
                <div class="num">{{ number_format($opname->total_system_qty, 0, ',', '.') }}</div>
                <div class="lbl">System Qty (Book)</div>
            </div>
        </td>
        <td style="width: 25%; padding: 0 4px;">
            <div class="summary-card">
                <div class="num">{{ number_format($opname->total_physical_qty, 0, ',', '.') }}</div>
                <div class="lbl">Real Qty (Physical)</div>
            </div>
        </td>
        <td style="width: 25%; padding: 0 0 0 4px;">
            <div class="summary-card" style="{{ $opname->total_variance != 0 ? 'background-color: #fef2f2; border-color: #fca5a5;' : 'background-color: #f0fdf4; border-color: #86efac;' }}">
                <div class="num" style="{{ $opname->total_variance > 0 ? 'color: #1e40af;' : ($opname->total_variance < 0 ? 'color: #dc2626;' : 'color: #166534;') }}">
                    {{ $opname->total_variance > 0 ? '+' : '' }}{{ number_format($opname->total_variance, 0, ',', '.') }}
                </div>
                <div class="lbl">Total Variance</div>
            </div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th class="text-center" style="width: 24px;">No</th>
            <th>Part Number</th>
            <th>Product Name</th>
            <th class="text-right" style="width: 60px;">System</th>
            <th class="text-right" style="width: 60px;">Real</th>
            <th class="text-right" style="width: 60px;">Variance</th>
            <th class="text-center" style="width: 75px;">Status</th>
            <th style="width: 120px;">Notes / Serials</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($opname->items as $idx => $line)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td style="font-family: 'DejaVu Sans Mono', monospace; font-size: 7.5pt; font-weight: bold;">
                    {{ $line->part_number ?: '-' }}
                </td>
                <td>
                    <b>{{ $line->item_name }}</b>
                    @if ($line->scanned_serials)
                        <div style="font-size: 7pt; color: #64748b; margin-top: 2px;">
                            SN: {{ Str::limit(str_replace("\n", ', ', $line->scanned_serials), 45) }}
                        </div>
                    @endif
                </td>
                <td class="text-right">{{ $line->system_qty }}</td>
                <td class="text-right" style="font-weight: bold;">{{ $line->physical_qty }}</td>
                <td class="text-right" style="font-weight: bold; {{ $line->variance > 0 ? 'color: #1e40af;' : ($line->variance < 0 ? 'color: #dc2626;' : 'color: #166534;') }}">
                    {{ $line->variance > 0 ? '+' : '' }}{{ $line->variance }}
                </td>
                <td class="text-center">
                    @if ($line->variance == 0)
                        <span class="badge badge-match">Match</span>
                    @elseif ($line->variance > 0)
                        <span class="badge badge-surplus">+Surplus</span>
                    @else
                        <span class="badge badge-deficit">-Deficit</span>
                    @endif
                </td>
                <td style="font-size: 7.5pt; color: #475569;">
                    {{ $line->notes ?: '-' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center" style="padding: 14px; color: #94a3b8;">
                    No items registered in this stock opname session.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr style="font-weight: bold; background-color: #f1f5f9;">
            <td colspan="3" class="text-right" style="padding: 6px;">TOTAL:</td>
            <td class="text-right" style="padding: 6px;">{{ number_format($opname->total_system_qty, 0, ',', '.') }}</td>
            <td class="text-right" style="padding: 6px;">{{ number_format($opname->total_physical_qty, 0, ',', '.') }}</td>
            <td class="text-right" style="padding: 6px; {{ $opname->total_variance > 0 ? 'color: #1e40af;' : ($opname->total_variance < 0 ? 'color: #dc2626;' : 'color: #166534;') }}">
                {{ $opname->total_variance > 0 ? '+' : '' }}{{ number_format($opname->total_variance, 0, ',', '.') }}
            </td>
            <td colspan="2" class="text-center" style="padding: 6px;">
                @if ($opname->total_variance == 0)
                    <span class="badge badge-match">MATCHED 100%</span>
                @else
                    <span class="badge {{ $opname->total_variance > 0 ? 'badge-surplus' : 'badge-deficit' }}">VARIANCE DETECTED</span>
                @endif
            </td>
        </tr>
    </tfoot>
</table>

<table class="sign-table">
    <tr>
        <td>
            <div class="sign-role">Physical Counter</div>
            <div class="sign-name">{{ $opname->createdBy->name ?? '( ................................ )' }}</div>
            <div style="font-size: 7pt; color: #64748b; margin-top: 2px;">Warehouse Counter</div>
        </td>
        <td>
            <div class="sign-role">Warehouse Supervisor</div>
            <div class="sign-name">( ................................ )</div>
            <div style="font-size: 7pt; color: #64748b; margin-top: 2px;">Warehouse Head</div>
        </td>
        <td>
            <div class="sign-role">Operational Manager</div>
            <div class="sign-name">{{ $opname->completedBy->name ?? '( ................................ )' }}</div>
            <div style="font-size: 7pt; color: #64748b; margin-top: 2px;">Management Approval</div>
        </td>
    </tr>
</table>

<div class="footer">
    Official Physical Inventory Audit Report &bull; PT. Mitra Jaringan Global &bull; Enterprise WMS
</div>

</body>
</html>
