<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportTitle ?? __('receivable.customer_bill_title') }} - {{ $customer->name }}</title>
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 12px; line-height: 1.28; color: #111; font-weight: 600; }
        .container { max-width: 900px; margin: 0 auto; }
        .head { display: grid; grid-template-columns: minmax(0, 48%) minmax(180px, 22%) minmax(0, 30%); align-items: flex-start; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 12px; }
        .company-left { display: flex; gap: 6px; min-width: 0; }
        .logo { width: 40px; height: 60px; object-fit: contain; border: none; padding: 0; background: transparent; }
        .company-name { font-size: 15px; font-weight: 800; text-transform: uppercase; line-height: 1.15; white-space: nowrap; }
        .company-meta { margin-top: 2px; white-space: pre-line; font-size: 12px; line-height: 1.35; font-weight: 600; }
        .doc-center { min-width: 0; text-align: center; align-self: center; justify-self: center; }
        .doc-title { font-size: 20px; font-weight: 800; text-transform: uppercase; line-height: 1.1; }
        .doc-number { margin-top: 2px; }
        .doc-right { font-size: 12px; line-height: 1.3; min-width: 180px; max-width: 270px; justify-self: end; width: 100%; margin-left: auto; font-weight: 700; }
        .doc-right .meta-line { display: grid; grid-template-columns: 76px 8px minmax(0, 1fr); align-items: start; }
        .doc-right .meta-value { white-space: pre-line; word-break: break-word; overflow-wrap: anywhere; }
        @include('partials.print.table_styles')
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
        .footer-summary { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; margin-top: 10px; }
        .footer-box { border: 1px solid #111; padding: 8px; min-height: 64px; }
        .muted { color: #444; }
        @media print {
            .no-print { display: none; }
            body { margin: 4mm; font-size: 12px; line-height: 1.28; font-weight: 600; }
        }
    </style>
</head>
<body>
<div class="container">
    @php
        $companyName = trim((string) ($companyName ?? 'CV. PUSTAKA GRAFIKA'));
        $companyAddress = \App\Support\PrintTextFormatter::wrapWords(trim((string) ($companyAddress ?? '')), 5);
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
        $notesText = \App\Support\PrintTextFormatter::wrapWords($notesText, 4);
        $transferText = trim((string) ($companyTransferAccounts ?? ''));
        $maxBlankRows = 3;
        $customerAddress = \App\Support\PrintTextFormatter::wrapWords((string) ($customer->address ?? ''), 4);
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
            <div class="doc-title">{{ $reportTitle ?? __('receivable.customer_bill_title') }}</div>
            <div class="doc-number">{{ __('receivable.customer_bill_title') }}</div>
            <div class="doc-number">{{ __('txn.no') }}: {{ $customer->code ?: $customer->id }}</div>
        </div>
        <div class="doc-right">
            <div class="meta-line"><strong>{{ __('receivable.print_date') }}</strong><span>:</span><span class="meta-value">{{ now()->format('d-m-Y') }}</span></div>
            @if($selectedSemester)
                <div class="meta-line"><strong>Semester</strong><span>:</span><span class="meta-value">{{ $selectedSemester }}</span></div>
            @endif
            <div class="meta-line"><strong>{{ __('receivable.customer') }}</strong><span>:</span><span class="meta-value">{{ $customer->name }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.phone') }}</strong><span>:</span><span class="meta-value">{{ $customer->phone ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.city') }}</strong><span>:</span><span class="meta-value">{{ $customer->city ?: '-' }}</span></div>
            <div class="meta-line"><strong>{{ __('txn.address') }}</strong><span>:</span><span class="meta-value">{{ $customerAddress !== '' ? $customerAddress : '-' }}</span></div>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 12%;">{{ __('receivable.bill_date') }}</th>
            <th style="width: 24%;">{{ __('receivable.bill_proof_number') }}</th>
            <th style="width: 12%;">{{ __('receivable.transaction_type') }}</th>
            <th style="width: 12%;">{{ __('receivable.printing_subtype') }}</th>
            <th style="width: 10%;">{{ __('receivable.bill_credit_sales') }}</th>
            <th style="width: 10%;">{{ __('receivable.bill_installment_payment') }}</th>
            <th style="width: 10%;">{{ __('receivable.bill_sales_return') }}</th>
            <th style="width: 10%;">{{ __('receivable.bill_running_balance') }}</th>
        </tr>
        </thead>
        <tbody>
        @if($rows->isEmpty())
            <tr>
                <td colspan="8">{{ __('receivable.no_outstanding_invoices') }}</td>
            </tr>
        @else
            @foreach($rows as $row)
                @php $isOpening = ($row['date_label'] ?? '') === __('receivable.bill_opening_balance'); @endphp
                @if($isOpening)
                    <tr>
                        <td>{{ $row['date_label'] ?? '' }}</td>
                        <td colspan="6"></td>
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
                        <td>{{ $row['transaction_type_label'] ?? __('receivable.transaction_type_none') }}</td>
                        <td>{{ ($row['printing_subtype_name'] ?? null) ? $row['printing_subtype_name'] : __('receivable.printing_subtype_none') }}</td>
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
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td class="num">&nbsp;</td>
                <td class="num">&nbsp;</td>
                <td class="num">&nbsp;</td>
                <td class="num">&nbsp;</td>
            </tr>
        @endfor
        <tr class="total-row">
            <td colspan="4" style="text-align:center;">{{ __('receivable.bill_total') }}</td>
            <td class="num">Rp {{ number_format((int) round((float) ($totals['credit_sales'] ?? 0)), 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format((int) round((float) ($totals['installment_payment'] ?? 0)), 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format((int) round((float) ($totals['sales_return'] ?? 0)), 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format((int) round((float) ($totals['running_balance'] ?? 0)), 0, ',', '.') }}</td>
        </tr>
        <tr class="total-balance-row">
            <td colspan="5"></td>
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

    @if($notesText !== '' || $transferText !== '')
        <div class="footer-summary">
            <div class="footer-box">
                @if($notesText !== '')
                    <div class="notes-title">{{ __('receivable.note_label') }} :</div>
                    @foreach(preg_split('/\r\n|\r|\n/', $notesText) ?: [] as $line)
                        @if(trim($line) !== '')
                            <div class="notes-line">{{ $line }}</div>
                        @endif
                    @endforeach
                @endif
            </div>
            <div class="footer-box">
                @if($transferText !== '')
                    <div class="transfer-title">{{ __('receivable.transfer_via_label') }} :</div>
                    @foreach(preg_split('/\r\n|\r|\n/', $transferText) ?: [] as $line)
                        @if(trim($line) !== '')
                            <div class="transfer-line">{{ $line }}</div>
                        @endif
                    @endforeach
                @endif
            </div>
        </div>
    @endif

</div>
</body>
</html>
