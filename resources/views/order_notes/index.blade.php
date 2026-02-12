@extends('layouts.app')

@section('title', __('txn.order_notes_title').' - PgPOS ERP')

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

    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.order_notes_title') }}</h1>
        <a class="btn" href="{{ route('order-notes.create') }}">{{ __('txn.create_order_note') }}</a>
    </div>

    <div class="card order-list-card">
        <form id="order-notes-filter-form" method="get" class="flex">
            <input id="order-notes-search-input" type="text" name="search" placeholder="{{ __('txn.search_order_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <input id="order-notes-date-input" type="date" name="note_date" value="{{ $selectedNoteDate }}" style="max-width: 180px;">
            <select id="order-notes-semester-input" name="semester" style="max-width: 180px;">
                <option value="">{{ __('txn.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <select id="order-notes-status-input" name="status" style="max-width: 180px;">
                <option value="">{{ __('txn.all_statuses') }}</option>
                <option value="active" @selected($selectedStatus === 'active')>{{ __('txn.status_active') }}</option>
                <option value="canceled" @selected($selectedStatus === 'canceled')>{{ __('txn.status_canceled') }}</option>
            </select>
            <button type="submit">{{ __('txn.search') }}</button>
            <div class="flex" style="margin-left: auto; padding-left: 10px; border-left: 1px solid var(--border);">
                <a class="btn secondary" href="{{ route('order-notes.index', ['search' => $search, 'status' => $selectedStatus, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.all') }}</a>
                <a class="btn secondary" href="{{ route('order-notes.index', ['search' => $search, 'semester' => $currentSemester, 'status' => $selectedStatus, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
                <a class="btn secondary" href="{{ route('order-notes.index', ['search' => $search, 'semester' => $previousSemester, 'status' => $selectedStatus, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.summary') }} {{ $selectedSemester ?: __('txn.all_semesters') }}</strong>
            <div class="muted">
                {{ __('txn.summary_total_order_notes') }}: {{ (int) round((int) ($summary->total_notes ?? 0)) }} |
                {{ __('txn.summary_total_qty') }}: {{ (int) round((int) ($summary->total_qty ?? 0)) }}
            </div>
        </div>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_order_notes') }}</strong>
            <select style="max-width: 220px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value=""></option>
                <option value="{{ route('reports.print', ['dataset' => 'order_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('reports.export.pdf', ['dataset' => 'order_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('reports.export.csv', ['dataset' => 'order_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>{{ __('txn.no') }}</th>
                <th>{{ __('txn.date') }}</th>
                <th>{{ __('txn.customer') }}</th>
                <th>{{ __('txn.city') }}</th>
                <th>{{ __('txn.created_by') }}</th>
                <th>{{ __('txn.status') }}</th>
                <th>{{ __('txn.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($notes as $note)
                <tr>
                    <td><a href="{{ route('order-notes.show', $note) }}">{{ $note->note_number }}</a></td>
                    <td>{{ $note->note_date->format('d-m-Y') }}</td>
                    <td>{{ $note->customer_name }}</td>
                    <td>{{ $note->city ?: '-' }}</td>
                    <td>{{ $note->created_by_name ?: '-' }}</td>
                    <td>{{ $note->is_canceled ? __('txn.status_canceled') : __('txn.status_active') }}</td>
                    <td>
                        <div class="flex">
                            <select style="max-width: 160px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                <option value=""></option>
                                <option value="{{ route('order-notes.print', $note) }}">{{ __('txn.print') }}</option>
                                <option value="{{ route('order-notes.export.pdf', $note) }}">{{ __('txn.pdf') }}</option>
                                <option value="{{ route('order-notes.export.excel', $note) }}">{{ __('txn.excel') }}</option>
                            </select>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ __('txn.no_order_notes_found') }}</td></tr>
            @endforelse
            </tbody>
        </table>

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
            const statusInput = document.getElementById('order-notes-status-input');

            if (!form || !searchInput || !dateInput || !semesterInput || !statusInput) {
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

            dateInput.addEventListener('change', () => form.requestSubmit());
            semesterInput.addEventListener('change', () => form.requestSubmit());
            statusInput.addEventListener('change', () => form.requestSubmit());
        })();
    </script>
@endsection


