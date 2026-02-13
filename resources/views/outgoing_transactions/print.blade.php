<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('txn.print') }} {{ $transaction->transaction_number }}</title>
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 11px; line-height: 1.2; color: #111; }
        .container { max-width: 900px; margin: 0 auto; }
        .company-head { display: grid; grid-template-columns: 1fr auto 1fr; align-items: flex-start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 10px; }
        .company-left { display: flex; gap: 8px; }
        .company-logo { width: 37px; height: 50px; border: 1px solid #111; display: grid; place-items: center; font-size: 11px; font-weight: 700; letter-spacing: 1px; overflow: hidden; flex-shrink: 0; }
        .company-logo-img { width: 100%; height: 100%; object-fit: contain; }
        .company-name { font-size: 16px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 1px; line-height: 1.2; text-transform: uppercase; }
        .company-detail { font-size: 11px; line-height: 1.3; white-space: pre-line; }
        .doc-title-center { font-size: 11px; line-height: 1.25; min-width: 210px; text-align: center; align-self: center; }
        .doc-meta-right { font-size: 11px; line-height: 1.25; min-width: 210px; justify-self: end; }
        .doc-title { font-size: 18px; font-weight: 700; text-align: center; }
        .doc-number { text-align: center; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #111; padding: 4px; text-align: left; vertical-align: top; }
        th { font-size: 10px; }
        .total-box { margin-top: 12px; width: 340px; margin-left: auto; }
        .total-box td { border: 1px solid #111; }
        .signature-table { margin-top: 24px; }
        .signature-table th, .signature-table td { text-align: center; }
        .signature-space { height: 64px; border-top: none !important; border-bottom: none !important; }
        .signature-name { font-weight: 600; }
        .pdf-mode { font-size: 10px; }
        .pdf-mode .container { max-width: 100%; }
        .pdf-mode .company-head { display: table; width: 100%; table-layout: fixed; border-collapse: collapse; }
        .pdf-mode .company-left,
        .pdf-mode .doc-title-center,
        .pdf-mode .doc-meta-right { display: table-cell; vertical-align: top; }
        .pdf-mode .company-left { width: 44%; padding-right: 8px; }
        .pdf-mode .doc-title-center { width: 20%; padding: 0 6px; text-align: center; }
        .pdf-mode .doc-meta-right { width: 36%; padding-left: 8px; min-width: 0; }
        .pdf-mode .company-name { font-size: 14px; }
        .pdf-mode .doc-title { font-size: 16px; }
        .pdf-mode th, .pdf-mode td { padding: 3px; }
        @media print {
            .no-print { display: none; }
            body { margin: 4mm; font-size: 10px; }
            th, td { padding: 3px; }
        }
    </style>
</head>
<body class="{{ !empty($isPdf) ? 'pdf-mode' : '' }}">
<div class="container">
    @php
        $companyLogoPath = \App\Models\AppSetting::getValue('company_logo_path');
        $companyName = trim((string) \App\Models\AppSetting::getValue('company_name', 'CV. PUSTAKA GRAFIKA'));
        $companyAddress = trim((string) \App\Models\AppSetting::getValue('company_address', ''));
        $companyPhone = trim((string) \App\Models\AppSetting::getValue('company_phone', ''));
        $companyEmail = trim((string) \App\Models\AppSetting::getValue('company_email', ''));
        $companyNotes = trim((string) \App\Models\AppSetting::getValue('company_notes', ''));
        $companyInvoiceNotes = trim((string) \App\Models\AppSetting::getValue('company_invoice_notes', ''));
        $printNotes = trim((string) ($transaction->notes ?: $companyInvoiceNotes));
        $companyDetailLines = collect([$companyAddress, $companyPhone, $companyEmail, $companyNotes])
            ->filter(fn (string $value): bool => $value !== '')
            ->values();
        $companyLogoSrc = null;

        if ($companyLogoPath) {
            $absoluteLogoPath = public_path('storage/' . $companyLogoPath);

            if (is_file($absoluteLogoPath)) {
                $mimeType = mime_content_type($absoluteLogoPath) ?: 'image/png';
                $companyLogoSrc = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($absoluteLogoPath));
            }
        }
    @endphp
    @if(empty($isPdf))
        <div class="no-print" style="margin-bottom: 10px;">
            <button onclick="window.print()">{{ __('txn.print') }}</button>
        </div>
    @endif

    <div class="company-head">
        <div class="company-left">
            <div class="company-logo">
                @if($companyLogoSrc)
                    <img src="{{ $companyLogoSrc }}" alt="Logo" class="company-logo-img">
                @else
                    PG
                @endif
            </div>
            <div>
                <div class="company-name">{{ $companyName !== '' ? $companyName : 'CV. PUSTAKA GRAFIKA' }}</div>
                @if($companyDetailLines->isNotEmpty())
                    <div class="company-detail">{{ $companyDetailLines->implode("\n") }}</div>
                @endif
            </div>
        </div>
        <div class="doc-title-center">
            <div class="doc-title">{{ __('txn.outgoing_transactions_title') }}</div>
            <div class="doc-number">{{ __('txn.no') }}: {{ $transaction->transaction_number }}</div>
        </div>
        <div class="doc-meta-right">
            <div><strong>{{ __('txn.date') }}</strong> : {{ optional($transaction->transaction_date)->format('d-m-Y') }}</div>
            <div><strong>{{ __('txn.semester_period') }}</strong> : {{ $transaction->semester_period ?: '-' }}</div>
            <div><strong>{{ __('txn.note_number') }}</strong> : {{ $transaction->note_number ?: '-' }}</div>
            <div><strong>{{ __('txn.supplier') }}</strong> : {{ $transaction->supplier?->name ?: '-' }}</div>
            <div><strong>{{ __('txn.phone') }}</strong> : {{ $transaction->supplier?->phone ?: '-' }}</div>
            <div><strong>{{ __('txn.address') }}</strong> : {{ $transaction->supplier?->address ?: '-' }}</div>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 6%">{{ __('txn.no') }}</th>
            <th style="width: 12%">{{ __('txn.code') }}</th>
            <th>{{ __('txn.name') }}</th>
            <th style="width: 8%">{{ __('txn.unit') }}</th>
            <th style="width: 8%">{{ __('txn.qty') }}</th>
            <th style="width: 14%">{{ __('txn.price') }}</th>
            <th style="width: 16%">{{ __('txn.subtotal') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($transaction->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->product_code ?: '-' }}</td>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->unit ?: '-' }}</td>
                <td>{{ (int) round((float) $item->quantity, 0) }}</td>
                <td>{{ number_format((int) round((float) $item->unit_cost, 0), 0, ',', '.') }}</td>
                <td>{{ number_format((int) round((float) $item->line_total, 0), 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="total-box">
        <tr><td>{{ __('txn.grand_total') }}</td><td>Rp {{ number_format((int) round((float) $transaction->total, 0), 0, ',', '.') }}</td></tr>
    </table>

    <div style="margin-top: 10px;"><strong>{{ __('txn.notes') }}:</strong> {{ $printNotes !== '' ? $printNotes : '-' }}</div>

    <table class="signature-table">
        <tr>
            <th>{{ __('txn.signature_created') }}</th>
            <th>{{ __('txn.signature_checked') }}</th>
            <th>{{ __('txn.signature_sender') }}</th>
            <th>{{ __('txn.signature_receiver') }}</th>
        </tr>
        <tr>
            <td class="signature-space"></td>
            <td class="signature-space"></td>
            <td class="signature-space"></td>
            <td class="signature-space"></td>
        </tr>
        <tr>
            <td class="signature-name">{{ $transaction->creator?->name ?: __('txn.system_user') }}</td>
            <td>________________</td>
            <td>________________</td>
            <td>________________</td>
        </tr>
    </table>
</div>
</body>
</html>
