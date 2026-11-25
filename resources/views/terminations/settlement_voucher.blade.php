<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settlement Voucher - {{ $termination->employee->name ?? 'Employee' }}</title>
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
        .voucher-meta {
            text-align: right;
        }
        .voucher-meta h2 {
            margin: 0 0 4px 0;
            font-size: 16px;
            color: #dc2626;
        }
        .voucher-meta p {
            margin: 2px 0;
            font-size: 13px;
        }
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
        .settlement-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .settlement-table th, .settlement-table td {
            border: 1px solid #cbd5e1;
            padding: 10px;
            font-size: 13px;
        }
        .settlement-table th {
            background: #f1f5f9;
            text-align: left;
            font-weight: 600;
        }
        .clearance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 24px;
        }
        .clearance-item {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px;
            background: #ffffff;
        }
        .clearance-item h4 {
            margin: 0 0 6px 0;
            font-size: 12px;
            color: #0f172a;
        }
        .clearance-item p {
            margin: 2px 0;
            font-size: 11px;
            color: #64748b;
        }
        .clearance-line {
            height: 32px;
            border-bottom: 1px dashed #94a3b8;
            margin-bottom: 4px;
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
                <p style="font-weight: 600; color: #0f172a; margin-top: 6px;">FINAL SETTLEMENT & CLEARANCE CERTIFICATE / የስራ መልቀቂያና የክሊራንስ ማስረጃ</p>
            </div>
            <div class="voucher-meta">
                <h2>TERM-{{ $termination->id }}</h2>
                <p>Date: <strong>{{ tenant_date(now(), 'd/m/Y', true) }}</strong></p>
                <p>Type: <strong>{{ $termination->terminationType->termination_type ?? 'Termination' }}</strong></p>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h3>EMPLOYEE PARTICULARS (የሰራተኛው መረጃ)</h3>
                <p><strong>Full Name:</strong> {{ $termination->employee->name ?? 'N/A' }}</p>
                <p><strong>Employee ID:</strong> {{ $employee->employee_id ?? 'N/A' }}</p>
                <p><strong>TIN Number:</strong> {{ $employee->tax_payer_id ?: 'N/A' }}</p>
                <p><strong>Basic Salary:</strong> ETB {{ number_format($basicSalary, 2) }}</p>
            </div>
            <div class="card">
                <h3>SERVICE DURATION (የስራ ዘመን)</h3>
                <p><strong>Date of Joining:</strong> {{ $employee && $employee->date_of_joining ? tenant_date($employee->date_of_joining, 'd/m/Y', true) : 'N/A' }}</p>
                <p><strong>Termination Date:</strong> {{ $termination->termination_date ? tenant_date($termination->termination_date, 'd/m/Y', true) : 'N/A' }}</p>
                <p><strong>Notice Date:</strong> {{ $termination->notice_date ? tenant_date($termination->notice_date, 'd/m/Y', true) : 'No Notice' }}</p>
                <p><strong>Total Duration:</strong> <strong>{{ $serviceDuration }}</strong></p>
            </div>
        </div>

        <table class="settlement-table">
            <thead>
                <tr>
                    <th>Statutory Entitlement / Deduction Category (የክፍያና ተቀናሽ አይነት)</th>
                    <th>Legal Basis</th>
                    <th style="text-align: right;">Amount (ETB)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Statutory Severance Pay (የስንብት ክፍያ)</strong></td>
                    <td>Labour Proc. 1156/2019, Art. 39</td>
                    <td style="text-align: right; color: #16a34a; font-weight: 600;">+ {{ number_format($severancePay, 2) }}</td>
                </tr>
                @if($outstandingLoans > 0)
                <tr>
                    <td><strong>Outstanding Staff Loan Deduction (ያልተከፈለ ብድር ተቀናሽ)</strong></td>
                    <td>Company Loan Agreement</td>
                    <td style="text-align: right; color: #dc2626;">- {{ number_format($outstandingLoans, 2) }}</td>
                </tr>
                @endif
                <tr style="background: #f8fafc; font-size: 14px;">
                    <td colspan="2"><strong>NET FINAL SETTLEMENT PAYABLE (የተጣራ የሚከፈል)</strong></td>
                    <td style="text-align: right; font-weight: 700; color: #0f172a; font-size: 16px;">
                        ETB {{ number_format($netSettlement, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <h3 style="font-size: 13px; color: #0f172a; margin-top: 24px; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px;">
            MANDATORY DEPARTMENTAL CLEARANCE / የክሊራንስ ማረጋገጫ ፊርማ
        </h3>

        <div class="clearance-grid">
            <div class="clearance-item">
                <h4>1. Department Head / Supervisor (የቅርብ አለቃ)</h4>
                <p>Duties & documents successfully handed over.</p>
                <div class="clearance-line"></div>
                <p>Signature / Date</p>
            </div>
            <div class="clearance-item">
                <h4>2. IT & Property Administration (ንብረትና አይቲ ክፍል)</h4>
                <p>Company laptop, phone, access badges returned.</p>
                <div class="clearance-line"></div>
                <p>Signature / Date</p>
            </div>
            <div class="clearance-item">
                <h4>3. Finance & Store (ፋይናንስና መጋዘን ክፍል)</h4>
                <p>No outstanding petty cash float or inventory shortage.</p>
                <div class="clearance-line"></div>
                <p>Signature / Date</p>
            </div>
            <div class="clearance-item">
                <h4>4. HR Director / General Manager (የሰው ኃይል / ዋና ሥራ አስኪያጅ)</h4>
                <p>Final settlement approved for disbursement.</p>
                <div class="clearance-line"></div>
                <p>Signature / Date</p>
            </div>
        </div>

        <div class="legal-footer">
            Issued in full compliance with the Federal Democratic Republic of Ethiopia Labour Proclamation No. 1156/2019.<br>
            All mutual rights and obligations between the employer and the employee stand fully resolved upon execution of this clearance.
        </div>
    </div>
</body>
</html>
