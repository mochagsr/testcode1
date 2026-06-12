@extends('layouts.app')

@section('title', __('txn.delivery_notes_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .sort-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
        .delivery-list-card {
            padding: 10px;
        }
        .delivery-list-card th,
        .delivery-list-card td {
            padding-top: 6px;
            padding-bottom: 6px;
        }
    </style>

    <div class="page-header-actions">
        <h1 class="page-title">{{ __('txn.delivery_notes_title') }}</h1>
        <div class="actions">
            @if(auth()->user()?->canAccess('delivery_notes.create'))
                <a class="btn create-transaction-btn" href="{{ route('delivery-notes.create') }}">{{ __('txn.create_delivery_note') }}</a>
            @endif
        </div>
    </div>

    <div class="card delivery-list-card">
        <form id="delivery-notes-filter-form" method="get" class="filter-toolbar">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="filter-field">
                <label for="delivery-notes-search-input">{{ __('txn.search') }}</label>
                <input id="delivery-notes-search-input" type="text" name="search" placeholder="{{ __('txn.search_delivery_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            </div>
            <div class="filter-field">
                <label for="delivery-notes-date-input">{{ __('txn.date') }}</label>
                <input id="delivery-notes-date-input" type="date" name="note_date" value="{{ $selectedNoteDate }}" style="max-width: 180px;">
            </div>
            <div class="filter-field">
                <label for="delivery-notes-semester-input">{{ __('txn.semester_period') }}</label>
                <select id="delivery-notes-semester-input" name="semester" style="max-width: 180px;">
                    <option value="">{{ __('txn.all_semesters') }}</option>
                    @foreach($semesterOptions as $semester)
                        <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="delivery-notes-status-input">{{ __('txn.status') }}</label>
                <select id="delivery-notes-status-input" name="status" style="max-width: 180px;">
                    <option value="">{{ __('txn.all_statuses') }}</option>
                    <option value="active" @selected($selectedStatus === 'active')>{{ __('txn.status_active') }}</option>
                    <option value="canceled" @selected($selectedStatus === 'canceled')>{{ __('txn.status_canceled') }}</option>
                </select>
            </div>
            <button type="submit">{{ __('txn.search') }}</button>
            <div class="stack-mobile" style="margin-left: auto; padding-left: 10px; border-left: 1px solid var(--border);">
                <a class="btn secondary" href="{{ route('delivery-notes.index', ['search' => $search, 'status' => $selectedStatus, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.all') }}</a>
                <a class="btn secondary" href="{{ route('delivery-notes.index', ['search' => $search, 'semester' => $currentSemester, 'status' => $selectedStatus, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
                <a class="btn secondary" href="{{ route('delivery-notes.index', ['search' => $search, 'semester' => $previousSemester, 'status' => $selectedStatus, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_delivery_notes') }}</strong>
            <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>Export</option>
                <option
                    data-ajax-sync
                    data-href-base="{{ route('reports.export.pdf', ['dataset' => 'delivery_notes']) }}"
                    data-href-params="semester"
                    value="{{ route('reports.export.pdf', ['dataset' => 'delivery_notes', 'semester' => $selectedSemester]) }}"
                >Export PDF</option>
                <option
                    data-ajax-sync
                    data-href-base="{{ route('reports.export.csv', ['dataset' => 'delivery_notes']) }}"
                    data-href-params="semester"
                    value="{{ route('reports.export.csv', ['dataset' => 'delivery_notes', 'semester' => $selectedSemester]) }}"
                >Export Excel</option>
            </select>
        </div>
    </div>

    <div id="delivery-notes-results">
        @include('delivery_notes.partials.results')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'delivery-notes-filter-form',
                container: 'delivery-notes-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('delivery-notes-search-input'), () => ajax.submit(), 100);
            window.PgposAutoSearch.bindChangeFilters([
                document.getElementById('delivery-notes-date-input'),
                document.getElementById('delivery-notes-semester-input'),
                document.getElementById('delivery-notes-status-input'),
            ], () => ajax.submit());
        });
    </script>
@endsection

