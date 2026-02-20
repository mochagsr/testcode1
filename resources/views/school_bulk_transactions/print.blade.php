<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('school_bulk.bulk_transaction_title') }} {{ $transaction->transaction_number }}</title>
    <style>
        @page { margin: 8mm 8mm 10mm 8mm; }
        body { font-family: "Courier New", Courier, monospace; font-size: 11px; line-height: 1.2; color: #111; }
        .container { max-width: 900px; margin: 0 auto; }
        .head { display: grid; grid-template-columns: 1.5fr 1fr; gap: 10px; border-bottom: 1px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
        .title { font-size: 17px; font-weight: 700; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #111; padding: 4px; text-align: left; vertical-align: top; }
        th { font-size: 10px; }
        .school-title { margin-top: 14px; font-weight: 700; }
        .total-box { margin-top: 12px; width: 360px; margin-left: auto; }
        .total-box td { border: 1px solid #111; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
<div class="container">
    @if(empty($isPdf))
        <div class="no-print" style="margin-bottom: 10px;">
            <button onclick="window.print()">{{ __('txn.print') }}</button>
        </div>
    @endif

    <div class="head">
        <div>
            <div class="title">{{ __('school_bulk.bulk_transaction_title') }}</div>
            <div><strong>{{ __('school_bulk.transaction_number') }}</strong>: {{ $transaction->transaction_number }}</div>
            <div><strong>{{ __('txn.date') }}</strong>: {{ optional($transaction->transaction_date)->format('d-m-Y') ?: '-' }}</div>
            <div><strong>{{ __('txn.semester_period') }}</strong>: {{ $transaction->semester_period ?: '-' }}</div>
        </div>
        <div>
            <div><strong>{{ __('txn.customer') }}</strong>: {{ $transaction->customer?->name ?: '-' }}</div>
            <div><strong>{{ __('txn.phone') }}</strong>: {{ $transaction->customer?->phone ?: '-' }}</div>
            <div><strong>{{ __('txn.address') }}</strong>: {{ $transaction->customer?->address ?: '-' }}</div>
            <div><strong>{{ __('txn.city') }}</strong>: {{ $transaction->customer?->city ?: '-' }}</div>
        </div>
    </div>

    @php
        $perSchoolTotal = 0;
        foreach ($transaction->items as $item) {
            $perSchoolTotal += ((int) $item->quantity) * ((int) ($item->unit_price ?? 0));
        }
    @endphp

    @foreach($transaction->locations as $location)
        <div class="school-title">{{ __('school_bulk.school_name') }}: {{ $location->school_name }}</div>
        <div style="margin-top:2px;">
            {{ __('txn.phone') }}: {{ $location->recipient_phone ?: '-' }} |
            {{ __('txn.city') }}: {{ $location->city ?: '-' }} |
            {{ __('txn.address') }}: {{ $location->address ?: '-' }}
        </div>

        <table>
            <thead>
            <tr>
                <th style="width:6%;">{{ __('txn.no') }}</th>
                <th>{{ __('txn.name') }}</th>
                <th style="width:10%;">{{ __('txn.qty') }}</th>
                <th style="width:12%;">{{ __('txn.unit') }}</th>
                <th style="width:18%;">{{ __('txn.price') }}</th>
                <th style="width:20%;">{{ __('txn.subtotal') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($transaction->items as $item)
                @php
                    $lineTotal = ((int) $item->quantity) * ((int) ($item->unit_price ?? 0));
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ (int) $item->quantity }}</td>
                    <td>{{ $item->unit ?: '-' }}</td>
                    <td>Rp {{ number_format((int) ($item->unit_price ?? 0), 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($lineTotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="5" style="text-align:right;"><strong>{{ __('school_bulk.total_per_school') }}</strong></td>
                <td><strong>Rp {{ number_format($perSchoolTotal, 0, ',', '.') }}</strong></td>
            </tr>
            </tbody>
        </table>
    @endforeach

    <table class="total-box">
        <tr>
            <td>{{ __('school_bulk.total_per_school') }}</td>
            <td>Rp {{ number_format($perSchoolTotal, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>{{ __('school_bulk.total_schools') }}</td>
            <td>{{ (int) $transaction->locations->count() }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('school_bulk.grand_total_all_schools') }}</strong></td>
            <td><strong>Rp {{ number_format($perSchoolTotal * (int) $transaction->locations->count(), 0, ',', '.') }}</strong></td>
        </tr>
    </table>

    <div style="margin-top: 10px;"><strong>{{ __('txn.notes') }}:</strong> {{ $transaction->notes ?: '-' }}</div>
</div>
</body>
</html>

