@extends('layouts.app')

@section('title', __('receivable.payment_menu').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('receivable.payment_menu') }}</h1>
        <a class="btn" href="{{ route('receivable-payments.create') }}">{{ __('receivable.create_payment') }}</a>
    </div>

    <div class="card">
        <form method="get" class="flex">
            <input type="text" name="search" placeholder="{{ __('receivable.payment_search_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <button type="submit">{{ __('txn.search') }}</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>{{ __('txn.no') }}</th>
                <th>{{ __('txn.date') }}</th>
                <th>{{ __('receivable.customer') }}</th>
                <th>{{ __('receivable.amount_paid') }}</th>
                <th>{{ __('receivable.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td><a href="{{ route('receivable-payments.show', $payment) }}">{{ $payment->payment_number }}</a></td>
                    <td>{{ $payment->payment_date?->format('d-m-Y') }}</td>
                    <td>{{ $payment->customer?->name }} <span class="muted">({{ $payment->customer?->city }})</span></td>
                    <td>Rp {{ number_format($payment->amount, 2) }}</td>
                    <td>
                        <div class="flex">
                            <a class="btn secondary" href="{{ route('receivable-payments.show', $payment) }}">{{ __('txn.detail') }}</a>
                            <a class="btn secondary" target="_blank" href="{{ route('receivable-payments.print', $payment) }}">{{ __('txn.print') }}</a>
                            <a class="btn" href="{{ route('receivable-payments.export.pdf', $payment) }}">{{ __('txn.pdf') }}</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">{{ __('receivable.no_receivable_payments') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $payments->links() }}
        </div>
    </div>
@endsection
