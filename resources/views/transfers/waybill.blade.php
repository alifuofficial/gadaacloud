<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waybill - {{ $transfer->waybill_number }}</title>
    <style>
        body {
            font-family: Arial, 'Segoe UI', Tahoma, sans-serif;
            margin: 0;
            padding: 24px;
            color: #1e293b;
            background: #f8fafc;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            padding: 32px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .company-info h1 {
            margin: 0 0 4px 0;
            font-size: 22px;
            color: #0f172a;
        }
        .company-info p {
            margin: 0;
            font-size: 13px;
            color: #64748b;
        }
        .waybill-meta {
            text-align: right;
        }
        .waybill-meta h2 {
            margin: 0 0 4px 0;
            font-size: 18px;
            color: #2563eb;
        }
        .waybill-meta p {
            margin: 2px 0;
            font-size: 13px;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-in_transit { background: #fef3c7; color: #92400e; }
        .badge-received { background: #dcfce7; color: #166534; }
        .badge-discrepancy { background: #fee2e2; color: #991b1b; }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px;
        }
        .card h3 {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
        }
        .card p {
            margin: 4px 0;
            font-size: 13px;
            color: #334155;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th, .items-table td {
            border: 1px solid #cbd5e1;
            padding: 10px;
            text-align: left;
            font-size: 13px;
        }
        .items-table th {
            background: #f1f5f9;
            font-weight: 600;
            color: #0f172a;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-top: 36px;
            padding-top: 20px;
            border-top: 1px solid #cbd5e1;
        }
        .sign-box {
            text-align: center;
        }
        .sign-line {
            height: 48px;
            border-bottom: 1px dashed #64748b;
            margin-bottom: 6px;
        }
        .sign-box p {
            margin: 2px 0;
            font-size: 12px;
            color: #475569;
        }
        .legal-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 12px;
        }
        @media print {
            body { padding: 0; background: none; }
            .container { box-shadow: none; border: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="no-print" style="margin-bottom: 16px; text-align: right;">
            <button onclick="window.print()" style="padding: 8px 16px; background: #0f172a; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">Print / Download PDF</button>
        </div>

        <div class="header">
            <div class="company-info">
                <h1>{{ $companyName }}</h1>
                <p>Tax Identification Number (TIN): <strong>{{ $companyVat }}</strong></p>
                <p style="font-weight: 600; color: #0f172a; margin-top: 6px;">INTER-BRANCH GOODS WAYBILL / የጭነት ማስተላለፊያ ደረሰኝ</p>
            </div>
            <div class="waybill-meta">
                <h2>{{ $transfer->waybill_number }}</h2>
                <p>Date: <strong>{{ tenant_date($transfer->date ?: now(), 'd/m/Y', true) }}</strong></p>
                <p>Status: <span class="badge badge-{{ $transfer->status }}">{{ strtoupper(str_replace('_', ' ', $transfer->status)) }}</span></p>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h3>DISPATCH FROM (ላኪ መጋዘን / ቅርንጫፍ)</h3>
                <p><strong>Warehouse:</strong> {{ $transfer->fromWarehouse->name ?? 'N/A' }}</p>
                <p><strong>Address:</strong> {{ $transfer->fromWarehouse->address ?? 'N/A' }}, {{ $transfer->fromWarehouse->city ?? '' }}</p>
                <p><strong>Dispatched At:</strong> {{ $transfer->dispatched_at ? tenant_datetime($transfer->dispatched_at, 'Y-m-d', 'H:i') : 'N/A' }}</p>
            </div>
            <div class="card">
                <h3>DELIVER TO (ተቀባይ መጋዘን / ቅርንጫፍ)</h3>
                <p><strong>Warehouse:</strong> {{ $transfer->toWarehouse->name ?? 'N/A' }}</p>
                <p><strong>Address:</strong> {{ $transfer->toWarehouse->address ?? 'N/A' }}, {{ $transfer->toWarehouse->city ?? '' }}</p>
                <p><strong>Received At:</strong> {{ $transfer->received_at ? tenant_datetime($transfer->received_at, 'Y-m-d', 'H:i') : 'Pending Arrival' }}</p>
            </div>
        </div>

        <div class="card" style="margin-bottom: 20px;">
            <h3>TRANSPORTER & DRIVER DETAILS (የአሽከርካሪና የተሸከርካሪ መረጃ)</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                <p><strong>Driver Name:</strong> {{ $transfer->driver_name ?: 'Self / Staff Transfer' }}</p>
                <p><strong>Driver Phone:</strong> {{ $transfer->driver_phone ?: 'N/A' }}</p>
                <p><strong>Vehicle Plate No:</strong> {{ $transfer->vehicle_plate_number ?: 'N/A' }}</p>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 40px;">No.</th>
                    <th>Item Description (የዕቃው አይነት)</th>
                    <th>SKU / Code</th>
                    <th style="text-align: right;">Quantity Dispatched</th>
                    <th style="text-align: right;">Quantity Received</th>
                    <th style="text-align: right;">Transit Loss / Damage</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td><strong>{{ $transfer->product->name ?? 'Product' }}</strong></td>
                    <td>{{ $transfer->product->sku ?? 'N/A' }}</td>
                    <td style="text-align: right;">{{ number_format($transfer->quantity, 2) }}</td>
                    <td style="text-align: right;">{{ $transfer->received_quantity !== null ? number_format($transfer->received_quantity, 2) : '-' }}</td>
                    <td style="text-align: right; color: {{ $transfer->damaged_quantity > 0 ? '#dc2626' : '#16a34a' }};">
                        {{ number_format($transfer->damaged_quantity ?? 0, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        @if($transfer->notes || $transfer->discrepancy_reason)
        <div class="card" style="margin-bottom: 20px;">
            <h3>NOTES & REMARKS (ማስታወሻ)</h3>
            @if($transfer->notes && !\App\Support\HtmlSanitizer::isVisuallyEmptyHtml($transfer->notes))
                <div><strong>Transfer Notes:</strong> <div style="margin-top: 4px; font-size: 13px; color: #334155;">{!! \App\Support\HtmlSanitizer::sanitize($transfer->notes) !!}</div></div>
            @endif
            @if($transfer->discrepancy_reason)<p style="color: #dc2626;"><strong>Discrepancy / Damage Reason:</strong> {{ $transfer->discrepancy_reason }}</p>@endif
        </div>
        @endif

        <div class="signatures">
            <div class="sign-box">
                <div class="sign-line"></div>
                <p><strong>Dispatched By (ላኪ ኃላፊ)</strong></p>
                <p>Signature & Official Stamp</p>
            </div>
            <div class="sign-box">
                <div class="sign-line"></div>
                <p><strong>Transporter (አሽከርካሪ)</strong></p>
                <p>Signature / Date</p>
            </div>
            <div class="sign-box">
                <div class="sign-line"></div>
                <p><strong>Received By (ተቀባይ ኃላፊ)</strong></p>
                <p>Signature & Official Stamp</p>
            </div>
        </div>

        <div class="legal-footer">
            Issued under Ethiopian commercial transport regulations for inter-branch transfer of company goods.<br>
            Any discrepancy between dispatched and received quantity must be documented at the receiving checkpoint.
        </div>
    </div>
</body>
</html>
