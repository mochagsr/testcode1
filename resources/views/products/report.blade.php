<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 11px; line-height: 1.2; color: #111; }
        .container { max-width: 900px; margin: 0 auto; }
        .company-head { display: grid; grid-template-columns: minmax(0, 42%) minmax(220px, 26%) minmax(0, 32%); align-items: flex-start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 18px; }
        .company-name { font-size: 16px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 1px; line-height: 1.2; text-transform: uppercase; }
        .company-detail { font-size: 11px; line-height: 1.3; white-space: pre-line; }
        .doc-title-center { font-size: 11px; line-height: 1.25; text-align: center; align-self: center; }
        .doc-meta-right { font-size: 11px; line-height: 1.25; max-width: 250px; justify-self: end; width: 100%; margin-left: auto; }
        .doc-meta-right .meta-line { display: grid; grid-template-columns: 76px 8px minmax(0, 1fr); align-items: start; }
        .doc-meta-right .meta-label { font-weight: 700; }
        .doc-meta-right .meta-value { white-space: pre-line; word-break: break-word; overflow-wrap: anywhere; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: 1px solid #111; padding: 4px 6px; vertical-align: top; }
        .report-table thead th { text-align: center; font-weight: 700; }
        .report-table td.num { text-align: right !important; white-space: nowrap; }
        .print-actions { margin-bottom: 8px; }
        @media print { .print-actions { display: none; } }
    </style>
</head>
<body>
@php
    $companyName = trim((string) ($settings['company_name'] ?? ''));
    $companyAddress = \App\Support\PrintTextFormatter::wrapWords(trim((string) ($settings['company_address'] ?? '')), 5);
@endphp
<div class="container">
    @if(empty($isPdf))
        <div class="print-actions">
            <button type="button" onclick="window.print()">{{ __('txn.print') }}</button>
        </div>
    @endif

    <div class="company-head">
        <div>
            <div class="company-name">{{ $companyName !== '' ? $companyName : '-' }}</div>
            <div class="company-detail">{{ $companyAddress !== '' ? $companyAddress : '-' }}</div>
            @if(($settings['company_phone'] ?? '') !== '')
                <div class="company-detail">{{ $settings['company_phone'] }}</div>
            @endif
            @if(($settings['company_email'] ?? '') !== '')
                <div class="company-detail">{{ $settings['company_email'] }}</div>
            @endif
        </div>
        <div class="doc-title-center">
            <div style="font-size:20px; font-weight:700;">{{ __('ui.products_title') }}</div>
            <div>{{ __('report.printed') }}</div>
        </div>
        <div class="doc-meta-right">
            <div class="meta-line"><div class="meta-label">{{ __('txn.date') }}</div><div>:</div><div class="meta-value">{{ $printedAt->format('d-m-Y H:i:s') }} WIB</div></div>
            <div class="meta-line"><div class="meta-label">{{ __('txn.search') }}</div><div>:</div><div class="meta-value">{{ $search !== '' ? $search : '-' }}</div></div>
            <div class="meta-line"><div class="meta-label">{{ __('txn.total') }}</div><div>:</div><div class="meta-value">{{ $products->count() }}</div></div>
        </div>
    </div>

    <table class="report-table">
        <thead>
        <tr>
            <th style="width:6%;">{{ __('txn.no') }}</th>
            <th style="width:16%;">{{ __('ui.code') }}</th>
            <th style="width:18%;">{{ __('ui.category') }}</th>
            <th>{{ __('ui.name') }}</th>
            <th style="width:12%;">{{ __('ui.stock') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($products as $index => $product)
            <tr>
                <td style="text-align:center;">{{ $index + 1 }}</td>
                <td>{{ $product->code ?: '-' }}</td>
                <td>{{ $product->category?->name ?: '-' }}</td>
                <td>{{ $product->name }}</td>
                <td class="num">{{ number_format((int) round((float) $product->stock), 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="text-align:center;">{{ __('ui.no_products') }}</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
