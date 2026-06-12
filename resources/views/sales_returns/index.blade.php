@extends('layouts.app')

@section('title', __('txn.sales_returns_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .return-list-card {
            padding: 10px;
        }
        .return-list-card th,
        .return-list-card td {
            padding-top: 6px;
            padding-bottom: 6px;
        }
        .sort-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
    </style>
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.sales_returns_title') }}</h1>
        @if(auth()->user()?->canAccess('sales_returns.create'))
            <a class="btn create-transaction-btn" href="{{ route('sales-returns.create') }}">{{ __('txn.create_return') }}</a>
        @endif
    </div>

    <div class="card return-list-card">
        <form id="sales-returns-filter-form" method="get" class="flex">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <input id="sales-returns-search-input" type="text" name="search" placeholder="{{ __('txn.search_return_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <input id="sales-returns-date-input" type="date" name="return_date" value="{{ $selectedReturnDate }}" style="max-width: 180px;">
            <select id="sales-returns-semester-input" name="semester" style="max-width: 180px;">
                <option value="">{{ __('txn.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <button type="submit">{{ __('txn.search') }}</button>
            <div class="flex" style="margin-left: auto; padding-left: 10px; border-left: 1px solid var(--border);">
                <a class="btn secondary" href="{{ route('sales-returns.index', ['search' => $search, 'return_date' => $selectedReturnDate]) }}">{{ __('txn.all') }}</a>
                <a class="btn secondary" href="{{ route('sales-returns.index', ['search' => $search, 'semester' => $currentSemester, 'return_date' => $selectedReturnDate]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
                <a class="btn secondary" href="{{ route('sales-returns.index', ['search' => $search, 'semester' => $previousSemester, 'return_date' => $selectedReturnDate]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
            </div>
        </form>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_sales_returns') }}</strong>
            <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>Export</option>
                <option
                    data-ajax-sync
                    data-href-base="{{ route('reports.export.pdf', ['dataset' => 'sales_returns']) }}"
                    data-href-params="semester"
                    value="{{ route('reports.export.pdf', ['dataset' => 'sales_returns', 'semester' => $selectedSemester]) }}"
                >Export PDF</option>
                <option
                    data-ajax-sync
                    data-href-base="{{ route('reports.export.csv', ['dataset' => 'sales_returns']) }}"
                    data-href-params="semester"
                    value="{{ route('reports.export.csv', ['dataset' => 'sales_returns', 'semester' => $selectedSemester]) }}"
                >Export Excel</option>
            </select>
        </div>
    </div>

    <div id="sales-returns-results">
        @include('sales_returns.partials.results')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'sales-returns-filter-form',
                container: 'sales-returns-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('sales-returns-search-input'), () => ajax.submit(), 100);
            window.PgposAutoSearch.bindChangeFilters([
                document.getElementById('sales-returns-date-input'),
                document.getElementById('sales-returns-semester-input'),
            ], () => ajax.submit());
        });
    </script>
@endsection

