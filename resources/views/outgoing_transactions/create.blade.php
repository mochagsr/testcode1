@extends('layouts.app')

@section('title', __('txn.create_outgoing_transaction_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('txn.create_outgoing_transaction_title') }}</h1>

    <form method="post" action="{{ route('outgoing-transactions.store') }}">
        @csrf

        <div class="card">
            <div class="row inline">
                <div class="col-6">
                    <div class="form-section">
                        <h3 class="form-section-title">{{ __('txn.outgoing_header') }}</h3>
                        <p class="form-section-note">{{ __('txn.outgoing_header_note') }}</p>
                        <div class="row">
                            <div class="col-12">
                                <label>{{ __('txn.supplier') }} <span class="label-required">*</span></label>
                                @php
                                    $suppliersById = $suppliers->keyBy('id');
                                    $oldSupplierId = old('supplier_id');
                                    $oldSupplierLabel = $oldSupplierId && $suppliersById->has($oldSupplierId)
                                        ? $suppliersById[$oldSupplierId]->name.' ('.($suppliersById[$oldSupplierId]->company_name ?: '-').')'
                                        : '';
                                @endphp
                                <input type="text" id="supplier-search" list="suppliers-list" value="{{ $oldSupplierLabel }}" placeholder="{{ __('txn.select_supplier') }}" required>
                                <input type="hidden" id="supplier-id" name="supplier_id" value="{{ $oldSupplierId }}" required>
                                <datalist id="suppliers-list">
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->name }} ({{ $supplier->company_name ?: '-' }})"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-6">
                                <label>{{ __('txn.date') }} <span class="label-required">*</span></label>
                                <input type="date" id="transaction-date" name="transaction_date" value="{{ old('transaction_date', now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-6">
                                <label>{{ __('txn.semester_period') }}</label>
                                <select id="semester-period" name="semester_period">
                                    @foreach($semesterOptions as $semester)
                                        <option value="{{ $semester }}" @selected(old('semester_period', $defaultSemesterPeriod) === $semester)>{{ $semester }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label>{{ __('txn.note_number') }}</label>
                                <input type="text" name="note_number" value="{{ old('note_number') }}">
                            </div>
                            <div class="col-12">
                                <label>{{ __('txn.notes') }}</label>
                                <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-section">
                        <h3 class="form-section-title">{{ __('txn.supplier_info') }}</h3>
                        <p class="form-section-note">{{ __('txn.supplier_info_note') }}</p>
                        <div class="row">
                            <div class="col-12">
                                <strong>{{ __('txn.supplier') }}</strong>
                                <div id="supplier-preview-name">-</div>
                            </div>
                            <div class="col-12">
                                <strong>{{ __('ui.supplier_company_name') }}</strong>
                                <div id="supplier-preview-company">-</div>
                            </div>
                            <div class="col-6">
                                <strong>{{ __('txn.phone') }}</strong>
                                <div id="supplier-preview-phone">-</div>
                            </div>
                            <div class="col-6">
                                <strong>{{ __('txn.address') }}</strong>
                                <div id="supplier-preview-address">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex" style="justify-content: space-between;">
                <h3 style="margin: 0;">{{ __('txn.outgoing_items') }}</h3>
                <button type="button" id="add-item" class="btn secondary">{{ __('txn.add_row') }}</button>
            </div>
            <table id="items-table" style="margin-top: 12px;">
                <thead>
                <tr>
                    <th style="width: 30%">{{ __('txn.product') }} *</th>
                    <th style="width: 20%">{{ __('txn.name') }}</th>
                    <th style="width: 9%">{{ __('txn.unit') }}</th>
                    <th style="width: 9%">{{ __('txn.qty') }} *</th>
                    <th style="width: 14%">{{ __('txn.price') }} *</th>
                    <th style="width: 14%">{{ __('txn.subtotal') }}</th>
                    <th style="width: 22%">{{ __('txn.notes') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
            <div style="margin-top: 10px; text-align: right;">
                <strong>{{ __('txn.total') }}: Rp <span id="grand-total">0</span></strong>
            </div>
        </div>

        <button class="btn" type="submit">{{ __('txn.save_outgoing_transaction') }}</button>
        <a class="btn secondary" href="{{ route('outgoing-transactions.index') }}">{{ __('txn.cancel') }}</a>
    </form>

    <script>
        (function () {
            let suppliers = @json($suppliers);
            let products = @json($products);
            const outgoingUnits = @json($outgoingUnitOptions);
            const SUPPLIER_LOOKUP_URL = @json(route('suppliers.lookup'));
            const PRODUCT_LOOKUP_URL = @json(route('api.products.index'));
            const LOOKUP_LIMIT = 20;
            const tableBody = document.querySelector('#items-table tbody');
            const addButton = document.getElementById('add-item');
            const grandTotal = document.getElementById('grand-total');
            const supplierSearch = document.getElementById('supplier-search');
            const suppliersList = document.getElementById('suppliers-list');
            const supplierIdField = document.getElementById('supplier-id');
            const transactionDateInput = document.getElementById('transaction-date');
            const semesterPeriodSelect = document.getElementById('semester-period');
            const form = document.querySelector('form');
            const productsList = document.getElementById('outgoing-products-list');

            if (!tableBody || !addButton || !grandTotal || !supplierSearch || !supplierIdField || !form) {
                return;
            }
            let supplierLookupAbort = null;
            let productLookupAbort = null;
            const SEARCH_DEBOUNCE_MS = 100;

            function debounce(fn, wait = SEARCH_DEBOUNCE_MS) {
                let timeoutId = null;
                return (...args) => {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => fn(...args), wait);
                };
            }

            function upsertSuppliers(rows) {
                const byId = new Map(suppliers.map((row) => [String(row.id), row]));
                (rows || []).forEach((row) => byId.set(String(row.id), row));
                suppliers = Array.from(byId.values());
            }

            function upsertProducts(rows) {
                const byId = new Map(products.map((row) => [String(row.id), row]));
                (rows || []).forEach((row) => byId.set(String(row.id), row));
                products = Array.from(byId.values());
            }

            function escapeAttribute(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            const numberFormat = (value) => new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(Number(value || 0))));

            function productLabel(product) {
                const code = String(product.code || '').trim();
                return code !== '' ? `${code} - ${product.name}` : product.name;
            }

            function supplierLabel(supplier) {
                return `${supplier.name} (${supplier.company_name || '-'})`;
            }

            function renderSupplierSuggestions(query) {
                if (!suppliersList) {
                    return;
                }
                const normalized = String(query || '').trim().toLowerCase();
                const matches = suppliers.filter((supplier) => {
                    const label = supplierLabel(supplier).toLowerCase();
                    const name = String(supplier.name || '').toLowerCase();
                    const company = String(supplier.company_name || '').toLowerCase();
                    return normalized === '' || label.includes(normalized) || name.includes(normalized) || company.includes(normalized);
                }).slice(0, 60);

                suppliersList.innerHTML = matches
                    .map((supplier) => `<option value="${escapeAttribute(supplierLabel(supplier))}"></option>`)
                    .join('');
            }

            async function fetchSupplierSuggestions(query) {
                if (!(window.PgposAutoSearch && window.PgposAutoSearch.canSearchInput({ value: query }))) {
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
                    upsertSuppliers(payload.data || []);
                    renderSupplierSuggestions(query);
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                }
            }

            function findSupplierByLabel(label) {
                if (!label) return null;
                const normalized = String(label).trim().toLowerCase();
                return suppliers.find((supplier) => supplierLabel(supplier).toLowerCase() === normalized)
                    || suppliers.find((supplier) => String(supplier.name || '').toLowerCase() === normalized)
                    || null;
            }

            function findSupplierLoose(label) {
                if (!label) return null;
                const normalized = String(label).trim().toLowerCase();
                return suppliers.find((supplier) => supplierLabel(supplier).toLowerCase().includes(normalized))
                    || suppliers.find((supplier) => String(supplier.name || '').toLowerCase().includes(normalized))
                    || null;
            }

            function updateSupplierPreview(supplier) {
                document.getElementById('supplier-preview-name').textContent = supplier?.name || '-';
                document.getElementById('supplier-preview-company').textContent = supplier?.company_name || '-';
                document.getElementById('supplier-preview-phone').textContent = supplier?.phone || '-';
                document.getElementById('supplier-preview-address').textContent = supplier?.address || '-';
            }

            function findProductByLabel(label) {
                if (!label) return null;
                const normalized = String(label).trim().toLowerCase();
                return products.find((product) => productLabel(product).toLowerCase() === normalized)
                    || products.find((product) => String(product.code || '').toLowerCase() === normalized)
                    || products.find((product) => String(product.name || '').toLowerCase() === normalized)
                    || null;
            }

            function findProductLoose(label) {
                if (!label) return null;
                const normalized = String(label).trim().toLowerCase();
                return products.find((product) => productLabel(product).toLowerCase().includes(normalized))
                    || products.find((product) => String(product.name || '').toLowerCase().includes(normalized))
                    || null;
            }

            function renderProductSuggestions(query) {
                if (!productsList) {
                    return;
                }
                const normalized = String(query || '').trim().toLowerCase();
                const matches = products.filter((product) => {
                    const label = productLabel(product).toLowerCase();
                    const code = String(product.code || '').toLowerCase();
                    const name = String(product.name || '').toLowerCase();
                    return normalized === '' || label.includes(normalized) || code.includes(normalized) || name.includes(normalized);
                }).slice(0, 60);

                productsList.innerHTML = matches
                    .map((product) => `<option value="${escapeAttribute(productLabel(product))}"></option>`)
                    .join('');
            }

            async function fetchProductSuggestions(query) {
                if (!(window.PgposAutoSearch && window.PgposAutoSearch.canSearchInput({ value: query }))) {
                    renderProductSuggestions(query);
                    return;
                }
                try {
                    if (productLookupAbort) {
                        productLookupAbort.abort();
                    }
                    productLookupAbort = new AbortController();
                    const url = `${PRODUCT_LOOKUP_URL}?search=${encodeURIComponent(query)}&active_only=1&per_page=${LOOKUP_LIMIT}`;
                    const response = await fetch(url, { signal: productLookupAbort.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) {
                        return;
                    }
                    const payload = await response.json();
                    upsertProducts(payload.data || []);
                    renderProductSuggestions(query);
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                }
            }

            function recalc() {
                let total = 0;
                tableBody.querySelectorAll('tr').forEach((row) => {
                    const qty = Math.max(0, Number(row.querySelector('.qty')?.value || 0));
                    const unitCost = Math.max(0, Number(row.querySelector('.unit-cost')?.value || 0));
                    const lineTotal = qty * unitCost;
                    total += lineTotal;
                    const lineNode = row.querySelector('.line-total');
                    if (lineNode) {
                        lineNode.textContent = numberFormat(lineTotal);
                    }
                });
                grandTotal.textContent = numberFormat(total);
            }

            function reindexRows() {
                Array.from(tableBody.querySelectorAll('tr')).forEach((row, index) => {
                    const mapping = [
                        ['.product-id', `items[${index}][product_id]`],
                        ['.product-name-manual', `items[${index}][product_name]`],
                        ['.unit', `items[${index}][unit]`],
                        ['.qty', `items[${index}][quantity]`],
                        ['.unit-cost', `items[${index}][unit_cost]`],
                        ['.item-notes', `items[${index}][notes]`],
                    ];
                    mapping.forEach(([selector, name]) => {
                        const field = row.querySelector(selector);
                        if (field) field.name = name;
                    });
                });
            }

            function buildUnitOptions(selectedUnit = '') {
                const selected = String(selectedUnit || '').trim().toLowerCase();
                const options = outgoingUnits.map((unit) => {
                    const code = String(unit.code || '').trim();
                    const label = String(unit.label || '').trim();
                    const text = label !== '' ? `${code} (${label})` : code;
                    const isSelected = selected !== '' && selected === code.toLowerCase() ? ' selected' : '';
                    return `<option value="${code}"${isSelected}>${text}</option>`;
                });
                if (selected !== '' && !outgoingUnits.some((unit) => String(unit.code || '').toLowerCase() === selected)) {
                    options.unshift(`<option value="${selected}" selected>${selected}</option>`);
                }
                return options.join('');
            }

            function deriveSemesterFromDate(dateValue) {
                if (!dateValue) {
                    return '';
                }

                const [yearText, monthText] = String(dateValue).split('-');
                const year = parseInt(yearText, 10);
                const month = parseInt(monthText, 10);
                if (!Number.isInteger(year) || !Number.isInteger(month)) {
                    return '';
                }

                if (month >= 5 && month <= 10) {
                    const nextYear = year + 1;
                    return `S1-${String(year).slice(-2)}${String(nextYear).slice(-2)}`;
                }

                if (month >= 11) {
                    const nextYear = year + 1;
                    return `S2-${String(year).slice(-2)}${String(nextYear).slice(-2)}`;
                }

                const startYear = year - 1;
                return `S2-${String(startYear).slice(-2)}${String(year).slice(-2)}`;
            }

            function autoSelectSemesterByDate() {
                if (!transactionDateInput || !semesterPeriodSelect) {
                    return;
                }
                const derived = deriveSemesterFromDate(transactionDateInput.value);
                if (derived === '') {
                    return;
                }

                const hasOption = Array.from(semesterPeriodSelect.options).some((option) => option.value === derived);
                if (!hasOption) {
                    const option = document.createElement('option');
                    option.value = derived;
                    option.textContent = derived;
                    semesterPeriodSelect.appendChild(option);
                }
                semesterPeriodSelect.value = derived;
            }

            function addRow(prefillProduct = null) {
                const index = tableBody.querySelectorAll('tr').length;
                const unitValue = prefillProduct?.unit || (outgoingUnits[0]?.code || 'exp');
                const qtyValue = 1;
                const unitCostValue = Number(prefillProduct?.price_general || 0);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="text" class="product-search" list="outgoing-products-list" placeholder="{{ __('txn.select_product') }}">
                        <input type="hidden" class="product-id" name="items[${index}][product_id]" value="">
                    </td>
                    <td>
                        <input type="text" class="product-name-manual" name="items[${index}][product_name]" placeholder="{{ __('txn.manual_item') }}" required>
                    </td>
                    <td>
                        <select class="unit w-xs" name="items[${index}][unit]">${buildUnitOptions(unitValue)}</select>
                    </td>
                    <td>
                        <input type="number" min="1" class="qty w-xs" name="items[${index}][quantity]" value="${qtyValue}" required>
                    </td>
                    <td>
                        <input type="number" min="0" step="1" class="unit-cost w-xs" name="items[${index}][unit_cost]" value="${unitCostValue}" required>
                    </td>
                    <td style="white-space: nowrap;">Rp <span class="line-total">0</span></td>
                    <td><input type="text" class="item-notes" name="items[${index}][notes]" placeholder="{{ __('txn.optional') }}"></td>
                    <td><button type="button" class="btn secondary remove">{{ __('txn.remove') }}</button></td>
                `;
                tableBody.appendChild(tr);

                const productSearch = tr.querySelector('.product-search');
                const productId = tr.querySelector('.product-id');
                const productName = tr.querySelector('.product-name-manual');
                const unitField = tr.querySelector('.unit');
                const unitCostField = tr.querySelector('.unit-cost');

                if (prefillProduct) {
                    productSearch.value = productLabel(prefillProduct);
                    productId.value = prefillProduct.id;
                    productName.value = prefillProduct.name;
                    unitField.value = prefillProduct.unit || unitValue;
                }

                const onProductInput = debounce(async () => {
                    await fetchProductSuggestions(productSearch.value);
                    const product = findProductByLabel(productSearch.value);
                    if (!product) {
                        productId.value = '';
                        return;
                    }
                    productId.value = product.id;
                    productName.value = product.name;
                    unitField.value = product.unit || unitField.value;
                    if (Number(unitCostField.value || 0) <= 0) {
                        unitCostField.value = Number(product.price_general || 0);
                    }
                    recalc();
                });
                productSearch.addEventListener('input', onProductInput);
                productSearch.addEventListener('focus', () => {
                    renderProductSuggestions(productSearch.value);
                });

                productSearch.addEventListener('change', () => {
                    const product = findProductByLabel(productSearch.value) || findProductLoose(productSearch.value);
                    if (!product) {
                        productId.value = '';
                        return;
                    }
                    productSearch.value = productLabel(product);
                    productId.value = product.id;
                    productName.value = product.name;
                    unitField.value = product.unit || unitField.value;
                    if (Number(unitCostField.value || 0) <= 0) {
                        unitCostField.value = Number(product.price_general || 0);
                    }
                    recalc();
                });

                tr.querySelectorAll('.qty,.unit-cost').forEach((field) => field.addEventListener('input', recalc));
                tr.querySelector('.remove').addEventListener('click', () => {
                    tr.remove();
                    if (!tableBody.querySelector('tr')) {
                        addRow();
                    }
                    reindexRows();
                    recalc();
                });

                reindexRows();
                recalc();
            }

            const onSupplierInput = debounce(async () => {
                await fetchSupplierSuggestions(supplierSearch.value);
                const supplier = findSupplierByLabel(supplierSearch.value);
                supplierIdField.value = supplier ? supplier.id : '';
                updateSupplierPreview(supplier);
            });
            supplierSearch.addEventListener('input', onSupplierInput);
            supplierSearch.addEventListener('focus', () => {
                renderSupplierSuggestions(supplierSearch.value);
            });

            supplierSearch.addEventListener('change', () => {
                const supplier = findSupplierByLabel(supplierSearch.value) || findSupplierLoose(supplierSearch.value);
                supplierIdField.value = supplier ? supplier.id : '';
                if (supplier) {
                    supplierSearch.value = supplierLabel(supplier);
                }
                updateSupplierPreview(supplier);
            });

            form.addEventListener('submit', (event) => {
                const hasSupplier = supplierIdField.value !== '';
                const rows = Array.from(tableBody.querySelectorAll('tr'));
                const hasRows = rows.length > 0;
                const invalidRows = rows.some((row) => {
                    const productName = String(row.querySelector('.product-name-manual')?.value || '').trim();
                    const qty = Number(row.querySelector('.qty')?.value || 0);
                    const cost = Number(row.querySelector('.unit-cost')?.value || 0);
                    return productName === '' || qty < 1 || cost < 0;
                });
                if (!hasSupplier || !hasRows || invalidRows) {
                    event.preventDefault();
                    alert('{{ __('txn.select_supplier') }} / {{ __('txn.outgoing_items_required') }}');
                }
            });

            const initialSupplier = supplierIdField.value
                ? suppliers.find((supplier) => String(supplier.id) === String(supplierIdField.value))
                : null;
            renderSupplierSuggestions('');
            renderProductSuggestions('');
            updateSupplierPreview(initialSupplier);
            addButton.addEventListener('click', () => addRow());
            addRow();
            autoSelectSemesterByDate();
            transactionDateInput?.addEventListener('change', autoSelectSemesterByDate);
        })();
    </script>

    <datalist id="outgoing-products-list">
        @foreach($products as $product)
            <option value="{{ $product->code ? $product->code.' - '.$product->name : $product->name }}"></option>
        @endforeach
    </datalist>
@endsection
