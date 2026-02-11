<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Courier, "Courier New", monospace; font-size: 10px; line-height: 1.2; color: #111; }
        .head { border-bottom: 1px solid #111; margin-bottom: 8px; padding-bottom: 6px; }
        .title { font-size: 15px; font-weight: bold; text-transform: uppercase; }
        .meta-grid { width: 100%; margin-bottom: 8px; }
        .meta-grid td { width: 50%; vertical-align: top; padding: 0; border: none; }
        .meta-table { width: 98%; border-collapse: collapse; margin-bottom: 6px; }
        .meta-table th, .meta-table td { border: 1px solid #111; padding: 2px 3px; text-align: left; vertical-align: top; }
        .meta-table th { width: 42%; background: #efefef; font-size: 9px; }
        table.report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: 1px solid #111; padding: 3px; text-align: left; vertical-align: top; word-break: break-word; white-space: normal; }
        .report-table th { background: #efefef; font-size: 9px; text-align: center; }
        .report-table .grand-total td { font-weight: 700; background: #f8f8f8; }
        .report-table .row-locked-auto td { background: #f5f8ff; }
        .report-table .row-locked-manual td { background: #fff7ea; }
        .num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <div class="head">
        <div class="title">{{ $title }}</div>
        <div>{{ __('report.printed') }}: {{ $printedAt->format('d-m-Y H:i:s') }}</div>
    </div>
    @if(($layout ?? null) !== 'receivable_recap' && (!empty($filters) || !empty($summary)))
        <table class="meta-grid">
            <tr>
                <td>
                    @if(!empty($filters))
                        <table class="meta-table">
                            <tbody>
                            @foreach($filters as $item)
                                <tr>
                                    <th>{{ $item['label'] }}</th>
                                    <td>{{ $item['value'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </td>
                <td>
                    @if(!empty($summary))
                        <table class="meta-table" style="margin-left: auto;">
                            <tbody>
                            @foreach($summary as $item)
                                <tr>
                                    <th>{{ $item['label'] }}</th>
                                    <td class="num">
                                        @if(($item['type'] ?? 'number') === 'currency')
                                            Rp {{ number_format((int) round((float) ($item['value'] ?? 0)), 0, ',', '.') }}
                                        @else
                                            {{ (int) round((float) ($item['value'] ?? 0)) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    @if(($layout ?? null) === 'receivable_recap')
        @php
            $semesterCount = count($receivableSemesterHeaders ?? []);
        @endphp
        <table class="report-table">
            <thead>
            <tr>
                <th rowspan="2" style="width: 5%;">NO</th>
                <th rowspan="2" style="width: 20%;">NAMA KONSUMEN</th>
                <th rowspan="2" style="width: 16%;">ALAMAT</th>
                <th rowspan="2" style="width: 12%;">STATUS BUKU</th>
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
            <caption style="caption-side: top; text-align: left; padding: 4px 0; font-size: 9px; color: #333;">
                {{ __('receivable.customer_semester_locked_auto') }}: biru lembut,
                {{ __('receivable.customer_semester_locked_manual') }}: kuning lembut
            </caption>
            <tbody>
            @forelse($rows as $row)
                @php
                    $isGrandTotal = strtoupper(trim((string) ($row[0] ?? ''))) === 'GRAND TOTAL PIUTANG';
                @endphp
                @if($isGrandTotal)
                    <tr class="grand-total">
                        <td colspan="4">{{ $row[0] }}</td>
                        @for($i = 4; $i < count($row); $i++)
                            <td class="num">
                                @if(is_numeric($row[$i]))
                                    {{ number_format((int) round((float) $row[$i]), 0, ',', '.') }}
                                @else
                                    {{ $row[$i] }}
                                @endif
                            </td>
                        @endfor
                    </tr>
                @else
                    @php
                        $bookStatus = strtolower(trim((string) ($row[3] ?? '')));
                        $lockedAutoLabel = strtolower(__('receivable.customer_semester_locked_auto'));
                        $lockedManualLabel = strtolower(__('receivable.customer_semester_locked_manual'));
                        $rowClass = '';
                        if ($bookStatus === $lockedAutoLabel) {
                            $rowClass = 'row-locked-auto';
                        } elseif ($bookStatus === $lockedManualLabel) {
                            $rowClass = 'row-locked-manual';
                        }
                    @endphp
                    <tr class="{{ $rowClass }}">
                        <td class="num">{{ $row[0] ?? '' }}</td>
                        <td>{{ $row[1] ?? '' }}</td>
                        <td>{{ $row[2] ?? '' }}</td>
                        <td>{{ $row[3] ?? '' }}</td>
                        @for($i = 4; $i < count($row); $i++)
                            <td class="{{ is_numeric($row[$i]) ? 'num' : '' }}">
                                @if(is_numeric($row[$i]))
                                    {{ number_format((int) round((float) $row[$i]), 0, ',', '.') }}
                                @else
                                    {{ $row[$i] }}
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="{{ 5 + max(1, $semesterCount) }}">{{ __('report.no_data') }}</td>
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
                    @endphp
                    <td class="{{ $isNumericCell ? 'num' : '' }}">
                        @if($isNumericCell && is_numeric($value))
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
</body>
</html>

