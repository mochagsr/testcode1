@extends('layouts.app')

@section('title', __('txn.sales_returns_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.sales_returns_title') }}</h1>
        <a class="btn" href="{{ route('sales-returns.create') }}">{{ __('txn.create_return') }}</a>
    </div>

    <div class="card">
        <form method="get" class="flex">
            <input type="text" name="search" placeholder="{{ __('txn.search_return_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <select name="semester" style="max-width: 180px;">
                <option value="">{{ __('txn.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <button type="submit">{{ __('txn.search') }}</button>
        </form>
        <div class="flex" style="margin-top: 10px; gap: 8px;">
            <a class="btn secondary" href="{{ route('sales-returns.index', ['search' => $search]) }}">{{ __('txn.all') }}</a>
            <a class="btn secondary" href="{{ route('sales-returns.index', ['search' => $search, 'semester' => $currentSemester]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
            <a class="btn secondary" href="{{ route('sales-returns.index', ['search' => $search, 'semester' => $previousSemester]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
        </div>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.summary') }} {{ $selectedSemester ?: __('txn.all_semesters') }}</strong>
            <div class="muted">
                {{ __('txn.summary_total_returns') }}: {{ number_format((int) ($semesterSummary->total_return ?? 0)) }} |
                {{ __('txn.summary_grand_total') }}: Rp {{ number_format((float) ($semesterSummary->grand_total ?? 0), 2) }}
            </div>
        </div>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_sales_returns') }}</strong>
            <div class="flex">
                <a class="btn secondary" target="_blank" href="{{ route('reports.print', ['dataset' => 'sales_returns', 'semester' => $selectedSemester]) }}">{{ __('txn.print') }}</a>
                <a class="btn secondary" href="{{ route('reports.export.pdf', ['dataset' => 'sales_returns', 'semester' => $selectedSemester]) }}">{{ __('txn.pdf') }}</a>
                <a class="btn" href="{{ route('reports.export.csv', ['dataset' => 'sales_returns', 'semester' => $selectedSemester]) }}">{{ __('txn.excel') }}</a>
            </div>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>{{ __('txn.return') }}</th>
                <th>{{ __('txn.date') }}</th>
                <th>{{ __('txn.customer') }}</th>
                <th>{{ __('txn.total_return') }}</th>
                <th>{{ __('txn.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($returns as $row)
                <tr>
                    <td><a href="{{ route('sales-returns.show', $row) }}">{{ $row->return_number }}</a></td>
                    <td>{{ $row->return_date->format('d-m-Y') }}</td>
                    <td>{{ $row->customer->name }} <span class="muted">({{ $row->customer->city }})</span></td>
                    <td>Rp {{ number_format($row->total, 2) }}</td>
                    <td>
                        <div class="flex">
                            <a class="btn secondary" href="{{ route('sales-returns.show', $row) }}">{{ __('txn.detail') }}</a>
                            <a class="btn secondary" target="_blank" href="{{ route('sales-returns.print', $row) }}">{{ __('txn.print') }}</a>
                            <a class="btn secondary" href="{{ route('sales-returns.export.pdf', $row) }}">{{ __('txn.pdf') }}</a>
                            <a class="btn" href="{{ route('sales-returns.export.excel', $row) }}">{{ __('txn.excel') }}</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">{{ __('txn.no_returns_found') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $returns->links() }}
        </div>
    </div>
@endsection
