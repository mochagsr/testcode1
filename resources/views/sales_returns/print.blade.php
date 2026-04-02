<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('txn.print') }} {{ $salesReturn->return_number }}</title>
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 12px; line-height: 1.28; color: #111; font-weight: 600; }
        .container { max-width: 900px; margin: 0 auto; }
        .company-head { display: grid; grid-template-columns: minmax(0, 48%) minmax(180px, 22%) minmax(0, 30%); align-items: flex-start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 12px; }
        .company-left { display: flex; gap: 6px; min-width: 0; }
        .company-logo { width: 40px; height: 60px; border: none; display: grid; place-items: center; font-size: 11px; font-weight: 700; letter-spacing: 1px; overflow: hidden; flex-shrink: 0; }
        .company-logo-img { width: 100%; height: 100%; object-fit: contain; }
        .company-name { font-size: 15px; font-weight: 800; letter-spacing: 0; margin-bottom: 2px; line-height: 1.15; text-transform: uppercase; white-space: nowrap; }
        .company-detail { font-size: 12px; line-height: 1.35; white-space: pre-line; font-weight: 600; }
        .doc-title-center { font-size: 12px; line-height: 1.3; min-width: 180px; text-align: center; align-self: center; min-width: 0; font-weight: 700; }
        .doc-meta-right { font-size: 12px; line-height: 1.3; min-width: 180px; max-width: 270px; justify-self: end; width: 100%; margin-left: auto; font-weight: 700; }
        .doc-meta-right .meta-line { display: grid; grid-template-columns: 76px 8px minmax(0, 1fr); align-items: start; }
        .doc-meta-right .meta-value { white-space: pre-line; word-break: break-word; overflow-wrap: anywhere; }
        .doc-title { font-size: 20px; font-weight: 800; text-align: center; }
        .doc-number { text-align: center; margin-bottom: 4px; }
        .canceled-banner { margin: 8px 0 2px; padding: 4px 8px; border: 1px solid #111; text-align: center; font-weight: 700; letter-spacing: 0.6px; }
        @include('partials.print.table_styles')
        .table-summary { display: grid; grid-template-columns: minmax(0, 1fr) 140px 200px; align-items: flex-start; gap: 16px; margin-top: 12px; }
        .notes-box { line-height: 1.35; white-space: pre-line; word-break: break-word; overflow-wrap: anywhere; }
        .qty-box { width: 100%; table-layout: fixed; }
        .qty-box table,
        .total-box { margin-top: 0; }
        .qty-box td:first-child { font-weight: 700; background: #f7f7f7; width: 68%; }
        .qty-box td:last-child { width: 32%; text-align: right; font-weight: 700; white-space: nowrap; }
        .total-box { width: 100%; table-layout: fixed; }
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
        $printNotes = \App\Support\PrintTextFormatter::wrapWords($companyInvoiceNotes, 4);
        $totalQty = (int) round((float) $salesReturn->items->sum('quantity'), 0);
        $customerAddress = \App\Support\PrintTextFormatter::wrapWords((string) ($salesReturn->customer?->address ?: ''), 4);
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
            <div class="doc-title">{{ $reportHeaderText !== '' ? $reportHeaderText : __('txn.sales_returns_title') }}</div>
            <div class="doc-number">{{ __('txn.no') }}: {{ $salesReturn->return_number }}</div>
        </div>
        <div class="doc-meta-right">
            <div class="meta-line"><strong>{{ __('txn.date') }}</strong><span>:</span><span class="meta-value">{{ $salesReturn->return_date->format('d-m-Y') }}</span></div>
            <div class="meta-line"><strong>Semester</strong><span>:</span><span class="meta-value">{{ $salesReturn->semester_period ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.name') }}</strong><span>:</span><span class="meta-value">{{ $salesReturn->customer?->name ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.phone') }}</strong><span>:</span><span class="meta-value">{{ $salesReturn->customer?->phone ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.city') }}</strong><span>:</span><span class="meta-value">{{ $salesReturn->customer?->city ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.address') }}</strong><span>:</span><span class="meta-value">{{ $customerAddress !== '' ? $customerAddress : '-' }}</span></div>
        </div>
    </div>
    @if($salesReturn->is_canceled)
        <div class="canceled-banner">{{ strtoupper(__('txn.status_canceled')) }}</div>
    @endif

    <table class="line-items">
        <thead>
        <tr>
            <th style="width: 6%">{{ __('txn.no') }}</th>
            <th>{{ __('txn.name') }}</th>
            <th class="num" style="width: 7%">{{ __('txn.qty') }}</th>
            <th class="num" style="width: 10%">{{ __('txn.price') }}</th>
            <th class="num" style="width: 15%">{{ __('txn.subtotal') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($salesReturn->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->product_name }}</td>
                <td class="num">{{ (int) round($item->quantity) }}</td>
                <td class="num">Rp {{ number_format((int) round($item->unit_price), 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format((int) round($item->line_total), 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="table-summary">
        <div class="notes-box">
            <strong>{{ __('txn.reason') }}:</strong> {{ $salesReturn->reason ?: '-' }}
            @if($printNotes !== '')
                <br><strong>{{ __('txn.notes') }}:</strong> {{ $printNotes }}
            @endif
        </div>
        <div class="qty-box">
            <table>
                <tr>
                    <td style="width: 68%;">{{ __('txn.summary_total_qty') }}</td>
                    <td style="width: 32%;">{{ number_format($totalQty, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
        <table class="total-box">
            <tr><td style="width: 58%;"><strong>{{ __('txn.total_return') }}</strong></td><td class="num" style="width: 42%;"><strong>Rp {{ number_format((int) round($salesReturn->total), 0, ',', '.') }}</strong></td></tr>
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


