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
        .company-head { display: grid; grid-template-columns: minmax(0, 44%) minmax(200px, 22%) minmax(0, 34%); align-items: flex-start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 10px; }
        .company-left { display: flex; gap: 8px; }
        .company-logo { width: 40px; height: 60px; border: none; display: grid; place-items: center; font-size: 11px; font-weight: 700; letter-spacing: 1px; overflow: hidden; flex-shrink: 0; }
        .company-logo-img { width: 100%; height: 100%; object-fit: contain; }
        .company-name { font-size: 16px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 1px; line-height: 1.2; text-transform: uppercase; }
        .company-detail { font-size: 11px; line-height: 1.3; white-space: pre-line; }
        .doc-title-center { font-size: 11px; line-height: 1.25; min-width: 210px; text-align: center; align-self: start; margin-top: -4px; margin-left: -18px; }
        .doc-meta-right { font-size: 11px; line-height: 1.25; min-width: 210px; justify-self: end; width: 100%; }
        .doc-meta-right .meta-line { display: grid; grid-template-columns: 76px 8px minmax(0, 1fr); align-items: start; }
        .doc-meta-right .meta-line .meta-value { white-space: normal; word-break: break-word; overflow-wrap: anywhere; }
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
        .pdf-mode .doc-title-center { width: 20%; padding: 0 6px; text-align: center; margin-top: -4px; margin-left: -14px; }
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
        $reportHeaderText = trim((string) \App\Models\AppSetting::getValue('report_header_text', ''));
        $reportFooterText = trim((string) \App\Models\AppSetting::getValue('report_footer_text', ''));
        $printNotes = trim((string) ($transaction->notes ?: $companyInvoiceNotes));
        $totalWeight = (float) $transaction->items->sum(fn($item) => (float) ($item->weight ?? 0));
        $companyDetailLines = collect([$companyAddress, $companyPhone, $companyEmail, $companyNotes])
            ->filter(fn (string $value): bool => $value !== '')
            ->values();
        $companyLogoSrc = null;
        $supplierInvoicePhotoSrc = null;

        if ($companyLogoPath) {
            $absoluteLogoPath = public_path('storage/' . $companyLogoPath);

            if (is_file($absoluteLogoPath)) {
                $mimeType = mime_content_type($absoluteLogoPath) ?: 'image/png';
                $companyLogoSrc = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($absoluteLogoPath));
            }
        }

        if (!empty($transaction->supplier_invoice_photo_path)) {
            $absoluteInvoicePhotoPath = public_path('storage/' . $transaction->supplier_invoice_photo_path);
            if (is_file($absoluteInvoicePhotoPath)) {
                $mimeType = mime_content_type($absoluteInvoicePhotoPath) ?: 'image/jpeg';
                $supplierInvoicePhotoSrc = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($absoluteInvoicePhotoPath));
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
            <div class="doc-title">{{ $reportHeaderText !== '' ? $reportHeaderText : __('txn.outgoing_receipt_title') }}</div>
            <div class="doc-number">{{ __('txn.no') }}: {{ $transaction->transaction_number }}</div>
        </div>
        <div class="doc-meta-right">
            <div class="meta-line"><strong>{{ __('txn.date') }}</strong><span>:</span><span class="meta-value">{{ optional($transaction->transaction_date)->format('d-m-Y') }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.semester_period') }}</strong><span>:</span><span class="meta-value">{{ $transaction->semester_period ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.note_number') }}</strong><span>:</span><span class="meta-value">{{ $transaction->note_number ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.supplier') }}</strong><span>:</span><span class="meta-value">{{ $transaction->supplier?->name ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.phone') }}</strong><span>:</span><span class="meta-value">{{ $transaction->supplier?->phone ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.address') }}</strong><span>:</span><span class="meta-value">{{ $transaction->supplier?->address ?: '-' }}</span></div>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 6%">{{ __('txn.no') }}</th>
            <th>{{ __('txn.name') }}</th>
            <th style="width: 10%">{{ __('txn.unit') }}</th>
            <th style="width: 8%">{{ __('txn.qty') }}</th>
            <th style="width: 10%">{{ __('txn.weight') }}</th>
            <th style="width: 14%">{{ __('txn.price') }}</th>
            <th style="width: 14%">{{ __('txn.subtotal') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($transaction->items as $item)
            @php
                $unitCost = (int) round((float) $item->unit_cost, 0);
                $lineTotal = (int) round((float) $item->line_total, 0);
                $unitCostText = $unitCost > 0 ? 'Rp ' . number_format($unitCost, 0, ',', '.') : '';
                $lineTotalText = $lineTotal > 0 ? 'Rp ' . number_format($lineTotal, 0, ',', '.') : '';
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->unit ?: '-' }}</td>
                <td>{{ (int) round((float) $item->quantity, 0) }}</td>
                <td>{{ $item->weight !== null ? number_format((float) $item->weight, 3, ',', '.') : '-' }}</td>
                <td>{{ $unitCostText }}</td>
                <td>{{ $lineTotalText }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="total-box">
        <tr><td>{{ __('txn.total_weight') }}</td><td>{{ number_format($totalWeight, 3, ',', '.') }}</td></tr>
        <tr><td>{{ __('txn.grand_total') }}</td><td>Rp {{ number_format((int) round((float) $transaction->total, 0), 0, ',', '.') }}</td></tr>
    </table>

    <div style="margin-top: 10px;"><strong>{{ __('txn.notes') }}:</strong> {{ $printNotes !== '' ? $printNotes : '-' }}</div>
    @if($supplierInvoicePhotoSrc)
        <div style="margin-top: 10px;">
            <strong>{{ __('supplier_payable.supplier_invoice_photo') }}:</strong><br>
            <img src="{{ $supplierInvoicePhotoSrc }}" alt="Supplier Invoice Photo" style="max-width: 260px; max-height: 260px; border: 1px solid #111; margin-top: 4px;">
        </div>
    @endif

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
    @if($reportFooterText !== '')
        <div style="margin-top: 10px; border-top: 1px solid #111; padding-top: 6px; font-size: 10px;">
            {{ $reportFooterText }}
        </div>
    @endif
</div>
</body>
</html>

