<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "Courier New", Courier, monospace; font-size: 12px; line-height: 1.28; color: #111; font-weight: 600; }
        .container { max-width: 1180px; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; align-items: end; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 12px; gap: 10px; }
        .head-left { display: flex; align-items: flex-start; gap: 10px; min-width: 0; }
        .logo { width: 40px; height: 60px; object-fit: contain; border: none; background: transparent; flex-shrink: 0; }
        .title-wrap { min-width: 0; }
        .title { font-size: 20px; font-weight: 800; text-transform: uppercase; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; }
        .meta-box table { width: 100%; border-collapse: collapse; }
        .meta-box th, .meta-box td { border: 1px solid #111; padding: 4px 5px; text-align: left; vertical-align: top; font-size: 12px; font-weight: 600; }
        .meta-box th { width: 42%; background: #f3f3f3; }
        table.report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: 1px solid #111; padding: 5px; vertical-align: top; word-break: break-word; white-space: normal; font-size: 12px; font-weight: 600; }
        .report-table th { background: #efefef; font-size: 12px; text-align: center; font-weight: 800; }
        .report-table td { text-align: left; }
        .report-table th:first-child, .report-table td:first-child { text-align: center; }
        .report-table .grand-total td { font-weight: 700; background: #f8f8f8; }
        .report-table .row-locked-auto td { background: #f5f8ff; }
        .report-table .row-locked-manual td { background: #fff7ea; }
        .num { text-align: right; white-space: nowrap; }
        .report-table td.num { text-align: right !important; white-space: nowrap; }
        .no-print { margin-bottom: 10px; }
        @media print {
            .no-print { display: none; }
            body { margin: 4mm; font-size: 12px; line-height: 1.28; font-weight: 600; }
            .report-table th, .report-table td { padding: 4px; }
        }
    </style>
</head>
<body>
<div class="container">
    @php
        $companyLogoPath = \App\Models\AppSetting::getValue('company_logo_path');
        $companyLogoSrc = \App\Support\PrintLogoDataUri::resolve((string) ($companyLogoPath ?? ''));
    @endphp
    @if(empty($isPdf))
        <div class="no-print">
            <button onclick="window.print()">{{ __('report.print_save_pdf') }}</button>
        </div>
    @endif
    <div class="head">
        <div class="head-left">
            @if($companyLogoSrc)
                <img class="logo" src="{{ $companyLogoSrc }}" alt="Logo">
            @endif
            <div class="title-wrap">
                <div class="title">{{ $title }}</div>
            </div>
        </div>
        <div>
            <div>{{ __('report.printed') }}: {{ $printedAt->format('d-m-Y H:i:s') }}</div>
        </div>
    </div>
    @if(($layout ?? null) !== 'receivable_recap' && (!empty($filters) || !empty($summary)))
        <div class="meta-grid">
            @if(!empty($filters))
                <div class="meta-box">
                    <table>
                        <tbody>
                        @foreach($filters as $item)
                            <tr>
                                <th>{{ $item['label'] }}</th>
                                <td>{{ $item['value'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if(!empty($summary))
                <div class="meta-box">
                    <table>
                        <tbody>
                        @foreach($summary as $item)
                            <tr>
                                <th>{{ $item['label'] }}</th>
                                <td class="num">
                                    @if(($item['type'] ?? 'number') === 'currency')
                                        Rp {{ number_format((int) round((float) ($item['value'] ?? 0)), 0, ',', '.') }}
                                    @elseif(($item['type'] ?? 'number') === 'decimal')
                                        {{ number_format((float) ($item['value'] ?? 0), 3, ',', '.') }}
                                    @else
                                        {{ (int) round((float) ($item['value'] ?? 0)) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if(($layout ?? null) === 'receivable_recap' && !empty($receivableCustomerDetail))
        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
            <table class="report-table" style="width: 52%; table-layout: fixed;">
                <tbody>
                <tr>
                    <th style="width: 30%;">NAMA</th>
                    <td style="width: 70%;">{{ $receivableCustomerDetail['customer']->name ?? '-' }}</td>
                </tr>
                <tr>
                    <th style="width: 30%;">ALAMAT</th>
                    <td style="width: 70%;">{{ trim((string) (($receivableCustomerDetail['customer']->address ?? '') !== '' ? $receivableCustomerDetail['customer']->address : ($receivableCustomerDetail['customer']->city ?? '-'))) }}</td>
                </tr>
                <tr>
                    <th style="width: 30%;">PERIODE</th>
                    <td style="width: 70%;">{{ $receivableCustomerDetail['period_label'] ?? __('report.all_semesters') }}</td>
                </tr>
                </tbody>
            </table>
        </div>

        <table class="report-table">
            <thead>
            <tr>
                <th style="width: 6%;">TANGGAL</th>
                <th style="width: 40%;">NOMOR BUKTI</th>
                <th style="width: 10%;">PENJUALAN KREDIT</th>
                <th style="width: 10%;">PEMBAYARAN ANGSURAN</th>
                <th style="width: 10%;">RETUR PENJUALAN</th>
                <th style="width: 10%;">SALDO</th>
            </tr>
            </thead>
            <tbody>
            @forelse(($receivableCustomerDetail['rows'] ?? []) as $detailRow)
                <tr>
                    <td>{{ ($detailRow['proof_number'] ?? '') === '' ? '' : ($detailRow['date_label'] ?? '-') }}</td>
                    <td>{{ $detailRow['proof_number'] !== '' ? $detailRow['proof_number'] : ($detailRow['date_label'] ?? '-') }}</td>
                    <td class="num">
                        @if((int) ($detailRow['credit_sales'] ?? 0) !== 0)
                            Rp {{ number_format((int) ($detailRow['credit_sales'] ?? 0), 0, ',', '.') }}
                        @endif
                    </td>
                    <td class="num">
                        @if((int) ($detailRow['installment_payment'] ?? 0) !== 0)
                            Rp {{ number_format((int) ($detailRow['installment_payment'] ?? 0), 0, ',', '.') }}
                        @endif
                    </td>
                    <td class="num">
                        @if((int) ($detailRow['sales_return'] ?? 0) !== 0)
                            Rp {{ number_format((int) ($detailRow['sales_return'] ?? 0), 0, ',', '.') }}
                        @endif
                    </td>
                    <td class="num">
                        @if(array_key_exists('running_balance', $detailRow))
                            @php $rowBalance = (int) ($detailRow['running_balance'] ?? 0); @endphp
                            @if($rowBalance < 0)
                                (Rp {{ number_format(abs($rowBalance), 0, ',', '.') }})
                            @else
                                Rp {{ number_format($rowBalance, 0, ',', '.') }}
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">{{ __('report.no_data') }}</td>
                </tr>
            @endforelse
            <tr class="grand-total">
                <td colspan="2">GRAND TOTAL PIUTANG</td>
                <td class="num">Rp {{ number_format((int) ($receivableCustomerDetail['total_credit_sales'] ?? 0), 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format((int) ($receivableCustomerDetail['total_installment_payment'] ?? 0), 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format((int) ($receivableCustomerDetail['total_sales_return'] ?? 0), 0, ',', '.') }}</td>
                <td class="num"></td>
            </tr>
            <tr class="grand-total">
                <td colspan="5">SALDO AKHIR</td>
                <td class="num">
                    @php $finalOutstanding = (int) ($receivableCustomerDetail['final_outstanding'] ?? 0); @endphp
                    @if($finalOutstanding < 0)
                        (Rp {{ number_format(abs($finalOutstanding), 0, ',', '.') }})
                    @else
                        Rp {{ number_format($finalOutstanding, 0, ',', '.') }}
                    @endif
                </td>
            </tr>
            @if(!empty($receivableCustomerDetail['closing_note']))
                <tr>
                    <td colspan="6" style="text-align: center; background:#dfe8f7; font-weight:700;">
                        {{ $receivableCustomerDetail['closing_note'] }}
                    </td>
                </tr>
            @endif
            @if(((int) ($receivableCustomerDetail['final_outstanding'] ?? 0)) === 0)
                <tr>
                    <td colspan="6" style="text-align: center; font-weight:700;">LUNAS</td>
                </tr>
            @endif
            </tbody>
        </table>
    @elseif(($layout ?? null) === 'receivable_recap')
        @php
            $semesterCount = count($receivableSemesterHeaders ?? []);
        @endphp
        <table class="report-table">
            <thead>
            <tr>
                <th rowspan="2" style="width: 5%;">NO</th>
                <th rowspan="2" style="width: 24%;">NAMA KONSUMEN</th>
                <th rowspan="2" style="width: 16%;">KOTA</th>
                <th colspan="{{ max(1, $semesterCount) }}">PIUTANG</th>
                <th rowspan="2" style="width: 11%;">TOTAL PIUTANG</th>
            </tr>
            <tr>
                @forelse(($receivableSemesterHeaders ?? []) as $semesterHeader)
                    <th>{{ $semesterHeader }}</th>
                @empty
                    <th>{{ __('report.columns.semester') }}</th>
                @endforelse
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $isGrandTotal = strtoupper(trim((string) ($row[0] ?? ''))) === 'GRAND TOTAL PIUTANG';
                @endphp
                @if($isGrandTotal)
                    <tr class="grand-total">
                        <td colspan="3">{{ $row[0] }}</td>
                        @for($i = 3; $i < count($row); $i++)
                            <td class="num">
                                @if(is_numeric($row[$i]))
                                    Rp {{ number_format((int) round((float) $row[$i]), 0, ',', '.') }}
                                @else
                                    {{ $row[$i] }}
                                @endif
                            </td>
                        @endfor
                    </tr>
                @else
                    <tr>
                        <td class="num">{{ $row[0] ?? '' }}</td>
                        <td>{{ $row[1] ?? '' }}</td>
                        <td>{{ $row[2] ?? '' }}</td>
                        @for($i = 3; $i < count($row); $i++)
                            <td class="num">
                                @if(is_numeric($row[$i]))
                                    Rp {{ number_format((int) round((float) $row[$i]), 0, ',', '.') }}
                                @else
                                    {{ $row[$i] }}
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="{{ 4 + max(1, $semesterCount) }}">{{ __('report.no_data') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    @else
    @php
        $headerWidthMap = [
            strtolower(__('report.columns.invoice_no')) => '12%',
            strtolower(__('report.columns.return_no')) => '12%',
            strtolower(__('report.columns.note_no')) => '12%',
            strtolower(__('report.columns.date')) => '9%',
            strtolower(__('report.columns.created_at')) => '12%',
            strtolower(__('report.columns.semester')) => '10%',
            strtolower(__('report.columns.status')) => '9%',
            strtolower(__('report.columns.email')) => '18%',
            strtolower(__('report.columns.phone')) => '11%',
            strtolower(__('report.columns.city')) => '10%',
            strtolower(__('report.columns.name')) => '16%',
            strtolower(__('report.columns.role')) => '8%',
            strtolower(__('report.columns.locale')) => '8%',
            strtolower(__('report.columns.theme')) => '8%',
            strtolower(__('report.columns.finance_lock')) => '10%',
            strtolower(__('report.columns.customer')) => '16%',
            strtolower(__('report.columns.recipient')) => '16%',
            strtolower(__('report.columns.created_by')) => '12%',
            strtolower(__('report.columns.category')) => '13%',
            strtolower(__('report.columns.level')) => '10%',
            strtolower(__('report.columns.stock')) => '8%',
            'qty' => '8%',
            strtolower(__('report.columns.total')) => '10%',
            strtolower(__('report.columns.paid')) => '10%',
            strtolower(__('report.columns.paid_cash')) => '10%',
            strtolower(__('report.columns.paid_customer_balance')) => '14%',
            strtolower(__('report.columns.balance')) => '10%',
            strtolower(__('report.columns.customer_balance')) => '12%',
            strtolower(__('report.columns.payment_method')) => '12%',
            strtolower(__('report.columns.outstanding_receivable')) => '14%',
            strtolower(__('report.columns.price_agent')) => '10%',
            strtolower(__('report.columns.price_sales')) => '10%',
            strtolower(__('report.columns.price_general')) => '10%',
            strtolower(__('report.columns.total_weight')) => '10%',
        ];
        $numericHeaders = [
            strtolower(__('report.columns.stock')),
            'qty',
            strtolower(__('report.columns.total')),
            strtolower(__('report.columns.paid')),
            strtolower(__('report.columns.paid_cash')),
            strtolower(__('report.columns.paid_customer_balance')),
            strtolower(__('report.columns.balance')),
            strtolower(__('report.columns.customer_balance')),
            strtolower(__('report.columns.outstanding_receivable')),
            strtolower(__('report.columns.price_agent')),
            strtolower(__('report.columns.price_sales')),
            strtolower(__('report.columns.price_general')),
        ];
        $decimalHeaders = [
            strtolower(__('report.columns.total_weight')),
        ];
    @endphp
    <table class="report-table">
        <colgroup>
            @foreach($headers as $header)
                @php
                    $key = strtolower(trim((string) $header));
                    $width = $headerWidthMap[$key] ?? null;
                @endphp
                <col @if($width) style="width: {{ $width }};" @endif>
            @endforeach
        </colgroup>
        <thead>
        <tr>
            @foreach($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                @foreach($row as $idx => $value)
                    @php
                        $headerKey = strtolower(trim((string) ($headers[$idx] ?? '')));
                        $isNumericCell = in_array($headerKey, $numericHeaders, true);
                        $isDecimalCell = in_array($headerKey, $decimalHeaders, true);
                    @endphp
                    <td class="{{ ($isNumericCell || $isDecimalCell) ? 'num' : '' }}">
                        @if($isDecimalCell && is_numeric($value))
                            {{ number_format((float) $value, 3, ',', '.') }}
                        @elseif($isNumericCell && is_numeric($value))
                            {{ number_format((int) round((float) $value), 0, ',', '.') }}
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ count($headers) }}">{{ __('report.no_data') }}</td>
            </tr>
        @endforelse
        </tbody>
    </table>
    @endif
</div>
</body>
</html>
