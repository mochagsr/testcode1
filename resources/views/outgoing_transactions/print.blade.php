<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('txn.print') }} {{ $transaction->transaction_number }}</title>
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 12px; line-height: 1.28; color: #111; font-weight: 600; }
        .container { max-width: 900px; margin: 0 auto; }
        .company-head { display: grid; grid-template-columns: minmax(0, 46%) minmax(220px, 26%) minmax(0, 28%); align-items: flex-start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 12px; }
        .company-left { display: flex; gap: 6px; min-width: 0; }
        .company-logo { width: 40px; height: 60px; border: none; display: grid; place-items: center; font-size: 11px; font-weight: 700; letter-spacing: 1px; overflow: hidden; flex-shrink: 0; }
        .company-logo-img { width: 100%; height: 100%; object-fit: contain; }
        .company-name { font-size: 15px; font-weight: 800; letter-spacing: 0; margin-bottom: 2px; line-height: 1.15; text-transform: uppercase; white-space: nowrap; }
        .company-detail { font-size: 12px; line-height: 1.35; white-space: pre-line; font-weight: 600; }
        .doc-title-center { font-size: 12px; line-height: 1.3; min-width: 220px; text-align: center; align-self: start; justify-self: start; margin-top: -2px; min-width: 0; font-weight: 700; margin-left: -48px; }
        .doc-meta-right { font-size: 12px; line-height: 1.3; min-width: 180px; max-width: 270px; justify-self: end; width: 100%; margin-left: auto; font-weight: 700; }
        .doc-meta-right .meta-line { display: grid; grid-template-columns: 76px 8px minmax(0, 1fr); align-items: start; }
        .doc-meta-right .meta-line .meta-value { white-space: pre-line; word-break: break-word; overflow-wrap: anywhere; }
        .doc-title { font-size: 20px; font-weight: 800; text-align: center; }
        .doc-number { text-align: center; margin-bottom: 4px; }
        @include('partials.print.table_styles')
        .table-summary { display: grid; grid-template-columns: minmax(0, 1fr) 130px 220px; align-items: flex-start; gap: 12px; margin-top: 10px; }
        .notes-box { line-height: 1.35; word-break: break-word; overflow-wrap: anywhere; }
        .notes-content { white-space: pre-line; }
        .qty-box { width: auto; justify-self: end; }
        .qty-box table { width: auto; table-layout: fixed; margin-top: 0; }
        .qty-box table,
        .total-box { margin-top: 0; }
        .qty-box td:first-child { font-weight: 700; background: #f7f7f7; width: 66%; }
        .qty-box td:last-child { width: 34%; text-align: right; font-weight: 700; white-space: nowrap; }
        .total-box { width: 100%; }
        .total-box td { border: 1px solid #111; }
        .total-box td:first-child { background: #f7f7f7; }
        .signature-table { margin-top: 24px; }
        .signature-table th, .signature-table td { text-align: center; }
        .signature-space { height: 64px; border-top: none !important; border-bottom: none !important; }
        .signature-name { font-weight: 600; }
        @media print {
            .no-print { display: none; }
            body { margin: 4mm; font-size: 12px; line-height: 1.28; font-weight: 600; }
        }
    </style>
</head>
<body>
<div class="container">
    @php
        $companyLogoPath = \App\Models\AppSetting::getValue('company_logo_path');
        $companyName = trim((string) \App\Models\AppSetting::getValue('company_name', 'CV. PUSTAKA GRAFIKA'));
        $companyAddress = \App\Support\PrintTextFormatter::wrapWords(trim((string) \App\Models\AppSetting::getValue('company_address', '')), 5);
        $companyPhone = trim((string) \App\Models\AppSetting::getValue('company_phone', ''));
        $companyEmail = trim((string) \App\Models\AppSetting::getValue('company_email', ''));
        $companyNotes = trim((string) \App\Models\AppSetting::getValue('company_notes', ''));
        $companyInvoiceNotes = trim((string) \App\Models\AppSetting::getValue('company_invoice_notes', ''));
        $reportHeaderText = trim((string) \App\Models\AppSetting::getValue('report_header_text', ''));
        $printNotes = \App\Support\PrintTextFormatter::normalizeMultiline((string) ($transaction->notes ?: $companyInvoiceNotes));
        $totalQty = (int) round((float) $transaction->items->sum('quantity'), 0);
        $totalWeight = (float) $transaction->items->sum(fn($item) => (float) ($item->weight ?? 0));
        $totalBeforeVat = (int) round((float) ($transaction->subtotal_before_tax ?? $transaction->total), 0);
        $totalVat = (int) round((float) ($transaction->total_tax ?? 0), 0);
        $supplierAddress = \App\Support\PrintTextFormatter::wrapWords((string) ($transaction->supplier?->address ?: ''), 4);
        $companyDetailLines = collect([$companyAddress, $companyPhone, $companyEmail, $companyNotes])
            ->filter(fn (string $value): bool => $value !== '')
            ->values();
        $companyLogoSrc = \App\Support\PrintLogoDataUri::resolveForPrint((string) $companyLogoPath, empty($isPdf));
        $supplierInvoicePhotoSrc = null;

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
            <div class="meta-line"><strong>Semester</strong><span>:</span><span class="meta-value">{{ $transaction->semester_period ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.note_number') }}</strong><span>:</span><span class="meta-value">{{ $transaction->note_number ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.supplier') }}</strong><span>:</span><span class="meta-value">{{ $transaction->supplier?->name ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.phone') }}</strong><span>:</span><span class="meta-value">{{ $transaction->supplier?->phone ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.address') }}</strong><span>:</span><span class="meta-value">{{ $supplierAddress !== '' ? $supplierAddress : '-' }}</span></div>
        </div>
    </div>

    <table class="line-items">
        <thead>
        <tr>
            <th style="width: 4%">{{ __('txn.no') }}</th>
            <th>{{ __('txn.name') }}</th>
            <th style="width: 7%">{{ __('txn.unit') }}</th>
            <th class="num" style="width: 7">{{ __('txn.qty') }}</th>
            <th class="num" style="width: 7%">{{ __('txn.weight') }}</th>
            <th class="num" style="width: 10%">{{ __('txn.price') }}</th>
            <th class="num" style="width: 8%">{{ __('txn.vat_percent_short') }}</th>
            <th class="num" style="width: 15%">{{ __('txn.subtotal') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($transaction->items as $item)
            @php
                $unitCost = (int) round((float) $item->unit_cost, 0);
                $taxPercent = (float) ($item->tax_percent ?? 0);
                $lineTotal = (int) round((float) $item->line_total, 0);
                $unitCostText = $unitCost > 0 ? 'Rp ' . number_format($unitCost, 0, ',', '.') : '';
                $lineTotalText = $lineTotal > 0 ? 'Rp ' . number_format($lineTotal, 0, ',', '.') : '';
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->product_name }}</td>
                <td>{{ $item->unit ?: '-' }}</td>
                <td class="num">{{ (int) round((float) $item->quantity, 0) }}</td>
                <td class="num">{{ $item->weight !== null ? number_format((float) $item->weight, 3, ',', '.') : '-' }}</td>
                <td class="num">{{ $unitCostText }}</td>
                <td class="num">{{ number_format($taxPercent, 0, ',', '.') }}%</td>
                <td class="num">{{ $lineTotalText }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="table-summary">
        <div class="notes-box">
            <div><strong>{{ __('txn.notes') }}:</strong></div>
            <div class="notes-content">{{ $printNotes !== '' ? $printNotes : '-' }}</div>
        </div>
        <div class="qty-box">
            <table>
                <tr><td style="width: 72px;">{{ __('txn.summary_total_qty') }}</td><td style="width: 58px;">{{ number_format($totalQty, 0, ',', '.') }}</td></tr>
                <tr><td style="width: 72px;">{{ __('txn.total_weight') }}</td><td style="width: 58px;">{{ number_format($totalWeight, 3, ',', '.') }}</td></tr>
            </table>
        </div>
        <table class="total-box">
            <tr><td style="width: 50%;">{{ __('txn.total_before_vat') }}</td><td class="num" style="width: 50%;">Rp {{ number_format($totalBeforeVat, 0, ',', '.') }}</td></tr>
            <tr><td style="width: 50%;">{{ __('txn.vat_total') }}</td><td class="num" style="width: 50%;">Rp {{ number_format($totalVat, 0, ',', '.') }}</td></tr>
            <tr><td style="width: 50%;">{{ __('txn.grand_total') }}</td><td class="num" style="width: 50%;">Rp {{ number_format((int) round((float) $transaction->total, 0), 0, ',', '.') }}</td></tr>
        </table>
    </div>
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
</div>
</body>
</html>





