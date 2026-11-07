<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>WORK ORDER - {{ $wo->wo_number }}</title>
<style>
    @page {
        margin: 20px 24px 20px 24px;
        size: a4 portrait;
    }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 9px;
        line-height: 1.35;
        color: #0F2233;
        margin: 0;
        padding: 0;
    }
    .page-frame {
        border: 1.5px dashed #0066A0;
        padding: 14px 18px 12px 18px;
        min-height: 980px;
        box-sizing: border-box;
        position: relative;
    }
    .header-table {
        width: 100%;
        border-collapse: collapse;
        padding-bottom: 6px;
    }
    .header-divider {
        border-bottom: 2px dashed #0066A0;
        margin-top: 4px;
        margin-bottom: 10px;
    }
    .company-title {
        color: #B0104A;
        font-size: 13.5px;
        font-weight: bold;
        letter-spacing: 0.5px;
        text-align: right;
    }
    .company-tagline {
        color: #0066A0;
        font-size: 9.5px;
        font-weight: bold;
        font-style: italic;
        text-align: right;
        margin-top: 1px;
    }
    .doc-title-container {
        text-align: center;
        margin: 8px 0 10px 0;
    }
    .doc-title {
        font-size: 16px;
        font-weight: bold;
        color: #0066A0;
        letter-spacing: 1.5px;
        display: inline-block;
        border-bottom: 2.5px double #0066A0;
        padding-bottom: 2px;
    }
    .meta-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    .p-text {
        margin: 3px 0;
        text-align: justify;
    }
    .contact-box {
        border-radius: 4px;
        padding: 6px 10px;
        box-sizing: border-box;
    }
    .contact-title {
        font-size: 9px;
        font-weight: bold;
        text-align: center;
        letter-spacing: 0.8px;
        border-bottom: 1px dashed;
        padding-bottom: 3px;
        margin-bottom: 5px;
    }
    .contact-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8px;
    }
    .contact-table td {
        padding: 1.5px 0;
        vertical-align: top;
    }
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
        font-size: 8px;
    }
    .items-table th {
        background: #f8fafc;
        color: #0066A0;
        border: 1.2px solid #0066A0;
        padding: 5px 6px;
        font-weight: bold;
        font-size: 8px;
        text-align: left;
    }
    .items-table td {
        border: 1px solid #0066A0;
        padding: 5px 6px;
        vertical-align: top;
    }
    .sign-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
    }
    .sign-table td {
        width: 50%;
        text-align: center;
        vertical-align: top;
        font-size: 9px;
    }
    .sign-name {
        font-weight: bold;
        display: inline-block;
        min-width: 150px;
        border-bottom: 1.5px solid #0066A0;
        padding-bottom: 2px;
    }
    .sign-title {
        font-size: 8px;
        color: #334155;
        font-weight: bold;
        text-transform: uppercase;
        margin-top: 3px;
    }
    .page-footer {
        margin-top: 18px;
        padding-top: 6px;
        border-top: 1px solid #cbd5e1;
        text-align: center;
        font-size: 7.5px;
        color: #64748b;
    }
