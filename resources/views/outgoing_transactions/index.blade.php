@extends('layouts.app')

@section('title', __('txn.outgoing_transactions_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .outgoing-scroll-wrap {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 420px;
            border: 1px solid color-mix(in srgb, var(--border) 75%, var(--text) 25%);
            border-radius: 8px;
            scrollbar-gutter: stable;
        }
        .outgoing-scroll-wrap table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--card);
        }
        .outgoing-transactions-table,
        .outgoing-recap-table {
            width: 100%;
            border-collapse: collapse;
        }
        .outgoing-transactions-table {
            min-width: 980px;
            table-layout: fixed;
        }
        .outgoing-recap-table {
            min-width: 640px;
            table-layout: fixed;
        }
        .outgoing-transactions-table td,
        .outgoing-transactions-table th,
        .outgoing-recap-table td,
        .outgoing-recap-table th {
            border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            vertical-align: middle;
            padding: 10px 8px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .outgoing-transactions-table td.num,
        .outgoing-transactions-table th.num,
        .outgoing-recap-table td.num,
        .outgoing-recap-table th.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .outgoing-transactions-table td.action,
        .outgoing-transactions-table th.action {
            text-align: center;
            white-space: nowrap;
            width: 8%;
            min-width: 86px;
        }
        .outgoing-transactions-table .action-menu.action-menu-sm {
            min-width: 92px;
            max-width: 92px;
            padding: 5px 8px;
            font-size: 11px;
            min-height: 30px;
        }
        .outgoing-transactions-table td.num,
        .outgoing-transactions-table th.num,
        .outgoing-recap-table td.num,
        .outgoing-recap-table th.num {
            overflow: visible;
            text-overflow: clip;
        }
        .outgoing-transactions-table td.supplier-col,
        .outgoing-transactions-table th.supplier-col {
            white-space: normal;
            max-width: 0;
        }
        .outgoing-main-grid .outgoing-col-list {
            grid-column: span 8;
        }
        .outgoing-main-grid .outgoing-col-recap {
            grid-column: span 4;
        }
        @media (max-width: 1366px) {
            .outgoing-main-grid .outgoing-col-list,
            .outgoing-main-grid .outgoing-col-recap {
                grid-column: span 12;
            }
        }
    </style>

    @php
        $canCreateOutgoingTransaction = auth()->user()?->canAccess('outgoing_transactions.create') ?? false;
    @endphp
    <div class="page-header-actions">
        <h1 class="page-title">{{ __('txn.outgoing_transactions_title') }}</h1>
        <div class="actions">
            @if($canCreateOutgoingTransaction)
                <a class="btn create-transaction-btn" href="{{ route('outgoing-transactions.create') }}">{{ __('txn.create_outgoing_transaction') }}</a>
            @endif
        </div>
    </div>

    <div class="card">
        <form id="outgoing-filter-form" method="get" class="filter-toolbar">
            <div class="filter-field">
                <label for="outgoing-search-input">{{ __('txn.search') }}</label>
                <input id="outgoing-search-input" type="text" name="search" value="{{ $search }}" placeholder="{{ __('txn.search_outgoing_placeholder') }}" style="max-width: 320px;">
            </div>
            <div class="filter-field">
                <label for="outgoing-date-input">{{ __('txn.date') }}</label>
                <input id="outgoing-date-input" type="date" name="transaction_date" value="{{ $selectedTransactionDate ?? '' }}" style="max-width: 150px;">
            </div>
            <div class="filter-field">
                <label for="outgoing-semester-input">{{ __('txn.semester_period') }}</label>
                <select id="outgoing-semester-input" name="semester" style="max-width: 150px;">
                    <option value="">{{ __('txn.all_semesters') }}</option>
                    @foreach($semesterOptions as $semester)
                        <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="outgoing-year-input">{{ __('txn.year') }}</label>
                <select id="outgoing-year-input" name="year" style="max-width: 120px;">
                    <option value="">{{ __('txn.all_years') }}</option>
                    @foreach($yearOptions as $yearOption)
                        <option value="{{ $yearOption }}" @selected($selectedYear === $yearOption)>{{ $yearOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label for="outgoing-supplier-input">{{ __('txn.supplier') }}</label>
                <select id="outgoing-supplier-input" name="supplier_id" style="max-width: 260px;">
                    <option value="">{{ __('txn.all_suppliers') }}</option>
                    @foreach($supplierOptions as $supplierOption)
                        <option value="{{ $supplierOption->id }}" @selected((int) $selectedSupplierId === (int) $supplierOption->id)>
                            {{ $supplierOption->name }}{{ $supplierOption->company_name ? ' ('.$supplierOption->company_name.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit">{{ __('txn.search') }}</button>
        </form>
    </div>

    <div class="card">
        <div class="mobile-summary">
            <strong>{{ __('txn.summary') }}</strong>
            <div class="muted">
                {{ __('txn.outgoing_transaction_count') }}: {{ (int) ($supplierRecapSummary->total_transactions ?? 0) }} |
                {{ __('txn.summary_grand_total') }}: Rp {{ number_format((int) round((float) ($supplierRecapSummary->total_amount ?? 0), 0), 0, ',', '.') }} |
                {{ __('txn.total_weight') }}: {{ number_format((float) ($supplierRecapSummaryTotalWeight ?? 0), 3, ',', '.') }}
            </div>
        </div>
    </div>

    <div class="row outgoing-main-grid">
        <div class="col-8 outgoing-col-list">
            <div class="card">
                <strong>{{ __('txn.outgoing_transactions_title') }}</strong>
                <div class="outgoing-scroll-wrap" style="margin-top: 10px;">
                <table class="outgoing-transactions-table mobile-stack-table">
                    <colgroup>
                        <col style="width: 22%;">
                        <col style="width: 11%;">
                        <col style="width: 14%;">
                        <col style="width: 14%;">
                        <col style="width: 10%;">
                        <col style="width: 11%;">
                        <col style="width: 10%;">
                        <col style="width: 8%;">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>{{ __('txn.transaction_number') }}</th>
                        <th>{{ __('txn.date') }}</th>
                        <th class="supplier-col">{{ __('txn.supplier') }}</th>
                        <th>{{ __('txn.note_number') }}</th>
                        <th>{{ __('txn.semester_period') }}</th>
                        <th class="num">{{ __('txn.total') }}</th>
                        <th class="num">{{ __('txn.total_weight') }}</th>
                        <th class="action">{{ __('txn.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($transactions as $transaction)
                        @php
                            $adminAction = $transactionAdminActionMap[(int) $transaction->id] ?? ['edited' => false];
                        @endphp
                        <tr>
                            <td data-label="{{ __('txn.transaction_number') }}">
                                <div class="list-doc-cell">
                                    <a class="list-doc-link" href="{{ route('outgoing-transactions.show', $transaction) }}">{{ $transaction->transaction_number }}</a>
                                    <span class="list-doc-badges">
                                        @if((bool) ($adminAction['edited'] ?? false))
                                            <span class="badge warning">{{ __('txn.admin_badge_edit') }}</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td data-label="{{ __('txn.date') }}">{{ optional($transaction->transaction_date)->format('d-m-Y') }}</td>
                            <td data-label="{{ __('txn.supplier') }}" class="supplier-col">{{ $transaction->supplier?->name ?: '-' }}</td>
                            <td data-label="{{ __('txn.note_number') }}">{{ $transaction->note_number ?: '-' }}</td>
                            <td data-label="{{ __('txn.semester_period') }}">{{ $transaction->semester_period ?: '-' }}</td>
                            <td data-label="{{ __('txn.total') }}" class="num">Rp {{ number_format((int) round((float) $transaction->total, 0), 0, ',', '.') }}</td>
                            <td data-label="{{ __('txn.total_weight') }}" class="num">{{ number_format((float) ($transaction->total_weight ?? 0), 3, ',', '.') }}</td>
                            <td data-label="{{ __('txn.action') }}" class="action">
                                <select class="action-menu action-menu-sm" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                    <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                                    <option value="{{ route('outgoing-transactions.print', $transaction) }}">{{ __('txn.print') }}</option>
                                    <option value="{{ route('outgoing-transactions.export.pdf', $transaction) }}">{{ __('txn.pdf') }}</option>
                                    <option value="{{ route('outgoing-transactions.export.excel', $transaction) }}">{{ __('txn.excel') }}</option>
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">{{ __('txn.no_outgoing_found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                </div>
                <div style="margin-top: 12px;">{{ $transactions->links() }}</div>
            </div>
        </div>
        <div class="col-4 outgoing-col-recap">
            <div class="card">
                <div class="form-section">
                    <h3 class="form-section-title">{{ __('txn.outgoing_supplier_recap_title') }}</h3>
                    <p class="form-section-note">{{ __('txn.outgoing_supplier_recap_note') }}</p>
                    <div class="outgoing-scroll-wrap">
                    <table class="outgoing-recap-table">
                        <colgroup>
                            <col style="width: 38%;">
                            <col style="width: 16%;">
                            <col style="width: 24%;">
                            <col style="width: 22%;">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>{{ __('txn.supplier') }}</th>
                            <th class="num">{{ __('txn.outgoing_transaction_count') }}</th>
                            <th class="num">{{ __('txn.total') }}</th>
                            <th class="num">{{ __('txn.total_weight') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($supplierRecap as $recap)
                            @php
                                $state = $supplierYearClosedMap[(int) $recap->supplier_id] ?? false;
                            @endphp
                            <tr>
                                <td>
                                    {{ $recap->supplier_name }}
                                    @if($recap->supplier_company_name)
                                        <div class="muted">{{ $recap->supplier_company_name }}</div>
                                    @endif
                                    @if($selectedYear)
                                        <div style="margin-top: 4px;">
                                            @if($state)
                                                <span class="badge danger">{{ __('txn.supplier_semester_closed') }}</span>
                                            @else
                                                <span class="badge success">{{ __('txn.supplier_semester_open') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="num">{{ (int) $recap->transaction_count }}</td>
                                <td class="num">Rp {{ number_format((int) round((float) $recap->total_amount, 0), 0, ',', '.') }}</td>
                                <td class="num">{{ number_format((float) ($recap->total_weight ?? 0), 3, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">{{ __('txn.no_outgoing_supplier_recap') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    </div>
                    <div style="margin-top: 10px;">{{ $supplierRecap->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('outgoing-filter-form');
            const searchInput = document.getElementById('outgoing-search-input');
            const dateInput = document.getElementById('outgoing-date-input');
            const semesterInput = document.getElementById('outgoing-semester-input');
            const yearInput = document.getElementById('outgoing-year-input');
            const supplierInput = document.getElementById('outgoing-supplier-input');

            if (!form || !searchInput || !dateInput || !semesterInput || !yearInput || !supplierInput) {
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
            yearInput.addEventListener('change', () => form.requestSubmit());
            supplierInput.addEventListener('change', () => form.requestSubmit());
        })();
    </script>
@endsection

