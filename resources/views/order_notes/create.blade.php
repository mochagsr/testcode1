@extends('layouts.app')

@section('title', __('txn.create_order_note_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <h1 class="page-title">{{ __('txn.create_order_note_title') }}</h1>

    <style>
        .product-ac-dropdown {
            position: fixed;
            z-index: 9999;
            background: var(--card, #fff);
            border: 1px solid var(--border, #d0d7de);
            border-radius: 6px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.16);
            min-width: 300px;
            max-height: 280px;
            overflow-y: auto;
            font-size: 13px;
        }
        .product-ac-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 2px 12px;
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid var(--border, #d0d7de);
            line-height: 1.35;
        }
        .product-ac-item:last-child { border-bottom: none; }
        .product-ac-item.is-active, .product-ac-item:hover { background: var(--hover-bg, rgba(59,130,246,0.08)); }
        .product-ac-item.out-of-stock { opacity: 0.4; }
        .product-ac-name { font-weight: 700; }
        .product-ac-code { font-size: 11px; color: var(--muted, #6b7280); }
        .product-ac-meta { font-size: 11px; color: var(--muted, #6b7280); text-align: right; white-space: nowrap; align-self: center; }
        .product-ac-empty { padding: 10px 12px; color: var(--muted, #6b7280); font-style: italic; }
        #items-table input[type=number].qty-input::-webkit-outer-spin-button,
        #items-table input[type=number].qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #items-table input[type=number].qty-input {
            -moz-appearance: textfield;
            appearance: textfield;
        }
        .items-total-box {
            margin-top: 10px;
            text-align: right;
            font-size: 16px;
        }
        .quantity-with-unit {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 130px;
        }
        .quantity-with-unit .qty-input {
            flex: 0 0 88px;
            max-width: 88px;
        }
        .qty-unit-label {
            color: #526173;
            font-weight: 700;
            white-space: nowrap;
        }
    </style>

    <form method="post" action="{{ route('order-notes.store') }}">
        @csrf

        <div class="card">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('txn.order_header') }}</h3>
                <p class="form-section-note">{{ __('txn.order_summary_note') }}</p>
                <div class="row">
                    <div class="col-4">
                        <label>{{ __('txn.date') }} <span class="label-required">*</span></label>
                        <input type="date" name="note_date" value="{{ old('note_date', now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-4">
                        <label class="label-with-feedback">
                            <span>{{ __('ui.customer_name') }} <span class="label-required">*</span></span>
                            <span id="customer-search-error" class="field-inline-error" aria-live="polite"></span>
                        </label>
                        @php
                            $customerMap = $customers->keyBy('id');
                            $oldCustomerId = old('customer_id');
                            $oldCustomerLabel = $oldCustomerId && $customerMap->has($oldCustomerId)
                                ? $customerMap[$oldCustomerId]->name.' ('.($customerMap[$oldCustomerId]->city ?: '-').')'
                                : old('customer_name', '');
                        @endphp
                        <input type="text"
                               id="customer-search"
                               name="customer_name"
                               list="customers-list"
                               value="{{ $oldCustomerLabel }}"
                               placeholder="Pilih customer terdaftar"
                               required>
                        <input type="hidden" id="customer_id" name="customer_id" value="{{ $oldCustomerId }}">
                        <datalist id="customers-list">
                            @foreach($customers as $customer)
                                <option value="{{ $customer->name }} ({{ $customer->city ?: '-' }})"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.phone') }}</label>
                        <input id="customer_phone" type="text" name="customer_phone" value="{{ old('customer_phone') }}">
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.city') }}</label>
                        <input id="city" type="text" name="city" value="{{ old('city') }}">
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.transaction_type') }}</label>
                        <select id="order-note-transaction-type" name="transaction_type">
                            <option value="product" @selected(old('transaction_type', 'product') === 'product')>{{ __('txn.transaction_type_product') }}</option>
                            <option value="printing" @selected(old('transaction_type') === 'printing')>{{ __('txn.transaction_type_printing') }}</option>
                        </select>
                    </div>
                    @include('partials.printing_subtype_fields', [
                        'customerFieldId' => 'customer_id',
                        'transactionTypeFieldId' => 'order-note-transaction-type',
                        'subtypeFieldId' => 'order-note-printing-subtype-id',
                        'selectedSubtypeId' => old('customer_printing_subtype_id'),
                        'selectedSubtypeName' => old('printing_subtype_name'),
                        'colClass' => 'col-4',
                    ])
                    <div class="col-12">
                        <label>{{ __('txn.address') }}</label>
                        <textarea id="address" name="address" rows="2">{{ old('address') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label>{{ __('txn.notes') }}</label>
                        <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex" style="justify-content: space-between;">
                <h3 style="margin: 0;">{{ __('txn.items') }}</h3>
                <button type="button" id="add-item" class="btn process-soft-btn">{{ __('txn.add_row') }}</button>
            </div>
            <div class="table-mobile-scroll" style="margin-top: 12px;">
                <table id="items-table">
                    <thead>
                    <tr>
                        <th style="width: 40%">{{ __('txn.product') }} *</th>
                        <th style="width: 8%">{{ __('txn.qty') }} *</th>
                        <th>{{ __('txn.notes') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="items-total-box">
                <strong>{{ __('txn.summary_total_qty') }}: <span id="items-total-qty">0</span></strong>
            </div>
        </div>

        <button class="btn" type="submit">{{ __('txn.save_order_note') }}</button>
        <a class="btn secondary" href="{{ route('order-notes.index') }}">{{ __('txn.cancel') }}</a>
    </form>

    <script>
        let customers = @json($customers->values());
        let products = @json($products);
        let customerById = new Map((customers || []).map((customer) => [String(customer.id), customer]));
        let customerByLabel = new Map();
        let customerByName = new Map();
        let productByLabel = new Map();
        let productByCode = new Map();
        let productByName = new Map();
        const CUSTOMER_LOOKUP_URL = @json(route('api.customers.index'));
        const PRODUCT_LOOKUP_URL = @json(route('api.products.index'));
        const LOOKUP_LIMIT = 20;
        const tbody = document.querySelector('#items-table tbody');
        const customersList = document.getElementById('customers-list');
        const addBtn = document.getElementById('add-item');
        const itemsTotalQty = document.getElementById('items-total-qty');
        const customerSearch = document.getElementById('customer-search');
        const customerIdField = document.getElementById('customer_id');
        const customerSearchError = document.getElementById('customer-search-error');
        const customerPhoneField = document.getElementById('customer_phone');
        const cityField = document.getElementById('city');
        const addressField = document.getElementById('address');
        const form = document.querySelector('form');
        const SEARCH_DEBOUNCE_MS = 100;
        let customerLookupAbort = null;
        let productLookupAbort = null;
        let lastCustomerLookupQuery = '';
        let lastProductLookupQuery = '';

        function normalizeLookup(value) {
            return String(value || '').trim().toLowerCase();
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

        function upsertCustomers(rows) {
            const byId = new Map(customers.map((row) => [String(row.id), row]));
            (rows || []).forEach((row) => byId.set(String(row.id), row));
            customers = Array.from(byId.values());
            customerById = new Map(customers.map((customer) => [String(customer.id), customer]));
            rebuildCustomerIndexes();
        }

        function upsertProducts(rows) {
            const byId = new Map(products.map((row) => [String(row.id), row]));
            (rows || []).forEach((row) => byId.set(String(row.id), row));
            products = Array.from(byId.values());
            rebuildProductIndexes();
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
            return customers.find((customer) => customerLabel(customer).toLowerCase() === normalized)
                || customers.find((customer) => customer.name.toLowerCase() === normalized)
                || null;
        }

        async function resolveCustomerFromInput(rawValue) {
            const input = String(rawValue || '').trim();
            if (input === '') {
                return null;
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
            }

            for (const variant of Array.from(new Set(variants))) {
                const customer = findCustomerByLabel(variant) || findCustomerLoose(variant);
                if (customer) {
                    return customer;
                }
            }

            for (const variant of Array.from(new Set(variants))) {
                await fetchCustomerSuggestions(variant);
                const customer = findCustomerByLabel(variant) || findCustomerLoose(variant);
                if (customer) {
                    return customer;
                }
            }

            return null;
        }

        function getCustomerById(id) {
            return customerById.get(String(id)) || null;
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

        function applyCustomerFields(customer) {
            customerIdField.value = customer ? customer.id : '';
            if (!customer) {
                if (customerPhoneField) customerPhoneField.value = '';
                if (cityField) cityField.value = '';
                if (addressField) addressField.value = '';
                return;
            }
            setCustomerFieldError('');
            if (customerPhoneField) customerPhoneField.value = customer.phone || '';
            if (cityField) cityField.value = customer.city || '';
            if (addressField) addressField.value = customer.address || '';
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

        function updateRowUnit(row, product) {
            const unitLabel = row?.querySelector('.qty-unit-label');
            if (unitLabel) {
                unitLabel.textContent = productUnitLabel(product);
            }
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

        async function resolveProductFromInput(rawValue) {
            const input = String(rawValue || '').trim();
            if (input === '') return null;
            const variants = [input];
            const match = input.match(/^(.+?)\s*-\s*(.+)\s*$/);
            if (match) {
                const a = String(match[1] || '').trim();
                const b = String(match[2] || '').trim();
                if (a) variants.push(a);
                if (b) variants.push(b);
            }
            for (const v of Array.from(new Set(variants))) {
                const p = findProductByLabel(v) || findProductLoose(v);
                if (p) return p;
            }
            for (const v of Array.from(new Set(variants))) {
                await fetchProductSuggestions(v);
                const p = findProductByLabel(v) || findProductLoose(v);
                if (p) return p;
            }
            return null;
        }

        function createProductAutocomplete(inputEl, hiddenEl, onSelect) {
            let dropdown = null;
            let activeIdx = -1;
            let currentMatches = [];
            let blurTimer = null;

            function esc(s) {
                return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

            function getMatches(query) {
                const q = (query || '').trim().toLowerCase();
                if (q.length < 2) return [];
                return products.filter(p => {
                    const label = productLabel(p).toLowerCase();
                    const code = (p.code || '').toLowerCase();
                    const name = (p.name || '').toLowerCase();
                    return label.includes(q) || code.includes(q) || name.includes(q);
                }).slice(0, 10);
            }

            function position() {
                if (!dropdown) return;
                const r = inputEl.getBoundingClientRect();
                const dropH = Math.min(280, currentMatches.length * 52 + 12);
                const below = window.innerHeight - r.bottom;
                dropdown.style.left = r.left + 'px';
                dropdown.style.width = Math.max(r.width, 300) + 'px';
                dropdown.style.top = (below >= dropH || below >= r.top)
                    ? (r.bottom + 2) + 'px'
                    : (r.top - dropH - 2) + 'px';
            }

            function close() {
                dropdown?.remove();
                dropdown = null;
                currentMatches = [];
                activeIdx = -1;
            }

            function setActive(idx) {
                activeIdx = idx;
                dropdown?.querySelectorAll('.product-ac-item').forEach((el, i) => {
                    el.classList.toggle('is-active', i === idx);
                });
            }

            function pick(idx) {
                const product = currentMatches[idx];
                if (!product) return;
                inputEl.value = productLabel(product);
                hiddenEl.value = product.id;
                close();
                onSelect(product);
            }

            function open(matches) {
                close();
                currentMatches = matches;
                dropdown = document.createElement('div');
                dropdown.className = 'product-ac-dropdown';
                if (matches.length === 0) {
                    dropdown.innerHTML = '<div class="product-ac-empty">Barang tidak ditemukan</div>';
                } else {
                    dropdown.innerHTML = matches.map((p, i) => {
                        const outOfStock = Number(p.stock ?? 1) <= 0;
                        const unit = productUnitLabel(p);
                        return `<div class="product-ac-item${outOfStock ? ' out-of-stock' : ''}" data-idx="${i}">
                            <div>
                                <div class="product-ac-name">${esc(p.name)}</div>
                                ${p.code ? `<div class="product-ac-code">${esc(p.code)}</div>` : ''}
                            </div>
                            <div class="product-ac-meta">Stok: ${p.stock ?? '?'} ${esc(unit)}</div>
                        </div>`;
                    }).join('');
                }
                document.body.appendChild(dropdown);
                position();
                dropdown.addEventListener('mousedown', e => {
                    const item = e.target.closest('.product-ac-item');
                    if (!item) return;
                    e.preventDefault();
                    pick(parseInt(item.dataset.idx, 10));
                    inputEl.closest('tr')?.querySelector('.qty-input')?.focus();
                });
                dropdown.addEventListener('mousemove', e => {
                    const item = e.target.closest('.product-ac-item');
                    if (item) setActive(parseInt(item.dataset.idx, 10));
                });
            }

            async function suggest(query) {
                if ((query || '').trim().length < 2) { close(); return; }
                await fetchProductSuggestions(query);
                open(getMatches(query));
            }

            const onInput = debounce(async e => {
                hiddenEl.value = '';
                onSelect(null);
                await suggest(e.target.value);
            }, 250);

            inputEl.addEventListener('input', onInput);
            inputEl.addEventListener('focus', async e => {
                clearTimeout(blurTimer);
                await suggest(e.target.value);
            });
            inputEl.addEventListener('blur', () => {
                blurTimer = setTimeout(async () => {
                    close();
                    const val = inputEl.value.trim();
                    if (val === '') { hiddenEl.value = ''; onSelect(null); return; }
                    const product = await resolveProductFromInput(val);
                    const row = inputEl.closest('tr');
                    hiddenEl.value = product ? product.id : '';
                    if (product) {
                        inputEl.value = productLabel(product);
                        onSelect(product);
                        setProductFieldError(row, '');
                    } else {
                        onSelect(null);
                        setProductFieldError(row, @json(__('txn.product_not_registered')));
                    }
                }, 200);
            });
            inputEl.addEventListener('keydown', e => {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!dropdown) { suggest(inputEl.value).then(() => setActive(0)); return; }
                    setActive(Math.min(activeIdx + 1, currentMatches.length - 1));
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    setActive(Math.max(activeIdx - 1, 0));
                } else if (e.key === 'Enter' && dropdown && activeIdx >= 0) {
                    e.preventDefault();
                    pick(activeIdx);
                    inputEl.closest('tr')?.querySelector('.qty-input')?.focus();
                } else if (e.key === 'Escape') {
                    close();
                }
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

        function recalcItemsTotal() {
            const total = Array.from(tbody.querySelectorAll('.qty-input'))
                .reduce((sum, input) => sum + Math.max(0, window.PgposNumberFormat.parseInt(input.value || 0)), 0);
            if (itemsTotalQty) {
                itemsTotalQty.textContent = total.toLocaleString('id-ID', { maximumFractionDigits: 0 });
            }
        }

        function addRow() {
            const index = tbody.children.length;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="text" class="product-search" name="items[${index}][product_name]" placeholder="Pilih barang..." autocomplete="off" required>
                    <input type="hidden" name="items[${index}][product_id]" class="product-id">
                    <div class="field-inline-error product-search-error" style="display:block; margin-top:4px;"></div>
                </td>
                <td>
                    <div class="quantity-with-unit">
                        <input name="items[${index}][quantity]" type="text" inputmode="numeric" value="" placeholder="0" class="qty-input js-thousand-input" required>
                        <span class="qty-unit-label">-</span>
                    </div>
                </td>
                <td><input name="items[${index}][notes]"></td>
                <td><button type="button" class="btn danger-btn remove">{{ __('txn.remove') }}</button></td>
            `;
            tbody.appendChild(tr);
            tr.querySelectorAll('.js-thousand-input').forEach((input) => window.PgposNumberFormat.formatInput(input));
            tr.querySelector('.qty-input')?.addEventListener('input', recalcItemsTotal);

            createProductAutocomplete(
                tr.querySelector('.product-search'),
                tr.querySelector('.product-id'),
                (product) => { updateRowUnit(tr, product); if (product) setProductFieldError(tr, ''); }
            );

            tr.querySelector('.remove').addEventListener('click', () => {
                tr.remove();
                recalcItemsTotal();
            });
            recalcItemsTotal();
        }

        if (customerSearch) {
            const bootCustomer = customerIdField.value
                ? getCustomerById(customerIdField.value)
                : findCustomerByLabel(customerSearch.value);
            applyCustomerFields(bootCustomer);
            const onCustomerInput = debounce(async (event) => {
                setCustomerFieldError('');
                await fetchCustomerSuggestions(event.currentTarget.value);
                const customer = findCustomerByLabel(event.currentTarget.value);
                applyCustomerFields(customer);
            });
            const syncCustomerSelection = async (rawValue) => {
                const value = String(rawValue || '').trim();
                if (value === '') {
                    applyCustomerFields(null);
                    setCustomerFieldError('');
                    return;
                }
                const customer = await resolveCustomerFromInput(value);
                applyCustomerFields(customer);
                if (customer) {
                    customerSearch.value = customerLabel(customer);
                    setCustomerFieldError('');
                } else {
                    setCustomerFieldError(@json(__('txn.customer_not_registered')));
                }
            };
            customerSearch.addEventListener('input', onCustomerInput);
            customerSearch.addEventListener('change', async (event) => {
                await syncCustomerSelection(event.currentTarget.value);
            });
            customerSearch.addEventListener('blur', async (event) => {
                await syncCustomerSelection(event.currentTarget.value);
            });
        }

        form?.addEventListener('submit', async (event) => {
            let shouldAlert = false;
            if (!customerIdField.value && customerSearch?.value) {
                const customer = await resolveCustomerFromInput(customerSearch.value);
                applyCustomerFields(customer);
                if (customer) {
                    customerSearch.value = customerLabel(customer);
                    setCustomerFieldError('');
                } else {
                    setCustomerFieldError(@json(__('txn.customer_not_registered')));
                    event.preventDefault();
                    customerSearch.focus();
                    return;
                }
            }

            let hasMissingProduct = false;
            for (const row of Array.from(tbody.querySelectorAll('tr'))) {
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
                    shouldAlert = true;
                    continue;
                }
                productIdField.value = product.id;
                productSearchField.value = productLabel(product);
                updateRowUnit(row, product);
                setProductFieldError(row, '');
            }

            if (hasMissingProduct) {
                event.preventDefault();
                if (shouldAlert) {
                    window.PgposDialog.showMessage(@json(__('txn.fix_invalid_products')));
                }
            }
        });

        rebuildCustomerIndexes();
        rebuildProductIndexes();
        addBtn.addEventListener('click', addRow);
        renderCustomerSuggestions('');
        addRow();
        recalcItemsTotal();
    </script>

    @include('partials.printing_subtype_script')
@endsection

