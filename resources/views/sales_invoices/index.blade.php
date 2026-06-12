@extends('layouts.app')

@section('title', __('txn.sales_invoices_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .invoice-list-card {
            padding: 8px;
        }
        .invoice-list-card th,
        .invoice-list-card td {
            padding-top: 4px;
            padding-bottom: 4px;
        }
    </style>

    <div class="page-header-actions">
        <h1 class="page-title">{{ __('txn.sales_invoices_title') }}</h1>
        <div class="actions">
            @if(auth()->user()?->canAccess('sales_invoices.create'))
                <a class="btn process-btn" href="{{ route('sales-invoices.pending-delivery-notes') }}">{{ __('txn.pending_delivery_notes_invoice') }}</a>
                <a class="btn create-transaction-btn" href="{{ route('sales-invoices.create') }}">{{ __('txn.create_invoice_manual') }}</a>
            @endif
        </div>
    </div>

    <div class="card">
        <form id="sales-invoices-filter-form" method="get" class="filter-toolbar">
            <div class="filter-field">
                <label for="sales-invoices-search-input">{{ __('txn.search') }}</label>
                <input id="sales-invoices-search-input" type="text" name="search" placeholder="{{ __('txn.search_invoice_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            </div>
            <div class="filter-field">
                <label for="sales-invoices-date-input">{{ __('txn.date') }}</label>
                <input id="sales-invoices-date-input" type="date" name="invoice_date" value="{{ $selectedInvoiceDate }}" style="max-width: 180px;">
            </div>
            <div class="filter-field">
                <label for="sales-invoices-semester-input">{{ __('txn.semester_period') }}</label>
                <select id="sales-invoices-semester-input" name="semester" style="max-width: 180px;">
                    <option value="">{{ __('txn.all_semesters') }}</option>
                    @foreach($semesterOptions as $semester)
                        <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="sales-invoices-status-input">{{ __('txn.status') }}</label>
                <select id="sales-invoices-status-input" name="status" style="max-width: 180px;">
                    <option value="">{{ __('txn.all_statuses') }}</option>
                    <option value="active" @selected($selectedStatus === 'active')>{{ __('txn.status_active') }}</option>
                    <option value="canceled" @selected($selectedStatus === 'canceled')>{{ __('txn.status_canceled') }}</option>
                </select>
            </div>
            <button type="submit">{{ __('txn.search') }}</button>
            <div class="stack-mobile" style="margin-left: auto; padding-left: 10px; border-left: 1px solid var(--border);">
                <a class="btn secondary" href="{{ route('sales-invoices.index', ['search' => $search, 'status' => $selectedStatus, 'invoice_date' => $selectedInvoiceDate]) }}">{{ __('txn.all') }}</a>
                <a class="btn secondary" href="{{ route('sales-invoices.index', ['search' => $search, 'semester' => $currentSemester, 'status' => $selectedStatus, 'invoice_date' => $selectedInvoiceDate]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
                <a class="btn secondary" href="{{ route('sales-invoices.index', ['search' => $search, 'semester' => $previousSemester, 'status' => $selectedStatus, 'invoice_date' => $selectedInvoiceDate]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
            </div>
        </form>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_sales_invoices') }}</strong>
            <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>Export</option>
                <option
                    data-ajax-sync
                    data-href-base="{{ route('reports.export.pdf', ['dataset' => 'sales_invoices']) }}"
                    data-href-params="semester"
                    value="{{ route('reports.export.pdf', ['dataset' => 'sales_invoices', 'semester' => $selectedSemester]) }}"
                >Export PDF</option>
                <option
                    data-ajax-sync
                    data-href-base="{{ route('reports.export.csv', ['dataset' => 'sales_invoices']) }}"
                    data-href-params="semester"
                    value="{{ route('reports.export.csv', ['dataset' => 'sales_invoices', 'semester' => $selectedSemester]) }}"
                >Export Excel</option>
            </select>
        </div>
    </div>

    <div id="sales-invoices-results">
        @include('sales_invoices.partials.results')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'sales-invoices-filter-form',
                container: 'sales-invoices-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('sales-invoices-search-input'), () => ajax.submit(), 100);
            window.PgposAutoSearch.bindChangeFilters([
                document.getElementById('sales-invoices-date-input'),
                document.getElementById('sales-invoices-semester-input'),
                document.getElementById('sales-invoices-status-input'),
            ], () => ajax.submit());
        });
    </script>
@endsection

