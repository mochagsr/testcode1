<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $printTitle ?? $title }}</title>
    <style>
        body { font-family: "Courier New", Courier, monospace; font-size: 12px; line-height: 1.28; color: #111; font-weight: 600; }
        .container { max-width: 1380px; margin: 0 auto; }
        .no-print { margin-bottom: 10px; }
        .head { margin-bottom: 8px; position: relative; min-height: 34px; }
        .head-title { text-align: center; font-size: 20px; font-weight: 800; text-transform: uppercase; }
        .head-update { position: absolute; right: 0; bottom: 0; font-size: 13px; font-style: italic; font-weight: 800; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: 1px solid #111; padding: 5px 6px; vertical-align: top; font-size: 12px; font-weight: 600; }
        .report-table thead th { background: #fff54f; font-weight: 800; text-align: center; }
        .num { text-align: right; white-space: nowrap; }
        .total-row td { font-weight: 700; background: #2f74c8; color: #fff; }
        .status-paid { color: #d60000; font-weight: 700; text-transform: uppercase; text-align: center; }
        .status-open { color: #111; font-weight: 700; text-transform: uppercase; text-align: center; }
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

    <div class="head">
        <div class="head-title">{{ $printTitle ?? $title }}</div>
        <div class="head-update">Update : {{ now()->translatedFormat('j F Y') }}</div>
    </div>

    <table class="report-table">
        <colgroup>
            <col style="width: 4%;">
            <col style="width: 13%;">
            <col style="width: 26%;">
            <col style="width: 5%;">
            <col style="width: 15%;">
            <col style="width: 15%;">
            <col style="width: 14%;">
            <col style="width: 16%;">
            <col style="width: 8%;">
        </colgroup>
        <thead>
        <tr>
            <th>No.</th>
            <th>NAMA</th>
            <th>ALAMAT</th>
            <th>KET.</th>
            <th>REKAP PENJUALAN</th>
            <th>ANGSURAN</th>
            <th>RETUR PENJUALAN</th>
            <th>PIUTANG</th>
            <th>STATUS</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $index => $row)
            @php
                $isPaid = (int) $row['outstanding_total'] <= 0;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ strtoupper($row['name']) }}</td>
                <td>{{ $row['address'] }}</td>
                <td>{{ $row['level_label'] !== '' ? $row['level_label'] : '-' }}</td>
                <td class="num">Rp {{ number_format((int) $row['sales_total'], 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format((int) $row['payment_total'], 0, ',', '.') }}</td>
                <td class="num">Rp {{ number_format((int) $row['return_total'], 0, ',', '.') }}</td>
                <td class="num">
                    @if((int) $row['outstanding_total'] < 0)
                        -Rp {{ number_format(abs((int) $row['outstanding_total']), 0, ',', '.') }}
                    @else
                        Rp {{ number_format((int) $row['outstanding_total'], 0, ',', '.') }}
                    @endif
                </td>
                <td class="{{ $isPaid ? 'status-paid' : 'status-open' }}">{{ $isPaid ? 'LUNAS' : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="9">{{ __('receivable.semester_no_data') }}</td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr class="total-row">
            <td colspan="4">{{ __('receivable.semester_total') }}</td>
            <td class="num">Rp {{ number_format((int) ($totals['sales_total'] ?? 0), 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format((int) ($totals['payment_total'] ?? 0), 0, ',', '.') }}</td>
            <td class="num">Rp {{ number_format((int) ($totals['return_total'] ?? 0), 0, ',', '.') }}</td>
            <td class="num">
                @if((int) ($totals['outstanding_total'] ?? 0) < 0)
                    -Rp {{ number_format(abs((int) ($totals['outstanding_total'] ?? 0)), 0, ',', '.') }}
                @else
                    Rp {{ number_format((int) ($totals['outstanding_total'] ?? 0), 0, ',', '.') }}
                @endif
            </td>
            <td></td>
        </tr>
        </tfoot>
    </table>
</div>
</body>
</html>
