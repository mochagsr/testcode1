@extends('layouts.app')

@section('title', __('supplier_payable.title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .supplier-payable-scroll-wrap {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 440px;
            border: 1px solid color-mix(in srgb, var(--border) 75%, var(--text) 25%);
            border-radius: 8px;
            scrollbar-gutter: stable;
        }
        .supplier-payable-scroll-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--card);
        }
        .supplier-payable-customers-table,
        .supplier-payable-ledger-table {
            width: 100%;
            border-collapse: collapse;
        }
        .supplier-payable-customers-table {
            min-width: 760px;
            table-layout: fixed;
        }
        .supplier-payable-ledger-table {
            min-width: 980px;
            table-layout: fixed;
        }
        .supplier-payable-customers-table td,
        .supplier-payable-customers-table th,
        .supplier-payable-ledger-table td,
        .supplier-payable-ledger-table th {
            border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            vertical-align: middle;
            padding: 10px 8px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .supplier-payable-customers-table td.num,
        .supplier-payable-customers-table th.num,
        .supplier-payable-ledger-table td.num,
        .supplier-payable-ledger-table th.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            overflow: visible;
            text-overflow: clip;
        }
        .supplier-payable-customers-table td.action,
        .supplier-payable-customers-table th.action {
            width: 38%;
            white-space: nowrap;
            min-width: 220px;
        }
        .supplier-payable-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            width: 100%;
        }
        .supplier-payable-actions .btn {
            min-height: 30px;
            padding: 5px 9px;
            font-size: 11px;
            min-width: 98px;
            text-align: center;
        }
        .supplier-payable-customers-table td.supplier-name,
        .supplier-payable-customers-table th.supplier-name {
            white-space: normal;
            max-width: 0;
        }
        .supplier-payable-main-grid .supplier-payable-col-suppliers {
            grid-column: span 5;
        }
        .supplier-payable-main-grid .supplier-payable-col-ledger {
            grid-column: span 7;
        }
        .supplier-payable-ledger-table td,
        .supplier-payable-ledger-table th {
            font-variant-numeric: tabular-nums;
        }
        .supplier-payable-summary-slider {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }
        .supplier-payable-summary-item {
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
        }
        .supplier-payable-final-summary {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            text-align: right;
        }
        @media (max-width: 1366px) {
            .supplier-payable-main-grid .supplier-payable-col-suppliers,
            .supplier-payable-main-grid .supplier-payable-col-ledger {
                grid-column: span 12;
            }
        }
    </style>

    <h1 class="page-title">{{ __('supplier_payable.title') }}</h1>

    <div class="card">
        <form method="get" class="filter-toolbar" id="supplier-payable-filter-form">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="filter-field">
                <label for="supplier-payable-search">{{ __('txn.search') }}</label>
                <input id="supplier-payable-search" type="text" name="search" value="{{ $search }}" placeholder="{{ __('supplier_payable.search_placeholder') }}" style="max-width:320px;">
            </div>
            <div class="filter-field">
                <label for="supplier-payable-supplier">{{ __('txn.supplier') }}</label>
                <select name="supplier_id" id="supplier-payable-supplier" style="max-width:240px;">
                    <option value="">{{ __('supplier_payable.all_suppliers') }}</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((int) $selectedSupplierId === (int) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="supplier-payable-year">{{ __('txn.year') }}</label>
                <select name="year" id="supplier-payable-year" style="max-width:160px;">
                    <option value="">{{ __('supplier_payable.all_years') }}</option>
                    @foreach($yearOptions as $option)
                        <option value="{{ $option }}" @selected($selectedYear === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="supplier-payable-month">{{ __('supplier_payable.month_label') }}</label>
                <select name="month" id="supplier-payable-month" style="max-width:170px;">
                    <option value="">{{ __('supplier_payable.all_months') }}</option>
                    @foreach($monthOptions as $monthValue => $monthLabel)
                        <option value="{{ $monthValue }}" @selected((int) ($selectedMonth ?? 0) === (int) $monthValue)>{{ $monthLabel }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit">{{ __('txn.search') }}</button>
            <a
                class="btn info-btn"
                data-ajax-sync
                data-href-base="{{ route('supplier-payables.print') }}"
                data-href-params="search,supplier_id,year,month"
                href="{{ route('supplier-payables.print', ['search' => $search, 'supplier_id' => $selectedSupplierId, 'year' => $selectedYear, 'month' => $selectedMonth]) }}"
                target="_blank"
            >{{ __('txn.print') }}</a>
            <select class="action-menu action-menu-md" aria-label="Export" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>Export</option>
                <option
                    data-ajax-sync
                    data-href-base="{{ route('supplier-payables.export.pdf') }}"
                    data-href-params="search,supplier_id,year,month"
                    value="{{ route('supplier-payables.export.pdf', ['search' => $search, 'supplier_id' => $selectedSupplierId, 'year' => $selectedYear, 'month' => $selectedMonth]) }}"
                >Export PDF</option>
                <option
                    data-ajax-sync
                    data-href-base="{{ route('supplier-payables.export.excel') }}"
                    data-href-params="search,supplier_id,year,month"
                    value="{{ route('supplier-payables.export.excel', ['search' => $search, 'supplier_id' => $selectedSupplierId, 'year' => $selectedYear, 'month' => $selectedMonth]) }}"
                >Export Excel</option>
            </select>
            <a class="btn payment-btn" href="{{ route('supplier-payables.create') }}">{{ __('supplier_payable.add_payment') }}</a>
        </form>
    </div>

    @if(auth()->user()?->canAccess('supplier_payables.adjust'))
        <div class="card">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('supplier_payable.book_title') }}</h3>
                <p class="form-section-note">{{ __('supplier_payable.book_note') }}</p>
                @if($selectedSupplier && $selectedYear)
                    <div class="flex" style="align-items:center; justify-content:space-between;">
                        <div>
                            <strong>{{ $selectedSupplier->name }}</strong>
                            <div class="muted">{{ __('supplier_payable.year_label') }}: {{ $selectedYear }}</div>
                            <div class="muted">
                                {{ __('supplier_payable.month_label') }}:
                                {{ $selectedMonth ? ($monthOptions[$selectedMonth] ?? sprintf('%02d', $selectedMonth)) : __('supplier_payable.all_months') }}
                            </div>
                            <div class="muted" style="margin-top: 4px;">
                                {{ $selectedMonth ? __('supplier_payable.month_book_note') : __('supplier_payable.year_book_note') }}
                            </div>
                        </div>
                        <div class="flex" style="align-items:center; gap:8px;">
                            @if($selectedSupplierMonthClosed)
                                <span class="badge warning">{{ $selectedMonth ? __('supplier_payable.month_closed_badge') : __('supplier_payable.year_closed_badge') }}</span>
                                <form method="post" action="{{ route('supplier-payables.year-open') }}">
                                    @csrf
                                    <input type="hidden" name="supplier_id" value="{{ $selectedSupplier->id }}">
                                    <input type="hidden" name="year" value="{{ $selectedYear }}">
                                    @if($selectedMonth)
                                        <input type="hidden" name="month" value="{{ $selectedMonth }}">
                                    @endif
                                    <input type="hidden" name="search" value="{{ $search }}">
                                    <button type="submit" class="btn payment-btn">{{ $selectedMonth ? __('supplier_payable.open_month_action') : __('supplier_payable.open_year_action') }}</button>
                                </form>
                            @else
                                <span class="badge success">{{ $selectedMonth ? __('supplier_payable.month_open_badge') : __('supplier_payable.year_open_badge') }}</span>
                                <form method="post" action="{{ route('supplier-payables.year-close') }}">
                                    @csrf
                                    <input type="hidden" name="supplier_id" value="{{ $selectedSupplier->id }}">
                                    <input type="hidden" name="year" value="{{ $selectedYear }}">
                                    @if($selectedMonth)
                                        <input type="hidden" name="month" value="{{ $selectedMonth }}">
                                    @endif
                                    <input type="hidden" name="search" value="{{ $search }}">
                                    <button type="submit" class="btn warning-btn">{{ $selectedMonth ? __('supplier_payable.close_month_action') : __('supplier_payable.close_year_action') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="muted">{{ __('supplier_payable.select_supplier_book_hint') }}</div>
                @endif
            </div>
        </div>
    @endif

    <div id="supplier-payable-results">
        @include('supplier_payables.partials.results')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'supplier-payable-filter-form',
                container: 'supplier-payable-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('supplier-payable-search'), () => ajax.submit(), 100);
            window.PgposAutoSearch.bindChangeFilters([
                document.getElementById('supplier-payable-supplier'),
                document.getElementById('supplier-payable-year'),
                document.getElementById('supplier-payable-month'),
            ], () => ajax.submit());
        });
    </script>
@endsection


