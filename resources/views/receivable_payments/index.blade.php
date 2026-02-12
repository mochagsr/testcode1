@extends('layouts.app')

@section('title', __('receivable.payment_menu').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('receivable.payment_menu') }}</h1>
        <a class="btn" href="{{ route('receivable-payments.create') }}">{{ __('receivable.create_payment') }}</a>
    </div>

    <div class="card">
        <form id="receivable-payments-filter-form" method="get" class="flex">
            <input id="receivable-payments-search-input" type="text" name="search" placeholder="{{ __('receivable.payment_search_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <select id="receivable-payments-status-input" name="status" style="max-width: 180px;">
                <option value="">{{ __('txn.all_statuses') }}</option>
                <option value="active" @selected($selectedStatus === 'active')>{{ __('txn.status_active') }}</option>
                <option value="canceled" @selected($selectedStatus === 'canceled')>{{ __('txn.status_canceled') }}</option>
            </select>
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
                <th>{{ __('txn.status') }}</th>
                <th>{{ __('receivable.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td><a href="{{ route('receivable-payments.show', $payment) }}">{{ $payment->payment_number }}</a></td>
                    <td>{{ $payment->payment_date?->format('d-m-Y') }}</td>
                    <td>{{ $payment->customer?->name }} <span class="muted">({{ $payment->customer?->city }})</span></td>
                    <td>Rp {{ number_format((int) round($payment->amount), 0, ',', '.') }}</td>
                    <td>{{ $payment->is_canceled ? __('txn.status_canceled') : __('txn.status_active') }}</td>
                    <td>
                        <div class="flex">
                            <a class="btn secondary" href="{{ route('receivable-payments.show', $payment) }}">{{ __('txn.detail') }}</a>
                            <select style="max-width: 170px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                <option value=""></option>
                                <option value="{{ route('receivable-payments.print', $payment) }}">{{ __('txn.print') }}</option>
                                <option value="{{ route('receivable-payments.export.pdf', $payment) }}">{{ __('txn.pdf') }}</option>
                            </select>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">{{ __('receivable.no_receivable_payments') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $payments->links() }}
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('receivable-payments-filter-form');
            const searchInput = document.getElementById('receivable-payments-search-input');
            const statusInput = document.getElementById('receivable-payments-status-input');
            if (!form || !searchInput || !statusInput) {
                return;
            }

            let debounceTimer = null;
            searchInput.addEventListener('input', () => {
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }
                debounceTimer = setTimeout(() => {
                    form.requestSubmit();
                }, 100);
            });

            statusInput.addEventListener('change', () => {
                form.requestSubmit();
            });
        })();
    </script>
@endsection



