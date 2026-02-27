@extends('layouts.app')

@section('title', __('txn.create_outgoing_transaction_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('txn.create_outgoing_transaction_title') }}</h1>

    <form method="post" action="{{ route('outgoing-transactions.store') }}" enctype="multipart/form-data">
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
                                <input type="date" id="transaction-date" name="transaction_date" value="{{ old('transaction_date', now()->format('Y-m-d')) }}" required style="max-width: 150px;">
                            </div>
                            <div class="col-6">
                                <label>{{ __('txn.semester_period') }}</label>
                                <select id="semester-period" name="semester_period" style="max-width: 150px;">
                                    @foreach($semesterOptions as $semester)
                                        <option value="{{ $semester }}" @selected(old('semester_period', $defaultSemesterPeriod) === $semester)>{{ $semester }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label>{{ __('txn.note_number') }}</label>
                                <input type="text" name="note_number" value="{{ old('note_number') }}">
                            </div>
                            <div class="col-6">
                                <label>{{ __('supplier_payable.supplier_invoice_photo') }}</label>
                                <input type="file" name="supplier_invoice_photo" accept="image/*">
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
                    <th style="width: 22%">{{ __('txn.product') }} *</th>
                    <th style="width: 16%">{{ __('ui.category') }}</th>
                    <th style="width: 8%">{{ __('txn.unit') }}</th>
                    <th style="width: 8%">{{ __('txn.qty') }} *</th>
                    <th style="width: 10%">{{ __('txn.weight') }}</th>
                    <th style="width: 12%">{{ __('txn.price') }}</th>
                    <th style="width: 12%">{{ __('txn.subtotal') }}</th>
                    <th style="width: 18%">{{ __('txn.notes') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
            <div style="margin-top: 10px; text-align: right;">
                <strong>{{ __('txn.total') }}: Rp <span id="grand-total">0</span></strong>
                <br>
                <strong>{{ __('txn.total_weight') }}: <span id="total-weight">0</span></strong>
            </div>
        </div>

        <button class="btn" type="submit">{{ __('txn.save_outgoing_transaction') }}</button>
        <a class="btn secondary" href="{{ route('outgoing-transactions.index') }}">{{ __('txn.cancel') }}</a>
    </form>

    <div id="outgoing-category-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1200;"></div>
    <div id="outgoing-category-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:min(520px, calc(100vw - 24px)); background:var(--card); border:1px solid var(--border); border-radius:10px; padding:14px; z-index:1201;">
        <div class="flex" style="justify-content:space-between; margin-bottom:10px;">
            <strong>{{ __('txn.create_category_title') }}</strong>
            <button type="button" id="outgoing-category-modal-close" class="btn secondary" style="min-height:30px; padding:4px 10px;">&times;</button>
        </div>
        <div class="row">
            <div class="col-12">
                <label>{{ __('txn.category_name') }}</label>
                <input type="text" id="outgoing-category-name" maxlength="50" placeholder="{{ __('txn.category_name') }}">
            </div>
            <div class="col-12">
                <label>{{ __('ui.description') }}</label>
                <textarea id="outgoing-category-description" rows="2" placeholder="{{ __('ui.description') }}"></textarea>
            </div>
        </div>
        <div class="muted" id="outgoing-category-status" style="margin-top:6px;">{{ __('txn.category_modal_hint') }}</div>
    </div>

    <script>
        (function () {
            let suppliers = @json($suppliers);
            let products = @json($products);
            let itemCategories = @json($itemCategories);
            let supplierById = new Map((suppliers || []).map((supplier) => [String(supplier.id), supplier]));
            let supplierByLabel = new Map();
            let supplierByName = new Map();
            let productByLabel = new Map();
            let productByCode = new Map();
            let productByName = new Map();
            const outgoingUnits = @json($outgoingUnitOptions);
            const SUPPLIER_LOOKUP_URL = @json(route('suppliers.lookup'));
            const PRODUCT_LOOKUP_URL = @json(route('api.products.index'));
            const CATEGORY_API_URL = @json(route('api.item-categories.store'));
            const NEW_CATEGORY_VALUE = '__new__';
            const LOOKUP_LIMIT = 20;
            const tableBody = document.querySelector('#items-table tbody');
            const addButton = document.getElementById('add-item');
            const grandTotal = document.getElementById('grand-total');
            const totalWeightNode = document.getElementById('total-weight');
            const supplierSearch = document.getElementById('supplier-search');
            const suppliersList = document.getElementById('suppliers-list');
            const supplierIdField = document.getElementById('supplier-id');
            const transactionDateInput = document.getElementById('transaction-date');
            const semesterPeriodSelect = document.getElementById('semester-period');
            const form = document.querySelector('form');
            const productsList = document.getElementById('outgoing-products-list');
            const csrfToken = form?.querySelector('input[name="_token"]')?.value || '';
            const categoryModal = document.getElementById('outgoing-category-modal');
            const categoryModalOverlay = document.getElementById('outgoing-category-modal-overlay');
            const categoryModalClose = document.getElementById('outgoing-category-modal-close');
            const categoryNameInput = document.getElementById('outgoing-category-name');
            const categoryDescriptionInput = document.getElementById('outgoing-category-description');
            const categoryStatus = document.getElementById('outgoing-category-status');

            if (!tableBody || !addButton || !grandTotal || !supplierSearch || !supplierIdField || !form) {
                return;
            }
            let supplierLookupAbort = null;
            let productLookupAbort = null;
            let lastSupplierLookupQuery = '';
            let lastProductLookupQuery = '';
            const SEARCH_DEBOUNCE_MS = 100;
            const oldItems = @json(old('items', []));

            const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                ? (fn, wait = SEARCH_DEBOUNCE_MS) => window.PgposAutoSearch.debounce(fn, wait)
                : (fn, wait = SEARCH_DEBOUNCE_MS) => {
                    let timeoutId = null;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => fn(...args), wait);
                    };
                };

            function upsertSuppliers(rows) {
                const byId = new Map(suppliers.map((row) => [String(row.id), row]));
                (rows || []).forEach((row) => byId.set(String(row.id), row));
                suppliers = Array.from(byId.values());
                supplierById = new Map(suppliers.map((supplier) => [String(supplier.id), supplier]));
                rebuildSupplierIndexes();
            }

            function upsertProducts(rows) {
                const byId = new Map(products.map((row) => [String(row.id), row]));
                (rows || []).forEach((row) => byId.set(String(row.id), row));
                products = Array.from(byId.values());
                rebuildProductIndexes();
            }

            function getSupplierById(id) {
                return supplierById.get(String(id)) || null;
            }

            const escapeAttribute = (window.PgposAutoSearch && window.PgposAutoSearch.escapeAttribute)
                ? window.PgposAutoSearch.escapeAttribute
                : (value) => String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');

            const idNumberFormatter = new Intl.NumberFormat('id-ID');
            const idWeightFormatter = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
            const numberFormat = (value) => idNumberFormatter.format(Math.max(0, Math.round(Number(value || 0))));
            const weightFormat = (value) => idWeightFormatter.format(Math.max(0, Number(value || 0)));
            const normalizeLookup = (value) => String(value || '').trim().toLowerCase();

            function productLabel(product) {
                const code = String(product.code || '').trim();
                return code !== '' ? `${code} - ${product.name}` : product.name;
            }

            function supplierLabel(supplier) {
                return `${supplier.name} (${supplier.company_name || '-'})`;
            }

            function rebuildSupplierIndexes() {
                supplierByLabel = new Map();
                supplierByName = new Map();
                suppliers.forEach((supplier) => {
                    const byLabel = normalizeLookup(supplierLabel(supplier));
                    const byName = normalizeLookup(supplier.name);
                    if (byLabel !== '' && !supplierByLabel.has(byLabel)) {
                        supplierByLabel.set(byLabel, supplier);
                    }
                    if (byName !== '' && !supplierByName.has(byName)) {
                        supplierByName.set(byName, supplier);
                    }
                });
            }

            function rebuildProductIndexes() {
                productByLabel = new Map();
                productByCode = new Map();
                productByName = new Map();
                products.forEach((product) => {
                    const byLabel = normalizeLookup(productLabel(product));
                    const byCode = normalizeLookup(product.code);
                    const byName = normalizeLookup(product.name);
                    if (byLabel !== '' && !productByLabel.has(byLabel)) {
                        productByLabel.set(byLabel, product);
                    }
                    if (byCode !== '' && !productByCode.has(byCode)) {
                        productByCode.set(byCode, product);
                    }
                    if (byName !== '' && !productByName.has(byName)) {
                        productByName.set(byName, product);
                    }
                });
            }

            function categoryLabel(category) {
                const code = String(category.code || '').trim();
                const name = String(category.name || '').trim();
                if (code !== '' && name !== '' && code.toLowerCase() !== name.toLowerCase()) {
                    return `${code} - ${name}`;
                }
                return code !== '' ? code : name;
            }

            function buildCategoryOptions(selectedId = '') {
                const selected = String(selectedId || '').trim();
                const rows = (itemCategories || []).slice().sort((a, b) => {
                    const left = String(a.code || a.name || '').toLowerCase();
                    const right = String(b.code || b.name || '').toLowerCase();
                    return left.localeCompare(right);
                });
                const options = [
                    `<option value="">${escapeAttribute(@json(__('ui.select_category')))}</option>`,
                    ...rows.map((category) => {
                        const value = String(category.id);
                        const selectedAttr = selected === value ? ' selected' : '';
                        return `<option value="${escapeAttribute(value)}"${selectedAttr}>${escapeAttribute(categoryLabel(category))}</option>`;
                    }),
                ];
                const isNewSelected = selected === NEW_CATEGORY_VALUE ? ' selected' : '';
                options.push(`<option value="${NEW_CATEGORY_VALUE}"${isNewSelected}>+ ${escapeAttribute(@json(__('txn.new_category_option')))}</option>`);
                return options.join('');
            }

            function renderCategorySelect(select, selectedId = '') {
                if (!select) {
                    return;
                }
                select.innerHTML = buildCategoryOptions(selectedId);
                if (selectedId !== '' && selectedId !== NEW_CATEGORY_VALUE) {
                    select.value = String(selectedId);
                }
            }

            function upsertCategory(row) {
                if (!row || !row.id) {
                    return;
                }
                const map = new Map((itemCategories || []).map((category) => [String(category.id), category]));
                map.set(String(row.id), row);
                itemCategories = Array.from(map.values());
            }

            function refreshAllCategorySelects(overrideSelect = null, overrideValue = '') {
                tableBody.querySelectorAll('.item-category').forEach((select) => {
                    const currentValue = select === overrideSelect ? String(overrideValue) : String(select.value || '');
                    renderCategorySelect(select, currentValue);
                });
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

            function findSupplierByLabel(label) {
                if (!label) return null;
                const normalized = normalizeLookup(label);
                return supplierByLabel.get(normalized)
                    || supplierByName.get(normalized)
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
                const normalized = normalizeLookup(label);
                return productByLabel.get(normalized)
                    || productByCode.get(normalized)
                    || productByName.get(normalized)
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
                const normalizedQuery = normalizeLookup(query);
                if (!(window.PgposAutoSearch && window.PgposAutoSearch.canSearchInput({ value: query }))) {
                    lastProductLookupQuery = '';
                    renderProductSuggestions(query);
                    return;
                }
                if (normalizedQuery !== '' && normalizedQuery === lastProductLookupQuery) {
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
                    lastProductLookupQuery = normalizedQuery;
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
                let totalWeight = 0;
                tableBody.querySelectorAll('tr').forEach((row) => {
                    const qty = Math.max(0, Number(row.querySelector('.qty')?.value || 0));
                    const weight = Math.max(0, Number(row.querySelector('.weight')?.value || 0));
                    const unitCost = Math.max(0, Number(row.querySelector('.unit-cost')?.value || 0));
                    const lineTotal = qty * unitCost;
                    total += lineTotal;
                    totalWeight += weight;
                    const lineNode = row.querySelector('.line-total');
                    if (lineNode) {
                        lineNode.textContent = numberFormat(lineTotal);
                    }
                });
                grandTotal.textContent = numberFormat(total);
                if (totalWeightNode) {
                    totalWeightNode.textContent = weightFormat(totalWeight);
                }
            }

            let activeCategorySelect = null;
            let isCategorySaving = false;

            function openCategoryModal(targetSelect) {
                if (!categoryModal || !categoryModalOverlay || !categoryNameInput || !categoryDescriptionInput || !categoryStatus) {
                    return;
                }
                activeCategorySelect = targetSelect;
                categoryNameInput.value = '';
                categoryDescriptionInput.value = '';
                categoryStatus.textContent = @json(__('txn.category_modal_hint'));
                categoryModal.style.display = 'block';
                categoryModalOverlay.style.display = 'block';
                setTimeout(() => categoryNameInput.focus(), 50);
            }

            function hideCategoryModal() {
                if (!categoryModal || !categoryModalOverlay) {
                    return;
                }
                categoryModal.style.display = 'none';
                categoryModalOverlay.style.display = 'none';
            }

            async function saveCategoryFromModalIfNeeded() {
                if (!activeCategorySelect || !categoryNameInput || !categoryDescriptionInput || !categoryStatus) {
                    return true;
                }
                const categoryName = String(categoryNameInput.value || '').trim();
                const categoryDescription = String(categoryDescriptionInput.value || '').trim();
                if (categoryName === '') {
                    activeCategorySelect.value = '';
                    return true;
                }
                if (isCategorySaving) {
                    return false;
                }
                isCategorySaving = true;
                categoryStatus.textContent = @json(__('ui.saving'));
                try {
                    const response = await fetch(CATEGORY_API_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            code: categoryName,
                            description: categoryDescription !== '' ? categoryDescription : null,
                        }),
                    });

                    if (!response.ok) {
                        if (response.status === 422) {
                            const payload = await response.json().catch(() => ({}));
                            const errors = payload.errors || {};
                            const firstError = (errors.code && errors.code[0]) || (errors.description && errors.description[0]) || @json(__('txn.category_create_failed'));
                            categoryStatus.textContent = firstError;
                            return false;
                        }
                        categoryStatus.textContent = @json(__('txn.category_create_failed'));
                        return false;
                    }

                    const payload = await response.json();
                    upsertCategory(payload);
                    refreshAllCategorySelects(activeCategorySelect, String(payload.id));
                    categoryStatus.textContent = @json(__('txn.category_created_success_hint'));
                    return true;
                } catch (error) {
                    categoryStatus.textContent = @json(__('txn.category_create_failed'));
                    return false;
                } finally {
                    isCategorySaving = false;
                }
            }

            async function requestCloseCategoryModal() {
                if (!categoryModal || !categoryModalOverlay) {
                    return;
                }
                const saved = await saveCategoryFromModalIfNeeded();
                if (!saved) {
                    return;
                }
                hideCategoryModal();
                activeCategorySelect = null;
            }

            function reindexRows() {
                Array.from(tableBody.querySelectorAll('tr')).forEach((row, index) => {
                    const mapping = [
                        ['.product-id', `items[${index}][product_id]`],
                        ['.product-name', `items[${index}][product_name]`],
                        ['.item-category', `items[${index}][item_category_id]`],
                        ['.unit', `items[${index}][unit]`],
                        ['.qty', `items[${index}][quantity]`],
                        ['.weight', `items[${index}][weight]`],
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

            function autoSelectSemesterByDate() {
                if (!transactionDateInput || !semesterPeriodSelect) {
                    return;
                }
                const deriveSemesterFromDate = (window.PgposAutoSearch && window.PgposAutoSearch.deriveSemesterFromDate)
                    ? window.PgposAutoSearch.deriveSemesterFromDate
                    : () => '';
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

            function addRow(prefillProduct = null, rowPrefill = null) {
                const index = tableBody.querySelectorAll('tr').length;
                const unitValue = String(rowPrefill?.unit || prefillProduct?.unit || (outgoingUnits[0]?.code || 'exp'));
                const categoryValue = rowPrefill?.item_category_id
                    ? String(rowPrefill.item_category_id)
                    : (prefillProduct?.item_category_id ? String(prefillProduct.item_category_id) : '');
                const qtyCandidate = Number(rowPrefill?.quantity ?? 1);
                const qtyValue = Number.isFinite(qtyCandidate) && qtyCandidate > 0 ? Math.round(qtyCandidate) : 1;
                const weightRaw = rowPrefill?.weight;
                const weightValue = weightRaw === null || weightRaw === undefined || String(weightRaw).trim() === ''
                    ? ''
                    : String(weightRaw);
                const unitCostCandidate = Number(rowPrefill?.unit_cost ?? prefillProduct?.price_general ?? 0);
                const unitCostValue = Number.isFinite(unitCostCandidate) && unitCostCandidate >= 0
                    ? Math.round(unitCostCandidate)
                    : 0;
                const notesValue = String(rowPrefill?.notes ?? '');
                const prefillProductId = rowPrefill?.product_id ? String(rowPrefill.product_id) : '';
                const prefillProductName = String(rowPrefill?.product_name || prefillProduct?.name || '').trim();
                const productSearchValue = prefillProduct ? productLabel(prefillProduct) : prefillProductName;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="text" class="product-search" list="outgoing-products-list" value="${escapeAttribute(productSearchValue)}" placeholder="{{ __('txn.select_product') }}">
                        <input type="hidden" class="product-id" name="items[${index}][product_id]" value="${escapeAttribute(prefillProductId)}">
                        <input type="hidden" class="product-name" name="items[${index}][product_name]" value="${escapeAttribute(prefillProductName)}">
                    </td>
                    <td>
                        <select class="item-category w-sm" name="items[${index}][item_category_id]">
                            ${buildCategoryOptions(categoryValue)}
                        </select>
                    </td>
                    <td>
                        <select class="unit w-xs" name="items[${index}][unit]">${buildUnitOptions(unitValue)}</select>
                    </td>
                    <td>
                        <input type="number" min="1" class="qty w-xs" name="items[${index}][quantity]" value="${qtyValue}" required>
                    </td>
                    <td>
                        <input type="number" min="0" step="0.001" class="weight w-xs" name="items[${index}][weight]" value="${escapeAttribute(weightValue)}">
                    </td>
                    <td>
                        <input type="number" min="0" step="1" class="unit-cost w-xs" name="items[${index}][unit_cost]" value="${unitCostValue}">
                    </td>
                    <td style="white-space: nowrap;">Rp <span class="line-total">0</span></td>
                    <td><input type="text" class="item-notes" name="items[${index}][notes]" value="${escapeAttribute(notesValue)}" placeholder="{{ __('txn.optional') }}"></td>
                    <td><button type="button" class="btn secondary remove">{{ __('txn.remove') }}</button></td>
                `;
                tableBody.appendChild(tr);

                const productSearch = tr.querySelector('.product-search');
                const productId = tr.querySelector('.product-id');
                const productName = tr.querySelector('.product-name');
                const categoryField = tr.querySelector('.item-category');
                const unitField = tr.querySelector('.unit');
                const unitCostField = tr.querySelector('.unit-cost');

                if (prefillProduct && !rowPrefill?.product_name) {
                    if (prefillProduct.item_category_id) {
                        categoryField.value = String(prefillProduct.item_category_id);
                    }
                    unitField.value = prefillProduct.unit || unitValue;
                }

                const onProductInput = debounce(async () => {
                    await fetchProductSuggestions(productSearch.value);
                    const typedName = String(productSearch.value || '').trim();
                    const product = findProductByLabel(productSearch.value);
                    if (!product) {
                        productId.value = '';
                        productName.value = typedName;
                        return;
                    }
                    productId.value = product.id;
                    productName.value = product.name;
                    if (product.item_category_id) {
                        categoryField.value = String(product.item_category_id);
                    }
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
                    const typedName = String(productSearch.value || '').trim();
                    const product = findProductByLabel(productSearch.value) || findProductLoose(productSearch.value);
                    if (!product) {
                        productId.value = '';
                        productName.value = typedName;
                        return;
                    }
                    productSearch.value = productLabel(product);
                    productId.value = product.id;
                    productName.value = product.name;
                    if (product.item_category_id) {
                        categoryField.value = String(product.item_category_id);
                    }
                    unitField.value = product.unit || unitField.value;
                    if (Number(unitCostField.value || 0) <= 0) {
                        unitCostField.value = Number(product.price_general || 0);
                    }
                    recalc();
                });

                tr.querySelectorAll('.qty,.unit-cost').forEach((field) => field.addEventListener('input', recalc));
                categoryField?.addEventListener('change', () => {
                    if (categoryField.value === NEW_CATEGORY_VALUE) {
                        openCategoryModal(categoryField);
                    }
                });
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

            categoryModalClose?.addEventListener('click', () => {
                requestCloseCategoryModal();
            });
            categoryModalOverlay?.addEventListener('click', () => {
                requestCloseCategoryModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && categoryModal && categoryModal.style.display !== 'none') {
                    requestCloseCategoryModal();
                }
            });

            form.addEventListener('submit', (event) => {
                const hasSupplier = supplierIdField.value !== '';
                const rows = Array.from(tableBody.querySelectorAll('tr'));
                const hasRows = rows.length > 0;
                const invalidRows = rows.some((row) => {
                    const productId = String(row.querySelector('.product-id')?.value || '').trim();
                    const productName = String(row.querySelector('.product-name')?.value || '').trim();
                    const categoryId = String(row.querySelector('.item-category')?.value || '').trim();
                    const qty = Number(row.querySelector('.qty')?.value || 0);
                    const cost = Number(row.querySelector('.unit-cost')?.value || 0);
                    return productName === ''
                        || categoryId === NEW_CATEGORY_VALUE
                        || (productId === '' && categoryId === '')
                        || qty < 1
                        || cost < 0;
                });
                if (!hasSupplier || !hasRows || invalidRows) {
                    event.preventDefault();
                    alert('{{ __('txn.select_supplier') }} / {{ __('txn.outgoing_items_required') }}');
                }
            });

            const initialSupplier = supplierIdField.value
                ? getSupplierById(supplierIdField.value)
                : null;
            rebuildSupplierIndexes();
            rebuildProductIndexes();
            renderSupplierSuggestions('');
            renderProductSuggestions('');
            updateSupplierPreview(initialSupplier);
            addButton.addEventListener('click', () => addRow());
            if (Array.isArray(oldItems) && oldItems.length > 0) {
                oldItems.forEach((item) => {
                    const productId = Number(item?.product_id || 0);
                    const prefillProduct = productId > 0
                        ? products.find((product) => Number(product.id) === productId) || null
                        : null;
                    addRow(prefillProduct, item || null);
                });
            } else {
                addRow();
            }
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
