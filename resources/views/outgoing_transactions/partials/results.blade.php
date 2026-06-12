@php
    $sortUrl = function (string $field) use ($search, $selectedSemester, $selectedYear, $selectedTransactionDate, $selectedSupplierId, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('outgoing-transactions.index', array_filter(['search' => $search, 'semester' => $selectedSemester, 'year' => $selectedYear, 'transaction_date' => $selectedTransactionDate, 'supplier_id' => $selectedSupplierId, 'sort' => $field, 'direction' => $nextDir], fn ($v) => $v !== null && $v !== ''));
    };
    $sortMark = function (string $field) use ($sort, $direction): string {
        if ($sort !== $field) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
@endphp

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
                    <th><a class="sort-link" href="{{ $sortUrl('date') }}">{{ __('txn.date') }} <span class="sort-mark">{{ $sortMark('date') }}</span></a></th>
                    <th class="supplier-col"><a class="sort-link" href="{{ $sortUrl('supplier') }}">{{ __('txn.supplier') }} <span class="sort-mark">{{ $sortMark('supplier') }}</span></a></th>
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
