@extends('layouts.app')

@section('title', __('receivable.payment_menu').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('receivable.payment_menu') }} {{ $payment->payment_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('receivable-payments.index') }}">{{ __('txn.back') }}</a>
            <a class="btn" target="_blank" href="{{ route('receivable-payments.print', $payment) }}">{{ __('txn.print') }}</a>
            <a class="btn secondary" href="{{ route('receivable-payments.export.pdf', $payment) }}">{{ __('txn.pdf') }}</a>
        </div>
    </div>

    <div class="card">
        <div class="row">
            <div class="col-6"><strong>{{ __('txn.date') }}</strong><div>{{ $payment->payment_date?->format('d-m-Y') }}</div></div>
            <div class="col-6"><strong>{{ __('txn.no') }}</strong><div>{{ $payment->payment_number }}</div></div>
            <div class="col-6"><strong>{{ __('receivable.customer') }}</strong><div>{{ $payment->customer?->name }}</div></div>
            <div class="col-6"><strong>{{ __('txn.city') }}</strong><div>{{ $payment->customer?->city ?: '-' }}</div></div>
            <div class="col-12"><strong>{{ __('txn.address') }}</strong><div>{{ $payment->customer_address ?: '-' }}</div></div>
            <div class="col-6"><strong>{{ __('receivable.amount_paid') }}</strong><div>Rp {{ number_format($payment->amount, 2) }}</div></div>
            <div class="col-6"><strong>{{ __('receivable.amount_in_words') }}</strong><div>{{ $payment->amount_in_words }}</div></div>
            <div class="col-6"><strong>{{ __('receivable.customer_signature') }}</strong><div>{{ $payment->customer_signature }}</div></div>
            <div class="col-6"><strong>{{ __('receivable.user_signature') }}</strong><div>{{ $payment->user_signature }}</div></div>
            <div class="col-12"><strong>{{ __('txn.notes') }}</strong><div>{{ $payment->notes ?: '-' }}</div></div>
        </div>
    </div>
@endsection
