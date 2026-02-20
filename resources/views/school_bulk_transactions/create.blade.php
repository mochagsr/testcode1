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
                    <select id="customer-id" name="customer_id" required>
                        <option value="">{{ __('school_bulk.select_customer') }}</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_id') === (int) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
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
                    <input type="number" id="school-count" min="1" max="100" value="1" style="max-width: 110px;">
                    <button type="button" class="btn secondary" id="generate-locations">{{ __('school_bulk.generate_school_rows') }}</button>
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
            <div class="flex" style="justify-content: space-between;">
                <h3 style="margin: 0;">{{ __('txn.items') }}</h3>
                <button type="button" class="btn secondary" id="add-item">{{ __('txn.add_row') }}</button>
            </div>
            <p class="muted" style="margin-top: 8px;">{{ __('school_bulk.bulk_items_note') }}</p>
            <table id="items-table" style="margin-top: 10px;">
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
            let products = @json($products->values());
            let productByLabel = new Map();
            let productByCode = new Map();
            let productByName = new Map();
            let shipLocations = @json($shipLocations->values());
            let shipByLabel = new Map();
            let shipByName = new Map();
            const SHIP_LOCATION_LOOKUP_URL = @json(route('customer-ship-locations.lookup'));
            const PRODUCT_LOOKUP_URL = @json(route('api.products.index'));
            const LOOKUP_LIMIT = 20;
            const SEARCH_DEBOUNCE_MS = 100;
            const schoolCountInput = document.getElementById('school-count');
            const generateLocationsBtn = document.getElementById('generate-locations');
            const addLocationBtn = document.getElementById('add-location');
            const addItemBtn = document.getElementById('add-item');
            const customerSelect = document.getElementById('customer-id');
            const schoolLocationsList = document.getElementById('school-locations-list');
            const locationsTbody = document.querySelector('#locations-table tbody');
            const itemsTbody = document.querySelector('#items-table tbody');
            const transactionDateInput = document.getElementById('transaction-date');
            const semesterPeriodInput = document.getElementById('semester-period');
            const form = document.querySelector('form');
            const oldLocations = @json(old('locations', []));
            const oldItems = @json(old('items', []));
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
                const customerId = Number(customerSelect?.value || 0);
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
                    row.querySelector('.school-name').name = `locations[${index}][school_name]`;
                    row.querySelector('.school-phone').name = `locations[${index}][recipient_phone]`;
                    row.querySelector('.school-city').name = `locations[${index}][city]`;
                    row.querySelector('.school-address').name = `locations[${index}][address]`;
                });
            }

            function reindexItemRows() {
                Array.from(itemsTbody.querySelectorAll('tr')).forEach((row, index) => {
                    row.querySelector('.product-id').name = `items[${index}][product_id]`;
                    row.querySelector('.product-name').name = `items[${index}][product_name]`;
                    row.querySelector('.product-qty').name = `items[${index}][quantity]`;
                    row.querySelector('.product-unit').name = `items[${index}][unit]`;
                    row.querySelector('.product-price').name = `items[${index}][unit_price]`;
                    row.querySelector('.product-notes').name = `items[${index}][notes]`;
                });
            }

            function addLocationRow(initial = {}) {
                const index = locationsTbody.querySelectorAll('tr').length;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="text" class="school-name" list="school-locations-list" name="locations[${index}][school_name]" value="${initial.school_name || ''}" required>
                        <input type="hidden" class="school-location-id" name="locations[${index}][customer_ship_location_id]" value="${initial.customer_ship_location_id || ''}">
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
                });
                tr.querySelector('.remove-location').addEventListener('click', () => {
                    tr.remove();
                    if (locationsTbody.querySelectorAll('tr').length === 0) {
                        addLocationRow();
                    }
                    reindexLocationRows();
                });
                locationsTbody.appendChild(tr);
                reindexLocationRows();
            }

            function addItemRow(initial = {}) {
                const index = itemsTbody.querySelectorAll('tr').length;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="text" list="products-list" class="product-name" name="items[${index}][product_name]" value="${initial.product_name || ''}" required>
                        <input type="hidden" class="product-id" name="items[${index}][product_id]" value="${initial.product_id || ''}">
                    </td>
                    <td><input type="number" min="1" class="product-qty" name="items[${index}][quantity]" value="${initial.quantity || 1}" required style="max-width: 92px;"></td>
                    <td><input type="text" class="product-unit" name="items[${index}][unit]" value="${initial.unit || ''}" style="max-width: 92px;"></td>
                    <td><input type="number" min="0" step="1" class="product-price" name="items[${index}][unit_price]" value="${initial.unit_price || ''}" style="max-width: 110px;"></td>
                    <td><input type="text" class="product-notes" name="items[${index}][notes]" value="${initial.notes || ''}" style="max-width: 220px;"></td>
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
                    if (itemsTbody.querySelectorAll('tr').length === 0) {
                        addItemRow();
                    }
                    reindexItemRows();
                });
                itemsTbody.appendChild(tr);
                reindexItemRows();
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

            generateLocationsBtn?.addEventListener('click', () => {
                const count = Math.max(1, Math.min(100, Number(schoolCountInput?.value || 1)));
                locationsTbody.innerHTML = '';
                for (let i = 0; i < count; i += 1) {
                    addLocationRow();
                }
            });
            addLocationBtn?.addEventListener('click', () => addLocationRow());
            addItemBtn?.addEventListener('click', () => addItemRow());
            customerSelect?.addEventListener('change', () => {
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
            });

            form?.addEventListener('submit', (event) => {
                const locationRows = Array.from(locationsTbody.querySelectorAll('tr'));
                if (locationRows.length === 0 || locationRows.some((row) => !(row.querySelector('.school-name')?.value || '').trim())) {
                    event.preventDefault();
                    alert(@json(__('school_bulk.fill_school_locations')));
                    return;
                }
                const itemRows = Array.from(itemsTbody.querySelectorAll('tr'));
                if (itemRows.length === 0 || itemRows.some((row) => !(row.querySelector('.product-name')?.value || '').trim())) {
                    event.preventDefault();
                    alert(@json(__('school_bulk.fill_items')));
                }
            });

            if (Array.isArray(oldLocations) && oldLocations.length > 0) {
                oldLocations.forEach((row) => addLocationRow(row || {}));
            } else {
                addLocationRow();
            }
            if (Array.isArray(oldItems) && oldItems.length > 0) {
                oldItems.forEach((row) => addItemRow(row || {}));
            } else {
                addItemRow();
            }
            rebuildShipIndexes();
            rebuildProductIndexes();
            renderShipLocationSuggestions('');
            renderProductSuggestions('');
            syncSemesterFromDate();
            transactionDateInput?.addEventListener('change', syncSemesterFromDate);
        })();
    </script>
@endsection
