@extends('layouts.app')

@section('title', __('receivable.payment_menu').' - '.config('app.name', 'Laravel'))

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('receivable.payment_menu') }}</h1>
        <a class="btn payment-btn" href="{{ route('receivable-payments.create') }}">{{ __('receivable.create_payment') }}</a>
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
    </div>

    <div class="card">
        <div id="receivable-payments-results">
            @include('receivable_payments.partials.results')
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'receivable-payments-filter-form',
                container: 'receivable-payments-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('receivable-payments-search-input'), () => ajax.submit(), 100);
            window.PgposAutoSearch.bindChangeFilters([
                document.getElementById('receivable-payments-date-input'),
                document.getElementById('receivable-payments-status-input'),
            ], () => ajax.submit());
        });
    </script>
@endsection

