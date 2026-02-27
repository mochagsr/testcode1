@extends('layouts.app')

@section('title', __('supplier_payable.title').' - PgPOS ERP')

@section('content')
    <style>
        .supplier-payable-scroll-wrap {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 440px;
            border: 1px solid color-mix(in srgb, var(--border) 75%, var(--text) 25%);
            border-radius: 8px;
            scrollbar-gutter: stable;
        }
        .supplier-payable-scroll-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--card);
        }
        .supplier-payable-customers-table,
        .supplier-payable-ledger-table {
            width: 100%;
            border-collapse: collapse;
        }
        .supplier-payable-customers-table {
            min-width: 760px;
            table-layout: fixed;
        }
        .supplier-payable-ledger-table {
            min-width: 980px;
            table-layout: fixed;
        }
        .supplier-payable-customers-table td,
        .supplier-payable-customers-table th,
        .supplier-payable-ledger-table td,
        .supplier-payable-ledger-table th {
            border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            vertical-align: middle;
            padding: 10px 8px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .supplier-payable-customers-table td.num,
        .supplier-payable-customers-table th.num,
        .supplier-payable-ledger-table td.num,
        .supplier-payable-ledger-table th.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            overflow: visible;
            text-overflow: clip;
        }
        .supplier-payable-customers-table td.action,
        .supplier-payable-customers-table th.action {
            width: 38%;
            white-space: nowrap;
            min-width: 220px;
        }
        .supplier-payable-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            width: 100%;
        }
        .supplier-payable-actions .btn {
            min-height: 30px;
            padding: 5px 9px;
            font-size: 11px;
            min-width: 98px;
            text-align: center;
        }
        .supplier-payable-customers-table td.supplier-name,
        .supplier-payable-customers-table th.supplier-name {
            white-space: normal;
            max-width: 0;
        }
        .supplier-payable-main-grid .supplier-payable-col-suppliers {
            grid-column: span 5;
        }
        .supplier-payable-main-grid .supplier-payable-col-ledger {
            grid-column: span 7;
        }
        .supplier-payable-ledger-table td,
        .supplier-payable-ledger-table th {
            font-variant-numeric: tabular-nums;
        }
        @media (max-width: 1366px) {
            .supplier-payable-main-grid .supplier-payable-col-suppliers,
            .supplier-payable-main-grid .supplier-payable-col-ledger {
                grid-column: span 12;
            }
        }
    </style>

    <h1 class="page-title">{{ __('supplier_payable.title') }}</h1>

    <div class="card">
        <form method="get" class="flex" id="supplier-payable-filter-form">
            <input id="supplier-payable-search" type="text" name="search" value="{{ $search }}" placeholder="{{ __('supplier_payable.search_placeholder') }}" style="max-width:320px;">
            <select name="supplier_id" id="supplier-payable-supplier" style="max-width:240px;">
                <option value="">{{ __('supplier_payable.all_suppliers') }}</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((int) $selectedSupplierId === (int) $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
            <select name="semester" id="supplier-payable-semester" style="max-width:200px;">
                <option value="">{{ __('supplier_payable.all_semesters') }}</option>
                @foreach($semesterOptions as $option)
                    <option value="{{ $option }}" @selected($selectedSemester === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <button type="submit">{{ __('txn.search') }}</button>
            <a class="btn" href="{{ route('supplier-payables.create') }}">{{ __('supplier_payable.add_payment') }}</a>
        </form>
    </div>

    <div class="row supplier-payable-main-grid">
        <div class="col-4 supplier-payable-col-suppliers">
            <div class="card">
                <div class="supplier-payable-scroll-wrap">
                <table class="supplier-payable-customers-table">
                    <colgroup>
                        <col style="width: 38%;">
                        <col style="width: 24%;">
                        <col style="width: 38%;">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>{{ __('txn.supplier') }}</th>
                        <th class="num">{{ __('supplier_payable.outstanding') }}</th>
                        <th class="action">{{ __('txn.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td class="supplier-name">{{ $supplier->name }}</td>
                            <td class="num">Rp {{ number_format((int) ($supplier->outstanding_payable ?? 0), 0, ',', '.') }}</td>
                            <td class="action">
                                <div class="supplier-payable-actions">
                                    <a class="btn secondary" href="{{ route('supplier-payables.index', ['supplier_id' => $supplier->id, 'search' => $search, 'semester' => $selectedSemester]) }}">
                                        {{ __('supplier_payable.mutation') }}
                                    </a>
                                    <a class="btn secondary" href="{{ route('supplier-stock-cards.index', ['supplier_id' => $supplier->id]) }}">
                                        {{ __('menu.supplier_stock_cards') }}
                                    </a>
                                    @if((int) ($supplier->outstanding_payable ?? 0) > 0)
                                        <a class="btn" href="{{ route('supplier-payables.create', ['supplier_id' => $supplier->id]) }}">{{ __('supplier_payable.pay') }}</a>
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
                                    @php($isOutgoingEdited = (bool) ($outgoingTransactionAdminEditedMap[(int) $row->outgoing_transaction_id] ?? false))
                                    <div>
                                        <a href="{{ route('outgoing-transactions.show', $row->outgoingTransaction) }}" target="_blank">{{ $row->outgoingTransaction->transaction_number }}</a>
                                        @if($isOutgoingEdited)
                                            <span class="badge warning" style="margin-left: 6px;">{{ __('txn.admin_badge_edit') }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if($row->supplierPayment)
                                    @php($isPaymentEdited = (bool) ($supplierPaymentAdminEditedMap[(int) $row->supplier_payment_id] ?? false))
                                    <div>
                                        <a href="{{ route('supplier-payables.show-payment', $row->supplierPayment) }}" target="_blank">{{ $row->supplierPayment->payment_number }}</a>
                                        @if($isPaymentEdited)
                                            <span class="badge warning" style="margin-left: 6px;">{{ __('txn.admin_badge_edit') }}</span>
                                        @endif
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
            </div>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('supplier-payable-filter-form');
            const searchInput = document.getElementById('supplier-payable-search');
            const supplierSelect = document.getElementById('supplier-payable-supplier');
            const semesterSelect = document.getElementById('supplier-payable-semester');
            if (!form || !searchInput || !supplierSelect || !semesterSelect) return;
            const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                ? window.PgposAutoSearch.debounce
                : (fn, wait = 100) => { let t = null; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), wait); }; };
            const onSearch = debounce(() => {
                if (window.PgposAutoSearch && !window.PgposAutoSearch.canSearchInput(searchInput)) return;
                form.requestSubmit();
            }, 100);
            searchInput.addEventListener('input', onSearch);
            supplierSelect.addEventListener('change', () => form.requestSubmit());
            semesterSelect.addEventListener('change', () => form.requestSubmit());
        })();
    </script>
@endsection
