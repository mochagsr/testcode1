@extends('layouts.app')

@section('title', __('txn.create_sales_invoice_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('txn.create_sales_invoice_title') }}</h1>

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
                                <label>{{ __('txn.customer') }} <span class="label-required">*</span></label>
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
                                    <option value="tunai" @selected(old('payment_method', 'tunai') === 'tunai')>{{ __('txn.cash') }}</option>
                                    <option value="kredit" @selected(old('payment_method') === 'kredit')>{{ __('txn.credit') }}</option>
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
                <button type="button" id="add-item" class="btn secondary">{{ __('txn.add_row') }}</button>
            </div>
            <table id="items-table" style="margin-top: 12px;">
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
            <div style="margin-top: 10px; text-align: right;">
                <strong>{{ __('txn.total') }}: Rp <span id="grand-total">0</span></strong>
            </div>
        </div>

        <button class="btn" type="submit">{{ __('txn.save_invoice') }}</button>
        <a class="btn secondary" href="{{ route('sales-invoices.index') }}">{{ __('txn.cancel') }}</a>
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
        const selectProductLabel = @json(__('txn.select_product'));
        const selectOrderNoteLabel = @json(__('txn.select_order_note'));
        const tbody = document.querySelector('#items-table tbody');
        const productsList = document.getElementById('products-list');
        const customersList = document.getElementById('customers-list');
        const customerSearch = document.getElementById('customer-search');
        const customerIdField = document.getElementById('customer-id');
        const orderNoteField = document.getElementById('order-note-id');
        const orderNoteInfo = document.getElementById('order-note-info');
        const grandTotal = document.getElementById('grand-total');
        const addBtn = document.getElementById('add-item');
        const invoiceDateInput = document.getElementById('invoice-date');
        const semesterPeriodSelect = document.getElementById('semester-period');
        const form = document.querySelector('form');
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
            if (combined.includes('sales')) {
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

        async function fetchCustomerSuggestions(query) {
            const normalizedQuery = normalizeLookup(query);
            if (!(window.PgposAutoSearch && window.PgposAutoSearch.canSearchInput({ value: query }))) {
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
                const url = `${CUSTOMER_LOOKUP_URL}?search=${encodeURIComponent(query)}&per_page=${LOOKUP_LIMIT}`;
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
            return customers.find((customer) => customerLabel(customer).toLowerCase().includes(normalized))
                || customers.find((customer) => customer.name.toLowerCase().includes(normalized))
                || null;
        }

        function setCurrentCustomer(customer, resetOrderNote = true) {
            currentCustomer = customer;
            if (customer) {
                customerIdField.value = customer.id;
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
                    quantity: Number(item.remaining_qty || item.ordered_qty || 1),
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
                const url = `${ORDER_NOTE_LOOKUP_URL}?customer_id=${encodeURIComponent(customerId)}&per_page=20`;
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
            if (previousCustomerId !== nextCustomerId) {
                await fetchOrderNotesForCustomer(nextCustomerId);
            }
        }

        function productLabel(product) {
            const code = (product.code || '').trim();
            if (code !== '') {
                return `${code} - ${product.name}`;
            }
            return `${product.name}`;
        }

        const escapeAttribute = (window.PgposAutoSearch && window.PgposAutoSearch.escapeAttribute)
            ? window.PgposAutoSearch.escapeAttribute
            : (value) => String(value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

        function renderProductSuggestions(query) {
            if (!productsList) {
                return;
            }
            const normalized = (query || '').trim().toLowerCase();
            const matches = products.filter((product) => {
                const label = productLabel(product).toLowerCase();
                const code = (product.code || '').toLowerCase();
                const name = (product.name || '').toLowerCase();
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
            });
            recalc();
        }

        function recalc() {
            let total = 0;
            document.querySelectorAll('#items-table tbody tr').forEach((row) => {
                const qty = parseFloat(row.querySelector('.qty').value || 0);
                const price = parseFloat(row.querySelector('.price').value || 0);
                const discountPercent = Math.max(0, Math.min(100, parseFloat(row.querySelector('.discount').value || 0)));
                const gross = qty * price;
                const discountAmount = gross * (discountPercent / 100);
                const line = Math.max(0, gross - discountAmount);
                row.querySelector('.line-total').textContent = line.toFixed(0);
                total += line;
            });
            grandTotal.textContent = total.toFixed(0);
        }

        function updateRowMeta(row, product) {
            row.querySelector('.stock').textContent = product ? product.stock : '-';
            const priceInput = row.querySelector('.price');
            priceInput.value = resolveProductPrice(product);
            priceInput.dataset.manual = '0';
            recalc();
        }

        function addRow(prefill = null) {
            const index = tbody.children.length;
            const prefillQuantity = Number(prefill?.quantity || 1);
            const initialQty = Number.isFinite(prefillQuantity) && prefillQuantity > 0 ? Math.round(prefillQuantity) : 1;
            const prefillProductName = String(prefill?.product_name || '');
            const prefillProductCode = String(prefill?.product_code || '');
            const productText = prefillProductCode !== '' ? `${prefillProductCode} - ${prefillProductName}` : prefillProductName;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="text" class="product-search" list="products-list" placeholder="${selectProductLabel}" required value="${escapeAttribute(productText)}">
                    <input type="hidden" name="items[${index}][product_id]" class="product-id">
                    <input type="hidden" name="items[${index}][order_note_item_id]" class="order-note-item-id" value="${escapeAttribute(String(prefill?.order_note_item_id || ''))}">
                </td>
                <td class="stock">-</td>
                <td><input class="qty" type="number" min="1" name="items[${index}][quantity]" value="${initialQty}" required style="max-width: 88px;"></td>
                <td><input class="price" type="number" min="0" step="1" name="items[${index}][unit_price]" value="0" required style="max-width: 88px;"></td>
                <td>
                    <div style="display:flex; align-items:center; gap:4px;">
                        <input class="discount" type="number" min="0" max="100" step="1" name="items[${index}][discount]" value="0" style="max-width: 54px;">
                        <span>%</span>
                    </div>
                </td>
                <td style="white-space: nowrap; text-align: right;">Rp <span class="line-total">0</span></td>
                <td><button type="button" class="btn secondary remove">{{ __('txn.remove') }}</button></td>
            `;
            tbody.appendChild(tr);

            const onProductInput = debounce(async (event) => {
                await fetchProductSuggestions(event.currentTarget.value);
                const product = findProductByLabel(event.currentTarget.value);
                tr.querySelector('.product-id').value = product ? product.id : '';
                if (!product) {
                    tr.querySelector('.order-note-item-id').value = '';
                }
                updateRowMeta(tr, product);
            });
            tr.querySelector('.product-search').addEventListener('input', onProductInput);
            tr.querySelector('.product-search').addEventListener('focus', (event) => {
                renderProductSuggestions(event.currentTarget.value);
            });
            tr.querySelector('.product-search').addEventListener('change', (event) => {
                const product = findProductByLabel(event.currentTarget.value) || findProductLoose(event.currentTarget.value);
                tr.querySelector('.product-id').value = product ? product.id : '';
                if (product) {
                    tr.querySelector('.product-search').value = productLabel(product);
                } else {
                    tr.querySelector('.order-note-item-id').value = '';
                }
                updateRowMeta(tr, product);
            });
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
                    quantity: Number(row.quantity || 1),
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
            renderProductSuggestions('');

            const bootCustomer = customerIdField.value
                ? (customerById.get(String(customerIdField.value)) || null)
                : findCustomerByLabel(customerSearch?.value || '');

            setCurrentCustomer(bootCustomer, false);

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
                await fetchCustomerSuggestions(event.currentTarget.value);
                const customer = findCustomerByLabel(event.currentTarget.value);
                await handleResolvedCustomer(customer, true);
            });
            customerSearch.addEventListener('input', onCustomerInput);
            customerSearch.addEventListener('change', async (event) => {
                const customer = findCustomerByLabel(event.currentTarget.value) || findCustomerLoose(event.currentTarget.value);
                await handleResolvedCustomer(customer, true);
                if (customer) {
                    customerSearch.value = customerLabel(customer);
                }
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
        }
        if (form) {
            form.addEventListener('submit', (event) => {
                const missing = Array.from(document.querySelectorAll('.product-id'))
                    .some(input => !input.value);
                if (missing || !customerIdField.value) {
                    event.preventDefault();
                    alert('{{ __('txn.select_customer') }} / {{ __('txn.select_product') }}');
                }
            });
        }
        initializeInvoiceForm();
        autoSelectSemesterByDate();
        invoiceDateInput?.addEventListener('change', autoSelectSemesterByDate);
    </script>

    <datalist id="products-list">
        @foreach($products as $product)
            <option value="{{ $product->code ? $product->code.' - '.$product->name : $product->name }}"></option>
        @endforeach
    </datalist>
@endsection
