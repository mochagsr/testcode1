@extends('layouts.app')

@section('title', __('txn.sales_returns_title').' - PgPOS ERP')

@section('content')
    <style>
        .return-list-card {
            padding: 10px;
        }
        .return-list-card th,
        .return-list-card td {
            padding-top: 6px;
            padding-bottom: 6px;
        }
    </style>

    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.sales_returns_title') }}</h1>
        <a class="btn" href="{{ route('sales-returns.create') }}">{{ __('txn.create_return') }}</a>
    </div>

    <div class="card return-list-card">
        <form id="sales-returns-filter-form" method="get" class="flex">
            <input id="sales-returns-search-input" type="text" name="search" placeholder="{{ __('txn.search_return_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <input id="sales-returns-date-input" type="date" name="return_date" value="{{ $selectedReturnDate }}" style="max-width: 180px;">
            <select id="sales-returns-semester-input" name="semester" style="max-width: 180px;">
                <option value="">{{ __('txn.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <select id="sales-returns-status-input" name="status" style="max-width: 180px;">
                <option value="">{{ __('txn.all_statuses') }}</option>
                <option value="active" @selected($selectedStatus === 'active')>{{ __('txn.status_active') }}</option>
                <option value="canceled" @selected($selectedStatus === 'canceled')>{{ __('txn.status_canceled') }}</option>
            </select>
            <button type="submit">{{ __('txn.search') }}</button>
            <div class="flex" style="margin-left: auto; padding-left: 10px; border-left: 1px solid var(--border);">
                <a class="btn secondary" href="{{ route('sales-returns.index', ['search' => $search, 'status' => $selectedStatus, 'return_date' => $selectedReturnDate]) }}">{{ __('txn.all') }}</a>
                <a class="btn secondary" href="{{ route('sales-returns.index', ['search' => $search, 'semester' => $currentSemester, 'status' => $selectedStatus, 'return_date' => $selectedReturnDate]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
                <a class="btn secondary" href="{{ route('sales-returns.index', ['search' => $search, 'semester' => $previousSemester, 'status' => $selectedStatus, 'return_date' => $selectedReturnDate]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
            </div>
        </form>
        @if(!empty($isDefaultRecentMode))
            <p class="muted" style="margin: 8px 0 0 0;">Menampilkan data 7 hari terakhir (default). Gunakan filter tanggal/semester untuk data lebih lama.</p>
        @endif
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.summary') }} {{ __('txn.date') }} {{ now()->format('d-m-Y') }}</strong>
            <div class="muted">
                {{ __('txn.summary_total_returns') }}: {{ (int) round((int) ($todaySummary->total_return ?? 0)) }} |
                {{ __('txn.summary_grand_total') }}: Rp {{ number_format((int) round((float) ($todaySummary->grand_total ?? 0), 0), 0, ',', '.') }}
            </div>
        </div>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_sales_returns') }}</strong>
            <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('reports.print', ['dataset' => 'sales_returns', 'semester' => $selectedSemester]) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('reports.export.pdf', ['dataset' => 'sales_returns', 'semester' => $selectedSemester]) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('reports.export.csv', ['dataset' => 'sales_returns', 'semester' => $selectedSemester]) }}">{{ __('txn.excel') }}</option>
            </select>
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
                <th>{{ __('txn.status') }}</th>
                <th>{{ __('txn.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($returns as $row)
                @php
                    $lockKey = ((int) $row->customer_id).':'.(string) $row->semester_period;
                    $lockState = $customerSemesterLockMap[$lockKey] ?? ['locked' => false, 'manual' => false, 'auto' => false];
                    $adminAction = $returnAdminActionMap[(int) $row->id] ?? ['edited' => false, 'canceled' => false];
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('sales-returns.show', $row) }}">{{ $row->return_number }}</a>
                        @if((bool) ($lockState['auto'] ?? false))
                            <span class="badge danger" style="margin-left: 6px;">{{ __('receivable.customer_semester_locked_auto') }}</span>
                        @endif
                        @if((bool) ($lockState['manual'] ?? false))
                            <span class="badge warning" style="margin-left: 6px;">{{ __('receivable.customer_semester_locked_manual') }}</span>
                        @endif
                        @if((bool) ($adminAction['edited'] ?? false))
                            <span class="badge warning" style="margin-left: 6px;">{{ __('txn.admin_badge_edit') }}</span>
                        @endif
                        @if((bool) ($adminAction['canceled'] ?? false))
                            <span class="badge danger" style="margin-left: 6px;">{{ __('txn.admin_badge_cancel') }}</span>
                        @endif
                    </td>
                    <td>{{ $row->return_date->format('d-m-Y') }}</td>
                    <td>
                        {{ $row->customer->name }} <span class="muted">({{ $row->customer->city }})</span>
                    </td>
                    <td>Rp {{ number_format((int) round($row->total), 0, ',', '.') }}</td>
                    <td>{{ $row->is_canceled ? __('txn.status_canceled') : __('txn.status_active') }}</td>
                    <td>
                        <div class="flex">
                            <select class="action-menu action-menu-sm" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                                <option value="{{ route('sales-returns.print', $row) }}">{{ __('txn.print') }}</option>
                                <option value="{{ route('sales-returns.export.pdf', $row) }}">{{ __('txn.pdf') }}</option>
                                <option value="{{ route('sales-returns.export.excel', $row) }}">{{ __('txn.excel') }}</option>
                            </select>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">{{ __('txn.no_returns_found') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $returns->links() }}
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('sales-returns-filter-form');
            const searchInput = document.getElementById('sales-returns-search-input');
            const dateInput = document.getElementById('sales-returns-date-input');
            const semesterInput = document.getElementById('sales-returns-semester-input');
            const statusInput = document.getElementById('sales-returns-status-input');

            if (!form || !searchInput || !dateInput || !semesterInput || !statusInput) {
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
            statusInput.addEventListener('change', () => form.requestSubmit());
        })();
    </script>
@endsection
