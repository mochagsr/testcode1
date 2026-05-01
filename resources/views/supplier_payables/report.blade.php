<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('supplier_payable.report_title') }}</title>
    <style>
        @include('partials.print.paper_a4')
        body { font-family: "Courier New", Courier, monospace; font-size: 12px; line-height: 1.28; color: #111; font-weight: 600; }
        .container { max-width: 900px; margin: 0 auto; }
        .company-head { display: grid; grid-template-columns: minmax(0, 48%) minmax(180px, 22%) minmax(0, 30%); align-items: flex-start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 12px; }
        .company-brand { display: flex; align-items: flex-start; gap: 10px; min-width: 0; }
        .company-logo { width: 40px; height: 60px; border: none; display: grid; place-items: center; overflow: hidden; flex-shrink: 0; }
        .company-logo img { width: 100%; height: 100%; object-fit: contain; }
        .company-name { font-size: 15px; font-weight: 800; letter-spacing: 0; margin-bottom: 2px; line-height: 1.15; text-transform: uppercase; white-space: nowrap; }
        .company-detail { font-size: 12px; line-height: 1.35; white-space: pre-line; font-weight: 600; }
        .doc-title-center { font-size: 12px; line-height: 1.3; text-align: center; align-self: center; justify-self: start; font-weight: 700; margin-left: -28px; }
        .doc-meta-right { font-size: 12px; line-height: 1.3; max-width: 270px; justify-self: end; width: 100%; margin-left: auto; font-weight: 700; }
        .doc-meta-right .meta-line { display: grid; grid-template-columns: 76px 8px minmax(0, 1fr); align-items: start; }
        .doc-meta-right .meta-label { font-weight: 700; }
        .doc-meta-right .meta-value { white-space: pre-line; word-break: break-word; overflow-wrap: anywhere; }
        .section-title { font-weight: 700; margin: 12px 0 6px; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: 1px solid #111; padding: 5px 6px; vertical-align: top; font-size: 12px; font-weight: 600; }
        .report-table thead th { text-align: center; font-weight: 800; }
        .report-table td.num, .report-table th.num { text-align: right !important; white-space: nowrap; }
        .report-table td:first-child, .report-table th:first-child { text-align: center; width: 6%; }
        .print-actions { margin-bottom: 8px; }
        @media print { .print-actions { display: none; } }
    </style>
</head>
<body>
@php
    $settings = \App\Models\AppSetting::getValues([
        'company_logo_path' => null,
        'company_name' => '',
        'company_address' => '',
        'company_phone' => '',
        'company_email' => '',
    ]);
    $companyLogoSrc = \App\Support\PrintLogoDataUri::resolveForPrint((string) ($settings['company_logo_path'] ?? ''), empty($isPdf));
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
        <div class="company-brand">
            <div class="company-logo">
                @if($companyLogoSrc)
                    <img src="{{ $companyLogoSrc }}" alt="Logo">
                @else
                    PG
                @endif
            </div>
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
        </div>
        <div class="doc-title-center">
            <div style="font-size:20px; font-weight:700;">{{ __('supplier_payable.report_title') }}</div>
            <div>{{ __('supplier_payable.report_subtitle') }}</div>
        </div>
        <div class="doc-meta-right">
            <div class="meta-line"><div class="meta-label">{{ __('txn.supplier') }}</div><div>:</div><div class="meta-value">{{ $selectedSupplier?->name ?: __('supplier_payable.all_suppliers') }}</div></div>
            <div class="meta-line"><div class="meta-label">{{ __('supplier_payable.year_label') }}</div><div>:</div><div class="meta-value">{{ $selectedYear ?: __('supplier_payable.all_years') }}</div></div>
            <div class="meta-line"><div class="meta-label">{{ __('supplier_payable.month_label') }}</div><div>:</div><div class="meta-value">{{ $selectedMonthLabel ?: __('supplier_payable.all_months') }}</div></div>
            <div class="meta-line"><div class="meta-label">{{ __('txn.date') }}</div><div>:</div><div class="meta-value">{{ now()->format('d-m-Y H:i:s') }}</div></div>
        </div>
    </div>

    <table class="report-table">
        <thead>
        <tr>
            <th>{{ __('txn.no') }}</th>
            <th>{{ __('txn.supplier') }}</th>
            <th class="num">{{ __('receivable.total_debit') }}</th>
            <th class="num">{{ __('receivable.total_credit') }}</th>
            <th class="num">{{ __('supplier_payable.final_outstanding') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($summarySuppliers as $index => $supplier)
            @php
                $supplierId = (int) $supplier->id;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $supplier->name }}</td>
                <td class="num">Rp {{ number_format((int) ($summaryDebitMap[$supplierId] ?? 0), 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format((int) ($summaryCreditMap[$supplierId] ?? 0), 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format((int) ($summaryBalanceMap[$supplierId] ?? ($supplier->outstanding_payable ?? 0)), 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="text-align:center;">{{ __('supplier_payable.no_data') }}</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="4" class="num" style="font-weight:700;">{{ __('supplier_payable.final_outstanding') }}</td>
            <td class="num" style="font-weight:700;">Rp {{ number_format((int) $totalOutstanding, 0, ',', '.') }}</td>
        </tr>
        </tbody>
    </table>

    @if($selectedSupplier && $ledgerRows->isNotEmpty())
        <div class="section-title">{{ __('supplier_payable.mutation') }}</div>
        <table class="report-table">
            <thead>
            <tr>
                <th style="width:12%;">{{ __('txn.date') }}</th>
                <th>{{ __('receivable.description') }}</th>
                <th class="num" style="width:16%;">{{ __('receivable.debit') }}</th>
                <th class="num" style="width:16%;">{{ __('receivable.credit') }}</th>
                <th class="num" style="width:18%;">{{ __('receivable.balance') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($ledgerRows as $row)
                <tr>
                    <td>{{ $row->entry_date?->format('d-m-Y') ?: '-' }}</td>
                    <td>{{ $row->description ?: '-' }}</td>
                    <td class="num">Rp {{ number_format((int) $row->debit, 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format((int) $row->credit, 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format((int) $row->balance_after, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
</body>
</html>
