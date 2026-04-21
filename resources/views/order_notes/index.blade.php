@extends('layouts.app')

@section('title', __('txn.order_notes_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .order-list-card {
            padding: 10px;
        }
        .order-list-card th,
        .order-list-card td {
            padding-top: 6px;
            padding-bottom: 6px;
        }
    </style>

    <div class="page-header-actions">
        <h1 class="page-title">{{ __('txn.order_notes_title') }}</h1>
        <div class="actions">
            <a class="btn create-transaction-btn" href="{{ route('order-notes.create') }}">{{ __('txn.create_order_note') }}</a>
        </div>
    </div>

    <div class="card order-list-card">
        <form id="order-notes-filter-form" method="get" class="filter-toolbar">
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
            <strong>{{ __('txn.summary') }} {{ __('txn.date') }} {{ now()->format('d-m-Y') }}</strong>
            <div class="muted">
                {{ __('txn.summary_total_order_notes') }}: {{ (int) round((int) ($todaySummary->total_notes ?? 0)) }} |
                {{ __('txn.summary_total_qty') }}: {{ (int) round((int) ($todaySummary->total_qty ?? 0)) }}
            </div>
        </div>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_order_notes') }}</strong>
            <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('reports.print', ['dataset' => 'order_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('reports.export.pdf', ['dataset' => 'order_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('reports.export.csv', ['dataset' => 'order_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="table-mobile-scroll">
        <table class="mobile-stack-table">
            <thead>
            <tr>
                <th>{{ __('txn.no') }}</th>
                <th>{{ __('txn.date') }}</th>
                <th>{{ __('txn.customer') }}</th>
                <th>{{ __('txn.city') }}</th>
                <th>{{ __('txn.order_note_progress') }}</th>
                <th>{{ __('txn.balance') }}</th>
                <th>{{ __('txn.status') }}</th>
                <th>{{ __('txn.created_by') }}</th>
                <th>{{ __('txn.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($notes as $note)
                @php
                    $progress = $noteProgressMap[(int) $note->id] ?? [
                        'ordered_total' => 0,
                        'fulfilled_total' => 0,
                        'remaining_total' => 0,
                        'progress_percent' => 0,
                        'status' => 'open',
                    ];
                    $progressLabel = rtrim(rtrim(number_format((float) ($progress['progress_percent'] ?? 0), 2, '.', ''), '0'), '.');
                    $statusLabel = ($progress['status'] ?? 'open') === 'finished' ? __('txn.order_note_status_finished') : __('txn.order_note_status_open');
                @endphp
                <tr>
                    <td data-label="{{ __('txn.no') }}">
                        <div class="list-doc-cell">
                            <a class="list-doc-link" href="{{ route('order-notes.show', $note) }}">{{ $note->note_number }}</a>
                            <span class="list-doc-badges">
                                @if($note->is_canceled)
                                    <span class="badge danger">{{ __('txn.status_canceled') }}</span>
                                @endif
                            </span>
                        </div>
                    </td>
                    <td data-label="{{ __('txn.date') }}">{{ $note->note_date->format('d-m-Y') }}</td>
                    <td data-label="{{ __('txn.customer') }}">{{ $note->customer_name }}</td>
                    <td data-label="{{ __('txn.city') }}">{{ $note->city ?: '-' }}</td>
                    <td data-label="{{ __('txn.order_note_progress') }}">{{ $progressLabel }}%</td>
                    <td data-label="{{ __('txn.balance') }}">{{ number_format((int) ($progress['remaining_total'] ?? 0), 0, ',', '.') }}</td>
                    <td data-label="{{ __('txn.status') }}">
                        @if($note->is_canceled)
                            <span class="badge danger">{{ __('txn.status_canceled') }}</span>
                        @elseif(($progress['status'] ?? 'open') === 'finished')
                            <span class="badge success">{{ $statusLabel }}</span>
                        @else
                            <span class="badge warning">{{ $statusLabel }}</span>
                        @endif
                    </td>
                    <td data-label="{{ __('txn.created_by') }}">{{ $note->created_by_name ?: '-' }}</td>
                    <td data-label="{{ __('txn.action') }}" class="action">
                        <div class="flex">
                            <select class="action-menu action-menu-sm" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                                <option value="{{ route('order-notes.print', $note) }}">{{ __('txn.print') }}</option>
                                <option value="{{ route('order-notes.export.pdf', $note) }}">{{ __('txn.pdf') }}</option>
                                <option value="{{ route('order-notes.export.excel', $note) }}">{{ __('txn.excel') }}</option>
                            </select>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">{{ __('txn.no_order_notes_found') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        <div style="margin-top: 12px;">
            {{ $notes->links() }}
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('order-notes-filter-form');
            const searchInput = document.getElementById('order-notes-search-input');
            const dateInput = document.getElementById('order-notes-date-input');
            const semesterInput = document.getElementById('order-notes-semester-input');

            if (!form || !searchInput || !dateInput || !semesterInput) {
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

            dateInput.addEventListener('change', () => form.requestSubmit());
            semesterInput.addEventListener('change', () => form.requestSubmit());
        })();
    </script>
@endsection

