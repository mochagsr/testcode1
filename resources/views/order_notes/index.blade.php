@extends('layouts.app')

@section('title', __('txn.order_notes_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .sort-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
        .order-list-card {
            padding: 10px;
        }
        .order-list-card th,
        .order-list-card td {
            padding-top: 6px;
            padding-bottom: 6px;
        }
        .order-status-badge.badge.warning {
            background: #fff4d6;
            border-color: #f6b73c;
            color: #7a3f00;
        }
    </style>

    <div class="page-header-actions">
        <h1 class="page-title">{{ __('txn.order_notes_title') }}</h1>
        <div class="actions">
            @if(auth()->user()?->canAccess('order_notes.create'))
                <a class="btn create-transaction-btn" href="{{ route('order-notes.create') }}">{{ __('txn.create_order_note') }}</a>
            @endif
        </div>
    </div>

    <div class="card order-list-card">
        <form id="order-notes-filter-form" method="get" class="filter-toolbar">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="filter-field">
                <label for="order-notes-search-input">{{ __('txn.search') }}</label>
                <input id="order-notes-search-input" type="text" name="search" placeholder="{{ __('txn.search_order_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            </div>
            <div class="filter-field">
                <label for="order-notes-date-input">{{ __('txn.date') }}</label>
                <input id="order-notes-date-input" type="date" name="note_date" value="{{ $selectedNoteDate }}" style="max-width: 180px;">
            </div>
            <div class="filter-field">
                <label for="order-notes-semester-input">{{ __('txn.semester_period') }}</label>
                <select id="order-notes-semester-input" name="semester" style="max-width: 180px;">
                    <option value="">{{ __('txn.all_semesters') }}</option>
                    @foreach($semesterOptions as $semester)
                        <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit">{{ __('txn.search') }}</button>
            <div class="stack-mobile" style="margin-left: auto; padding-left: 10px; border-left: 1px solid var(--border);">
                <a class="btn secondary" href="{{ route('order-notes.index', ['search' => $search, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.all') }}</a>
                <a class="btn secondary" href="{{ route('order-notes.index', ['search' => $search, 'semester' => $currentSemester, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
                <a class="btn secondary" href="{{ route('order-notes.index', ['search' => $search, 'semester' => $previousSemester, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_order_notes') }}</strong>
            <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>Export</option>
                <option
                    data-ajax-sync
                    data-href-base="{{ route('reports.export.pdf', ['dataset' => 'order_notes']) }}"
                    data-href-params="semester"
                    value="{{ route('reports.export.pdf', ['dataset' => 'order_notes', 'semester' => $selectedSemester]) }}"
                >Export PDF</option>
                <option
                    data-ajax-sync
                    data-href-base="{{ route('reports.export.csv', ['dataset' => 'order_notes']) }}"
                    data-href-params="semester"
                    value="{{ route('reports.export.csv', ['dataset' => 'order_notes', 'semester' => $selectedSemester]) }}"
                >Export Excel</option>
            </select>
        </div>
    </div>

    <div id="order-notes-results">
        @include('order_notes.partials.results')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'order-notes-filter-form',
                container: 'order-notes-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('order-notes-search-input'), () => ajax.submit(), 100);
            window.PgposAutoSearch.bindChangeFilters([
                document.getElementById('order-notes-date-input'),
                document.getElementById('order-notes-semester-input'),
            ], () => ajax.submit());
        });
    </script>
@endsection

