<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('receivable.customer_bill_title') }} - {{ $customer->name }}</title>
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 11px; line-height: 1.2; color: #111; }
        .container { max-width: 900px; margin: 0 auto; }
        .head { display: grid; grid-template-columns: minmax(0, 1.7fr) auto minmax(0, 1fr); align-items: flex-start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 12px; }
        .company-left { display: flex; gap: 8px; min-width: 0; }
        .logo { width: 40px; height: 60px; object-fit: contain; border: none; padding: 0; background: transparent; }
        .company-name { font-size: 17px; font-weight: 700; text-transform: uppercase; line-height: 1.1; }
        .company-meta { margin-top: 2px; white-space: pre-line; }
        .doc-center { min-width: 220px; text-align: center; align-self: center; justify-self: center; margin-left: -36px; }
        .doc-title { font-size: 18px; font-weight: 700; text-transform: uppercase; line-height: 1.1; }
        .doc-number { margin-top: 2px; }
        .doc-right { font-size: 11px; line-height: 1.25; min-width: 210px; justify-self: end; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #111; padding: 4px; }
        th { text-align: center; background: #efefef; }
        td.num { text-align: right; white-space: nowrap; }
        .total-row td { font-weight: 700; background: #f7f7f7; }
        .total-balance-row td { font-weight: 700; }
        .notes-wrap { margin-top: 10px; }
        .notes-title { font-weight: 700; margin-bottom: 4px; }
        .notes-line { margin-bottom: 2px; white-space: pre-line; }
        .transfer-wrap { margin-top: 10px; }
        .transfer-title { font-weight: 700; margin-bottom: 4px; }
        .transfer-line { white-space: pre-line; }
        .muted { color: #444; }
        .pdf-mode { font-size: 10px; }
        .pdf-mode .container { max-width: 100%; }
        .pdf-mode .head { display: table; width: 100%; table-layout: fixed; }
        .pdf-mode .company-left,
        .pdf-mode .doc-center,
        .pdf-mode .doc-right { display: table-cell; vertical-align: top; }
        .pdf-mode .company-left { width: 48%; padding-right: 8px; }
        .pdf-mode .doc-center { width: 18%; padding: 0 6px; margin-left: 0; }
        .pdf-mode .doc-right { width: 34%; min-width: 0; padding-left: 8px; }
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
        $companyName = trim((string) ($companyName ?? 'CV. PUSTAKA GRAFIKA'));
        $companyAddress = trim((string) ($companyAddress ?? ''));
        $companyPhone = trim((string) ($companyPhone ?? ''));
        $companyEmail = trim((string) ($companyEmail ?? ''));
        $companyNotes = trim((string) ($companyNotes ?? ''));
        $companyLogoPath = (string) ($companyLogoPath ?? '');
        $notesText = trim((string) ($companyInvoiceNotes ?? ''));
        if ($notesText === '') {
            $notesText = trim((string) ($companyBillingNote ?? ''));
        }
        if ($notesText === '') {
            $notesText = trim((string) ($companyNotes ?? ''));
        }
        $transferText = trim((string) ($companyTransferAccounts ?? ''));
        $maxBlankRows = 3;
        $companyLogoSrc = null;
        $companyMetaLines = collect([
            $companyAddress,
            $companyPhone !== '' ? 'Telp: '.$companyPhone : '',
            $companyEmail !== '' ? 'Email: '.$companyEmail : '',
            $companyNotes,
        ])->filter(fn (string $line): bool => trim($line) !== '');

        if ($companyLogoPath !== '') {
            $absoluteLogoPath = public_path('storage/'.$companyLogoPath);
            if (is_file($absoluteLogoPath)) {
                $mimeType = mime_content_type($absoluteLogoPath) ?: 'image/png';
                $companyLogoSrc = 'data:'.$mimeType.';base64,'.base64_encode(file_get_contents($absoluteLogoPath));
            }
        }
    @endphp

    @if(empty($isPdf))
        <div class="no-print" style="margin-bottom: 10px;">
            <button onclick="window.print()">{{ __('txn.print') }}</button>
        </div>
    @endif

    <div class="head">
        <div class="company-left">
            @if($companyLogoSrc)
                <img class="logo" src="{{ $companyLogoSrc }}" alt="Logo">
            @endif
            <div>
                <div class="company-name">{{ $companyName !== '' ? $companyName : 'CV. PUSTAKA GRAFIKA' }}</div>
                @if($companyMetaLines->isNotEmpty())
                    <div class="company-meta">{{ $companyMetaLines->implode("\n") }}</div>
                @endif
            </div>
        </div>
        <div class="doc-center">
            <div class="doc-title">{{ __('receivable.customer_bill_title') }}</div>
            <div class="doc-number">{{ __('txn.no') }}: {{ $customer->code ?: $customer->id }}</div>
        </div>
        <div class="doc-right">
            <div><strong>{{ __('receivable.print_date') }}</strong> : {{ now()->format('d-m-Y') }}</div>
            @if($selectedSemester)
                <div><strong>{{ __('txn.semester_period') }}</strong> : {{ $selectedSemester }}</div>
            @endif
            <div><strong>{{ __('receivable.customer') }}</strong> : {{ $customer->name }}</div>
            <div><strong>{{ __('txn.phone') }}</strong> : {{ $customer->phone ?: '-' }}</div>
            <div><strong>{{ __('txn.address') }}</strong> : {{ $customer->address ?: '-' }}</div>
            <div><strong>{{ __('txn.city') }}</strong> : {{ $customer->city ?: '-' }}</div>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 14%;">{{ __('receivable.bill_date') }}</th>
            <th style="width: 24%;">{{ __('receivable.bill_proof_number') }}</th>
            <th style="width: 14%;">{{ __('receivable.bill_credit_sales') }}</th>
            <th style="width: 14%;">{{ __('receivable.bill_installment_payment') }}</th>
            <th style="width: 14%;">{{ __('receivable.bill_sales_return') }}</th>
            <th style="width: 20%;">{{ __('receivable.bill_running_balance') }}</th>
        </tr>
        </thead>
        <tbody>
        @if($rows->isEmpty())
            <tr>
                <td colspan="6">{{ __('receivable.no_outstanding_invoices') }}</td>
            </tr>
        @else
            @foreach($rows as $row)
                @php $isOpening = ($row['date_label'] ?? '') === __('receivable.bill_opening_balance'); @endphp
                @if($isOpening)
                    <tr>
                        <td>{{ $row['date_label'] ?? '' }}</td>
                        <td colspan="4"></td>
                        <td class="num">Rp {{ number_format((int) round((float) ($row['running_balance'] ?? 0)), 0, ',', '.') }}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $row['date_label'] ?? '' }}</td>
                        <td>
                            {{ $row['proof_number'] ?? '' }}
                            @if((int) ($row['adjustment_amount'] ?? 0) !== 0)
                                <br>
                                <span style="font-size:10px;">
                                    ({{ (int) ($row['adjustment_amount'] ?? 0) > 0 ? '+' : '-' }}Rp {{ number_format(abs((int) ($row['adjustment_amount'] ?? 0)), 0, ',', '.') }})
                                </span>
                            @endif
                        </td>
                        <td class="num">Rp {{ number_format((int) round((float) ($row['credit_sales'] ?? 0)), 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((int) round((float) ($row['installment_payment'] ?? 0)), 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((int) round((float) ($row['sales_return'] ?? 0)), 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((int) round((float) ($row['running_balance'] ?? 0)), 0, ',', '.') }}</td>
                    </tr>
                @endif
            @endforeach
        @endif
        @for($i = 0; $i < $maxBlankRows; $i++)
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td class="num">&nbsp;</td>
                <td class="num">&nbsp;</td>
                <td class="num">&nbsp;</td>
                <td class="num">&nbsp;</td>
            </tr>
        @endfor
        <tr class="total-row">
            <td colspan="2" style="text-align:center;">{{ __('receivable.bill_total') }}</td>
            <td class="num">Rp {{ number_format((int) round((float) ($totals['credit_sales'] ?? 0)), 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format((int) round((float) ($totals['installment_payment'] ?? 0)), 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format((int) round((float) ($totals['sales_return'] ?? 0)), 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format((int) round((float) ($totals['running_balance'] ?? 0)), 0, ',', '.') }}</td>
        </tr>
        <tr class="total-balance-row">
            <td colspan="3"></td>
            <td colspan="2" style="text-align:right;">{{ __('receivable.bill_total_receivable') }}</td>
            <td class="num">Rp {{ number_format((int) round((float) ($totals['running_balance'] ?? 0)), 0, ',', '.') }}</td>
        </tr>
        </tbody>
    </table>

    @if(($schoolBreakdown ?? collect())->isNotEmpty())
        <div style="margin-top: 12px; font-weight: 700;">{{ __('receivable.school_breakdown_title') }}</div>
        @foreach($schoolBreakdown as $group)
            <div style="margin-top: 8px; font-weight: 700;">
                {{ __('receivable.school_name') }}: {{ $group['school_name'] ?? '-' }}
                ({{ __('receivable.school_city') }}: {{ $group['school_city'] ?? '-' }})
            </div>
            <table style="margin-top: 4px;">
                <thead>
                <tr>
                    <th style="width: 16%;">{{ __('receivable.bill_date') }}</th>
                    <th style="width: 28%;">{{ __('receivable.bill_proof_number') }}</th>
                    <th style="width: 18%;">{{ __('receivable.school_invoice_total') }}</th>
                    <th style="width: 18%;">{{ __('receivable.school_paid_total') }}</th>
                    <th style="width: 20%;">{{ __('receivable.school_balance_total') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach(($group['rows'] ?? collect()) as $row)
                    <tr>
                        <td>{{ $row['date_label'] ?? '-' }}</td>
                        <td>{{ $row['invoice_number'] ?? '-' }}</td>
                        <td class="num">Rp {{ number_format((int) round((float) ($row['invoice_total'] ?? 0)), 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((int) round((float) ($row['paid_total'] ?? 0)), 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((int) round((float) ($row['balance_total'] ?? 0)), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2" style="text-align:center;">{{ __('receivable.bill_total') }}</td>
                    <td class="num">Rp {{ number_format((int) round((float) (($group['totals']['invoice_total'] ?? 0))), 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format((int) round((float) (($group['totals']['paid_total'] ?? 0))), 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format((int) round((float) (($group['totals']['balance_total'] ?? 0))), 0, ',', '.') }}</td>
                </tr>
                </tbody>
            </table>
        @endforeach
    @endif

    @if($notesText !== '')
        <div class="notes-wrap">
            <div class="notes-title">{{ __('receivable.note_label') }} :</div>
            @foreach(preg_split('/\r\n|\r|\n/', $notesText) ?: [] as $line)
                @if(trim($line) !== '')
                    <div class="notes-line">{{ $line }}</div>
                @endif
            @endforeach
        </div>
    @endif

    @if($transferText !== '')
        <div class="transfer-wrap">
            <div class="transfer-title">{{ __('receivable.transfer_via_label') }} :</div>
            @foreach(preg_split('/\r\n|\r|\n/', $transferText) ?: [] as $line)
                @if(trim($line) !== '')
                    <div class="transfer-line">{{ $line }}</div>
                @endif
            @endforeach
        </div>
    @endif

</div>
</body>
</html>

