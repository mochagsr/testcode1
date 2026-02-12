@extends('layouts.app')

@section('title', __('txn.delivery_notes_title').' - PgPOS ERP')

@section('content')
    <style>
        .delivery-list-card {
            padding: 10px;
        }
        .delivery-list-card th,
        .delivery-list-card td {
            padding-top: 6px;
            padding-bottom: 6px;
        }
    </style>

    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.delivery_notes_title') }}</h1>
        <a class="btn" href="{{ route('delivery-notes.create') }}">{{ __('txn.create_delivery_note') }}</a>
    </div>

    <div class="card delivery-list-card">
        <form id="delivery-notes-filter-form" method="get" class="flex">
            <input id="delivery-notes-search-input" type="text" name="search" placeholder="{{ __('txn.search_delivery_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <input id="delivery-notes-date-input" type="date" name="note_date" value="{{ $selectedNoteDate }}" style="max-width: 180px;">
            <select id="delivery-notes-semester-input" name="semester" style="max-width: 180px;">
                <option value="">{{ __('txn.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <select id="delivery-notes-status-input" name="status" style="max-width: 180px;">
                <option value="">{{ __('txn.all_statuses') }}</option>
                <option value="active" @selected($selectedStatus === 'active')>{{ __('txn.status_active') }}</option>
                <option value="canceled" @selected($selectedStatus === 'canceled')>{{ __('txn.status_canceled') }}</option>
            </select>
            <button type="submit">{{ __('txn.search') }}</button>
            <div class="flex" style="margin-left: auto; padding-left: 10px; border-left: 1px solid var(--border);">
                <a class="btn secondary" href="{{ route('delivery-notes.index', ['search' => $search, 'status' => $selectedStatus, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.all') }}</a>
                <a class="btn secondary" href="{{ route('delivery-notes.index', ['search' => $search, 'semester' => $currentSemester, 'status' => $selectedStatus, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
                <a class="btn secondary" href="{{ route('delivery-notes.index', ['search' => $search, 'semester' => $previousSemester, 'status' => $selectedStatus, 'note_date' => $selectedNoteDate]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.summary') }} {{ $selectedSemester ?: __('txn.all_semesters') }}</strong>
            <div class="muted">
                {{ __('txn.summary_total_delivery_notes') }}: {{ (int) round((int) ($summary->total_notes ?? 0)) }} |
                {{ __('txn.summary_total_qty') }}: {{ (int) round((int) ($summary->total_qty ?? 0)) }}
            </div>
        </div>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_delivery_notes') }}</strong>
            <select style="max-width: 180px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('reports.print', ['dataset' => 'delivery_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('reports.export.pdf', ['dataset' => 'delivery_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('reports.export.csv', ['dataset' => 'delivery_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>{{ __('txn.no') }}</th>
                <th>{{ __('txn.date') }}</th>
                <th>{{ __('txn.recipient') }}</th>
                <th>{{ __('txn.city') }}</th>
                <th>{{ __('txn.created_by') }}</th>
                <th>{{ __('txn.status') }}</th>
                <th>{{ __('txn.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($notes as $note)
                <tr>
                    <td><a href="{{ route('delivery-notes.show', $note) }}">{{ $note->note_number }}</a></td>
                    <td>{{ $note->note_date->format('d-m-Y') }}</td>
                    <td>{{ $note->recipient_name }}</td>
                    <td>{{ $note->city ?: '-' }}</td>
                    <td>{{ $note->created_by_name ?: '-' }}</td>
                    <td>{{ $note->is_canceled ? __('txn.status_canceled') : __('txn.status_active') }}</td>
                    <td>
                        <div class="flex">
                            <select style="max-width: 130px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                                <option value="{{ route('delivery-notes.print', $note) }}">{{ __('txn.print') }}</option>
                                <option value="{{ route('delivery-notes.export.pdf', $note) }}">{{ __('txn.pdf') }}</option>
                                <option value="{{ route('delivery-notes.export.excel', $note) }}">{{ __('txn.excel') }}</option>
                            </select>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ __('txn.no_delivery_notes_found') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $notes->links() }}
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('delivery-notes-filter-form');
            const searchInput = document.getElementById('delivery-notes-search-input');
            const dateInput = document.getElementById('delivery-notes-date-input');
            const semesterInput = document.getElementById('delivery-notes-semester-input');
            const statusInput = document.getElementById('delivery-notes-status-input');

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