</style>
</head>
<body>
<div class="page-frame">
    {{-- Header Kop Surat --}}
    <table class="header-table">
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                @if ($logoBase64)
                    <img src="{{ $logoBase64 }}" style="max-height: 48px; max-width: 220px;" alt="Logo">
                @else
                    <div style="font-weight: bold; font-size: 14px; color: #0066A0;">PT. MITRA JARINGAN GLOBAL</div>
                @endif
            </td>
            <td style="width: 50%; vertical-align: middle;">
                <div class="company-title">PT. MITRA JARINGAN GLOBAL</div>
                <div class="company-tagline">Manage Your Network Problem</div>
            </td>
        </tr>
    </table>
    <div class="header-divider"></div>

    {{-- Work Order Title --}}
    <div class="doc-title-container">
        <span class="doc-title">WORK ORDER</span>
    </div>

    {{-- Meta Dates & Document Number --}}
    <table class="meta-table">
        <tr>
            <td style="width: 60%; font-size: 9px;">
                <b>Date :</b> {{ $wo->wo_date ? $wo->wo_date->format('l, d F Y') : '-' }}
            </td>
            <td style="width: 40%; text-align: right; font-weight: bold; font-size: 9.5px; color: #0F2233;">
                {{ $wo->wo_number }}
            </td>
        </tr>
    </table>

    <div style="margin-bottom: 6px;">
        <div><b>Dear,</b></div>
        <div style="font-weight: bold;">Operational Manager</div>
    </div>

    {{-- Body Paragraphs --}}
    <div class="p-text">
        Referring to the <b>{{ $wo->ref_number ?: '-' }}</b> from <b>'{{ $wo->ref_company ?: '-' }}'</b>, I would like with my pleasure telling you and entire your team also with this WORK ORDER letter.
    </div>
    <div class="p-text">
        The work we've received for <b>'{{ $wo->work_type ?: 'DELIVERY' }}'</b>. PO DATE on <b>{{ $wo->po_date ? $wo->po_date->format('d F Y') : '-' }}</b> has been approved internally. and please note that work must be done or completed before the RFS DATE on <b>{{ $wo->rfs_date ? $wo->rfs_date->format('d F Y') : '-' }}</b>
    </div>
    <div class="p-text">
        The contract with our customer will ended on <b>{{ $wo->contract_end_date ? $wo->contract_end_date->format('d F Y') : '-' }}</b> with business type they are offers is <b>'{{ $wo->business_type ?: 'NEW-PO' }}'</b>.
    </div>
    <div class="p-text" style="margin-top: 5px;">
        Also, The Subject this work they are called with :
        <div style="font-weight: bold; font-size: 9.5px; margin: 2px 0 4px 0; color: #004E7A;">
            {{ $wo->subject }}
        </div>
    </div>
    <div class="p-text">
        We really hope that the team you supervise can carry out this work. If there is anything else that needs to be discussed. please feel free to ask us and thankyou for your attention to this letter.
    </div>
    <div class="p-text" style="margin-bottom: 8px;">
        Are attached is some other information related to our work ;
    </div>

    {{-- Customer Contact & User Contact Boxes (Side by Side) --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <tr>
            {{-- Customer Contact --}}
            <td style="width: 49%; vertical-align: top; padding-right: 4px;">
                <div class="contact-box" style="border: 1.5px dashed #0066A0;">
                    <div class="contact-title" style="color: #0066A0; border-color: #0066A0;">CUSTOMER CONTACT</div>
                    <table class="contact-table">
                        <tr><td style="width: 55px; font-weight: bold;">Name</td><td>: {{ $wo->cust_name ?: '-' }}</td></tr>
                        <tr><td style="font-weight: bold;">Position</td><td>: {{ $wo->cust_position ?: '-' }}</td></tr>
                        <tr><td style="font-weight: bold;">Phone</td><td>: {{ $wo->cust_phone ?: '-' }}</td></tr>
                        <tr><td style="font-weight: bold;">Email</td><td>: {{ $wo->cust_email ?: '-' }}</td></tr>
                        <tr><td style="font-weight: bold;">Address</td><td>: {{ $wo->cust_address ?: '-' }}</td></tr>
                    </table>
                </div>
            </td>
            {{-- User Contact --}}
            <td style="width: 49%; vertical-align: top; padding-left: 4px;">
                <div class="contact-box" style="border: 1.5px dashed #B0104A;">
                    <div class="contact-title" style="color: #B0104A; border-color: #B0104A;">USER CONTACT</div>
                    <table class="contact-table">
                        <tr><td style="width: 55px; font-weight: bold;">Name</td><td>: {{ $wo->user_name ?: '-' }}</td></tr>
                        <tr><td style="font-weight: bold;">Position</td><td>: {{ $wo->user_position ?: '-' }}</td></tr>
                        <tr><td style="font-weight: bold;">Phone</td><td>: {{ $wo->user_phone ?: '-' }}</td></tr>
                        <tr><td style="font-weight: bold;">Email</td><td>: {{ $wo->user_email ?: '-' }}</td></tr>
                        <tr><td style="font-weight: bold;">Address</td><td>: {{ $wo->user_address ?: '-' }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- The Information Table --}}
    <div style="color: #B0104A; font-weight: bold; font-size: 9px; margin-bottom: 3px;">
        THE INFORMATION TABLE ;
    </div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">NO</th>
                <th style="width: 17%;">TYPE NAME</th>
                <th style="width: 25%;">PART NUMBER</th>
                <th style="width: 8%; text-align: center;">QTY</th>
                <th style="width: 45%;">SOW EXPLANATION</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($wo->items as $i => $line)
                <tr>
                    <td style="text-align: center;">{{ $i + 1 }}</td>
                    <td style="font-weight: 600;">{{ $line->type_name ?: ($wo->work_type ?: 'DELIVERY') }}</td>
                    <td>
                        <div style="font-weight: bold; color: #004E7A;">{{ $line->part_number }}</div>
                        @if ($line->item_name && $line->item_name !== $line->part_number)
                            <div style="font-size: 7.5px; color: #64748b;">{{ $line->item_name }}</div>
                        @endif
                    </td>
                    <td style="text-align: center; font-weight: bold;">{{ $line->qty }} <span style="font-size: 7.5px; font-weight: normal; color: #64748b;">{{ $line->unit ?: 'pcs' }}</span></td>
                    <td>
                        <div>{!! nl2br(e($line->sow_explanation ?: ($line->description ?: '-'))) !!}</div>
                        @if ($line->serialList()->isNotEmpty())
                            <div style="margin-top: 2px; font-size: 7.5px; font-family: monospace; color: #334155;">
                                <b>SN:</b> {{ $line->serialList()->implode(', ') }}
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align: center; color: #64748b;">No items recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Closing Message --}}
    <div class="p-text" style="margin-top: 10px;">
        We will be grateful so that we can carry out this work well and professionally.
    </div>
    <div class="p-text">
        Greetings success for us all..
    </div>

    {{-- Signatures --}}
    <table class="sign-table">
        <tr>
            <td>
                <div>My Best Regards,</div>
                <div style="height: 48px;"></div>
                <div class="sign-name">{{ $wo->sales_name ?: '-' }}</div>
                <div class="sign-title">{{ $wo->sales_title ?: 'SALES' }}</div>
            </td>
            <td>
                <div>Accepted,</div>
                <div style="height: 48px;"></div>
                <div class="sign-name">
                    {{ $wo->status === 'accepted' ? ($wo->acceptedBy?->name ?? ($wo->approver_name ?: 'Achmad Bagir')) : ($wo->approver_name ?: 'Achmad Bagir') }}
                </div>
                <div class="sign-title">
                    {{ $wo->approver_title ?: 'OPERATIONAL MANAGER' }}
                    @if ($wo->status === 'accepted' && $wo->accepted_at)
                        <span style="font-weight: normal; font-size: 7.5px; color: #15803d;">(Accepted {{ $wo->accepted_at->format('d/m/Y') }})</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="page-footer">
        Jl. Bahagia Raya No. 3-4, Kota Depok 16417, Jawa Barat - Indonesia | Office : (62-21) 77831954 | www.mjg.co.id
    </div>
</div>
</body>
</html>
