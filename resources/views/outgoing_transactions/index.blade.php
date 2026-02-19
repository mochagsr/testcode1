@extends('layouts.app')

@section('title', __('txn.outgoing_transactions_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.outgoing_transactions_title') }}</h1>
        <a class="btn" href="{{ route('outgoing-transactions.create') }}">{{ __('txn.create_outgoing_transaction') }}</a>
    </div>

    <div class="card">
        <form id="outgoing-filter-form" method="get" class="flex">
            <input id="outgoing-search-input" type="text" name="search" value="{{ $search }}" placeholder="{{ __('txn.search_outgoing_placeholder') }}" style="max-width: 320px;">
            <input id="outgoing-date-input" type="date" name="transaction_date" value="{{ $selectedTransactionDate ?? '' }}" style="max-width: 180px;">
            <select id="outgoing-semester-input" name="semester" style="max-width: 180px;">
                <option value="">{{ __('txn.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <select id="outgoing-supplier-input" name="supplier_id" style="max-width: 260px;">
                <option value="">{{ __('txn.all_suppliers') }}</option>
                @foreach($supplierOptions as $supplierOption)
                    <option value="{{ $supplierOption->id }}" @selected((int) $selectedSupplierId === (int) $supplierOption->id)>
                        {{ $supplierOption->name }}{{ $supplierOption->company_name ? ' ('.$supplierOption->company_name.')' : '' }}
                    </option>
                @endforeach
            </select>
            <button type="submit">{{ __('txn.search') }}</button>
            <div class="flex" style="margin-left: auto; padding-left: 10px; border-left: 1px solid var(--border);">
                <a class="btn secondary" href="{{ route('outgoing-transactions.index', ['search' => $search, 'supplier_id' => $selectedSupplierId, 'transaction_date' => $selectedTransactionDate]) }}">{{ __('txn.all') }}</a>
                <a class="btn secondary" href="{{ route('outgoing-transactions.index', ['search' => $search, 'semester' => $currentSemester, 'supplier_id' => $selectedSupplierId, 'transaction_date' => $selectedTransactionDate]) }}">{{ __('txn.semester_this') }} ({{ $currentSemester }})</a>
                <a class="btn secondary" href="{{ route('outgoing-transactions.index', ['search' => $search, 'semester' => $previousSemester, 'supplier_id' => $selectedSupplierId, 'transaction_date' => $selectedTransactionDate]) }}">{{ __('txn.semester_last') }} ({{ $previousSemester }})</a>
            </div>
        </form>
        @if(!empty($isDefaultRecentMode))
            <p class="muted" style="margin: 8px 0 0 0;">Menampilkan data 7 hari terakhir (default). Gunakan filter tanggal/semester untuk data lebih lama.</p>
        @endif
    </div>

    <div class="card">
        <div class="flex" style="justify-content: space-between;">
            <strong>{{ __('txn.summary') }}</strong>
            <div class="muted">
                {{ __('txn.outgoing_transaction_count') }}: {{ (int) ($supplierRecapSummary->total_transactions ?? 0) }} |
                {{ __('txn.summary_grand_total') }}: Rp {{ number_format((int) round((float) ($supplierRecapSummary->total_amount ?? 0), 0), 0, ',', '.') }}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-8">
            <div class="card">
                <strong>{{ __('txn.outgoing_transactions_title') }}</strong>
                <table style="margin-top: 10px;">
                    <thead>
                    <tr>
                        <th>{{ __('txn.transaction_number') }}</th>
                        <th>{{ __('txn.date') }}</th>
                        <th>{{ __('txn.supplier') }}</th>
                        <th>{{ __('txn.note_number') }}</th>
                        <th>{{ __('txn.semester_period') }}</th>
                        <th>{{ __('txn.total') }}</th>
                        <th>{{ __('txn.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($transactions as $transaction)
                        @php
                            $adminAction = $transactionAdminActionMap[(int) $transaction->id] ?? ['edited' => false];
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('outgoing-transactions.show', $transaction) }}">{{ $transaction->transaction_number }}</a>
                                @if((bool) ($adminAction['edited'] ?? false))
                                    <span class="badge warning" style="margin-left: 6px;">{{ __('txn.admin_badge_edit') }}</span>
                                @endif
                            </td>
                            <td>{{ optional($transaction->transaction_date)->format('d-m-Y') }}</td>
                            <td>{{ $transaction->supplier?->name ?: '-' }}</td>
                            <td>{{ $transaction->note_number ?: '-' }}</td>
                            <td>{{ $transaction->semester_period ?: '-' }}</td>
                            <td>Rp {{ number_format((int) round((float) $transaction->total, 0), 0, ',', '.') }}</td>
                            <td>
                                <select class="action-menu action-menu-sm" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                    <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                                    <option value="{{ route('outgoing-transactions.print', $transaction) }}">{{ __('txn.print') }}</option>
                                    <option value="{{ route('outgoing-transactions.export.pdf', $transaction) }}">{{ __('txn.pdf') }}</option>
                                    <option value="{{ route('outgoing-transactions.export.excel', $transaction) }}">{{ __('txn.excel') }}</option>
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">{{ __('txn.no_outgoing_found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <div style="margin-top: 12px;">{{ $transactions->links() }}</div>
            </div>
        </div>
        <div class="col-4">
            <div class="card">
                <div class="form-section">
                    <h3 class="form-section-title">{{ __('txn.outgoing_supplier_recap_title') }}</h3>
                    <p class="form-section-note">{{ __('txn.outgoing_supplier_recap_note') }}</p>
                    <table>
                        <thead>
                        <tr>
                            <th>{{ __('txn.supplier') }}</th>
                            <th>{{ __('txn.outgoing_transaction_count') }}</th>
                            <th>{{ __('txn.total') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($supplierRecap as $recap)
                            @php
                                $state = $supplierSemesterClosedMap[(int) $recap->supplier_id] ?? false;
                            @endphp
                            <tr>
                                <td>
                                    {{ $recap->supplier_name }}
                                    @if($recap->supplier_company_name)
                                        <div class="muted">{{ $recap->supplier_company_name }}</div>
                                    @endif
                                    @if($selectedSemester)
                                        <div style="margin-top: 4px;">
                                            @if($state)
                                                <span class="badge danger">{{ __('txn.supplier_semester_closed') }}</span>
                                            @else
                                                <span class="badge success">{{ __('txn.supplier_semester_open') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>{{ (int) $recap->transaction_count }}</td>
                                <td>Rp {{ number_format((int) round((float) $recap->total_amount, 0), 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">{{ __('txn.no_outgoing_supplier_recap') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    <div style="margin-top: 10px;">{{ $supplierRecap->links() }}</div>
                </div>
            </div>
            @if(auth()->user()->role === 'admin' && $selectedSemester && $selectedSupplierId)
                @php
                    $selectedSupplier = $supplierOptions->firstWhere('id', $selectedSupplierId);
                @endphp
                @if($selectedSupplier)
                    <div class="card">
                        <div class="form-section">
                            <h3 class="form-section-title">{{ __('txn.supplier_semester_book_title') }}</h3>
                            <p class="form-section-note">{{ __('txn.supplier_semester_status') }}: {{ $selectedSupplier->name }} / {{ $selectedSemester }}</p>
                            @if($selectedSupplierSemesterClosed)
                                <span class="badge danger">{{ __('txn.supplier_semester_closed') }}</span>
                                <form method="post" action="{{ route('outgoing-transactions.supplier-semester.open', ['supplier' => $selectedSupplierId]) }}" style="margin-top: 10px;">
                                    @csrf
                                    <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                                    <input type="hidden" name="search" value="{{ $search }}">
                                    <input type="hidden" name="supplier_id" value="{{ $selectedSupplierId }}">
                                    <button class="btn secondary" type="submit">{{ __('txn.supplier_semester_open_button') }}</button>
                                </form>
                            @else
                                <span class="badge success">{{ __('txn.supplier_semester_open') }}</span>
                                <form method="post" action="{{ route('outgoing-transactions.supplier-semester.close', ['supplier' => $selectedSupplierId]) }}" style="margin-top: 10px;">
                                    @csrf
                                    <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                                    <input type="hidden" name="search" value="{{ $search }}">
                                    <input type="hidden" name="supplier_id" value="{{ $selectedSupplierId }}">
                                    <button class="btn secondary" type="submit">{{ __('txn.supplier_semester_close_button') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('outgoing-filter-form');
            const searchInput = document.getElementById('outgoing-search-input');
            const dateInput = document.getElementById('outgoing-date-input');
            const semesterInput = document.getElementById('outgoing-semester-input');
            const supplierInput = document.getElementById('outgoing-supplier-input');

            if (!form || !searchInput || !dateInput || !semesterInput || !supplierInput) {
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
            supplierInput.addEventListener('change', () => form.requestSubmit());
        })();
    </script>
@endsection
