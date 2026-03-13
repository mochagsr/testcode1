<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('supplier_stock.report_title') }}</title>
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
        .section-title { font-weight: 700; margin: 12px 0 6px; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: 1px solid #111; padding: 4px 6px; vertical-align: top; }
        .report-table thead th { text-align: center; font-weight: 700; }
        .report-table td.num, .report-table th.num { text-align: right !important; white-space: nowrap; }
        .print-actions { margin-bottom: 8px; }
        @media print { .print-actions { display: none; } }
    </style>
</head>
<body>
@php
    $settings = \App\Models\AppSetting::getValues([
        'company_name' => '',
        'company_address' => '',
        'company_phone' => '',
        'company_email' => '',
    ]);
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
            <div style="font-size:20px; font-weight:700;">{{ __('supplier_stock.report_title') }}</div>
            <div>{{ __('supplier_stock.report_subtitle') }}</div>
        </div>
        <div class="doc-meta-right">
            <div class="meta-line"><div class="meta-label">{{ __('txn.supplier') }}</div><div>:</div><div class="meta-value">{{ $selectedSupplier?->name ?: __('supplier_stock.all_suppliers') }}</div></div>
            <div class="meta-line"><div class="meta-label">{{ __('txn.product') }}</div><div>:</div><div class="meta-value">{{ $selectedProductLabel }}</div></div>
            <div class="meta-line"><div class="meta-label">{{ __('txn.date_from') }}</div><div>:</div><div class="meta-value">{{ $dateFrom ? date('d-m-Y', strtotime($dateFrom)) : '-' }}</div></div>
            <div class="meta-line"><div class="meta-label">{{ __('txn.date_to') }}</div><div>:</div><div class="meta-value">{{ $dateTo ? date('d-m-Y', strtotime($dateTo)) : '-' }}</div></div>
        </div>
    </div>

    <div class="section-title">{{ __('supplier_stock.product_summary') }}</div>
    <table class="report-table">
        <thead>
        <tr>
            <th style="width:22%;">{{ __('txn.supplier') }}</th>
            <th style="width:16%;">{{ __('ui.category') }}</th>
            <th>{{ __('txn.name') }}</th>
            <th class="num" style="width:12%;">{{ __('supplier_stock.total_in') }}</th>
            <th class="num" style="width:12%;">{{ __('supplier_stock.total_out') }}</th>
            <th class="num" style="width:14%;">{{ __('supplier_stock.total_balance') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($summaryRows as $row)
            <tr>
                <td>{{ $row['supplier_name'] ?? '-' }}</td>
                <td>{{ $row['category_name'] ?? '-' }}</td>
                <td>{{ $row['product_name'] ?? '-' }}</td>
                <td class="num">{{ number_format((int) ($row['qty_in'] ?? 0), 0, ',', '.') }}</td>
                <td class="num">{{ number_format((int) ($row['qty_out'] ?? 0), 0, ',', '.') }}</td>
                <td class="num">{{ number_format((int) ($row['balance'] ?? 0), 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="text-align:center;">{{ __('supplier_stock.no_data') }}</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="3" class="num" style="font-weight:700;">{{ __('txn.total') }}</td>
            <td class="num" style="font-weight:700;">{{ number_format((int) ($totals['qty_in'] ?? 0), 0, ',', '.') }}</td>
            <td class="num" style="font-weight:700;">{{ number_format((int) ($totals['qty_out'] ?? 0), 0, ',', '.') }}</td>
            <td class="num" style="font-weight:700;">{{ number_format((int) ($totals['balance'] ?? 0), 0, ',', '.') }}</td>
        </tr>
        </tbody>
    </table>

    @if($selectedSupplier && $movements->isNotEmpty())
        <div class="section-title">{{ __('supplier_stock.mutation_title') }}</div>
        <table class="report-table">
            <thead>
            <tr>
                <th style="width:11%;">{{ __('txn.date') }}</th>
                <th style="width:15%;">{{ __('ui.category') }}</th>
                <th style="width:22%;">{{ __('txn.product') }}</th>
                <th>{{ __('supplier_stock.description') }}</th>
                <th class="num" style="width:10%;">{{ __('supplier_stock.in') }}</th>
                <th class="num" style="width:10%;">{{ __('supplier_stock.out') }}</th>
                <th class="num" style="width:12%;">{{ __('supplier_stock.balance') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($movements as $row)
                <tr>
                    <td>{{ date('d-m-Y', strtotime((string) $row['event_date'])) }}</td>
                    <td>{{ $row['category_name'] ?? '-' }}</td>
                    <td>
                        @if((string) ($row['product_code'] ?? '') !== '')
                            {{ $row['product_code'] }} -
                        @endif
                        {{ $row['product_name'] ?? '-' }}
                    </td>
                    <td>
                        {{ $row['description'] ?? '-' }}
                        @if((int) ($row['reference_id'] ?? 0) > 0 && (string) ($row['reference_number'] ?? '') !== '')
                            <div>{{ $row['reference_number'] }}</div>
                        @endif
                    </td>
                    <td class="num">{{ (int) ($row['qty_in'] ?? 0) > 0 ? number_format((int) $row['qty_in'], 0, ',', '.') : '-' }}</td>
                    <td class="num">{{ (int) ($row['qty_out'] ?? 0) > 0 ? number_format((int) $row['qty_out'], 0, ',', '.') : '-' }}</td>
                    <td class="num">{{ number_format((int) ($row['balance_after'] ?? 0), 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
</body>
</html>
