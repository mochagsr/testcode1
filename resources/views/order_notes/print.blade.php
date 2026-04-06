<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('txn.print') }} {{ $note->note_number }}</title>
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
        .doc-title-center { font-size: 12px; line-height: 1.3; min-width: 180px; text-align: left; align-self: center; justify-self: start; min-width: 0; font-weight: 700; margin-left: -18px; }
        .doc-meta-right { font-size: 11px; line-height: 1.25; min-width: 210px; max-width: 250px; width: 100%; margin-left: auto; }
        .doc-meta-right .meta-line { display: grid; grid-template-columns: 76px 8px minmax(0, 1fr); align-items: start; }
        .doc-meta-right .meta-value { white-space: pre-line; word-break: break-word; overflow-wrap: anywhere; }
        .doc-title { font-size: 20px; font-weight: 800; text-align: left; }
        .doc-number { text-align: left; margin-bottom: 4px; }
        .canceled-banner { margin: 8px 0 2px; padding: 4px 8px; border: 1px solid #111; text-align: center; font-weight: 700; letter-spacing: 0.6px; }
        @include('partials.print.table_styles')
        .table-summary { display: grid; grid-template-columns: minmax(0, 1fr) 140px; align-items: flex-start; gap: 16px; margin-top: 12px; }
        .notes-box { line-height: 1.35; white-space: pre-line; word-break: break-word; overflow-wrap: anywhere; }
        .qty-box { width: 100%; table-layout: fixed; }
        .qty-box table { margin-top: 0; }
        .qty-box td:first-child { font-weight: 700; background: #f7f7f7; width: 68%; }
        .qty-box td:last-child { width: 32%; text-align: right; font-weight: 700; white-space: nowrap; }
        .fulfillment-box { margin-top: 10px; }
        .fulfillment-box .section-title { font-weight: 800; margin-bottom: 6px; }
        .delivery-list { margin: 0; padding-left: 16px; }
        .delivery-list li { margin-bottom: 4px; }
        .delivery-empty { font-style: italic; color: #444; }
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
        $printNotes = \App\Support\PrintTextFormatter::wrapWords(trim((string) ($note->notes ?: $companyInvoiceNotes)), 4);
        $customerAddress = \App\Support\PrintTextFormatter::wrapWords((string) ($note->address ?: $note->customer?->address ?: ''), 4);
        $customerCity = trim((string) ($note->city ?: $note->customer?->city ?: ''));
        $customerDisplayName = trim((string) ($note->customer?->name ?: preg_replace('/\s*\([^)]+\)\s*$/', '', (string) $note->customer_name)));
        $totalQty = (int) round((float) $note->items->sum('quantity'), 0);
        $fulfilledTotal = collect($fulfillmentDetails['items'] ?? [])->sum(fn (array $item): int => (int) ($item['fulfilled_qty'] ?? 0));
        $remainingTotal = collect($fulfillmentDetails['items'] ?? [])->sum(fn (array $item): int => (int) ($item['remaining_qty'] ?? 0));
        $companyDetailLines = collect([$companyAddress, $companyPhone, $companyEmail, $companyNotes])
            ->filter(fn (string $value): bool => $value !== '')
            ->values();
        $companyLogoSrc = \App\Support\PrintLogoDataUri::resolve((string) $companyLogoPath);
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
            <div class="doc-title">{{ __('txn.order_notes_title') }}</div>
            <div class="doc-number">{{ __('txn.no') }}: {{ $note->note_number }}</div>
        </div>
        <div class="doc-meta-right">
            <div class="meta-line"><strong>{{ __('txn.date') }}</strong><span>:</span><span class="meta-value">{{ $note->note_date->format('d-m-Y') }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.name') }}</strong><span>:</span><span class="meta-value">{{ $customerDisplayName !== '' ? $customerDisplayName : '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.phone') }}</strong><span>:</span><span class="meta-value">{{ $note->customer_phone ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.city') }}</strong><span>:</span><span class="meta-value">{{ $customerCity !== '' ? $customerCity : '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.address') }}</strong><span>:</span><span class="meta-value">{{ $customerAddress !== '' ? $customerAddress : '-' }}</span></div>
        </div>
    </div>
    @if($note->is_canceled)
        <div class="canceled-banner">{{ strtoupper(__('txn.status_canceled')) }}</div>
    @endif

    <table class="line-items">
        <thead>
        <tr>
            <th style="width: 6%">{{ __('txn.no') }}</th>
            <th>{{ __('txn.name') }}</th>
            <th class="num" style="width: 9%">{{ __('txn.order_note_qty_ordered') }}</th>
            <th class="num" style="width: 9%">{{ __('txn.order_note_qty_fulfilled') }}</th>
            <th class="num" style="width: 9%">{{ __('txn.order_note_qty_remaining') }}</th>
            <th style="width: 31%">{{ __('txn.notes') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach(($fulfillmentDetails['items'] ?? []) as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item['product_name'] }}</td>
                <td class="num">{{ number_format((int) ($item['ordered_qty'] ?? 0), 0, ',', '.') }}</td>
                <td class="num">{{ number_format((int) ($item['fulfilled_qty'] ?? 0), 0, ',', '.') }}</td>
                <td class="num">{{ number_format((int) ($item['remaining_qty'] ?? 0), 0, ',', '.') }}</td>
                <td>{{ $item['notes'] ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="table-summary">
        <div class="notes-box">
            <strong>{{ __('txn.notes') }}:</strong> {{ $printNotes !== '' ? $printNotes : '-' }}
        </div>
        <div class="qty-box">
            <table>
                <tr>
                    <td style="width: 50%;">{{ __('txn.summary_total_qty') }}</td>
                    <td style="width: 40%;">{{ number_format($totalQty, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>{{ __('txn.order_note_qty_fulfilled') }}</td>
                    <td>{{ number_format($fulfilledTotal, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>{{ __('txn.order_note_qty_remaining') }}</td>
                    <td>{{ number_format($remainingTotal, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="fulfillment-box">
        <div class="section-title">{{ __('txn.order_note_delivery_history_title') }}</div>
        <table class="line-items">
            <thead>
            <tr>
                <th>{{ __('txn.name') }}</th>
                <th class="num" style="width: 10%">{{ __('txn.order_note_qty_ordered') }}</th>
                <th class="num" style="width: 10%">{{ __('txn.order_note_qty_fulfilled') }}</th>
                <th class="num" style="width: 10%">{{ __('txn.order_note_qty_remaining') }}</th>
                <th style="width: 40%">{{ __('txn.order_note_delivered_in_invoice') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach(($fulfillmentDetails['items'] ?? []) as $item)
                <tr>
                    <td>{{ $item['product_name'] }}</td>
                    <td class="num">{{ number_format((int) ($item['ordered_qty'] ?? 0), 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((int) ($item['fulfilled_qty'] ?? 0), 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((int) ($item['remaining_qty'] ?? 0), 0, ',', '.') }}</td>
                    <td>
                        @if(!empty($item['deliveries']))
                            <ul class="delivery-list">
                                @foreach($item['deliveries'] as $delivery)
                                    <li>{{ $delivery['invoice_number'] }} ({{ $delivery['invoice_date'] }}) - {{ __('txn.qty') }} {{ number_format((int) ($delivery['quantity'] ?? 0), 0, ',', '.') }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="delivery-empty">{{ __('txn.order_note_no_delivery_history') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
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
            <td class="signature-name">{{ $note->created_by_name ?: '________________' }}</td>
            <td>________________</td>
            <td>________________</td>
            <td>________________</td>
        </tr>
    </table>
</div>
</body>
</html>





