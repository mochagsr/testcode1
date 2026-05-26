@extends('layouts.app')

@section('title', __('txn.create_sales_invoice_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <h1 class="page-title">{{ __('txn.create_sales_invoice_title') }}</h1>

    <style>
        .quantity-with-unit {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 130px;
        }
        .quantity-with-unit .qty {
            flex: 0 0 88px;
            max-width: 88px;
        }
        .qty-unit-label {
            color: #526173;
            font-weight: 700;
            white-space: nowrap;
        }
        .product-ac-dropdown {
            position: fixed; z-index: 9999; background: var(--card,#fff);
            border: 1px solid var(--border,#d0d7de); border-radius: 6px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.16); min-width: 300px;
            max-height: 300px; overflow-y: scroll; font-size: 13px;
        }
        .product-ac-dropdown::-webkit-scrollbar { width: 6px; }
        .product-ac-dropdown::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 0 6px 6px 0; }
        .product-ac-dropdown::-webkit-scrollbar-thumb { background: #c0c0c0; border-radius: 3px; }
        .product-ac-dropdown::-webkit-scrollbar-thumb:hover { background: #999; }
        .product-ac-item { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 2px 12px; padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border,#d0d7de); line-height: 1.35; }
        .product-ac-item:last-child { border-bottom: none; }
        .product-ac-item.is-active, .product-ac-item:hover { background: var(--hover-bg,rgba(59,130,246,0.08)); }
        .product-ac-item.out-of-stock { opacity: 0.4; }
        .product-ac-name { font-weight: 700; }
        .product-ac-code { font-size: 11px; color: var(--muted,#6b7280); }
        .product-ac-meta { font-size: 11px; color: var(--muted,#6b7280); text-align: right; white-space: nowrap; align-self: center; }
        .product-ac-empty { padding: 10px 12px; color: var(--muted,#6b7280); font-style: italic; }
    </style>

    <form method="post" action="{{ route('sales-invoices.store') }}">
        @csrf

        <div class="card">
            <div class="row inline">
                <div class="col-6">
                    <div class="form-section">
                        <h3 class="form-section-title">{{ __('txn.invoice_header') }}</h3>
                        <p class="form-section-note">{{ __('txn.invoice_header_note') }}</p>
                        <div class="row">
                            <div class="col-12">
                                <label class="label-with-feedback">
                                    <span>{{ __('txn.customer') }} <span class="label-required">*</span></span>
                                    <span id="customer-search-error" class="field-inline-error" aria-live="polite"></span>
                                </label>
                                @php
                                    $customerMap = $customers->keyBy('id');
                                    $oldCustomerId = old('customer_id');
                                    $oldOrderNoteId = (int) old('order_note_id', 0);
                                    $oldCustomerLabel = $oldCustomerId && $customerMap->has($oldCustomerId)
                                        ? $customerMap[$oldCustomerId]->name.' ('.($customerMap[$oldCustomerId]->city ?: '-').')'
                                        : '';
                                @endphp
                                <input type="text"
                                       id="customer-search"
                                       list="customers-list"
                                       value="{{ $oldCustomerLabel }}"
                                       placeholder="{{ __('txn.select_customer') }}"
                                       required>
                                <input type="hidden" id="customer-id" name="customer_id" value="{{ $oldCustomerId }}" required>
                                <datalist id="customers-list">
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->name }} ({{ $customer->city ?: '-' }})"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-12">
                                <label>{{ __('txn.order_notes_title') }}</label>
                                <select id="order-note-id" name="order_note_id" @disabled($oldCustomerId <= 0)>
                                    <option value="">{{ __('txn.select_order_note') }}</option>
                                    @foreach(($orderNotes ?? []) as $note)
                                        @php
                                            $orderedTotal = (int) round((float) ($note['ordered_total'] ?? 0));
                                            $fulfilledTotal = (int) round((float) ($note['fulfilled_total'] ?? 0));
                                            $remainingTotal = max(0, (int) round((float) ($note['remaining_total'] ?? 0)));
                                            $progressPercent = (float) ($note['progress_percent'] ?? ($orderedTotal > 0 ? ($fulfilledTotal / $orderedTotal) * 100 : 0));
                                            $progressLabel = rtrim(rtrim(number_format($progressPercent, 2, '.', ''), '0'), '.');
                                        @endphp
                                        <option value="{{ (int) ($note['id'] ?? 0) }}" @selected($oldOrderNoteId === (int) ($note['id'] ?? 0))>
                                            {{ (string) ($note['note_number'] ?? '-') }} | {{ (string) ($note['note_date'] ?? '-') }} | {{ $progressLabel }}% | {{ number_format($remainingTotal, 0, ',', '.') }}
                                        </option>
                                    @endforeach
                                </select>
                                <div id="order-note-info" class="muted" style="margin-top: 4px;">
                                    @if($oldCustomerId <= 0)
                                        {{ __('txn.order_note_pick_customer_first') }}
                                    @elseif(($orderNotes ?? collect())->isEmpty())
                                        {{ __('txn.no_order_note_available') }}
                                    @else
                                        {{ __('txn.order_note_auto_fill_hint') }}
                                    @endif
                                </div>
                            </div>
                            <div class="col-6">
                                <label>{{ __('txn.invoice_date') }} <span class="label-required">*</span></label>
                                <input type="date" id="invoice-date" name="invoice_date" value="{{ old('invoice_date', now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-6">
                                <label>{{ __('txn.due_date') }}</label>
                                <input type="date" name="due_date" value="{{ old('due_date') }}">
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
                                <label>{{ __('txn.transaction_type') }}</label>
                                <select id="invoice-transaction-type" name="transaction_type">
                                    <option value="product" @selected(old('transaction_type', 'product') === 'product')>{{ __('txn.transaction_type_product') }}</option>
                                    <option value="printing" @selected(old('transaction_type') === 'printing')>{{ __('txn.transaction_type_printing') }}</option>
                                </select>
                            </div>
                            @include('partials.printing_subtype_fields', [
                                'customerFieldId' => 'customer-id',
                                'transactionTypeFieldId' => 'invoice-transaction-type',
                                'subtypeFieldId' => 'invoice-printing-subtype-id',
                                'selectedSubtypeId' => old('customer_printing_subtype_id'),
                                'selectedSubtypeName' => old('printing_subtype_name'),
                                'colClass' => 'col-6',
                            ])
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-section">
                        <h3 class="form-section-title">{{ __('txn.payment_setup') }}</h3>
                        <p class="form-section-note">{{ __('txn.payment_setup_note') }}</p>
                        <div class="row">
                            <div class="col-12">
                                <label>{{ __('txn.payment_method') }}</label>
                                <select name="payment_method" required>
                                    <option value="tunai" @selected(old('payment_method') === 'tunai')>{{ __('txn.cash') }}</option>
                                    <option value="kredit" @selected(old('payment_method', 'kredit') === 'kredit')>{{ __('txn.credit') }}</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label>{{ __('txn.notes') }}</label>
                                <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex" style="justify-content: space-between;">
                <h3 style="margin: 0;">{{ __('txn.invoice_items') }}</h3>
                <button type="button" id="add-item" class="btn process-soft-btn">{{ __('txn.add_row') }}</button>
            </div>
            <div class="table-mobile-scroll" style="margin-top: 12px;">
                <table id="items-table">
                    <thead>
                    <tr>
                        <th style="width: 39%">{{ __('txn.product') }} *</th>
                        <th style="width: 9%">{{ __('txn.current_stock') }}</th>
                        <th style="width: 7%">{{ __('txn.qty') }} *</th>
                        <th style="width: 11%">{{ __('txn.price') }} *</th>
                        <th style="width: 10%">{{ __('txn.discount') }} (%)</th>
                        <th style="width: 22%">{{ __('txn.subtotal') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div style="margin-top: 10px; text-align: right;">
                <strong>{{ __('txn.total') }}: Rp <span id="grand-total">0</span></strong>
            </div>
        </div>

        <div class="form-submit-actions">
            <button class="btn" type="submit">{{ __('txn.save_invoice') }}</button>
            <a class="btn secondary" href="{{ route('sales-invoices.index') }}">{{ __('txn.cancel') }}</a>
        </div>
    </form>

    <script>
        let products = @json($products);
        let customers = @json($customers);
        let orderNotes = @json($orderNotes ?? []);
        const bootOrderNoteId = @json((string) old('order_note_id', ''));
        const bootItems = @json(old('items', []));
        let productById = new Map((products || []).map((product) => [String(product.id), product]));
        let customerById = new Map((customers || []).map((customer) => [String(customer.id), customer]));
        let orderNoteById = new Map((orderNotes || []).map((note) => [String(note.id), note]));
        let customerByLabel = new Map();
        let customerByName = new Map();
        let productByLabel = new Map();
        let productByCode = new Map();
        let productByName = new Map();
        const CUSTOMER_LOOKUP_URL = @json(route('api.customers.index'));
        const PRODUCT_LOOKUP_URL = @json(route('api.products.index'));
        const ORDER_NOTE_LOOKUP_URL = @json(route('api.order-notes.lookup'));
        const LOOKUP_LIMIT = 20;
        const ORDER_NOTE_LOOKUP_LIMIT = 200;
        const selectProductLabel = @json(__('txn.select_product'));
        const selectOrderNoteLabel = @json(__('txn.select_order_note'));
        const tbody = document.querySelector('#items-table tbody');
        const customersList = document.getElementById('customers-list');
        const customerSearch = document.getElementById('customer-search');
        const customerIdField = document.getElementById('customer-id');
        const customerSearchError = document.getElementById('customer-search-error');
        const orderNoteField = document.getElementById('order-note-id');
        const orderNoteInfo = document.getElementById('order-note-info');
        const grandTotal = document.getElementById('grand-total');
        const addBtn = document.getElementById('add-item');
        const invoiceDateInput = document.getElementById('invoice-date');
        const semesterPeriodSelect = document.getElementById('semester-period');
        const form = document.querySelector('form');
        let isSubmitting = false;
        const SEARCH_DEBOUNCE_MS = 100;
        let currentCustomer = null;
        let orderNoteLookupAbort = null;
        let customerLookupAbort = null;
        let productLookupAbort = null;
        let lastCustomerLookupQuery = '';
        let lastProductLookupQuery = '';
        let orderNotesCustomerId = '';

        function normalizeLookup(value) {
            return String(value || '').trim().toLowerCase();
        }

        function upsertCustomers(rows) {
            const byId = new Map(customers.map((row) => [String(row.id), row]));
            (rows || []).forEach((row) => {
                byId.set(String(row.id), row);
            });
            customers = Array.from(byId.values());
            customerById = new Map(customers.map((customer) => [String(customer.id), customer]));
            rebuildCustomerIndexes();
        }

        function upsertProducts(rows) {
            const byId = new Map(products.map((row) => [String(row.id), row]));
            (rows || []).forEach((row) => {
                byId.set(String(row.id), row);
            });
            products = Array.from(byId.values());
            productById = new Map(products.map((product) => [String(product.id), product]));
            rebuildProductIndexes();
        }

        function setOrderNotes(rows) {
            orderNotes = Array.isArray(rows) ? rows : [];
            orderNoteById = new Map(orderNotes.map((note) => [String(note.id), note]));
        }

        function getProductById(id) {
            return productById.get(String(id)) || null;
        }

        function rebuildCustomerIndexes() {
            customerByLabel = new Map();
            customerByName = new Map();
            customers.forEach((customer) => {
                customerByLabel.set(normalizeLookup(customerLabel(customer)), customer);
                customerByName.set(normalizeLookup(customer.name), customer);
            });
        }

        function rebuildProductIndexes() {
            productByLabel = new Map();
            productByCode = new Map();
            productByName = new Map();
            products.forEach((product) => {
                productByLabel.set(normalizeLookup(productLabel(product)), product);
                productByCode.set(normalizeLookup(product.code), product);
                productByName.set(normalizeLookup(product.name), product);
            });
        }

        const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
            ? (fn, wait = SEARCH_DEBOUNCE_MS) => window.PgposAutoSearch.debounce(fn, wait)
            : (fn, wait = SEARCH_DEBOUNCE_MS) => {
                let timeoutId = null;
                return (...args) => {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => fn(...args), wait);
                };
            };

        function normalizeLevelLabel(value) {
            return (value || '').toString().trim().toLowerCase();
        }

        function getPriceKeyForCustomer() {
            if (!currentCustomer) {
                return 'price_general';
            }
            const code = normalizeLevelLabel(currentCustomer.level?.code);
            const name = normalizeLevelLabel(currentCustomer.level?.name);
            const combined = `${code} ${name}`.trim();

            if (combined.includes('agent') || combined.includes('agen')) {
                return 'price_agent';
            }
            if (combined.includes('sales') || combined.includes('sale') || combined.includes('penjualan')) {
                return 'price_sales';
            }
            return 'price_general';
        }

        function customerLabel(customer) {
            const city = customer.city || '-';
            return `${customer.name} (${city})`;
        }

        function renderCustomerSuggestions(query) {
            if (!customersList) {
                return;
            }
            const normalized = (query || '').trim().toLowerCase();
            const matches = customers.filter((customer) => {
                const label = customerLabel(customer).toLowerCase();
                const name = (customer.name || '').toLowerCase();
                const city = (customer.city || '').toLowerCase();
                return normalized === '' || label.includes(normalized) || name.includes(normalized) || city.includes(normalized);
            }).slice(0, 60);

            customersList.innerHTML = matches
                .map((customer) => `<option value="${escapeAttribute(customerLabel(customer))}"></option>`)
                .join('');
        }

        async function fetchCustomerSuggestions(query, options = {}) {
            const force = options.force === true;
            const perPage = Number(options.perPage || LOOKUP_LIMIT);
            const normalizedQuery = normalizeLookup(query);
            if (!force && !(window.PgposAutoSearch && window.PgposAutoSearch.canSearchInput({ value: query }))) {
                lastCustomerLookupQuery = '';
                renderCustomerSuggestions(query);
                return;
            }
            if (normalizedQuery !== '' && normalizedQuery === lastCustomerLookupQuery) {
                renderCustomerSuggestions(query);
                return;
            }
            try {
                if (customerLookupAbort) {
                    customerLookupAbort.abort();
                }
                customerLookupAbort = new AbortController();
                const url = `${CUSTOMER_LOOKUP_URL}?search=${encodeURIComponent(query)}&per_page=${Math.max(1, perPage)}`;
                const response = await fetch(url, { signal: customerLookupAbort.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) {
                    return;
                }
                const payload = await response.json();
                lastCustomerLookupQuery = normalizedQuery;
                upsertCustomers(payload.data || []);
                renderCustomerSuggestions(query);
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
            }
        }

        function findCustomerByLabel(label) {
            if (!label) {
                return null;
            }
            const normalized = normalizeLookup(label);
            return customerByLabel.get(normalized)
                || customerByName.get(normalized)
                || null;
        }

        function findCustomerLoose(label) {
            if (!label) {
                return null;
            }
            const normalized = label.trim().toLowerCase();
            return customers.find((customer) => customerLabel(customer).toLowerCase() === normalized)
                || customers.find((customer) => customer.name.toLowerCase() === normalized)
                || null;
        }

        function customerSearchVariants(rawValue) {
            const input = String(rawValue || '').trim();
            if (input === '') {
                return [];
            }

            const variants = [input];
            const match = input.match(/^(.+?)\s*\((.+)\)\s*$/);
            if (match) {
                const namePart = String(match[1] || '').trim();
                const cityPart = String(match[2] || '').trim();
                if (namePart !== '') {
                    variants.push(namePart);
                }
                if (cityPart !== '') {
                    variants.push(cityPart);
                }
                if (namePart !== '' && cityPart !== '') {
                    variants.push(`${namePart} ${cityPart}`);
                }
            }

            return Array.from(new Set(variants));
        }

        function setCustomerFieldError(message = '') {
            const hasMessage = String(message || '').trim() !== '';
            if (customerSearchError) {
                customerSearchError.textContent = hasMessage ? message : '';
            }
            customerSearch?.classList.toggle('input-inline-error', hasMessage);
        }

        function setProductFieldError(row, message = '') {
            const hasMessage = String(message || '').trim() !== '';
            const input = row?.querySelector('.product-search');
            const error = row?.querySelector('.product-search-error');
            if (error) {
                error.textContent = hasMessage ? message : '';
            }
            input?.classList.toggle('input-inline-error', hasMessage);
        }

        async function resolveCustomerFromInput(rawValue) {
            const variants = customerSearchVariants(rawValue);
            if (variants.length === 0) {
                return null;
            }

            for (const variant of variants) {
                const customer = findCustomerByLabel(variant) || findCustomerLoose(variant);
                if (customer) {
                    return customer;
                }
            }

            for (const variant of variants) {
                await fetchCustomerSuggestions(variant, { force: true, perPage: 50 });
                const customer = findCustomerByLabel(variant) || findCustomerLoose(variant);
                if (customer) {
                    return customer;
                }
            }

            return null;
        }

        function setCurrentCustomer(customer, resetOrderNote = true) {
            currentCustomer = customer;
            if (customer) {
                customerIdField.value = customer.id;
                setCustomerFieldError('');
            } else {
                customerIdField.value = '';
            }
            if (resetOrderNote && orderNoteField) {
                orderNoteField.value = '';
            }
        }

        function orderNoteLabel(note) {
            const progress = Number(note.progress_percent || 0).toFixed(2).replace(/\.00$/, '');
            const remaining = Number(note.remaining_total || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
            const dateLabel = note.note_date || '-';
            return `${note.note_number} | ${dateLabel} | ${progress}% | ${remaining}`;
        }

        function renderOrderNoteOptions(selectedId = '') {
            if (!orderNoteField) {
                return;
            }

            const currentValue = selectedId || orderNoteField.value || '';
            const options = [`<option value="">${selectOrderNoteLabel}</option>`];
            orderNotes
                .filter((note) => Number(note.remaining_total || 0) > 0)
                .sort((a, b) => String(b.note_number || '').localeCompare(String(a.note_number || '')))
                .forEach((note) => {
                    const selected = String(note.id) === String(currentValue) ? ' selected' : '';
                    options.push(`<option value="${note.id}"${selected}>${escapeAttribute(orderNoteLabel(note))}</option>`);
                });
            orderNoteField.innerHTML = options.join('');
            orderNoteField.disabled = !currentCustomer || options.length <= 1;
            if (!currentCustomer) {
                orderNoteInfo.textContent = @json(__('txn.order_note_pick_customer_first'));
            } else if (options.length <= 1) {
                orderNoteInfo.textContent = @json(__('txn.no_order_note_available'));
            } else {
                orderNoteInfo.textContent = @json(__('txn.order_note_auto_fill_hint'));
            }
        }

        function updateOrderNoteInfoSelection(note) {
            if (!orderNoteInfo) {
                return;
            }
            if (!note) {
                return;
            }
            const progress = Number(note.progress_percent || 0).toFixed(2).replace(/\.00$/, '');
            const orderedTotal = Number(note.ordered_total || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
            const fulfilledTotal = Number(note.fulfilled_total || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
            const remainingTotal = Number(note.remaining_total || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
            orderNoteInfo.textContent = `${note.note_number} | ${progress}% | ${fulfilledTotal}/${orderedTotal} | ${remainingTotal}`;
        }

        function clearItemsAndReindex() {
            tbody.innerHTML = '';
            recalc();
        }

        function applyOrderNoteItems(note) {
            if (!note || !Array.isArray(note.items)) {
                return;
            }
            clearItemsAndReindex();
            const openItems = note.items.filter((item) => Number(item.remaining_qty || 0) > 0);
            if (openItems.length === 0) {
                addRow();
                return;
            }
            openItems.forEach((item) => {
                addRow({
                    product_id: item.product_id,
                    product_code: item.product_code || '',
                    product_name: item.product_name || '',
                    unit: item.unit || '',
                    quantity: Number(item.remaining_qty || item.ordered_qty || 0),
                    order_note_item_id: item.id || '',
                    stock: Number(item.stock || 0),
                    price_agent: Number(item.price_agent || 0),
                    price_sales: Number(item.price_sales || 0),
                    price_general: Number(item.price_general || 0),
                });
            });
            recalc();
        }

        async function fetchOrderNotesForCustomer(customerId, selectedId = '') {
            if (!orderNoteField) {
                return;
            }
            if (!customerId) {
                orderNotesCustomerId = '';
                setOrderNotes([]);
                renderOrderNoteOptions('');
                return;
            }
            if (String(orderNotesCustomerId) === String(customerId) && selectedId === '') {
                renderOrderNoteOptions(orderNoteField.value || '');
                return;
            }
            setOrderNotes([]);
            renderOrderNoteOptions('');
            try {
                if (orderNoteLookupAbort) {
                    orderNoteLookupAbort.abort();
                }
                orderNoteLookupAbort = new AbortController();
                const url = `${ORDER_NOTE_LOOKUP_URL}?customer_id=${encodeURIComponent(customerId)}&per_page=${ORDER_NOTE_LOOKUP_LIMIT}`;
                const response = await fetch(url, {
                    signal: orderNoteLookupAbort.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) {
                    renderOrderNoteOptions('');
                    return;
                }
                const payload = await response.json();
                const rows = Array.isArray(payload.data) ? payload.data : [];
                orderNotesCustomerId = String(customerId);
                setOrderNotes(rows);

                const noteProducts = rows
                    .flatMap((note) => Array.isArray(note.items) ? note.items : [])
                    .filter((item) => Number(item.product_id || 0) > 0)
                    .map((item) => ({
                        id: Number(item.product_id || 0),
                        code: String(item.product_code || ''),
                        name: String(item.product_name || ''),
                        unit: String(item.unit || ''),
                        stock: Number(item.stock || 0),
                        price_agent: Number(item.price_agent || 0),
                        price_sales: Number(item.price_sales || 0),
                        price_general: Number(item.price_general || 0),
                    }));
                if (noteProducts.length > 0) {
                    upsertProducts(noteProducts);
                }
                renderOrderNoteOptions(selectedId || '');
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                orderNotesCustomerId = '';
                setOrderNotes([]);
                renderOrderNoteOptions('');
            }
        }

        async function handleResolvedCustomer(customer, shouldResetOrderNote = true) {
            const previousCustomerId = currentCustomer ? String(currentCustomer.id) : '';
            const nextCustomerId = customer ? String(customer.id) : '';
            const mustResetOrderNote = shouldResetOrderNote && previousCustomerId !== nextCustomerId;
            setCurrentCustomer(customer, mustResetOrderNote);
            applyCustomerPricing();
            const shouldFetchOrderNotes = nextCustomerId !== ''
                && (
                    previousCustomerId !== nextCustomerId
                    || String(orderNotesCustomerId || '') !== nextCustomerId
                    || !Array.isArray(orderNotes)
                    || orderNotes.length === 0
                );
            if (shouldFetchOrderNotes) {
                await fetchOrderNotesForCustomer(nextCustomerId, orderNoteField?.value || '');
            }
        }

        function productLabel(product) {
            const code = (product.code || '').trim();
            if (code !== '') {
                return `${code} - ${product.name}`;
            }
            return `${product.name}`;
        }

        function productUnitLabel(product) {
            const unit = String(product?.unit || '').trim();
            return unit !== '' ? unit : '-';
        }

        const escapeAttribute = (window.PgposAutoSearch && window.PgposAutoSearch.escapeAttribute)
            ? window.PgposAutoSearch.escapeAttribute
            : (value) => String(value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

        async function fetchProductSuggestions(query) {
            const normalizedQuery = normalizeLookup(query);
            if (!(window.PgposAutoSearch && window.PgposAutoSearch.canSearchInput({ value: query }))) {
                return;
            }
            if (normalizedQuery !== '' && normalizedQuery === lastProductLookupQuery) {
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
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
            }
        }

        function createProductAutocomplete(inputEl, hiddenEl, onSelect) {
            let dropdown = null, activeIdx = -1, currentMatches = [], blurTimer = null;
            const esc = (s) => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            function getMatches(query) {
                const q = (query||'').trim().toLowerCase();
                if (q.length < 2) return [];
                return products.filter(p => {
                    const label = productLabel(p).toLowerCase(), code = (p.code||'').toLowerCase(), name = (p.name||'').toLowerCase();
                    return label.includes(q) || code.includes(q) || name.includes(q);
                }).slice(0, 10);
            }
            function position() {
                if (!dropdown) return;
                const r = inputEl.getBoundingClientRect(), dropH = Math.min(280, currentMatches.length * 52 + 12), below = window.innerHeight - r.bottom;
                dropdown.style.left = r.left + 'px'; dropdown.style.width = Math.max(r.width, 300) + 'px';
                dropdown.style.top = (below >= dropH || below >= r.top) ? (r.bottom + 2) + 'px' : (r.top - dropH - 2) + 'px';
            }
            function close() { dropdown?.remove(); dropdown = null; currentMatches = []; activeIdx = -1; }
            function setActive(idx) { activeIdx = idx; dropdown?.querySelectorAll('.product-ac-item').forEach((el, i) => el.classList.toggle('is-active', i === idx)); }
            function pick(idx) {
                const p = currentMatches[idx]; if (!p) return;
                inputEl.value = productLabel(p); hiddenEl.value = p.id; close(); onSelect(p);
            }
            function open(matches) {
                close(); currentMatches = matches;
                dropdown = document.createElement('div');
                dropdown.className = 'product-ac-dropdown';
                dropdown.innerHTML = matches.length === 0
                    ? '<div class="product-ac-empty">Barang tidak ditemukan</div>'
                    : matches.map((p, i) => {
                        const outOfStock = Number(p.stock ?? 1) <= 0;
                        return `<div class="product-ac-item${outOfStock?' out-of-stock':''}" data-idx="${i}"><div><div class="product-ac-name">${esc(p.name)}</div>${p.code?`<div class="product-ac-code">${esc(p.code)}</div>`:''}</div><div class="product-ac-meta">Stok: ${p.stock??'?'} ${esc(productUnitLabel(p))}</div></div>`;
                    }).join('');
                document.body.appendChild(dropdown); position();
                dropdown.addEventListener('mousedown', e => { const item = e.target.closest('.product-ac-item'); if (!item) return; e.preventDefault(); pick(parseInt(item.dataset.idx,10)); inputEl.closest('tr')?.querySelector('.qty')?.focus(); });
                dropdown.addEventListener('mousemove', e => { const item = e.target.closest('.product-ac-item'); if (item) setActive(parseInt(item.dataset.idx,10)); });
            }
            async function suggest(query) { if ((query||'').trim().length < 2) { close(); return; } await fetchProductSuggestions(query); open(getMatches(query)); }
            const onInput = debounce(async e => { hiddenEl.value = ''; onSelect(null); await suggest(e.target.value); }, 250);
            inputEl.addEventListener('input', onInput);
            inputEl.addEventListener('focus', async e => { clearTimeout(blurTimer); await suggest(e.target.value); });
            inputEl.addEventListener('blur', () => {
                blurTimer = setTimeout(async () => {
                    close();
                    const val = inputEl.value.trim();
                    if (val === '') { hiddenEl.value = ''; onSelect(null); return; }
                    const product = await resolveProductFromInput(val);
                    const row = inputEl.closest('tr');
                    hiddenEl.value = product ? product.id : '';
                    if (product) { inputEl.value = productLabel(product); onSelect(product); setProductFieldError(row, ''); }
                    else { onSelect(null); setProductFieldError(row, @json(__('txn.product_not_registered'))); }
                }, 200);
            });
            inputEl.addEventListener('keydown', e => {
                if (e.key === 'ArrowDown') { e.preventDefault(); if (!dropdown) { suggest(inputEl.value).then(() => setActive(0)); return; } setActive(Math.min(activeIdx+1, currentMatches.length-1)); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(Math.max(activeIdx-1, 0)); }
                else if (e.key === 'Enter' && dropdown && activeIdx >= 0) { e.preventDefault(); pick(activeIdx); inputEl.closest('tr')?.querySelector('.qty')?.focus(); }
                else if (e.key === 'Escape') { close(); }
            });
            const repos = () => position();
            window.addEventListener('scroll', repos, { passive: true });
            window.addEventListener('resize', repos, { passive: true });
        }

        function findProductByLabel(label) {
            if (!label) {
                return null;
            }
            const normalized = normalizeLookup(label);
            return productByLabel.get(normalized)
                || productByCode.get(normalized)
                || productByName.get(normalized)
                || null;
        }

        function findProductLoose(label) {
            if (!label) {
                return null;
            }
            const normalized = label.trim().toLowerCase();
            return products.find((product) => productLabel(product).toLowerCase().includes(normalized))
                || products.find((product) => product.name.toLowerCase().includes(normalized))
                || null;
        }

        async function resolveProductFromInput(rawValue) {
            const input = String(rawValue || '').trim();
            if (input === '') {
                return null;
            }

            const variants = [input];
            const match = input.match(/^(.+?)\s*-\s*(.+)\s*$/);
            if (match) {
                const codePart = String(match[1] || '').trim();
                const namePart = String(match[2] || '').trim();
                if (codePart !== '') {
                    variants.push(codePart);
                }
                if (namePart !== '') {
                    variants.push(namePart);
                }
            }

            for (const variant of Array.from(new Set(variants))) {
                const product = findProductByLabel(variant) || findProductLoose(variant);
                if (product) {
                    return product;
                }
            }

            for (const variant of Array.from(new Set(variants))) {
                await fetchProductSuggestions(variant);
                const product = findProductByLabel(variant) || findProductLoose(variant);
                if (product) {
                    return product;
                }
            }

            return null;
        }

        function resolveProductPrice(product) {
            if (!product) {
                return 0;
            }
            const key = getPriceKeyForCustomer();
            if (key === 'price_agent') {
                return product.price_agent ?? product.price_general ?? 0;
            }
            if (key === 'price_sales') {
                return product.price_sales ?? product.price_general ?? 0;
            }
            return product.price_general ?? 0;
        }

        function applyCustomerPricing() {
            document.querySelectorAll('#items-table tbody tr').forEach((row) => {
                const priceInput = row.querySelector('.price');
                if (!priceInput || priceInput.dataset.manual === '1') {
                    return;
                }
                const productId = row.querySelector('.product-id')?.value;
                const product = getProductById(productId);
                priceInput.value = resolveProductPrice(product);
                window.PgposNumberFormat.formatInput(priceInput);
            });
            recalc();
        }

        function recalc() {
            let total = 0;
            document.querySelectorAll('#items-table tbody tr').forEach((row) => {
                const qty = window.PgposNumberFormat.parseInt(row.querySelector('.qty').value || 0);
                const price = window.PgposNumberFormat.parseInt(row.querySelector('.price').value || 0);
                const discountPercent = Math.max(0, Math.min(100, window.PgposNumberFormat.parseInt(row.querySelector('.discount').value || 0)));
                const gross = qty * price;
                const discountAmount = gross * (discountPercent / 100);
                const line = Math.max(0, gross - discountAmount);
                row.querySelector('.line-total').textContent = Math.round(line).toLocaleString('id-ID', { maximumFractionDigits: 0 });
                total += line;
            });
            grandTotal.textContent = Math.round(total).toLocaleString('id-ID', { maximumFractionDigits: 0 });
        }

        function updateRowMeta(row, product) {
            row.querySelector('.stock').textContent = product ? product.stock : '-';
            const unitLabel = row.querySelector('.qty-unit-label');
            if (unitLabel) {
                unitLabel.textContent = productUnitLabel(product);
            }
            const priceInput = row.querySelector('.price');
            priceInput.value = resolveProductPrice(product);
            window.PgposNumberFormat.formatInput(priceInput);
            priceInput.dataset.manual = '0';
            recalc();
        }

        function addRow(prefill = null) {
            const index = tbody.children.length;
            const hasQuantityPrefill = prefill?.quantity !== undefined && prefill?.quantity !== null && String(prefill?.quantity).trim() !== '';
            const prefillQuantity = Number(prefill?.quantity || 0);
            const initialQty = hasQuantityPrefill && Number.isFinite(prefillQuantity) && prefillQuantity > 0 ? Math.round(prefillQuantity) : '';
            const prefillProductName = String(prefill?.product_name || '');
            const prefillProductCode = String(prefill?.product_code || '');
            const productText = prefillProductCode !== '' ? `${prefillProductCode} - ${prefillProductName}` : prefillProductName;
            const tr = document.createElement('tr');
              tr.innerHTML = `
                  <td>
                      <input type="text" class="product-search" placeholder="${selectProductLabel}" autocomplete="off" required value="${escapeAttribute(productText)}">
                      <input type="hidden" name="items[${index}][product_id]" class="product-id">
                      <input type="hidden" name="items[${index}][order_note_item_id]" class="order-note-item-id" value="${escapeAttribute(String(prefill?.order_note_item_id || ''))}">
                      <div class="field-inline-error product-search-error" style="display:block; margin-top:4px;"></div>
                  </td>
                <td class="stock">-</td>
                <td>
                    <div class="quantity-with-unit">
                        <input class="qty js-thousand-input" type="text" inputmode="numeric" name="items[${index}][quantity]" value="${initialQty}" placeholder="0" required>
                        <span class="qty-unit-label">-</span>
                    </div>
                </td>
                <td><input class="price js-thousand-input" type="text" inputmode="numeric" name="items[${index}][unit_price]" value="0" required style="max-width: 88px;"></td>
                <td>
                    <div style="display:flex; align-items:center; gap:4px;">
                        <input class="discount" type="number" min="0" max="100" step="1" name="items[${index}][discount]" value="0" style="max-width: 54px;">
                        <span>%</span>
                    </div>
                </td>
                <td style="white-space: nowrap; text-align: right;">Rp <span class="line-total">0</span></td>
                <td><button type="button" class="btn danger-btn remove">{{ __('txn.remove') }}</button></td>
            `;
            tbody.appendChild(tr);
            tr.querySelectorAll('.js-thousand-input').forEach((input) => window.PgposNumberFormat.formatInput(input));

            createProductAutocomplete(
                tr.querySelector('.product-search'),
                tr.querySelector('.product-id'),
                (product) => {
                    updateRowMeta(tr, product);
                    if (product) {
                        setProductFieldError(tr, '');
                        tr.querySelector('.order-note-item-id').value = '';
                    }
                }
            );
            tr.querySelector('.discount').addEventListener('input', (event) => {
                const current = parseFloat(event.currentTarget.value || 0);
                if (Number.isNaN(current)) {
                    return;
                }
                if (current < 0) {
                    event.currentTarget.value = 0;
                    return;
                }
                if (current > 100) {
                    event.currentTarget.value = 100;
                }
            });
            tr.querySelectorAll('.qty,.price,.discount').forEach((el) => el.addEventListener('input', recalc));
            tr.querySelector('.price').addEventListener('input', (event) => {
                event.currentTarget.dataset.manual = '1';
            });
            tr.querySelector('.remove').addEventListener('click', () => {
                tr.remove();
                recalc();
            });

            if (prefill && Number(prefill.product_id || 0) > 0) {
                const productId = Number(prefill.product_id);
                tr.querySelector('.product-id').value = productId;
                const existing = getProductById(productId) || null;
                if (!existing) {
                    upsertProducts([{
                        id: productId,
                        code: prefill.product_code || '',
                        name: prefill.product_name || '',
                        unit: prefill.unit || '',
                        stock: Number(prefill.stock || 0),
                        price_agent: Number(prefill.price_agent || 0),
                        price_sales: Number(prefill.price_sales || 0),
                        price_general: Number(prefill.price_general || 0),
                    }]);
                }
                const resolved = getProductById(productId);
                if (resolved) {
                    tr.querySelector('.product-search').value = productLabel(resolved);
                    updateRowMeta(tr, resolved);
                } else {
                    tr.querySelector('.stock').textContent = Number(prefill.stock || 0);
                }
            }
        }

        function autoSelectSemesterByDate() {
            if (!invoiceDateInput || !semesterPeriodSelect) {
                return;
            }

            const deriveSemesterFromDate = (window.PgposAutoSearch && window.PgposAutoSearch.deriveSemesterFromDate)
                ? window.PgposAutoSearch.deriveSemesterFromDate
                : () => '';
            const derived = deriveSemesterFromDate(invoiceDateInput.value);
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

        function applyBootItems() {
            clearItemsAndReindex();
            if (!Array.isArray(bootItems) || bootItems.length === 0) {
                addRow();
                return;
            }

            bootItems.forEach((row) => {
                const rowData = {
                    product_id: Number(row.product_id || 0) > 0 ? Number(row.product_id) : null,
                    quantity: row.quantity ?? '',
                    order_note_item_id: Number(row.order_note_item_id || 0) > 0 ? Number(row.order_note_item_id) : '',
                };
                addRow(rowData);
                const tableRow = tbody.lastElementChild;
                if (!tableRow) {
                    return;
                }

                const priceInput = tableRow.querySelector('.price');
                const discountInput = tableRow.querySelector('.discount');
                if (priceInput) {
                    priceInput.value = Number(row.unit_price || 0);
                    priceInput.dataset.manual = '1';
                }
                if (discountInput) {
                    discountInput.value = Number(row.discount || 0);
                }
            });
            recalc();
        }

        async function initializeInvoiceForm() {
            rebuildCustomerIndexes();
            rebuildProductIndexes();
            renderCustomerSuggestions('');

            const bootCustomer = customerIdField.value
                ? (customerById.get(String(customerIdField.value)) || null)
                : findCustomerByLabel(customerSearch?.value || '');

            setCurrentCustomer(bootCustomer, false);
            setCustomerFieldError('');

            if (bootCustomer) {
                await fetchOrderNotesForCustomer(String(bootCustomer.id), bootOrderNoteId || '');
            } else {
                setOrderNotes([]);
                renderOrderNoteOptions('');
            }

            if (Array.isArray(bootItems) && bootItems.length > 0) {
                applyBootItems();
            } else if (bootOrderNoteId !== '' && orderNoteById.has(String(bootOrderNoteId))) {
                if (orderNoteField) {
                    orderNoteField.value = String(bootOrderNoteId);
                }
                const selectedNote = orderNoteById.get(String(bootOrderNoteId));
                if (selectedNote) {
                    applyOrderNoteItems(selectedNote);
                    updateOrderNoteInfoSelection(selectedNote);
                } else {
                    addRow();
                }
            } else {
                addRow();
            }

            applyCustomerPricing();
        }

        addBtn.addEventListener('click', addRow);
        if (customerSearch) {
            const onCustomerInput = debounce(async (event) => {
                setCustomerFieldError('');
                await fetchCustomerSuggestions(event.currentTarget.value);
                const customer = findCustomerByLabel(event.currentTarget.value);
                await handleResolvedCustomer(customer, true);
            });
            const syncCustomerSelection = async (rawValue) => {
                const value = String(rawValue || '').trim();
                if (value === '') {
                    setCustomerFieldError('');
                    await handleResolvedCustomer(null, true);
                    return;
                }
                const customer = await resolveCustomerFromInput(value);
                await handleResolvedCustomer(customer, true);
                if (customer) {
                    customerSearch.value = customerLabel(customer);
                    setCustomerFieldError('');
                    return;
                }
                setCustomerFieldError(@json(__('txn.customer_not_registered')));
            };
            customerSearch.addEventListener('input', onCustomerInput);
            customerSearch.addEventListener('change', async (event) => {
                await syncCustomerSelection(event.currentTarget.value);
            });
            customerSearch.addEventListener('blur', async (event) => {
                await syncCustomerSelection(event.currentTarget.value);
            });
        }
        if (orderNoteField) {
            orderNoteField.addEventListener('change', () => {
                const note = orderNoteById.get(String(orderNoteField.value || '')) || null;
                if (!note) {
                    renderOrderNoteOptions('');
                    return;
                }
                updateOrderNoteInfoSelection(note);
                applyOrderNoteItems(note);
            });
            orderNoteField.addEventListener('focus', async () => {
                let customerId = String(customerIdField.value || '');
                if (customerId === '') {
                    const resolvedCustomer = await resolveCustomerFromInput(customerSearch?.value || '');
                    await handleResolvedCustomer(resolvedCustomer, false);
                    customerId = String(customerIdField.value || '');
                }
                if (customerId === '') {
                    return;
                }
                const needsReload = String(orderNotesCustomerId || '') !== customerId
                    || !Array.isArray(orderNotes)
                    || orderNotes.length === 0;
                if (needsReload) {
                    await fetchOrderNotesForCustomer(customerId, orderNoteField.value || '');
                }
            });
        }
        if (form) {
            form.addEventListener('submit', async (event) => {
                if (isSubmitting) {
                    return;
                }
                event.preventDefault();
                if (!customerIdField.value && customerSearch?.value) {
                    const customer = await resolveCustomerFromInput(customerSearch.value);
                    await handleResolvedCustomer(customer, false);
                    if (customer) {
                        customerSearch.value = customerLabel(customer);
                        setCustomerFieldError('');
                    } else {
                        setCustomerFieldError(@json(__('txn.customer_not_registered')));
                    }
                }

                const rows = Array.from(document.querySelectorAll('#items-table tbody tr'));
                let hasMissingProduct = false;
                for (const row of rows) {
                    const productIdField = row.querySelector('.product-id');
                    const productSearchField = row.querySelector('.product-search');
                    if (!productIdField || !productSearchField || String(productIdField.value || '').trim() !== '') {
                        continue;
                    }
                    const product = await resolveProductFromInput(productSearchField.value || '');
                    if (!product) {
                        if (String(productSearchField.value || '').trim() !== '') {
                            setProductFieldError(row, @json(__('txn.product_not_registered')));
                        }
                        hasMissingProduct = true;
                        continue;
                    }
                    productIdField.value = product.id;
                    productSearchField.value = productLabel(product);
                    setProductFieldError(row, '');
                    updateRowMeta(row, product);
                }

                const missing = Array.from(document.querySelectorAll('.product-id'))
                    .some(input => !input.value);
                if (missing || hasMissingProduct || !customerIdField.value) {
                    if (!customerIdField.value && customerSearch?.value) {
                        setCustomerFieldError(@json(__('txn.customer_not_registered')));
                    }
                    window.PgposDialog.showMessage(@json(__('txn.select_customer_and_product')));
                    return;
                }
                isSubmitting = true;
                form.submit();
            });
        }
        initializeInvoiceForm();
        autoSelectSemesterByDate();
        invoiceDateInput?.addEventListener('change', autoSelectSemesterByDate);

        // Popup: warn if user manually selects a past semester
        let autoChangingSemester = false;
        const origAutoSelect = autoSelectSemesterByDate;
        autoSelectSemesterByDate = function () {
            autoChangingSemester = true;
            origAutoSelect();
            autoChangingSemester = false;
        };

        if (semesterPeriodSelect) {
            semesterPeriodSelect.addEventListener('change', function () {
                if (autoChangingSemester) return;

                const deriveSemesterFromDate = window.PgposAutoSearch?.deriveSemesterFromDate ?? (() => '');
                const semesterSortKey = window.PgposAutoSearch?.semesterSortKey ?? (() => '');
                const expected = deriveSemesterFromDate(invoiceDateInput?.value ?? '');
                const selected = semesterPeriodSelect.value;

                if (!expected || !selected) return;
                if (semesterSortKey(selected) >= semesterSortKey(expected)) return;

                // Selected semester is earlier than what the date suggests
                const lanjutkan = window.confirm(
                    'Kamu menambahkan ke semester ' + selected + ' yang telah lewat.\n' +
                    'Seharusnya masuk semester ' + expected + '.\n\n' +
                    'Bersedia melanjutkan? (pilih Batal untuk kembali ke ' + expected + ')'
                );

                if (!lanjutkan) {
                    autoChangingSemester = true;
                    // Ensure expected option exists
                    const hasOption = Array.from(semesterPeriodSelect.options).some((o) => o.value === expected);
                    if (!hasOption) {
                        const opt = document.createElement('option');
                        opt.value = expected;
                        opt.textContent = expected;
                        semesterPeriodSelect.appendChild(opt);
                    }
                    semesterPeriodSelect.value = expected;
                    autoChangingSemester = false;
                }
            });
        }
    </script>

    @include('partials.printing_subtype_script')
@endsection

