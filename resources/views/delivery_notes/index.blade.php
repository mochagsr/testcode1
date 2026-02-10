@extends('layouts.app')

@section('title', __('txn.delivery_notes_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.delivery_notes_title') }}</h1>
        <a class="btn" href="{{ route('delivery-notes.create') }}">{{ __('txn.create_delivery_note') }}</a>
    </div>

    <div class="card">
        <form method="get" class="flex">
            <input type="text" name="search" placeholder="{{ __('txn.search_delivery_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <select name="semester" style="max-width: 180px;">
                <option value="">{{ __('txn.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <button type="submit">{{ __('txn.search') }}</button>
        </form>
        <div class="flex" style="margin-top: 10px; gap: 8px;">
            <a class="btn secondary" href="{{ route('delivery-notes.index', ['search' => $search]) }}">{{ __('txn.all') }}</a>
            <a class="btn secondary" href="{{ route('delivery-notes.index', ['search' => $search, 'semester' => $currentSemester]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
            <a class="btn secondary" href="{{ route('delivery-notes.index', ['search' => $search, 'semester' => $previousSemester]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
        </div>
    </div>

    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.summary') }} {{ $selectedSemester ?: __('txn.all_semesters') }}</strong>
            <div class="muted">
                {{ __('txn.summary_total_delivery_notes') }}: {{ number_format((int) ($summary->total_notes ?? 0)) }} |
                {{ __('txn.summary_total_qty') }}: {{ number_format((int) ($summary->total_qty ?? 0)) }}
            </div>
        </div>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_delivery_notes') }}</strong>
            <div class="flex">
                <a class="btn secondary" target="_blank" href="{{ route('reports.print', ['dataset' => 'delivery_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.print') }}</a>
                <a class="btn secondary" href="{{ route('reports.export.pdf', ['dataset' => 'delivery_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.pdf') }}</a>
                <a class="btn" href="{{ route('reports.export.csv', ['dataset' => 'delivery_notes', 'semester' => $selectedSemester]) }}">{{ __('txn.excel') }}</a>
            </div>
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
                    <td>
                        <div class="flex">
                            <a class="btn secondary" href="{{ route('delivery-notes.show', $note) }}">{{ __('txn.detail') }}</a>
                            <a class="btn secondary" target="_blank" href="{{ route('delivery-notes.print', $note) }}">{{ __('txn.print') }}</a>
                            <a class="btn secondary" href="{{ route('delivery-notes.export.pdf', $note) }}">{{ __('txn.pdf') }}</a>
                            <a class="btn" href="{{ route('delivery-notes.export.excel', $note) }}">{{ __('txn.excel') }}</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">{{ __('txn.no_delivery_notes_found') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $notes->links() }}
        </div>
    </div>
@endsection
