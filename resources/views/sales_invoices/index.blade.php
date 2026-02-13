@extends('layouts.app')

@section('title', __('txn.sales_invoices_title').' - PgPOS ERP')

@section('content')
    <style>
        .invoice-list-card {
            padding: 8px;
        }
        .invoice-list-card th,
        .invoice-list-card td {
            padding-top: 4px;
            padding-bottom: 4px;
        }
    </style>

    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.sales_invoices_title') }}</h1>
        <a class="btn" href="{{ route('sales-invoices.create') }}">{{ __('txn.create_invoice') }}</a>
    </div>

    <div class="card">
        <form id="sales-invoices-filter-form" method="get" class="flex">
            <input id="sales-invoices-search-input" type="text" name="search" placeholder="{{ __('txn.search_invoice_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <input id="sales-invoices-date-input" type="date" name="invoice_date" value="{{ $selectedInvoiceDate }}" style="max-width: 180px;">
            <select id="sales-invoices-semester-input" name="semester" style="max-width: 180px;">
                <option value="">{{ __('txn.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <select id="sales-invoices-status-input" name="status" style="max-width: 180px;">
                <option value="">{{ __('txn.all_statuses') }}</option>
                <option value="active" @selected($selectedStatus === 'active')>{{ __('txn.status_active') }}</option>
                <option value="canceled" @selected($selectedStatus === 'canceled')>{{ __('txn.status_canceled') }}</option>
            </select>
            <button type="submit">{{ __('txn.search') }}</button>
            <div class="flex" style="margin-left: auto; padding-left: 10px; border-left: 1px solid var(--border);">
                <a class="btn secondary" href="{{ route('sales-invoices.index', ['search' => $search, 'status' => $selectedStatus, 'invoice_date' => $selectedInvoiceDate]) }}">{{ __('txn.all') }}</a>
                <a class="btn secondary" href="{{ route('sales-invoices.index', ['search' => $search, 'semester' => $currentSemester, 'status' => $selectedStatus, 'invoice_date' => $selectedInvoiceDate]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
                <a class="btn secondary" href="{{ route('sales-invoices.index', ['search' => $search, 'semester' => $previousSemester, 'status' => $selectedStatus, 'invoice_date' => $selectedInvoiceDate]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
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
                {{ __('txn.summary_total_invoices') }}: {{ (int) round((int) ($todaySummary->total_invoice ?? 0)) }} |
                {{ __('txn.summary_grand_total') }}: Rp {{ number_format((int) round((float) ($todaySummary->grand_total ?? 0), 0), 0, ',', '.') }}
            </div>
        </div>
    </div>
    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.report_sales_invoices') }}</strong>
            <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('reports.print', ['dataset' => 'sales_invoices', 'semester' => $selectedSemester]) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('reports.export.pdf', ['dataset' => 'sales_invoices', 'semester' => $selectedSemester]) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('reports.export.csv', ['dataset' => 'sales_invoices', 'semester' => $selectedSemester]) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

    <div class="card invoice-list-card">
        <table>
            <thead>
            <tr>
                <th>{{ __('txn.invoice') }}</th>
                <th>{{ __('txn.date') }}</th>
                <th>{{ __('txn.customer') }}</th>
                <th>{{ __('txn.total') }}</th>
                <th>{{ __('txn.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($invoices as $invoice)
                @php
                    $lockKey = ((int) $invoice->customer_id).':'.(string) $invoice->semester_period;
                    $lockState = $customerSemesterLockMap[$lockKey] ?? ['locked' => false, 'manual' => false, 'auto' => false];
                    $adminAction = $invoiceAdminActionMap[(int) $invoice->id] ?? ['edited' => false, 'canceled' => false];
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('sales-invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a>
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
                    <td>{{ $invoice->invoice_date->format('d-m-Y') }}</td>
                    <td>
                        {{ $invoice->customer->name }} <span class="muted">({{ $invoice->customer->city }})</span>
                    </td>
                    <td>Rp {{ number_format((int) round($invoice->total), 0, ',', '.') }}</td>
                    <td>
                        <div class="flex">
                            <select class="action-menu action-menu-sm" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                                <option value="{{ route('sales-invoices.print', $invoice) }}">{{ __('txn.print') }}</option>
                                <option value="{{ route('sales-invoices.export.pdf', $invoice) }}">{{ __('txn.pdf') }}</option>
                                <option value="{{ route('sales-invoices.export.excel', $invoice) }}">{{ __('txn.excel') }}</option>
                            </select>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">{{ __('txn.no_data_found') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $invoices->links() }}
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('sales-invoices-filter-form');
            const searchInput = document.getElementById('sales-invoices-search-input');
            const dateInput = document.getElementById('sales-invoices-date-input');
            const semesterInput = document.getElementById('sales-invoices-semester-input');
            const statusInput = document.getElementById('sales-invoices-status-input');

            if (!form || !searchInput || !dateInput || !semesterInput || !statusInput) {
                return;
            }

            let debounceTimer = null;
            searchInput.addEventListener('input', () => {
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }

                debounceTimer = setTimeout(() => {
                    if (window.PgposAutoSearch && !window.PgposAutoSearch.canSearchInput(searchInput)) {
                        return;
                    }
                    form.requestSubmit();
                }, 100);
            });

            dateInput.addEventListener('change', () => form.requestSubmit());
            semesterInput.addEventListener('change', () => form.requestSubmit());
            statusInput.addEventListener('change', () => form.requestSubmit());
        })();
    </script>
@endsection





