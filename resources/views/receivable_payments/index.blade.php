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
            <input id="receivable-payments-date-input" type="date" name="payment_date" value="{{ $selectedPaymentDate ?? '' }}" style="max-width: 180px;">
            <select id="receivable-payments-status-input" name="status" style="max-width: 180px;">
                <option value="">{{ __('txn.all_statuses') }}</option>
                <option value="active" @selected($selectedStatus === 'active')>{{ __('txn.status_active') }}</option>
                <option value="canceled" @selected($selectedStatus === 'canceled')>{{ __('txn.status_canceled') }}</option>
            </select>
            <button type="submit">{{ __('txn.search') }}</button>
        </form>
        @if(!empty($isDefaultRecentMode))
            <p class="muted" style="margin: 8px 0 0 0;">Menampilkan data 7 hari terakhir (default). Gunakan filter tanggal untuk data lebih lama.</p>
        @endif
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
                            <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
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
            const dateInput = document.getElementById('receivable-payments-date-input');
            const statusInput = document.getElementById('receivable-payments-status-input');
            if (!form || !searchInput || !dateInput || !statusInput) {
                return;
            }

            const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                ? window.PgposAutoSearch.debounce
                : (fn, wait = 100) => {
                    let timeoutId = null;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => fn(...args), wait);
                    };
                };
            const onSearchInput = debounce(() => {
                if (window.PgposAutoSearch && !window.PgposAutoSearch.canSearchInput(searchInput)) {
                    return;
                }
                form.requestSubmit();
            }, 100);
            searchInput.addEventListener('input', onSearchInput);

            statusInput.addEventListener('change', () => {
                form.requestSubmit();
            });
            dateInput.addEventListener('change', () => {
                form.requestSubmit();
            });
        })();
    </script>
@endsection
