<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 34px 50px 34px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1c2b36; }
    .header { width: 100%; border-bottom: 3px solid #0066A0; padding-bottom: 8px; margin-bottom: 14px; }
    .header table { width: 100%; border-collapse: collapse; }
    .header img { height: 48px; }
    .company-name { color: #B0104A; font-size: 15px; font-weight: bold; }
    .company-tag { color: #0066A0; font-size: 9px; font-weight: bold; }
    .doc-title { text-align: right; font-size: 20px; font-weight: bold; color: #0066A0; letter-spacing: 1px; }
    .doc-number { text-align: right; font-size: 10px; color: #475569; font-family: 'DejaVu Sans Mono', monospace; }
    .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 12px; }
    .label { font-size: 8.5px; font-weight: bold; color: #64748b; text-transform: uppercase; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.items th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 5px 7px; text-align: left; font-size: 9px; text-transform: uppercase; color: #475569; }
    table.items td { border: 1px solid #e2e8f0; padding: 5px 7px; vertical-align: top; }
    .text-right { text-align: right; }
    .total-row td { font-weight: bold; background: #f8fafc; }
    .sign-table { width: 100%; margin-top: 36px; border-collapse: collapse; }
    .sign-table td { width: 50%; text-align: center; vertical-align: top; padding-top: 44px; font-size: 10px; }
    .sign-table .name { border-top: 1px solid #1c2b36; display: inline-block; padding-top: 4px; min-width: 160px; font-weight: bold; }
    .footer { position: fixed; bottom: -30px; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
    .muted { color: #64748b; }
</style>
</head>
<body>
<div class="header">
    <table>
        <tr>
            <td style="width: 60%;">
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/logo-mjg.png'))) }}">
            </td>
            <td style="width: 40%;">
                <div class="doc-title">{{ $docTitle }}</div>
                <div class="doc-number">{{ $docNumber }}</div>
            </td>
        </tr>
    </table>
</div>

{{ $slot }}

<div class="footer">Jl. Bahagia Raya No. 3-4, Kota Depok 16417, Jawa Barat - Indonesia | Office: (62-21) 77831954 | www.mjg.co.id</div>
</body>
</html>
