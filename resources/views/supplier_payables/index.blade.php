@extends('layouts.app')

@section('title', __('supplier_payable.title').' - PgPOS ERP')

@section('content')
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

    <div class="row">
        <div class="col-4">
            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('txn.supplier') }}</th>
                        <th>{{ __('supplier_payable.outstanding') }}</th>
                        <th>{{ __('txn.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td>{{ $supplier->name }}</td>
                            <td>Rp {{ number_format((int) ($supplier->outstanding_payable ?? 0), 0, ',', '.') }}</td>
                            <td>
                                <a class="btn secondary" href="{{ route('supplier-payables.index', ['supplier_id' => $supplier->id, 'search' => $search, 'semester' => $selectedSemester]) }}">
                                    {{ __('supplier_payable.mutation') }}
                                </a>
                                @if((int) ($supplier->outstanding_payable ?? 0) > 0)
                                    <a class="btn" href="{{ route('supplier-payables.create', ['supplier_id' => $supplier->id]) }}">{{ __('supplier_payable.pay') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">{{ __('supplier_payable.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <div style="margin-top:12px;">{{ $suppliers->links() }}</div>
            </div>
        </div>
        <div class="col-8">
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
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('txn.date') }}</th>
                        <th>{{ __('receivable.description') }}</th>
                        <th>{{ __('receivable.debit') }}</th>
                        <th>{{ __('receivable.credit') }}</th>
                        <th>{{ __('receivable.balance') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($ledgerRows as $row)
                        <tr>
                            <td>{{ $row->entry_date?->format('d-m-Y') }}</td>
                            <td>
                                {{ $row->description ?: '-' }}
                                @if($row->outgoingTransaction)
                                    <div><a href="{{ route('outgoing-transactions.show', $row->outgoingTransaction) }}" target="_blank">{{ $row->outgoingTransaction->transaction_number }}</a></div>
                                @endif
                                @if($row->supplierPayment)
                                    <div><a href="{{ route('supplier-payables.show-payment', $row->supplierPayment) }}" target="_blank">{{ $row->supplierPayment->payment_number }}</a></div>
                                @endif
                            </td>
                            <td>Rp {{ number_format((int) $row->debit, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((int) $row->credit, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((int) $row->balance_after, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">{{ __('supplier_payable.no_mutation') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
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
