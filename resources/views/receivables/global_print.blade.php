<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $printTitle ?? $title }}</title>
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 12px; line-height: 1.28; color: #111; font-weight: 600; }
        .container { max-width: 1180px; margin: 0 auto; }
        .no-print { margin-bottom: 10px; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: 1px solid #111; padding: 5px 6px; vertical-align: top; font-size: 12px; font-weight: 600; }
        .report-table thead th { background: #fff54f; font-weight: 800; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .total-row td { font-weight: 700; background: #2f74c8; color: #fff; }
        .head { margin-bottom: 8px; position: relative; min-height: 34px; }
        .head-title { text-align: left; font-size: 20px; font-weight: 800; text-transform: uppercase; margin-left: -18px; }
        .head-update { position: absolute; right: 0; bottom: 0; font-size: 13px; font-style: italic; font-weight: 800; }
        .invoice-wrap { width: 100%; }
        .invoice-grid {
            display: grid;
            grid-template-columns: minmax(0, 48%) minmax(0, 22%) minmax(0, 30%);
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 10px;
        }
        .company-left { display: flex; gap: 10px; min-width: 0; }
        .logo { width: 52px; height: 72px; object-fit: contain; border: none; background: transparent; }
        .logo-fallback {
            width: 52px;
            height: 72px;
            border: none;
            background: transparent;
            display: grid;
            place-items: center;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1px;
            flex-shrink: 0;
        }
        .company-name { font-size: 15px; font-weight: 800; text-transform: uppercase; line-height: 1.15; white-space: nowrap; }
        .company-meta { white-space: pre-line; margin-top: 3px; font-size: 12px; line-height: 1.35; font-weight: 600; }
        .invoice-title { font-size: 24px; font-weight: 700; text-align: left; margin-top: 36px; margin-left: -18px; }
        .customer-meta { margin-top: 10px; width: 62%; }
        .customer-meta table { width: 100%; border-collapse: collapse; }
        .customer-meta td { padding: 2px 4px 2px 0; vertical-align: top; }
        .customer-meta .label { width: 110px; }
        .customer-meta .sep { width: 12px; text-align: center; }
        .right-info { text-align: right; font-style: italic; font-weight: 700; }
        .invoice-table { width: 74%; margin-top: 10px; border-collapse: collapse; }
        .invoice-table th, .invoice-table td { border: 1px solid #111; padding: 6px 6px; }
        .invoice-table thead th { background: #efefef; text-align: center; font-weight: 700; }
        .invoice-table td.num { text-align: right; white-space: nowrap; }
        .invoice-total { width: 74%; margin-top: 8px; border-collapse: collapse; }
        .invoice-total td { border: 1px solid #111; padding: 6px; font-weight: 700; }
        .invoice-total .label { width: 72%; text-align: center; background: #efefef; }
        .invoice-total .value { width: 28%; text-align: right; }
        .notes-section { margin-top: 14px; width: 74%; }
        .notes-label { font-weight: 700; margin-bottom: 4px; }
        .notes-line, .transfer-line { white-space: pre-line; margin-bottom: 2px; }
        @media print {
            .no-print { display: none; }
            body { margin: 4mm; font-size: 12px; line-height: 1.28; font-weight: 600; }
        }
    </style>
</head>
<body>
<div class="container">
    @if(empty($isPdf))
        <div class="no-print"><button onclick="window.print()">{{ __('report.print_save_pdf') }}</button></div>
    @endif

    @if($selectedCustomer)
        @php
            $companyName = trim((string) ($companyName ?? 'CV. PUSTAKA GRAFIKA'));
            $companyAddress = \App\Support\PrintTextFormatter::wrapWords(trim((string) ($companyAddress ?? '')), 5);
            $companyPhone = trim((string) ($companyPhone ?? ''));
            $companyEmail = trim((string) ($companyEmail ?? ''));
            $customerAddress = \App\Support\PrintTextFormatter::wrapWords(trim((string) ($selectedCustomer->address ?? '')), 4);
            $notesText = \App\Support\PrintTextFormatter::wrapWords(trim((string) ($companyInvoiceNotes ?? '')), 4);
            $transferText = trim((string) ($companyTransferAccounts ?? ''));
            $companyLogoSrc = \App\Support\PrintLogoDataUri::resolveForPrint((string) ($companyLogoPath ?? ''), empty($isPdf));
            $blankRows = max(0, 7 - $customerInvoiceRows->count());
        @endphp

        <div class="invoice-wrap">
            <div class="invoice-grid">
                <div>
                    <div class="company-left">
                        @if($companyLogoSrc)
                            <img class="logo" src="{{ $companyLogoSrc }}" alt="Logo">
                        @else
                            <div class="logo-fallback">PG</div>
                        @endif
                        <div>
                            <div class="company-name">{{ $companyName }}</div>
                            <div class="company-meta">{{ collect([$companyAddress, $companyPhone, $companyEmail])->filter()->implode("\n") }}</div>
                        </div>
                    </div>
                    <div class="customer-meta">
                        <table>
                            <tbody>
                            <tr>
                                <td class="label">Konsumen</td>
                                <td class="sep">:</td>
                                <td>{{ strtoupper((string) $selectedCustomer->name) }}</td>
                            </tr>
                            <tr>
                                <td class="label">Alamat</td>
                                <td class="sep">:</td>
                                <td>{{ $customerAddress !== '' ? strtoupper($customerAddress) : '-' }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="invoice-title">Invoice</div>
                <div class="right-info">Update : {{ now()->translatedFormat('j F Y') }}</div>
            </div>

            <table class="invoice-table">
                <colgroup>
                    <col style="width: 10%;">
                    <col style="width: 60%;">
                    <col style="width: 30%;">
                </colgroup>
                <thead>
                <tr>
                    <th>No.</th>
                    <th>Deskripsi</th>
                    <th>Nominal</th>
                </tr>
                </thead>
                <tbody>
                @foreach($customerInvoiceRows as $index => $invoiceRow)
                    <tr>
                        <td style="text-align:center;">{{ $index + 1 }}</td>
                        <td>{{ strtoupper((string) ($invoiceRow['description'] ?? '')) }}</td>
                        <td class="num">Rp {{ number_format((int) ($invoiceRow['nominal'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                @for($i = 0; $i < $blankRows; $i++)
                    <tr>
                        <td style="text-align:center;">{{ $customerInvoiceRows->count() + $i + 1 }}</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                @endfor
                </tbody>
            </table>

            <table class="invoice-total">
                <tbody>
                <tr>
                    <td class="label">TOTAL</td>
                    <td class="value">Rp {{ number_format((int) $customerInvoiceTotal, 0, ',', '.') }}</td>
                </tr>
                </tbody>
            </table>

            @if($notesText !== '')
                <div class="notes-section">
                    <div class="notes-label">Note :</div>
                    @foreach(preg_split('/\r\n|\r|\n/', $notesText) ?: [] as $line)
                        @if(trim($line) !== '')
                            <div class="notes-line">{{ $line }}</div>
                        @endif
                    @endforeach
                </div>
            @endif

            @if($transferText !== '')
                <div class="notes-section">
                    <div class="notes-label">Transfer via :</div>
                    @foreach(preg_split('/\r\n|\r|\n/', $transferText) ?: [] as $line)
                        @if(trim($line) !== '')
                            <div class="transfer-line">{{ $line }}</div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <div class="head">
            <div class="head-title">{{ $printTitle ?? $title }}</div>
            <div class="head-update">Update : {{ now()->translatedFormat('j F Y') }}</div>
        </div>

        <table class="report-table">
            <colgroup>
                <col style="width: 4%;">
                <col style="width: 18%;">
                <col style="width: 16%;">
                <col style="width: 29%;">
                @foreach($semesterCodes as $semesterCode)
                    <col style="width: {{ count($semesterCodes) > 0 ? max(10, (21 / count($semesterCodes))) : 12 }}%;">
                @endforeach
                <col style="width: 12%;">
            </colgroup>
            <thead>
            <tr>
                <th rowspan="2">NO.</th>
                <th rowspan="2">NAMA PELANGGAN</th>
                <th rowspan="2">KOTA</th>
                <th rowspan="2">ALAMAT</th>
                <th colspan="{{ max(1, count($semesterHeaders)) }}">PIUTANG</th>
                <th rowspan="2">TOTAL PIUTANG</th>
            </tr>
            <tr>
                @forelse($semesterHeaders as $semesterHeader)
                    <th>{{ strtoupper($semesterHeader) }}</th>
                @empty
                    <th>{{ __('report.columns.semester') }}</th>
                @endforelse
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td style="text-align:center;">{{ $index + 1 }}</td>
                    <td>{{ strtoupper($row['name']) }}</td>
                    <td>{{ strtoupper($row['city']) }}</td>
                    <td>{{ strtoupper($row['address']) }}</td>
                    @foreach($semesterCodes as $semesterCode)
                        <td class="num">Rp {{ number_format((int) ($row['semester_totals'][$semesterCode] ?? 0), 0, ',', '.') }}</td>
                    @endforeach
                    <td class="num">Rp {{ number_format((int) ($row['total_outstanding'] ?? 0), 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 5 + count($semesterCodes) }}">{{ __('receivable.global_no_data') }}</td></tr>
            @endforelse
            </tbody>
            <tfoot>
            <tr class="total-row">
                <td colspan="4">{{ __('receivable.semester_total') }}</td>
                @foreach($semesterCodes as $semesterCode)
                    <td class="num">Rp {{ number_format((int) ($totals['per_semester'][$semesterCode] ?? 0), 0, ',', '.') }}</td>
                @endforeach
                <td class="num">Rp {{ number_format((int) ($totals['grand_total'] ?? 0), 0, ',', '.') }}</td>
            </tr>
            </tfoot>
        </table>
    @endif
</div>
</body>
</html>

