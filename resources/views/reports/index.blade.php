@extends('layouts.app')

@section('title', __('menu.reports').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('report.export_title') }}</h1>

    <div class="card">
        <form method="get" class="flex" style="margin-bottom: 10px;">
            <label for="semester" style="min-width: 130px; align-self: center;">{{ __('report.semester_filter') }}</label>
            <select id="semester" name="semester" style="max-width: 220px;">
                <option value="">{{ __('report.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <label for="customer_id" style="min-width: 130px; align-self: center;">{{ __('report.customer_filter') }}</label>
            <select id="customer_id" name="customer_id" style="max-width: 260px;">
                <option value="">{{ __('report.all_customers') }}</option>
                @foreach($receivableCustomers as $customer)
                    <option value="{{ $customer->id }}" @selected($selectedCustomerId === $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
            <button type="submit">{{ __('report.apply_filter') }}</button>
        </form>
        <p class="muted" style="margin-top: -4px;">
            {{ __('report.customer_filter_note') }}
        </p>
        <p class="muted">{{ __('report.export_options') }}</p>
        <table>
            <thead>
            <tr>
                <th>{{ __('report.dataset') }}</th>
                <th>{{ __('report.csv_excel') }}</th>
                <th>{{ __('report.pdf') }}</th>
                <th>{{ __('report.print_view') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($datasets as $key => $label)
                @php
                    $query = in_array($key, $semesterEnabledDatasets, true)
                        ? ['dataset' => $key, 'semester' => $selectedSemester]
                        : ['dataset' => $key];
                    if ($key === 'receivables') {
                        $query['customer_id'] = $selectedCustomerId;
                    }
                @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td><a class="btn secondary" href="{{ route('reports.export.csv', $query) }}">{{ __('report.download_csv') }}</a></td>
                    <td><a class="btn secondary" href="{{ route('reports.export.pdf', $query) }}">{{ __('report.download_pdf') }}</a></td>
                    <td><a class="btn" target="_blank" href="{{ route('reports.print', $query) }}">{{ __('report.open_print') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
