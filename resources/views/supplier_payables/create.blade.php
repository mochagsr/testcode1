@extends('layouts.app')

@section('title', __('supplier_payable.add_payment').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('supplier_payable.add_payment') }}</h1>

    <div class="card">
        <form method="post" action="{{ route('supplier-payables.store') }}" enctype="multipart/form-data">
            @csrf
            @php
                $suppliersById = $suppliers->keyBy('id');
                $oldSupplierId = (int) old('supplier_id', (int) ($prefillSupplierId ?? 0));
                $oldSupplier = $oldSupplierId > 0 ? $suppliersById->get($oldSupplierId) : null;
                $oldSupplierLabel = $oldSupplier ? $oldSupplier->name.($oldSupplier->company_name ? ' ('.$oldSupplier->company_name.')' : '') : '';
            @endphp
            <div class="row inline">
                <div class="col-4">
                    <label>{{ __('txn.supplier') }}</label>
                    <input type="text" id="supplier-search" list="suppliers-list" value="{{ $oldSupplierLabel }}" placeholder="{{ __('txn.select_supplier') }}" required>
                    <input type="hidden" id="supplier-id" name="supplier_id" value="{{ $oldSupplierId > 0 ? $oldSupplierId : '' }}" required>
                    <datalist id="suppliers-list">
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->name }}{{ $supplier->company_name ? ' ('.$supplier->company_name.')' : '' }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-4">
                    <label>{{ __('txn.date') }}</label>
                    <input type="date" name="payment_date" value="{{ old('payment_date', $prefillDate) }}" required>
                </div>
                <div class="col-4">
                    <label>{{ __('supplier_payable.proof_number') }}</label>
                    <input type="text" name="proof_number" value="{{ old('proof_number') }}" maxlength="80">
                </div>
                <div class="col-4">
                    <label>{{ __('supplier_payable.payment_proof_photo') }}</label>
                    <input type="file" name="payment_proof_photo" accept="image/*">
                </div>
                <div class="col-4">
                    <label>{{ __('txn.amount') }}</label>
                    <input type="number" min="1" step="1" name="amount" value="{{ old('amount') }}" required>
                </div>
                <div class="col-4">
                    <label>{{ __('supplier_payable.supplier_signature') }}</label>
                    <input type="text" name="supplier_signature" value="{{ old('supplier_signature') }}" maxlength="120">
                </div>
                <div class="col-4">
                    <label>{{ __('supplier_payable.user_signature') }}</label>
                    <input type="text" name="user_signature" value="{{ old('user_signature', auth()->user()->name) }}" maxlength="120">
                </div>
                <div class="col-12">
                    <label>{{ __('txn.notes') }}</label>
                    <textarea name="notes">{{ old('notes') }}</textarea>
                </div>
            </div>
            <button type="submit">{{ __('supplier_payable.save_payment') }}</button>
        </form>
    </div>

    <script>
        (function () {
            const form = document.querySelector('form');
            const supplierSearch = document.getElementById('supplier-search');
            const supplierIdField = document.getElementById('supplier-id');
            const suppliersList = document.getElementById('suppliers-list');
            const SUPPLIER_LOOKUP_URL = @json(route('suppliers.lookup'));
            const LOOKUP_LIMIT = 20;
            let suppliers = @json($suppliers);
            let supplierByLabel = new Map();
            let supplierByName = new Map();
            let lastSupplierLookupQuery = '';
            let supplierLookupAbort = null;
            let isSubmitting = false;

            if (!form || !supplierSearch || !supplierIdField || !suppliersList) {
                return;
            }

            const normalizeLookup = (value) => String(value || '').trim().toLowerCase();
            const supplierLabel = (supplier) => `${supplier.name}${supplier.company_name ? ` (${supplier.company_name})` : ''}`;
            const escapeAttribute = (value) => String(value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                ? window.PgposAutoSearch.debounce
                : (fn, wait = 100) => {
                    let timeoutId = null;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => fn(...args), wait);
                    };
                };

            function rebuildSupplierIndexes() {
                supplierByLabel = new Map();
                supplierByName = new Map();
                suppliers.forEach((supplier) => {
                    const labelKey = normalizeLookup(supplierLabel(supplier));
                    const nameKey = normalizeLookup(supplier.name);
                    if (labelKey !== '' && !supplierByLabel.has(labelKey)) {
                        supplierByLabel.set(labelKey, supplier);
                    }
                    if (nameKey !== '' && !supplierByName.has(nameKey)) {
                        supplierByName.set(nameKey, supplier);
                    }
                });
            }

            function renderSupplierSuggestions(query) {
                const normalized = normalizeLookup(query);
                const matches = suppliers.filter((supplier) => {
                    const label = supplierLabel(supplier).toLowerCase();
                    const name = String(supplier.name || '').toLowerCase();
                    return normalized === '' || label.includes(normalized) || name.includes(normalized);
                }).slice(0, 60);

                suppliersList.innerHTML = matches
                    .map((supplier) => `<option value="${escapeAttribute(supplierLabel(supplier))}"></option>`)
                    .join('');
            }

            function upsertSuppliers(rows) {
                const byId = new Map(suppliers.map((row) => [String(row.id), row]));
                (rows || []).forEach((row) => byId.set(String(row.id), row));
                suppliers = Array.from(byId.values());
                rebuildSupplierIndexes();
            }

            function findSupplierByLabel(label) {
                if (!label) return null;
                const normalized = normalizeLookup(label);
                return supplierByLabel.get(normalized) || supplierByName.get(normalized) || null;
            }

            function findSupplierLoose(label) {
                if (!label) return null;
                const normalized = normalizeLookup(label);
                return suppliers.find((supplier) => supplierLabel(supplier).toLowerCase().includes(normalized))
                    || suppliers.find((supplier) => String(supplier.name || '').toLowerCase().includes(normalized))
                    || null;
            }

            async function fetchSupplierSuggestions(query) {
                const normalizedQuery = normalizeLookup(query);
                if (!(window.PgposAutoSearch && window.PgposAutoSearch.canSearchInput({ value: query }))) {
                    lastSupplierLookupQuery = '';
                    renderSupplierSuggestions(query);
                    return;
                }
                if (normalizedQuery !== '' && normalizedQuery === lastSupplierLookupQuery) {
                    renderSupplierSuggestions(query);
                    return;
                }
                try {
                    if (supplierLookupAbort) {
                        supplierLookupAbort.abort();
                    }
                    supplierLookupAbort = new AbortController();
                    const url = `${SUPPLIER_LOOKUP_URL}?search=${encodeURIComponent(query)}&per_page=${LOOKUP_LIMIT}`;
                    const response = await fetch(url, { signal: supplierLookupAbort.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) {
                        return;
                    }
                    const payload = await response.json();
                    lastSupplierLookupQuery = normalizedQuery;
                    upsertSuppliers(payload.data || []);
                    renderSupplierSuggestions(query);
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                }
            }

            async function resolveSupplierFromInput(rawValue) {
                const trimmed = String(rawValue || '').trim();
                if (trimmed === '') {
                    return null;
                }

                let supplier = findSupplierByLabel(trimmed) || findSupplierLoose(trimmed);
                if (supplier) {
                    return supplier;
                }

                await fetchSupplierSuggestions(trimmed);
                return findSupplierByLabel(trimmed) || findSupplierLoose(trimmed);
            }

            const onSupplierInput = debounce(async () => {
                await fetchSupplierSuggestions(supplierSearch.value);
                const supplier = findSupplierByLabel(supplierSearch.value);
                supplierIdField.value = supplier ? supplier.id : '';
            });

            rebuildSupplierIndexes();
            renderSupplierSuggestions('');

            supplierSearch.addEventListener('input', onSupplierInput);
            supplierSearch.addEventListener('focus', () => renderSupplierSuggestions(supplierSearch.value));
            supplierSearch.addEventListener('change', () => {
                const supplier = findSupplierByLabel(supplierSearch.value) || findSupplierLoose(supplierSearch.value);
                supplierIdField.value = supplier ? supplier.id : '';
                if (supplier) {
                    supplierSearch.value = supplierLabel(supplier);
                }
            });

            form.addEventListener('submit', async (event) => {
                if (isSubmitting) {
                    return;
                }

                event.preventDefault();
                let supplierId = String(supplierIdField.value || '').trim();
                if (supplierId === '') {
                    const supplier = await resolveSupplierFromInput(supplierSearch.value);
                    if (!supplier) {
                        alert(@json(__('txn.select_supplier')));
                        supplierSearch.focus();
                        return;
                    }
                    supplierId = String(supplier.id);
                    supplierIdField.value = supplierId;
                    supplierSearch.value = supplierLabel(supplier);
                }

                isSubmitting = true;
                form.submit();
            });
        })();
    </script>
@endsection
