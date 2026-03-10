<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('txn.print') }} {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 11px; line-height: 1.2; color: #111; }
        .container { max-width: 900px; margin: 0 auto; }
        .company-head { display: grid; grid-template-columns: minmax(0, 42%) minmax(220px, 26%) minmax(0, 32%); align-items: flex-start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 18px; }
        .company-left { display: flex; gap: 8px; min-width: 0; }
        .company-logo { width: 40px; height: 60px; border: none; display: grid; place-items: center; font-size: 11px; font-weight: 700; letter-spacing: 1px; overflow: hidden; flex-shrink: 0; }
        .company-logo-img { width: 100%; height: 100%; object-fit: contain; }
        .company-name { font-size: 16px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 1px; line-height: 1.2; text-transform: uppercase; }
        .company-detail { font-size: 11px; line-height: 1.3; white-space: pre-line; }
        .doc-title-center { font-size: 11px; line-height: 1.25; min-width: 210px; text-align: center; align-self: center; min-width: 0; }
        .doc-meta-right { font-size: 11px; line-height: 1.25; min-width: 210px; max-width: 250px; justify-self: end; width: 100%; margin-left: auto; }
        .doc-meta-right .meta-line { display: grid; grid-template-columns: 76px 8px minmax(0, 1fr); align-items: start; }
        .doc-meta-right .meta-value { white-space: pre-line; word-break: break-word; overflow-wrap: anywhere; }
        .doc-title { font-size: 18px; font-weight: 700; text-align: center; }
        .doc-number { text-align: center; margin-bottom: 4px; }
        .canceled-banner { margin: 8px 0 2px; padding: 4px 8px; border: 1px solid #111; text-align: center; font-weight: 700; letter-spacing: 0.6px; }
        @include('partials.print.table_styles')
        .summary-row { display: grid; grid-template-columns: minmax(0, 1fr) 140px 280px; align-items: start; gap: 12px; margin-top: 10px; }
        .qty-box { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .qty-box td:first-child { font-weight: 700; background: #f7f7f7; width: 68%; }
        .qty-box td:last-child { width: 32%; text-align: right; font-weight: 700; white-space: nowrap; }
        .qty-box td,
        .total-box td { border: 1px solid #111; padding: 4px; }
        .total-box { width: 100%; border-collapse: collapse; }
        .total-box td { border: 1px solid #111; }
        .summary-spacer { min-height: 1px; }
        .notes-box { line-height: 1.35; white-space: pre-line; word-break: break-word; overflow-wrap: anywhere; }
        .signature-table { margin-top: 24px; }
        .signature-table th, .signature-table td { text-align: center; }
        .signature-space { height: 64px; border-top: none !important; border-bottom: none !important; }
        .signature-name { font-weight: 600; }
        @media print {
            .no-print { display: none; }
            body { margin: 4mm; font-size: 10px; }
        }
    </style>
</head>
<body>
<div class="container">
    @php
        $discountTotal = (float) $invoice->items->sum('discount');
        $hasCashOnCreate = $invoice->payments->contains(function ($payment) use ($invoice): bool {
            return strtolower((string) $payment->method) === 'cash'
                && optional($payment->payment_date)->format('Y-m-d') === optional($invoice->invoice_date)->format('Y-m-d')
                && (float) $payment->amount >= (float) $invoice->total;
        });
        $paymentMethodLabel = $hasCashOnCreate ? __('txn.cash') : __('txn.credit');
        $companyLogoPath = \App\Models\AppSetting::getValue('company_logo_path');
        $companyName = trim((string) \App\Models\AppSetting::getValue('company_name', 'CV. PUSTAKA GRAFIKA'));
        $companyAddress = \App\Support\PrintTextFormatter::wrapWords(trim((string) \App\Models\AppSetting::getValue('company_address', '')), 5);
        $companyPhone = trim((string) \App\Models\AppSetting::getValue('company_phone', ''));
        $companyEmail = trim((string) \App\Models\AppSetting::getValue('company_email', ''));
        $companyNotes = trim((string) \App\Models\AppSetting::getValue('company_notes', ''));
        $companyInvoiceNotes = trim((string) \App\Models\AppSetting::getValue('company_invoice_notes', ''));
        $reportHeaderText = trim((string) \App\Models\AppSetting::getValue('report_header_text', ''));
        $printNotes = \App\Support\PrintTextFormatter::wrapWords(trim((string) ($invoice->notes ?: $companyInvoiceNotes)), 4);
        $totalQty = (int) round((float) $invoice->items->sum('quantity'), 0);
        $customerAddress = \App\Support\PrintTextFormatter::wrapWords((string) ($invoice->customer?->address ?: ''), 4);
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
            <div class="doc-title">{{ $reportHeaderText !== '' ? $reportHeaderText : __('txn.sales_invoices_title') }}</div>
            <div class="doc-number">{{ __('txn.no') }}: {{ $invoice->invoice_number }}</div>
        </div>
        <div class="doc-meta-right">
            <div class="meta-line"><strong>{{ __('txn.date') }}</strong><span>:</span><span class="meta-value">{{ $invoice->invoice_date->format('d-m-Y') }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.due_date') }}</strong><span>:</span><span class="meta-value">{{ $invoice->due_date?->format('d-m-Y') ?: '-' }}</span></div>
            <div class="meta-line"><strong>Pembayaran</strong><span>:</span><span class="meta-value">{{ $paymentMethodLabel }}</span></div>
            <div class="meta-line"><strong>Semester</strong><span>:</span><span class="meta-value">{{ $invoice->semester_period ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.customer') }}</strong><span>:</span><span class="meta-value">{{ $invoice->customer?->name ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.phone') }}</strong><span>:</span><span class="meta-value">{{ $invoice->customer?->phone ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.city') }}</strong><span>:</span><span class="meta-value">{{ $invoice->customer?->city ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.address') }}</strong><span>:</span><span class="meta-value">{{ $customerAddress !== '' ? $customerAddress : '-' }}</span></div>
        </div>
    </div>
    @if($invoice->is_canceled)
        <div class="canceled-banner">{{ strtoupper(__('txn.status_canceled')) }}</div>
    @endif

    <table class="line-items">
        <thead>
        <tr>
            <th style="width: 4%">{{ __('txn.no') }}</th>
            <th>{{ __('txn.name') }}</th>
            <th class="num" style="width: 5%">{{ __('txn.qty') }}</th>
            <th class="num" style="width: 10%">{{ __('txn.price') }}</th>
            <th class="num" style="width: 8%">{{ __('txn.discount') }} (%)</th>
            <th class="num" style="width: 18%">{{ __('txn.subtotal') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($invoice->items as $item)
            @php
                $gross = (float) $item->quantity * (float) $item->unit_price;
                $discountPercent = $gross > 0 ? (float) $item->discount / $gross * 100 : 0;
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->product_name }}</td>
                <td class="num">{{ (int) round($item->quantity) }}</td>
                <td class="num">Rp {{ number_format((int) round($item->unit_price), 0, ',', '.') }}</td>
                <td class="num">{{ (int) round($discountPercent) > 0 ? ((int) round($discountPercent)).'%' : '-' }}</td>
                <td class="num">Rp {{ number_format((int) round($item->line_total), 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="summary-row">
        <div class="notes-box">
            <strong>{{ __('txn.notes') }}:</strong> {{ $printNotes !== '' ? $printNotes : '-' }}
        </div>
        <table class="qty-box">
            <tr>
                <td>{{ __('txn.summary_total_qty') }}</td>
                <td>{{ number_format($totalQty, 0, ',', '.') }}</td>
            </tr>
        </table>
        <table class="total-box">
            <tr><td>{{ __('txn.sub_total') }}</td><td class="num">Rp {{ number_format((int) round($invoice->subtotal), 0, ',', '.') }}</td></tr>
            <tr><td>{{ __('txn.discount') }}</td><td class="num">Rp {{ number_format((int) round($discountTotal), 0, ',', '.') }}</td></tr>
            <tr><td><strong>{{ __('txn.grand_total') }}</strong></td><td class="num"><strong>Rp {{ number_format((int) round($invoice->total), 0, ',', '.') }}</strong></td></tr>
        </table>
    </div>

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
            <td class="signature-name">{{ auth()->user()->name ?? '________________' }}</td>
            <td>________________</td>
            <td>________________</td>
            <td>________________</td>
        </tr>
    </table>
</div>
</body>
</html>


