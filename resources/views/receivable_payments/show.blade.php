@extends('layouts.app')

@section('title', __('receivable.payment_menu').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('receivable.payment_menu') }} {{ $payment->payment_number }}</h1>
        <div class="flex">
            @if(!empty($returnTo))
                <a class="btn secondary" href="{{ $returnTo }}">{{ __('receivable.back_to_receivables') }}</a>
            @endif
            <a class="btn secondary" href="{{ route('receivable-payments.index') }}">{{ __('receivable.back_to_payments') }}</a>
            <select style="max-width: 170px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled hidden></option>
                <option value="{{ route('receivable-payments.print', $payment) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('receivable-payments.export.pdf', $payment) }}">{{ __('txn.pdf') }}</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="row">
            <div class="col-6"><strong>{{ __('txn.date') }}</strong><div>{{ $payment->payment_date?->format('d-m-Y') }}</div></div>
            <div class="col-6"><strong>{{ __('txn.no') }}</strong><div>{{ $payment->payment_number }}</div></div>
            <div class="col-6"><strong>{{ __('txn.status') }}</strong><div>{{ $payment->is_canceled ? __('txn.status_canceled') : __('txn.status_active') }}</div></div>
            <div class="col-6"><strong>{{ __('receivable.customer') }}</strong><div>{{ $payment->customer?->name }}</div></div>
            <div class="col-6"><strong>{{ __('txn.city') }}</strong><div>{{ $payment->customer?->city ?: '-' }}</div></div>
            <div class="col-12"><strong>{{ __('txn.address') }}</strong><div>{{ $payment->customer_address ?: '-' }}</div></div>
            <div class="col-6"><strong>{{ __('receivable.amount_paid') }}</strong><div>Rp {{ number_format((int) round($payment->amount), 0, ',', '.') }}</div></div>
            <div class="col-6"><strong>{{ __('receivable.amount_in_words') }}</strong><div>{{ $payment->amount_in_words }}</div></div>
            <div class="col-6"><strong>{{ __('receivable.customer_signature') }}</strong><div>{{ $payment->customer_signature }}</div></div>
            <div class="col-6"><strong>{{ __('receivable.user_signature') }}</strong><div>{{ $payment->user_signature }}</div></div>
            <div class="col-12"><strong>{{ __('txn.notes') }}</strong><div>{{ $payment->notes ?: '-' }}</div></div>
            @if($payment->is_canceled)
                <div class="col-12"><strong>{{ __('txn.cancel_reason') }}</strong><div>{{ $payment->cancel_reason ?: '-' }}</div></div>
            @endif
        </div>
    </div>

    @if((auth()->user()?->role ?? '') === 'admin')
        <div class="card">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('txn.admin_actions') }}</h3>
                <form method="post" action="{{ route('receivable-payments.admin-update', $payment) }}" class="row" style="margin-bottom: 12px;">
                    @csrf
                    @method('PUT')
                    <div class="col-4">
                        <label>{{ __('txn.date') }}</label>
                        <input type="date" name="payment_date" value="{{ old('payment_date', optional($payment->payment_date)->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-12">
                        <label>{{ __('txn.address') }}</label>
                        <textarea name="customer_address" rows="2">{{ old('customer_address', $payment->customer_address) }}</textarea>
                    </div>
                    <div class="col-6">
                        <label>{{ __('receivable.customer_signature') }}</label>
                        <input type="text" name="customer_signature" value="{{ old('customer_signature', $payment->customer_signature) }}" required>
                    </div>
                    <div class="col-6">
                        <label>{{ __('receivable.user_signature') }}</label>
                        <input type="text" name="user_signature" value="{{ old('user_signature', $payment->user_signature) }}" required>
                    </div>
                    <div class="col-12">
                        <label>{{ __('txn.notes') }}</label>
                        <textarea name="notes" rows="2">{{ old('notes', $payment->notes) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn" type="submit">{{ __('txn.save_changes') }}</button>
                    </div>
                </form>
                @if(!$payment->is_canceled)
                    <form method="post" action="{{ route('receivable-payments.cancel', $payment) }}" class="row">
                        @csrf
                        <div class="col-12">
                            <label>{{ __('txn.cancel_reason') }}</label>
                            <textarea name="cancel_reason" rows="2" required></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn secondary" type="submit">{{ __('txn.cancel_transaction') }}</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection




