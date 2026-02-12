@extends('layouts.app')

@section('title', __('menu.reports').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('report.export_title') }}</h1>

    <div class="card">
        <form method="get" class="flex" style="margin-bottom: 10px;">
            <label for="semester" style="min-width: 130px; align-self: center;">{{ __('report.semester_filter') }}</label>
            <select id="semester" name="semester" style="max-width: 180px;">
                <option value="">{{ __('report.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <label for="customer_id" style="min-width: 130px; align-self: center;">{{ __('report.customer_filter') }}</label>
            <select id="customer_id" name="customer_id" style="max-width: 140px;">
                <option value="">{{ __('report.all_customers') }}</option>
                @foreach($receivableCustomers as $customer)
                    <option value="{{ $customer->id }}" @selected($selectedCustomerId === $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
            <label for="user_role" style="min-width: 120px; align-self: center;">{{ __('report.user_role_filter') }}</label>
            <select id="user_role" name="user_role" style="max-width: 180px;">
                <option value="">{{ __('report.all_roles') }}</option>
                <option value="admin" @selected($selectedUserRole === 'admin')>{{ __('report.values.role_admin') }}</option>
                <option value="user" @selected($selectedUserRole === 'user')>{{ __('report.values.role_user') }}</option>
            </select>
            <label for="finance_lock" style="min-width: 130px; align-self: center;">{{ __('report.finance_lock_filter') }}</label>
            <select id="finance_lock" name="finance_lock" style="max-width: 180px;">
                <option value="">{{ __('report.all_finance_lock') }}</option>
                <option value="1" @selected($selectedFinanceLock === 1)>{{ __('report.finance_lock_yes') }}</option>
                <option value="0" @selected($selectedFinanceLock === 0)>{{ __('report.finance_lock_no') }}</option>
            </select>
            <button type="submit">{{ __('report.apply_filter') }}</button>
        </form>
        <p class="muted" style="margin-top: -4px;">
            {{ __('report.customer_filter_note') }}
        </p>
        <p class="muted" style="margin-top: -4px;">
            {{ __('report.user_filter_note') }}
        </p>
        <p class="muted">{{ __('report.export_options') }}</p>
        <table>
            <thead>
            <tr>
                <th>{{ __('report.dataset') }}</th>
                <th>{{ __('txn.action') }}</th>
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
                    if ($key === 'users') {
                        $query['user_role'] = $selectedUserRole;
                        $query['finance_lock'] = $selectedFinanceLock;
                    }
                @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td>
                        <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                            <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                            <option value="{{ route('reports.print', $query) }}">{{ __('report.open_print') }}</option>
                            <option value="{{ route('reports.export.pdf', $query) }}">{{ __('report.download_pdf') }}</option>
                            <option value="{{ route('reports.export.csv', $query) }}">{{ __('report.download_csv') }}</option>
                        </select>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection





