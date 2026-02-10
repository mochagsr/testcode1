@extends('layouts.app')

@section('title', __('txn.sales_invoices_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.sales_invoices_title') }}</h1>
        <a class="btn" href="{{ route('sales-invoices.create') }}">{{ __('txn.create_invoice') }}</a>
    </div>

    <div class="card">
        <form method="get" class="flex">
            <input type="text" name="search" placeholder="{{ __('txn.search_invoice_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <select name="semester" style="max-width: 180px;">
                <option value="">{{ __('txn.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <button type="submit">{{ __('txn.search') }}</button>
        </form>
        <div class="flex" style="margin-top: 10px; gap: 8px;">
            <a class="btn secondary" href="{{ route('sales-invoices.index', ['search' => $search]) }}">{{ __('txn.all') }}</a>
            <a class="btn secondary" href="{{ route('sales-invoices.index', ['search' => $search, 'semester' => $currentSemester]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
            <a class="btn secondary" href="{{ route('sales-invoices.index', ['search' => $search, 'semester' => $previousSemester]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
        </div>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.summary') }} {{ $selectedSemester ?: __('txn.all_semesters') }}</strong>
            <div class="muted">
                {{ __('txn.summary_total_invoices') }}: {{ number_format((int) ($semesterSummary->total_invoice ?? 0)) }} |
                {{ __('txn.summary_grand_total') }}: Rp {{ number_format((float) ($semesterSummary->grand_total ?? 0), 2) }} |
                {{ __('txn.summary_paid') }}: Rp {{ number_format((float) ($semesterSummary->paid_total ?? 0), 2) }} |
                {{ __('txn.summary_balance') }}: Rp {{ number_format((float) ($semesterSummary->balance_total ?? 0), 2) }}
            </div>
        </div>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_sales_invoices') }}</strong>
            <div class="flex">
                <a class="btn secondary" target="_blank" href="{{ route('reports.print', ['dataset' => 'sales_invoices', 'semester' => $selectedSemester]) }}">{{ __('txn.print') }}</a>
                <a class="btn secondary" href="{{ route('reports.export.pdf', ['dataset' => 'sales_invoices', 'semester' => $selectedSemester]) }}">{{ __('txn.pdf') }}</a>
                <a class="btn" href="{{ route('reports.export.csv', ['dataset' => 'sales_invoices', 'semester' => $selectedSemester]) }}">{{ __('txn.excel') }}</a>
            </div>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>{{ __('txn.invoice') }}</th>
                <th>{{ __('txn.date') }}</th>
                <th>{{ __('txn.customer') }}</th>
                <th>{{ __('txn.total') }}</th>
                <th>{{ __('txn.paid') }}</th>
                <th>{{ __('txn.balance') }}</th>
                <th>{{ __('txn.status') }}</th>
                <th>{{ __('txn.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td><a href="{{ route('sales-invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                    <td>{{ $invoice->invoice_date->format('d-m-Y') }}</td>
                    <td>{{ $invoice->customer->name }} <span class="muted">({{ $invoice->customer->city }})</span></td>
                    <td>Rp {{ number_format($invoice->total, 2) }}</td>
                    <td>Rp {{ number_format($invoice->total_paid, 2) }}</td>
                    <td>Rp {{ number_format($invoice->balance, 2) }}</td>
                    <td>{{ strtoupper($invoice->payment_status) }}</td>
                    <td>
                        <div class="flex">
                            <a class="btn secondary" href="{{ route('sales-invoices.show', $invoice) }}">{{ __('txn.detail') }}</a>
                            <a class="btn secondary" target="_blank" href="{{ route('sales-invoices.print', $invoice) }}">{{ __('txn.print') }}</a>
                            <a class="btn secondary" href="{{ route('sales-invoices.export.pdf', $invoice) }}">{{ __('txn.pdf') }}</a>
                            <a class="btn" href="{{ route('sales-invoices.export.excel', $invoice) }}">{{ __('txn.excel') }}</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">{{ __('txn.no_data_found') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $invoices->links() }}
        </div>
    </div>
@endsection
