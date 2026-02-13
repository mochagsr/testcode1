@extends('layouts.app')

@section('title', __('txn.create_order_note_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('txn.create_order_note_title') }}</h1>

    <style>
        #items-table input[type=number].qty-input::-webkit-outer-spin-button,
        #items-table input[type=number].qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #items-table input[type=number].qty-input {
            -moz-appearance: textfield;
            appearance: textfield;
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
                        <label>{{ __('txn.customer') }} {{ __('txn.name') }} <span class="label-required">*</span></label>
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
                               placeholder="{{ __('txn.manual_customer') }}"
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
                <button type="button" id="add-item" class="btn secondary">{{ __('txn.add_row') }}</button>
            </div>
            <table id="items-table" style="margin-top: 12px;">
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
        const productsList = document.getElementById('products-list');
        const customersList = document.getElementById('customers-list');
        const addBtn = document.getElementById('add-item');
        const customerSearch = document.getElementById('customer-search');
        const customerIdField = document.getElementById('customer_id');
        const customerPhoneField = document.getElementById('customer_phone');
        const cityField = document.getElementById('city');
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
            return customers.find((customer) => customerLabel(customer).toLowerCase().includes(normalized))
                || customers.find((customer) => customer.name.toLowerCase().includes(normalized))
                || null;
        }

        function getCustomerById(id) {
            return customerById.get(String(id)) || null;
        }

        function applyCustomerFields(customer) {
            customerIdField.value = customer ? customer.id : '';
            if (!customer) {
                return;
            }
            if (customerPhoneField) customerPhoneField.value = customer.phone || '';
            if (cityField) cityField.value = customer.city || '';
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

        function addRow() {
            const index = tbody.children.length;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="text" class="product-search" name="items[${index}][product_name]" list="products-list" placeholder="{{ __('txn.manual_item') }}" required>
                    <input type="hidden" name="items[${index}][product_id]" class="product-id">
                </td>
                <td><input name="items[${index}][quantity]" type="number" min="1" value="1" class="qty-input" required style="max-width: 88px;"></td>
                <td><input name="items[${index}][notes]"></td>
                <td><button type="button" class="btn secondary remove">{{ __('txn.remove') }}</button></td>
            `;
            tbody.appendChild(tr);

            const onProductInput = debounce(async (event) => {
                await fetchProductSuggestions(event.currentTarget.value);
                const product = findProductByLabel(event.currentTarget.value);
                tr.querySelector('.product-id').value = product ? product.id : '';
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
                }
            });
            tr.querySelector('.remove').addEventListener('click', () => tr.remove());
        }

        if (customerSearch) {
            const bootCustomer = customerIdField.value
                ? getCustomerById(customerIdField.value)
                : findCustomerByLabel(customerSearch.value);
            applyCustomerFields(bootCustomer);
            const onCustomerInput = debounce(async (event) => {
                await fetchCustomerSuggestions(event.currentTarget.value);
                const customer = findCustomerByLabel(event.currentTarget.value);
                applyCustomerFields(customer);
            });
            customerSearch.addEventListener('input', onCustomerInput);
            customerSearch.addEventListener('change', (event) => {
                const customer = findCustomerByLabel(event.currentTarget.value) || findCustomerLoose(event.currentTarget.value);
                applyCustomerFields(customer);
                if (customer) {
                    customerSearch.value = customerLabel(customer);
                }
            });
        }

        rebuildCustomerIndexes();
        rebuildProductIndexes();
        addBtn.addEventListener('click', addRow);
        renderCustomerSuggestions('');
        renderProductSuggestions('');
        addRow();
    </script>

    <datalist id="products-list">
        @foreach($products as $product)
            <option value="{{ $product->code ? $product->code.' - '.$product->name : $product->name }}"></option>
        @endforeach
    </datalist>
@endsection
