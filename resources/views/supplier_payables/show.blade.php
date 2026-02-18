@extends('layouts.app')

@section('title', $payment->payment_number.' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between;">
        <h1 class="page-title" style="margin:0;">{{ $payment->payment_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('supplier-payables.print-payment', $payment) }}" target="_blank">{{ __('txn.print') }}</a>
            <a class="btn secondary" href="{{ route('supplier-payables.export-payment-pdf', $payment) }}">{{ __('txn.pdf') }}</a>
        </div>
    </div>

    <div class="card">
        <table>
            <tr><th>{{ __('txn.supplier') }}</th><td>{{ $payment->supplier?->name ?: '-' }}</td></tr>
            <tr><th>{{ __('txn.date') }}</th><td>{{ $payment->payment_date?->format('d-m-Y') }}</td></tr>
            <tr><th>{{ __('supplier_payable.proof_number') }}</th><td>{{ $payment->proof_number ?: '-' }}</td></tr>
            <tr><th>{{ __('txn.amount') }}</th><td>Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</td></tr>
            <tr><th>{{ __('supplier_payable.amount_in_words') }}</th><td>{{ $payment->amount_in_words ?: '-' }}</td></tr>
            <tr><th>{{ __('txn.notes') }}</th><td>{{ $payment->notes ?: '-' }}</td></tr>
        </table>
    </div>
@endsection
