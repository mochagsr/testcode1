<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @include('partials.print.paper_a4')
        body { font-family: "Courier New", Courier, monospace; font-size: 11px; line-height: 1.25; color: #111; font-weight: 600; }
        .container { max-width: 900px; margin: 0 auto; }
        .report-head { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; gap: 12px; }
        .report-title { font-size: 18px; font-weight: 800; text-transform: uppercase; }
        .report-meta { justify-self: end; width: 260px; }
        .meta-line { display: grid; grid-template-columns: 72px 8px 1fr; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: 1px solid #111; padding: 4px 5px; vertical-align: top; }
        .report-table th { text-align: center; font-weight: 800; }
        .num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
<div class="container">
    <div class="report-head">
        <div>
            <div class="report-title">{{ __('ui.customers_title') }}</div>
            <div>{{ config('app.name', 'Laravel') }}</div>
        </div>
        <div class="report-meta">
            <div class="meta-line"><div>{{ __('report.printed') }}</div><div>:</div><div>{{ $printedAt->format('d-m-Y H:i:s') }} WIB</div></div>
            <div class="meta-line"><div>{{ __('ui.level') }}</div><div>:</div><div>{{ $levelLabel }}</div></div>
            <div class="meta-line"><div>{{ __('txn.search') }}</div><div>:</div><div>{{ $search !== '' ? $search : '-' }}</div></div>
            <div class="meta-line"><div>{{ __('txn.total') }}</div><div>:</div><div>{{ $customers->count() }}</div></div>
        </div>
    </div>

    <table class="report-table">
        <thead>
        <tr>
            <th style="width: 6%;">{{ __('txn.no') }}</th>
            <th style="width: 22%;">{{ __('ui.name') }}</th>
            <th style="width: 14%;">{{ __('ui.level') }}</th>
            <th style="width: 18%;">{{ __('ui.phone') }}</th>
            <th style="width: 14%;">{{ __('ui.city') }}</th>
            <th>{{ __('ui.address') }}</th>
            <th style="width: 13%;">{{ __('ui.receivable') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($customers as $index => $customer)
            @php
                $phoneDisplay = collect([
                    (string) ($customer->phone ?? ''),
                    (string) ($customer->phone_secondary ?? ''),
                ])->map(fn (string $value): string => trim($value))->filter()->values();
            @endphp
            <tr>
                <td style="text-align:center;">{{ $index + 1 }}</td>
                <td>{{ $customer->name }}</td>
                <td>{{ $customer->level?->name ?: '-' }}</td>
                <td>{{ $phoneDisplay->isNotEmpty() ? $phoneDisplay->implode(' / ') : '-' }}</td>
                <td>{{ $customer->city ?: '-' }}</td>
                <td>{{ $customer->address ?: '-' }}</td>
                <td class="num">{{ number_format((int) round((float) $customer->outstanding_receivable), 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align:center;">{{ __('ui.no_customers') }}</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
