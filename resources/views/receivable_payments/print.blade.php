<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('txn.print') }} {{ $payment->payment_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; margin-bottom: 16px; border-bottom: 2px solid #111; padding-bottom: 8px; }
        .title { font-size: 24px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #111; padding: 7px; text-align: left; vertical-align: top; }
        .signature-table { margin-top: 24px; }
        .signature-table th, .signature-table td { text-align: center; }
        .signature-space { height: 64px; border-top: none !important; border-bottom: none !important; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="container">
    @if(empty($isPdf))
        <div class="no-print" style="margin-bottom: 10px;">
            <button onclick="window.print()">{{ __('txn.print') }}</button>
        </div>
    @endif

    <div class="header">
        <div>
            <div class="title">{{ __('receivable.payment_menu') }}</div>
            <div>{{ __('txn.no') }}: {{ $payment->payment_number }}</div>
        </div>
        <div>
            <div>{{ __('txn.date') }}: {{ $payment->payment_date?->format('d-m-Y') }}</div>
        </div>
    </div>

    <table>
        <tr><th style="width: 30%;">{{ __('receivable.customer') }}</th><td>{{ $payment->customer?->name }}</td></tr>
        <tr><th>{{ __('txn.address') }}</th><td>{{ $payment->customer_address ?: '-' }}</td></tr>
        <tr><th>{{ __('receivable.amount_paid') }}</th><td>Rp {{ number_format($payment->amount, 2) }}</td></tr>
        <tr><th>{{ __('receivable.amount_in_words') }}</th><td>{{ $payment->amount_in_words }}</td></tr>
        <tr><th>{{ __('txn.notes') }}</th><td>{{ $payment->notes ?: '-' }}</td></tr>
    </table>

    <table class="signature-table">
        <tr>
            <th>{{ __('receivable.customer_signature') }}</th>
            <th>{{ __('receivable.user_signature') }}</th>
        </tr>
        <tr>
            <td class="signature-space"></td>
            <td class="signature-space"></td>
        </tr>
        <tr>
            <td>{{ $payment->customer_signature }}</td>
            <td>{{ $payment->user_signature }}</td>
        </tr>
    </table>
</div>
</body>
</html>
