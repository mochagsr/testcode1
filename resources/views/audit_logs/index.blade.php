@extends('layouts.app')

@section('title', __('ui.audit_logs_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <h1 class="page-title">{{ __('ui.audit_logs_title') }}</h1>

    <div class="card">
        <form id="audit-logs-search-form" method="get" class="filter-toolbar">
            <div class="filter-field">
                <label for="audit-logs-module-input">{{ __('ui.audit_module_all') }}</label>
                <select id="audit-logs-module-input" name="module" style="max-width: 220px;">
                    <option value="">{{ __('ui.audit_module_all') }}</option>
                    <option value="sales_invoice" @selected($selectedModule === 'sales_invoice')>{{ __('ui.audit_module_sales_invoice') }}</option>
                    <option value="sales_return" @selected($selectedModule === 'sales_return')>{{ __('ui.audit_module_sales_return') }}</option>
                    <option value="delivery_note" @selected($selectedModule === 'delivery_note')>{{ __('ui.audit_module_delivery_note') }}</option>
                    <option value="order_note" @selected($selectedModule === 'order_note')>{{ __('ui.audit_module_order_note') }}</option>
                    <option value="receivable_payment" @selected($selectedModule === 'receivable_payment')>{{ __('ui.audit_module_receivable_payment') }}</option>
                    <option value="supplier_payment" @selected($selectedModule === 'supplier_payment')>{{ __('ui.audit_module_supplier_payment') }}</option>
                    <option value="outgoing_transaction" @selected($selectedModule === 'outgoing_transaction')>{{ __('ui.audit_module_outgoing_transaction') }}</option>
                    <option value="delivery_trip" @selected($selectedModule === 'delivery_trip')>{{ __('ui.audit_module_delivery_trip') }}</option>
                    <option value="school_bulk" @selected($selectedModule === 'school_bulk')>{{ __('ui.audit_module_school_bulk') }}</option>
                    <option value="master" @selected($selectedModule === 'master')>{{ __('ui.audit_module_master') }}</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="audit-logs-date-from-input">{{ __('ui.audit_filter_period') }}</label>
                <input id="audit-logs-date-from-input" type="date" name="date_from" value="{{ $selectedDateFrom }}" style="max-width: 180px;">
            </div>
            <div class="filter-field">
                <label for="audit-logs-date-to-input">{{ __('ui.audit_filter_period') }}</label>
                <input id="audit-logs-date-to-input" type="date" name="date_to" value="{{ $selectedDateTo }}" style="max-width: 180px;">
            </div>
            <div class="filter-field">
                <label for="audit-logs-search-input">{{ __('ui.search') }}</label>
                <input id="audit-logs-search-input" type="text" name="search" placeholder="{{ __('ui.search_audit_logs_placeholder') }}" value="{{ $search }}" style="max-width: 340px;">
            </div>
            <div class="filter-field">
                <label for="audit-logs-doc-code-input">No Dokumen</label>
                <input id="audit-logs-doc-code-input" type="text" name="doc_code" placeholder="No dokumen (INV-/RTR-/KWT-)" value="{{ $selectedDocumentCode ?? '' }}" style="max-width: 220px;">
            </div>
            <button type="submit">{{ __('ui.search') }}</button>
            <a
                class="btn info-btn"
                data-ajax-sync
                data-href-base="{{ route('audit-logs.export.csv') }}"
                data-href-params="search,module,date_from,date_to,doc_code"
                href="{{ route('audit-logs.export.csv', ['module' => $selectedModule, 'date_from' => $selectedDateFrom, 'date_to' => $selectedDateTo, 'search' => $search, 'doc_code' => ($selectedDocumentCode ?? '')]) }}"
            >
                {{ __('ui.export_audit_csv') }}
            </a>
        </form>
        <div class="muted" style="margin-top: 10px;">
            <strong>{{ __('ui.audit_help_title') }}:</strong>
            {{ __('ui.audit_help_note') }}
        </div>
    </div>

    <div id="audit-logs-results">
        @include('audit_logs.partials.results')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'audit-logs-search-form',
                container: 'audit-logs-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('audit-logs-search-input'), () => ajax.submit(), 100);
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('audit-logs-doc-code-input'), () => ajax.submit(), 250);
            window.PgposAutoSearch.bindChangeFilters([
                document.getElementById('audit-logs-module-input'),
                document.getElementById('audit-logs-date-from-input'),
                document.getElementById('audit-logs-date-to-input'),
            ], () => ajax.submit());
        });
    </script>
@endsection

