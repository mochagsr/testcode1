<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->payment_number }}</title>
    <style>
        body { font-family: "Courier New", monospace; font-size: 12px; color: #000; }
        .wrap { max-width: 760px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 6px; vertical-align: top; }
        th { text-align: left; width: 30%; }
        .title { text-align: center; font-weight: 700; margin-bottom: 10px; font-size: 14px; }
        .sign { margin-top: 26px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .sign-box { text-align: center; }
        .line { margin-top: 48px; border-top: 1px solid #000; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="title">{{ __('supplier_payable.receipt_title') }}</div>
    <table>
        <tr><th>{{ __('supplier_payable.receipt_number') }}</th><td>{{ $payment->payment_number }}</td></tr>
        <tr><th>{{ __('txn.date') }}</th><td>{{ $payment->payment_date?->format('d-m-Y') }}</td></tr>
        <tr><th>{{ __('txn.supplier') }}</th><td>{{ $payment->supplier?->name ?: '-' }}</td></tr>
        <tr><th>{{ __('supplier_payable.proof_number') }}</th><td>{{ $payment->proof_number ?: '-' }}</td></tr>
        <tr><th>{{ __('txn.amount') }}</th><td>Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</td></tr>
        <tr><th>{{ __('supplier_payable.amount_in_words') }}</th><td>{{ $payment->amount_in_words ?: '-' }}</td></tr>
        <tr><th>{{ __('txn.notes') }}</th><td>{{ $payment->notes ?: '-' }}</td></tr>
    </table>
    <div class="sign">
        <div class="sign-box">
            {{ __('supplier_payable.supplier_signature') }}
            <div class="line">{{ $payment->supplier_signature ?: '' }}</div>
        </div>
        <div class="sign-box">
            {{ __('supplier_payable.user_signature') }}
            <div class="line">{{ $payment->user_signature ?: '' }}</div>
        </div>
    </div>
</div>
</body>
</html>
