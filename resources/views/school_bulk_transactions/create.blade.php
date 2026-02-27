@extends('layouts.app')

@section('title', __('school_bulk.create_bulk_transaction').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('school_bulk.create_bulk_transaction') }}</h1>

    <form method="post" action="{{ route('school-bulk-transactions.store') }}">
        @csrf

        <div class="card">
            <div class="row">
                <div class="col-6">
                    <label>{{ __('txn.customer') }} <span class="label-required">*</span></label>
                    @php
                        $customerMap = $customers->keyBy('id');
                        $oldCustomerId = (int) old('customer_id', 0);
                        $oldCustomerLabel = $oldCustomerId > 0 && $customerMap->has($oldCustomerId)
                            ? $customerMap[$oldCustomerId]->name.' ('.($customerMap[$oldCustomerId]->city ?: '-').')'
                            : '';
                    @endphp
                    <input type="text"
                           id="customer-search"
                           list="customers-list"
                           value="{{ $oldCustomerLabel }}"
                           placeholder="{{ __('school_bulk.select_customer') }}"
                           required>
                    <input type="hidden" id="customer-id" name="customer_id" value="{{ $oldCustomerId }}" required>
                    <datalist id="customers-list">
                        @foreach($customers as $customer)
                            <option value="{{ $customer->name }} ({{ $customer->city ?: '-' }})"></option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-3">
                    <label>{{ __('txn.date') }} <span class="label-required">*</span></label>
                    <input type="date" id="transaction-date" name="transaction_date" value="{{ old('transaction_date', now()->format('Y-m-d')) }}" required>
                </div>
                <div class="col-3">
                    <label>{{ __('txn.semester_period') }}</label>
                    <input type="text" id="semester-period" name="semester_period" value="{{ old('semester_period', $defaultSemesterPeriod) }}">
                </div>
                <div class="col-12">
                    <label>{{ __('txn.notes') }}</label>
                    <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex" style="justify-content: space-between;">
                <h3 style="margin: 0;">{{ __('school_bulk.bulk_locations_title') }}</h3>
                <div class="flex">
                    <button type="button" class="btn secondary" id="fill-from-master">{{ __('school_bulk.fill_from_master') }}</button>
                    <button type="button" class="btn secondary" id="add-location">{{ __('txn.add_row') }}</button>
                </div>
            </div>
            <p class="muted" style="margin-top: 8px;">{{ __('school_bulk.bulk_locations_note') }}</p>
            <table id="locations-table" style="margin-top: 10px;">
                <thead>
                <tr>
                    <th>{{ __('school_bulk.school_name') }} *</th>
                    <th>{{ __('txn.phone') }}</th>
                    <th>{{ __('txn.city') }}</th>
                    <th>{{ __('txn.address') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="card">
            <h3 style="margin: 0;">{{ __('txn.items') }}</h3>
            <p class="muted" style="margin-top: 8px;">{{ __('school_bulk.bulk_items_note') }}</p>
            <div id="school-item-cards" style="margin-top: 10px;"></div>
            <p class="muted" id="school-item-empty-hint" style="margin-top: 10px;">{{ __('school_bulk.fill_school_locations') }}</p>
        </div>

        <button class="btn" type="submit">{{ __('school_bulk.save_bulk_transaction') }}</button>
        <a class="btn secondary" href="{{ route('school-bulk-transactions.index') }}">{{ __('txn.cancel') }}</a>
    </form>

    <datalist id="products-list">
        @foreach($products as $product)
            <option value="{{ $product->code ? $product->code.' - '.$product->name : $product->name }}"></option>
        @endforeach
    </datalist>
    <datalist id="school-locations-list">
        @foreach($shipLocations as $shipLocation)
            <option value="{{ $shipLocation->school_name }}{{ $shipLocation->city ? ' ('.$shipLocation->city.')' : '' }}"></option>
        @endforeach
    </datalist>

    <script>
        (function () {
            let customers = @json($customers->values());
            let customerById = new Map((customers || []).map((customer) => [String(customer.id), customer]));
            let customerByLabel = new Map();
            let customerByName = new Map();
            let products = @json($products->values());
            let productByLabel = new Map();
            let productByCode = new Map();
            let productByName = new Map();
            let shipLocations = @json($shipLocations->values());
            let shipByLabel = new Map();
            let shipByName = new Map();
            const CUSTOMER_LOOKUP_URL = @json(route('api.customers.index'));
            const SHIP_LOCATION_LOOKUP_URL = @json(route('customer-ship-locations.lookup'));
            const PRODUCT_LOOKUP_URL = @json(route('api.products.index'));
            const LOOKUP_LIMIT = 20;
            const SEARCH_DEBOUNCE_MS = 100;
            const fillFromMasterBtn = document.getElementById('fill-from-master');
            const addLocationBtn = document.getElementById('add-location');
            const customerSearch = document.getElementById('customer-search');
            const customerIdField = document.getElementById('customer-id');
            const customersList = document.getElementById('customers-list');
            const schoolLocationsList = document.getElementById('school-locations-list');
            const locationsTbody = document.querySelector('#locations-table tbody');
            const schoolItemCards = document.getElementById('school-item-cards');
            const schoolItemEmptyHint = document.getElementById('school-item-empty-hint');
            const transactionDateInput = document.getElementById('transaction-date');
            const semesterPeriodInput = document.getElementById('semester-period');
            const form = document.querySelector('form');
            const oldLocations = @json(old('locations', []));
            const oldLocationItems = @json(old('location_items', []));
            let locationItemsByUid = (oldLocationItems && typeof oldLocationItems === 'object')
                ? JSON.parse(JSON.stringify(oldLocationItems))
                : {};
            let customerLookupAbort = null;
            let lastCustomerLookupQuery = '';
            let shipLookupAbort = null;
            let lastShipLookupQuery = '';
            let productLookupAbort = null;
            let lastProductLookupQuery = '';

            const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                ? (fn, wait = SEARCH_DEBOUNCE_MS) => window.PgposAutoSearch.debounce(fn, wait)
                : (fn, wait = SEARCH_DEBOUNCE_MS) => {
                    let timeoutId = null;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => fn(...args), wait);
                    };
                };

            const escapeAttribute = (window.PgposAutoSearch && window.PgposAutoSearch.escapeAttribute)
                ? window.PgposAutoSearch.escapeAttribute
                : (value) => String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');

            function normalizeLookup(value) {
                return String(value || '').trim().toLowerCase();
            }

            function customerLabel(customer) {
                const city = customer.city || '-';
                return `${customer.name} (${city})`;
            }

            function rebuildCustomerIndexes() {
                customerByLabel = new Map();
                customerByName = new Map();
                customers.forEach((customer) => {
                    customerByLabel.set(normalizeLookup(customerLabel(customer)), customer);
                    customerByName.set(normalizeLookup(customer.name), customer);
                });
            }

            function upsertCustomers(rows) {
                const byId = new Map(customers.map((row) => [String(row.id), row]));
                (rows || []).forEach((row) => byId.set(String(row.id), row));
                customers = Array.from(byId.values());
                customerById = new Map(customers.map((customer) => [String(customer.id), customer]));
                rebuildCustomerIndexes();
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
                const normalized = String(label).trim().toLowerCase();
                return customers.find((customer) => customerLabel(customer).toLowerCase().includes(normalized))
                    || customers.find((customer) => String(customer.name || '').toLowerCase().includes(normalized))
                    || null;
            }

            function getCustomerById(id) {
                return customerById.get(String(id)) || null;
            }

            function applyCustomerSelection(customer) {
                if (!customerIdField) {
                    return;
                }
                customerIdField.value = customer ? customer.id : '';
                if (customer && customerSearch) {
                    customerSearch.value = customerLabel(customer);
                }
            }

            function productLabel(product) {
                const code = (product.code || '').trim();
                if (code !== '') {
                    return `${code} - ${product.name}`;
                }
                return `${product.name}`;
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

            function upsertProducts(rows) {
                const byId = new Map(products.map((row) => [String(row.id), row]));
                (rows || []).forEach((row) => byId.set(String(row.id), row));
                products = Array.from(byId.values());
                rebuildProductIndexes();
            }

            function renderProductSuggestions(query) {
                const productsList = document.getElementById('products-list');
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
                const normalized = String(label).trim().toLowerCase();
                return products.find((product) => productLabel(product).toLowerCase().includes(normalized))
                    || products.find((product) => String(product.name || '').toLowerCase().includes(normalized))
                    || null;
            }

            function shipLocationLabel(location) {
                const city = location.city || '';
                return city !== '' ? `${location.school_name} (${city})` : `${location.school_name}`;
            }

            function rebuildShipIndexes() {
                shipByLabel = new Map();
                shipByName = new Map();
                shipLocations.forEach((location) => {
                    shipByLabel.set(normalizeLookup(shipLocationLabel(location)), location);
                    shipByName.set(normalizeLookup(location.school_name), location);
                });
            }

            function upsertShipLocations(rows) {
                const byId = new Map(shipLocations.map((row) => [String(row.id), row]));
                (rows || []).forEach((row) => byId.set(String(row.id), row));
                shipLocations = Array.from(byId.values());
                rebuildShipIndexes();
            }

            function renderShipLocationSuggestions(query) {
                if (!schoolLocationsList) {
                    return;
                }
                const normalized = (query || '').trim().toLowerCase();
                const matches = shipLocations.filter((location) => {
                    const label = shipLocationLabel(location).toLowerCase();
                    const name = (location.school_name || '').toLowerCase();
                    const city = (location.city || '').toLowerCase();
                    return normalized === '' || label.includes(normalized) || name.includes(normalized) || city.includes(normalized);
                }).slice(0, 60);

                schoolLocationsList.innerHTML = matches
                    .map((location) => `<option value="${escapeAttribute(shipLocationLabel(location))}"></option>`)
                    .join('');
            }

            async function fetchShipLocationSuggestions(query) {
                const customerId = Number(customerIdField?.value || 0);
                if (customerId <= 0) {
                    renderShipLocationSuggestions(query);
                    return;
                }
                const normalizedQuery = normalizeLookup(query);
                if (!(window.PgposAutoSearch && window.PgposAutoSearch.canSearchInput({ value: query }))) {
                    lastShipLookupQuery = '';
                    renderShipLocationSuggestions(query);
                    return;
                }
                if (normalizedQuery !== '' && normalizedQuery === lastShipLookupQuery) {
                    renderShipLocationSuggestions(query);
                    return;
                }
                try {
                    if (shipLookupAbort) {
                        shipLookupAbort.abort();
                    }
                    shipLookupAbort = new AbortController();
                    const url = `${SHIP_LOCATION_LOOKUP_URL}?customer_id=${customerId}&search=${encodeURIComponent(query)}&per_page=${LOOKUP_LIMIT}`;
                    const response = await fetch(url, { signal: shipLookupAbort.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) {
                        return;
                    }
                    const payload = await response.json();
                    lastShipLookupQuery = normalizedQuery;
                    upsertShipLocations(payload.data || []);
                    renderShipLocationSuggestions(query);
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                }
            }

            async function fetchShipLocationPage(customerId, page = 1) {
                try {
                    if (shipLookupAbort) {
                        shipLookupAbort.abort();
                    }
                    shipLookupAbort = new AbortController();
                    const url = `${SHIP_LOCATION_LOOKUP_URL}?customer_id=${customerId}&search=&per_page=25&page=${page}`;
                    const response = await fetch(url, { signal: shipLookupAbort.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) {
                        return null;
                    }
                    return await response.json();
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        return null;
                    }
                    return null;
                }
            }

            async function ensureMasterShipLocations(customerId, minimumCount) {
                if (!Number.isFinite(minimumCount) || minimumCount <= 0) {
                    return;
                }
                if (shipLocations.length >= minimumCount) {
                    return;
                }

                const firstPage = await fetchShipLocationPage(customerId, 1);
                if (!firstPage || !Array.isArray(firstPage.data)) {
                    return;
                }
                upsertShipLocations(firstPage.data || []);
                renderShipLocationSuggestions('');

                const lastPage = Number(firstPage.last_page || 1);
                let currentPage = 2;
                while (shipLocations.length < minimumCount && currentPage <= lastPage) {
                    const nextPage = await fetchShipLocationPage(customerId, currentPage);
                    if (!nextPage || !Array.isArray(nextPage.data) || nextPage.data.length === 0) {
                        break;
                    }
                    upsertShipLocations(nextPage.data || []);
                    currentPage += 1;
                }
                renderShipLocationSuggestions('');
            }

            function findShipLocationByLabel(label) {
                if (!label) {
                    return null;
                }
                const normalized = normalizeLookup(label);
                return shipByLabel.get(normalized)
                    || shipByName.get(normalized)
                    || null;
            }

            function findShipLocationLoose(label) {
                if (!label) {
                    return null;
                }
                const normalized = String(label).trim().toLowerCase();
                return shipLocations.find((location) => shipLocationLabel(location).toLowerCase().includes(normalized))
                    || shipLocations.find((location) => String(location.school_name || '').toLowerCase().includes(normalized))
                    || null;
            }

            function applyLocationFromMaster(row, location) {
                row.querySelector('.school-location-id').value = location ? location.id : '';
                if (!location) {
                    return;
                }
                row.querySelector('.school-name').value = location.school_name || '';
                row.querySelector('.school-phone').value = location.recipient_phone || '';
                row.querySelector('.school-city').value = location.city || '';
                row.querySelector('.school-address').value = location.address || '';
            }

            function reindexLocationRows() {
                Array.from(locationsTbody.querySelectorAll('tr')).forEach((row, index) => {
                    row.querySelector('.school-location-id').name = `locations[${index}][customer_ship_location_id]`;
                    row.querySelector('.school-uid').name = `locations[${index}][uid]`;
                    row.querySelector('.school-name').name = `locations[${index}][school_name]`;
                    row.querySelector('.school-phone').name = `locations[${index}][recipient_phone]`;
                    row.querySelector('.school-city').name = `locations[${index}][city]`;
                    row.querySelector('.school-address').name = `locations[${index}][address]`;
                });
            }

            function generateUid() {
                return `loc-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
            }

            function ensureLocationItems(uid) {
                if (!Array.isArray(locationItemsByUid[uid])) {
                    locationItemsByUid[uid] = [];
                }
                return locationItemsByUid[uid];
            }

            function collectLocationItemsFromDom() {
                if (!schoolItemCards) {
                    return;
                }
                const cardEls = Array.from(schoolItemCards.querySelectorAll('.school-item-card'));
                cardEls.forEach((cardEl) => {
                    const uid = String(cardEl.getAttribute('data-location-uid') || '');
                    if (uid === '') {
                        return;
                    }
                    const rows = Array.from(cardEl.querySelectorAll('tbody tr'));
                    locationItemsByUid[uid] = rows.map((row) => ({
                        product_id: row.querySelector('.product-id')?.value || '',
                        product_name: row.querySelector('.product-name')?.value || '',
                        quantity: row.querySelector('.product-qty')?.value || 1,
                        unit: row.querySelector('.product-unit')?.value || '',
                        unit_price: row.querySelector('.product-price')?.value || '',
                        notes: row.querySelector('.product-notes')?.value || '',
                    }));
                });
            }

            function cleanupOrphanLocationItems() {
                const validUids = new Set(
                    Array.from(locationsTbody.querySelectorAll('.school-uid'))
                        .map((input) => String(input.value || '').trim())
                        .filter((value) => value !== '')
                );
                Object.keys(locationItemsByUid).forEach((uid) => {
                    if (!validUids.has(uid)) {
                        delete locationItemsByUid[uid];
                    }
                });
            }

            function reindexSchoolItemRows(cardEl, uid) {
                const rows = Array.from(cardEl.querySelectorAll('tbody tr'));
                rows.forEach((row, index) => {
                    row.querySelector('.product-id').name = `location_items[${uid}][${index}][product_id]`;
                    row.querySelector('.product-name').name = `location_items[${uid}][${index}][product_name]`;
                    row.querySelector('.product-qty').name = `location_items[${uid}][${index}][quantity]`;
                    row.querySelector('.product-unit').name = `location_items[${uid}][${index}][unit]`;
                    row.querySelector('.product-price').name = `location_items[${uid}][${index}][unit_price]`;
                    row.querySelector('.product-notes').name = `location_items[${uid}][${index}][notes]`;
                });
            }

            function addSchoolItemRow(cardEl, uid, initial = {}) {
                const tbody = cardEl.querySelector('tbody');
                if (!tbody) {
                    return;
                }
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="text" list="products-list" class="product-name" value="${initial.product_name || ''}" required>
                        <input type="hidden" class="product-id" value="${initial.product_id || ''}">
                    </td>
                    <td><input type="number" min="1" class="product-qty" value="${initial.quantity || 1}" required style="max-width: 92px;"></td>
                    <td><input type="text" class="product-unit" value="${initial.unit || ''}" style="max-width: 92px;"></td>
                    <td><input type="number" min="0" step="1" class="product-price" value="${initial.unit_price || ''}" style="max-width: 110px;"></td>
                    <td><input type="text" class="product-notes" value="${initial.notes || ''}" style="max-width: 220px;"></td>
                    <td><button type="button" class="btn secondary remove-item">{{ __('txn.remove') }}</button></td>
                `;

                const productNameInput = tr.querySelector('.product-name');
                const productIdInput = tr.querySelector('.product-id');
                const productUnitInput = tr.querySelector('.product-unit');
                const productPriceInput = tr.querySelector('.product-price');
                const applyProduct = (product) => {
                    productIdInput.value = product ? product.id : '';
                    if (!product) {
                        return;
                    }
                    productNameInput.value = productLabel(product);
                    productUnitInput.value = product.unit || productUnitInput.value || '';
                    if (!productPriceInput.value) {
                        productPriceInput.value = Math.round(Number(product.price_general || 0));
                    }
                };
                const onProductInput = debounce(async (event) => {
                    await fetchProductSuggestions(event.currentTarget.value);
                    const product = findProductByLabel(event.currentTarget.value);
                    applyProduct(product);
                });
                productNameInput?.addEventListener('input', onProductInput);
                productNameInput?.addEventListener('focus', (event) => {
                    renderProductSuggestions(event.currentTarget.value);
                });
                productNameInput?.addEventListener('change', (event) => {
                    const product = findProductByLabel(event.currentTarget.value) || findProductLoose(event.currentTarget.value);
                    applyProduct(product);
                });
                tr.querySelector('.remove-item').addEventListener('click', () => {
                    tr.remove();
                    if (tbody.querySelectorAll('tr').length === 0) {
                        addSchoolItemRow(cardEl, uid);
                    }
                    reindexSchoolItemRows(cardEl, uid);
                });
                tbody.appendChild(tr);
                reindexSchoolItemRows(cardEl, uid);
            }

            function renderSchoolItemCards() {
                if (!schoolItemCards) {
                    return;
                }
                collectLocationItemsFromDom();
                cleanupOrphanLocationItems();
                schoolItemCards.innerHTML = '';

                const locationRows = Array.from(locationsTbody.querySelectorAll('tr'));
                let activeCardCount = 0;
                locationRows.forEach((row, locationIndex) => {
                    const uidInput = row.querySelector('.school-uid');
                    const schoolNameInput = row.querySelector('.school-name');
                    const uid = String(uidInput?.value || '').trim();
                    const schoolName = String(schoolNameInput?.value || '').trim();
                    if (uid === '') {
                        return;
                    }

                    const card = document.createElement('div');
                    card.className = 'card school-item-card';
                    card.setAttribute('data-location-uid', uid);
                    card.style.marginTop = '10px';

                    const title = schoolName !== ''
                        ? `{{ __('txn.items') }} - ${schoolName}`
                        : `{{ __('txn.items') }} - {{ __('school_bulk.school_name') }} ${locationIndex + 1}`;
                    const items = ensureLocationItems(uid);

                    card.innerHTML = `
                        <div class="flex" style="justify-content: space-between; align-items: center;">
                            <h4 style="margin: 0;">${escapeAttribute(title)}</h4>
                            <button type="button" class="btn secondary school-add-item" ${schoolName === '' ? 'disabled' : ''}>{{ __('txn.add_row') }}</button>
                        </div>
                        ${schoolName === '' ? `<p class="muted" style="margin-top:8px;">{{ __('school_bulk.fill_school_locations') }}</p>` : ''}
                        <table style="margin-top: 10px; ${schoolName === '' ? 'opacity:0.6;' : ''}">
                            <thead>
                            <tr>
                                <th>{{ __('txn.product') }} *</th>
                                <th>{{ __('txn.qty') }} *</th>
                                <th>{{ __('txn.unit') }}</th>
                                <th>{{ __('txn.price') }}</th>
                                <th>{{ __('txn.notes') }}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    `;

                    schoolItemCards.appendChild(card);
                    const addBtn = card.querySelector('.school-add-item');
                    addBtn?.addEventListener('click', () => addSchoolItemRow(card, uid));

                    if (schoolName !== '') {
                        activeCardCount += 1;
                        if (!Array.isArray(items) || items.length === 0) {
                            addSchoolItemRow(card, uid);
                        } else {
                            items.forEach((item) => addSchoolItemRow(card, uid, item || {}));
                        }
                    }
                });

                if (schoolItemEmptyHint) {
                    schoolItemEmptyHint.style.display = activeCardCount > 0 ? 'none' : 'block';
                }
            }

            function addLocationRow(initial = {}) {
                const index = locationsTbody.querySelectorAll('tr').length;
                const uid = String(initial.uid || generateUid());
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="text" class="school-name" list="school-locations-list" name="locations[${index}][school_name]" value="${initial.school_name || ''}" required>
                        <input type="hidden" class="school-location-id" name="locations[${index}][customer_ship_location_id]" value="${initial.customer_ship_location_id || ''}">
                        <input type="hidden" class="school-uid" name="locations[${index}][uid]" value="${uid}">
                    </td>
                    <td><input type="text" class="school-phone" name="locations[${index}][recipient_phone]" value="${initial.recipient_phone || ''}" style="max-width: 130px;"></td>
                    <td><input type="text" class="school-city" name="locations[${index}][city]" value="${initial.city || ''}" style="max-width: 130px;"></td>
                    <td><input type="text" class="school-address" name="locations[${index}][address]" value="${initial.address || ''}" style="max-width: 320px;"></td>
                    <td><button type="button" class="btn secondary remove-location">{{ __('txn.remove') }}</button></td>
                `;
                const schoolNameInput = tr.querySelector('.school-name');
                const onSchoolInput = debounce(async (event) => {
                    await fetchShipLocationSuggestions(event.currentTarget.value);
                    const location = findShipLocationByLabel(event.currentTarget.value);
                    applyLocationFromMaster(tr, location);
                });
                schoolNameInput?.addEventListener('input', onSchoolInput);
                schoolNameInput?.addEventListener('focus', (event) => {
                    renderShipLocationSuggestions(event.currentTarget.value);
                });
                schoolNameInput?.addEventListener('change', (event) => {
                    const location = findShipLocationByLabel(event.currentTarget.value) || findShipLocationLoose(event.currentTarget.value);
                    applyLocationFromMaster(tr, location);
                    if (location) {
                        schoolNameInput.value = shipLocationLabel(location);
                    }
                    renderSchoolItemCards();
                });
                schoolNameInput?.addEventListener('input', () => renderSchoolItemCards());
                tr.querySelector('.remove-location').addEventListener('click', () => {
                    tr.remove();
                    if (locationsTbody.querySelectorAll('tr').length === 0) {
                        addLocationRow();
                    }
                    reindexLocationRows();
                    renderSchoolItemCards();
                });
                locationsTbody.appendChild(tr);
                reindexLocationRows();
                ensureLocationItems(uid);
                renderSchoolItemCards();
            }

            function syncSemesterFromDate() {
                if (!transactionDateInput || !semesterPeriodInput) {
                    return;
                }
                const deriveSemesterFromDate = (window.PgposAutoSearch && window.PgposAutoSearch.deriveSemesterFromDate)
                    ? window.PgposAutoSearch.deriveSemesterFromDate
                    : () => '';
                const derived = deriveSemesterFromDate(transactionDateInput.value);
                if (derived !== '') {
                    semesterPeriodInput.value = derived;
                }
            }

            addLocationBtn?.addEventListener('click', () => addLocationRow());
            const resetMasterShipLocations = () => {
                shipLocations = [];
                lastShipLookupQuery = '';
                rebuildShipIndexes();
                renderShipLocationSuggestions('');
                Array.from(locationsTbody.querySelectorAll('tr')).forEach((row) => {
                    const hiddenLocationId = row.querySelector('.school-location-id');
                    if (hiddenLocationId) {
                        hiddenLocationId.value = '';
                    }
                });
                renderSchoolItemCards();
            };
            if (customerSearch) {
                const bootCustomer = customerIdField.value
                    ? getCustomerById(customerIdField.value)
                    : findCustomerByLabel(customerSearch.value);
                applyCustomerSelection(bootCustomer);
                const onCustomerInput = debounce(async (event) => {
                    const previousCustomerId = String(customerIdField.value || '');
                    await fetchCustomerSuggestions(event.currentTarget.value);
                    const customer = findCustomerByLabel(event.currentTarget.value);
                    applyCustomerSelection(customer);
                    if (String(customerIdField.value || '') !== previousCustomerId) {
                        resetMasterShipLocations();
                    }
                });
                customerSearch.addEventListener('input', onCustomerInput);
                customerSearch.addEventListener('change', (event) => {
                    const previousCustomerId = String(customerIdField.value || '');
                    const customer = findCustomerByLabel(event.currentTarget.value) || findCustomerLoose(event.currentTarget.value);
                    applyCustomerSelection(customer);
                    if (String(customerIdField.value || '') !== previousCustomerId) {
                        resetMasterShipLocations();
                    }
                });
            }
            fillFromMasterBtn?.addEventListener('click', async () => {
                const customerId = Number(customerIdField?.value || 0);
                if (customerId <= 0) {
                    alert(@json(__('school_bulk.select_customer_first')));
                    return;
                }
                const count = Math.max(1, Number(locationsTbody.querySelectorAll('tr').length || 1));
                await ensureMasterShipLocations(customerId, count);
                if (shipLocations.length === 0) {
                    alert(@json(__('school_bulk.no_master_locations')));
                    return;
                }
                const picked = shipLocations.slice(0, count);
                collectLocationItemsFromDom();
                locationsTbody.innerHTML = '';
                picked.forEach((location) => {
                    addLocationRow({
                        customer_ship_location_id: location.id,
                        school_name: location.school_name || '',
                        recipient_phone: location.recipient_phone || '',
                        city: location.city || '',
                        address: location.address || '',
                    });
                });
                if (picked.length < count) {
                    for (let i = picked.length; i < count; i += 1) {
                        addLocationRow();
                    }
                }
                renderSchoolItemCards();
            });

            form?.addEventListener('submit', (event) => {
                const locationRows = Array.from(locationsTbody.querySelectorAll('tr'));
                if (locationRows.length === 0 || locationRows.some((row) => !(row.querySelector('.school-name')?.value || '').trim())) {
                    event.preventDefault();
                    alert(@json(__('school_bulk.fill_school_locations')));
                    return;
                }
                collectLocationItemsFromDom();
                const hasInvalidItemRows = locationRows.some((row) => {
                    const uid = String(row.querySelector('.school-uid')?.value || '').trim();
                    const schoolName = String(row.querySelector('.school-name')?.value || '').trim();
                    if (uid === '' || schoolName === '') {
                        return true;
                    }
                    const items = Array.isArray(locationItemsByUid[uid]) ? locationItemsByUid[uid] : [];
                    if (items.length === 0) {
                        return true;
                    }
                    return items.some((item) => !String(item.product_name || '').trim());
                });
                if (hasInvalidItemRows) {
                    event.preventDefault();
                    alert(@json(__('school_bulk.fill_items')));
                }
            });

            if (Array.isArray(oldLocations) && oldLocations.length > 0) {
                oldLocations.forEach((row) => addLocationRow(row || {}));
            } else {
                addLocationRow();
            }
            renderSchoolItemCards();
            rebuildCustomerIndexes();
            rebuildShipIndexes();
            rebuildProductIndexes();
            renderCustomerSuggestions('');
            renderShipLocationSuggestions('');
            renderProductSuggestions('');
            syncSemesterFromDate();
            transactionDateInput?.addEventListener('change', syncSemesterFromDate);
        })();
    </script>
@endsection
