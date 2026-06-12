@php
    $supplierSortUrl = function () use ($search, $selectedSupplierId, $selectedYear, $selectedMonth, $direction): string {
        $nextDir = $direction === 'asc' ? 'desc' : 'asc';
        return route('supplier-payables.index', array_filter(['search' => $search, 'supplier_id' => $selectedSupplierId, 'year' => $selectedYear, 'month' => $selectedMonth, 'direction' => $nextDir], fn ($v) => $v !== null && $v !== '' && $v !== 0));
    };
@endphp

<div class="row supplier-payable-main-grid">
    <div class="col-4 supplier-payable-col-suppliers">
        <div class="card">
            <div class="supplier-payable-scroll-wrap">
            <table class="supplier-payable-customers-table mobile-stack-table">
                <colgroup>
                    <col style="width: 38%;">
                    <col style="width: 24%;">
                    <col style="width: 38%;">
                </colgroup>
                <thead>
                <tr>
                    <th><a style="color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:3px;cursor:pointer;" href="{{ $supplierSortUrl() }}">{{ __('txn.supplier') }} <span style="font-size:11px;opacity:0.65;">{{ $direction === 'asc' ? '↑' : '↓' }}</span></a></th>
                    <th class="num">{{ __('supplier_payable.outstanding') }}</th>
                    <th class="action">{{ __('txn.action') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($suppliers as $supplier)
                    <tr>
                        <td data-label="{{ __('txn.supplier') }}" class="supplier-name">{{ $supplier->name }}</td>
                        <td data-label="{{ __('supplier_payable.outstanding') }}" class="num">Rp {{ number_format((int) ($supplier->outstanding_payable ?? 0), 0, ',', '.') }}</td>
                        <td data-label="{{ __('txn.action') }}" class="action">
                            <div class="supplier-payable-actions">
                                <a class="btn orange-btn" href="{{ route('supplier-payables.index', ['supplier_id' => $supplier->id, 'search' => $search, 'year' => $selectedYear, 'month' => $selectedMonth]) }}">
                                    {{ __('supplier_payable.mutation') }}
                                </a>
                                <a class="btn process-btn" href="{{ route('supplier-stock-cards.index', ['supplier_id' => $supplier->id]) }}">
                                    {{ __('menu.supplier_stock_cards') }}
                                </a>
                                @if((int) ($supplier->outstanding_payable ?? 0) > 0)
                                    <a class="btn payment-btn" href="{{ route('supplier-payables.create', ['supplier_id' => $supplier->id]) }}">{{ __('supplier_payable.pay') }}</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">{{ __('supplier_payable.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
            <div style="margin-top:12px;">{{ $suppliers->links() }}</div>
        </div>
    </div>
    <div class="col-8 supplier-payable-col-ledger">
        <div class="card">
            <h3 style="margin-top:0;">
                {{ __('supplier_payable.mutation') }}
                @if($selectedSupplier) ({{ $selectedSupplier->name }}) @endif
            </h3>
            @if($selectedSupplier)
                <div class="supplier-payable-summary-slider">
                    <div class="supplier-payable-summary-item" style="background:#f15b6c;">
                        <small>{{ __('receivable.total_debit') }}</small>
                        <h3 style="margin:4px 0 0 0;">Rp {{ number_format((int) ($totalDebit ?? 0), 0, ',', '.') }}</h3>
                    </div>
                    <div class="supplier-payable-summary-item" style="background:#4ac35f;">
                        <small>{{ __('receivable.total_credit') }}</small>
                        <h3 style="margin:4px 0 0 0;">Rp {{ number_format((int) ($totalCredit ?? 0), 0, ',', '.') }}</h3>
                    </div>
                    <div class="supplier-payable-summary-item" style="background:#4f6de6;">
                        <small>{{ __('supplier_payable.final_outstanding') }}</small>
                        <h3 style="margin:4px 0 0 0;">Rp {{ number_format((int) ($finalOutstanding ?? 0), 0, ',', '.') }}</h3>
                    </div>
                </div>
                <div class="muted" style="margin-bottom: 10px;">
                    {{ __('supplier_payable.outstanding') }}:
                    <strong>Rp {{ number_format((int) ($selectedSupplier->outstanding_payable ?? 0), 0, ',', '.') }}</strong>
                </div>
            @endif
            <div class="supplier-payable-scroll-wrap">
            <table class="supplier-payable-ledger-table">
                <colgroup>
                    <col style="width: 11%;">
                    <col style="width: 37%;">
                    <col style="width: 16%;">
                    <col style="width: 16%;">
                    <col style="width: 20%;">
                </colgroup>
                <thead>
                <tr>
                    <th>{{ __('txn.date') }}</th>
                    <th>{{ __('receivable.description') }}</th>
                    <th class="num">{{ __('receivable.debit') }}</th>
                    <th class="num">{{ __('receivable.credit') }}</th>
                    <th class="num">{{ __('receivable.balance') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($ledgerRows as $row)
                    <tr>
                        <td>{{ $row->entry_date?->format('d-m-Y') }}</td>
                        <td>
                            {{ $row->description ?: '-' }}
                            @if($row->outgoingTransaction)
                                @php
                                    $isOutgoingEdited = (bool) ($outgoingTransactionAdminEditedMap[(int) $row->outgoing_transaction_id] ?? false);
                                @endphp
                                <div class="list-doc-cell" style="margin-top: 4px;">
                                    <a class="list-doc-link" href="{{ route('outgoing-transactions.show', $row->outgoingTransaction) }}" target="_blank">{{ $row->outgoingTransaction->transaction_number }}</a>
                                    <span class="list-doc-badges">
                                        @if($isOutgoingEdited)
                                            <span class="badge warning">{{ __('txn.admin_badge_edit') }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endif
                            @if($row->supplierPayment)
                                @php
                                    $isPaymentEdited = (bool) ($supplierPaymentAdminEditedMap[(int) $row->supplier_payment_id] ?? false);
                                @endphp
                                <div class="list-doc-cell" style="margin-top: 4px;">
                                    <a class="list-doc-link" href="{{ route('supplier-payables.show-payment', $row->supplierPayment) }}" target="_blank">{{ $row->supplierPayment->payment_number }}</a>
                                    <span class="list-doc-badges">
                                        @if($isPaymentEdited)
                                            <span class="badge warning">{{ __('txn.admin_badge_edit') }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td class="num">Rp {{ number_format((int) $row->debit, 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((int) $row->credit, 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((int) $row->balance_after, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('supplier_payable.no_mutation') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
            @if($selectedSupplier)
                <div class="supplier-payable-final-summary">
                    <div>
                        <strong>{{ __('supplier_payable.final_outstanding') }}: Rp {{ number_format((int) ($finalOutstanding ?? 0), 0, ',', '.') }}</strong>
                    </div>
                    <div class="muted">
                        {{ __('supplier_payable.mutation_balance') }}: Rp {{ number_format((int) ($mutationBalance ?? 0), 0, ',', '.') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
