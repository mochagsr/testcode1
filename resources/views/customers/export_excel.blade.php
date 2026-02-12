<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
            word-wrap: break-word;
        }
        th {
            text-align: left;
            font-weight: 700;
        }
        .col-no { width: 56px; }
        .col-name { width: 240px; }
        .col-phone {
            width: 170px;
            mso-number-format: "\@";
            white-space: nowrap;
        }
        .col-city { width: 160px; }
        .col-address { width: 360px; }
        .col-receivable { width: 150px; text-align: right; }
        .title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
            font-family: Arial, sans-serif;
        }
        .meta {
            margin-bottom: 12px;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="title">{{ __('ui.customers_title') }}</div>
<div class="meta">{{ __('report.printed') }}: {{ $printedAt->format('d-m-Y H:i:s') }}</div>

<table>
    <thead>
    <tr>
        <th class="col-no">No</th>
        <th class="col-name">{{ __('ui.name') }}</th>
        <th class="col-phone">{{ __('ui.phone') }}</th>
        <th class="col-city">{{ __('ui.city') }}</th>
        <th class="col-address">{{ __('ui.address') }}</th>
        <th class="col-receivable">{{ __('ui.receivable') }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse($customers as $index => $customer)
        @php
            $phoneRaw = (string) ($customer->phone ?? '');
            $phoneDigits = preg_replace('/[^0-9]/', '', $phoneRaw);
            $phoneDisplay = $phoneDigits !== '' ? $phoneDigits : '-';
        @endphp
        <tr>
            <td class="col-no">{{ $index + 1 }}</td>
            <td class="col-name">{{ $customer->name }}</td>
            <td class="col-phone">{{ $phoneDisplay }}</td>
            <td class="col-city">{{ $customer->city ?: '-' }}</td>
            <td class="col-address">{{ $customer->address ?: '-' }}</td>
            <td class="col-receivable">{{ number_format((int) round((float) $customer->outstanding_receivable), 0, ',', '.') }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6">{{ __('ui.no_customers') }}</td>
        </tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
