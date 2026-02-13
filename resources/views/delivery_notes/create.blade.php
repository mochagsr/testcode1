@extends('layouts.app')

@section('title', __('txn.create_delivery_note_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('txn.create_delivery_note_title') }}</h1>

    <style>
        #items-table input[type=number].qty-input::-webkit-outer-spin-button,
        #items-table input[type=number].qty-input::-webkit-inner-spin-button,
        #items-table input[type=number].price-input::-webkit-outer-spin-button,
        #items-table input[type=number].price-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #items-table input[type=number].qty-input,
        #items-table input[type=number].price-input {
            -moz-appearance: textfield;
            appearance: textfield;
        }
    </style>

    <form method="post" action="{{ route('delivery-notes.store') }}">
        @csrf

        <div class="card">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('txn.delivery_header') }}</h3>
                <p class="form-section-note">{{ __('txn.delivery_summary_note') }}</p>
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
                                : old('recipient_name', '');
                        @endphp
                        <input type="text"
                               id="customer-search"
                               name="recipient_name"
                               list="customers-list"
                               value="{{ $oldCustomerLabel }}"
                               placeholder="{{ __('txn.no_linked_customer') }}"
                               required>
                        <input type="hidden" id="customer_id" name="customer_id" value="{{ $oldCustomerId }}">
                        <datalist id="customers-list">
                            @foreach($customers as $customer)
                                <option value="{{ $customer->name }} ({{ $customer->city ?: '-' }})"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.recipient_phone') }}</label>
                        <input id="recipient_phone" type="text" name="recipient_phone" value="{{ old('recipient_phone') }}">
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.city') }}</label>
                        <input id="city" type="text" name="city" value="{{ old('city') }}">
                    </div>
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
                <button type="button" id="add-item" class="btn secondary">{{ __('txn.add_row') }}</button>
            </div>
            <table id="items-table" style="margin-top: 12px;">
                <thead>
                <tr>
                    <th style="width: 36%">{{ __('txn.product') }} *</th>
                    <th style="width: 10%">{{ __('txn.qty') }} *</th>
                    <th style="width: 7%">{{ __('txn.unit') }}</th>
                    <th style="width: 14%">{{ __('txn.price') }} ({{ __('txn.optional') }})</th>
                    <th>{{ __('txn.notes') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <button class="btn" type="submit">{{ __('txn.save_delivery_note') }}</button>
        <a class="btn secondary" href="{{ route('delivery-notes.index') }}">{{ __('txn.cancel') }}</a>
    </form>

    <script>
        let customers = @json($customers->values());
        let products = @json($products);
        const CUSTOMER_LOOKUP_URL = @json(route('api.customers.index'));
        const PRODUCT_LOOKUP_URL = @json(route('api.products.index'));
        const LOOKUP_LIMIT = 20;
        const tbody = document.querySelector('#items-table tbody');
        const productsList = document.getElementById('products-list');
        const customersList = document.getElementById('customers-list');
        const addBtn = document.getElementById('add-item');
        const customerSearch = document.getElementById('customer-search');
        const customerIdField = document.getElementById('customer_id');
        const SEARCH_DEBOUNCE_MS = 100;
        let customerLookupAbort = null;
        let productLookupAbort = null;

        function debounce(fn, wait = SEARCH_DEBOUNCE_MS) {
            let timeoutId = null;
            return (...args) => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => fn(...args), wait);
            };
        }

        function upsertCustomers(rows) {
            const byId = new Map(customers.map((row) => [String(row.id), row]));
            (rows || []).forEach((row) => byId.set(String(row.id), row));
            customers = Array.from(byId.values());
        }

        function upsertProducts(rows) {
            const byId = new Map(products.map((row) => [String(row.id), row]));
            (rows || []).forEach((row) => byId.set(String(row.id), row));
            products = Array.from(byId.values());
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
            if (!(window.PgposAutoSearch && window.PgposAutoSearch.canSearchInput({ value: query }))) {
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
            const normalized = label.trim().toLowerCase();
            return customers.find((customer) => customerLabel(customer).toLowerCase() === normalized)
                || customers.find((customer) => customer.name.toLowerCase() === normalized)
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

        function productLabel(product) {
            const code = (product.code || '').trim();
            if (code !== '') {
                return `${code} - ${product.name}`;
            }
            return `${product.name}`;
        }

        function escapeAttribute(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

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

        function findProductByLabel(label) {
            if (!label) {
                return null;
            }
            const normalized = label.trim().toLowerCase();
            return products.find((product) => productLabel(product).toLowerCase() === normalized)
                || products.find((product) => product.code.toLowerCase() === normalized)
                || products.find((product) => product.name.toLowerCase() === normalized)
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
                <td><input name="items[${index}][quantity]" type="number" min="1" value="1" class="qty-input" required style="max-width: 104px;"></td>
                <td><input name="items[${index}][unit]" class="unit" style="max-width: 72px;"></td>
                <td><input name="items[${index}][unit_price]" type="number" min="0" step="1" class="price-input" style="max-width: 104px;"></td>
                <td><input name="items[${index}][notes]"></td>
                <td><button type="button" class="btn secondary remove">{{ __('txn.remove') }}</button></td>
            `;
            tbody.appendChild(tr);

            const onProductInput = debounce(async (event) => {
                await fetchProductSuggestions(event.currentTarget.value);
                renderProductSuggestions(event.currentTarget.value);
                const product = findProductByLabel(event.currentTarget.value);
                tr.querySelector('.product-id').value = product ? product.id : '';
                if (product) {
                    tr.querySelector('.unit').value = product.unit || '';
                    tr.querySelector('[name="items[' + index + '][unit_price]"]').value = product.price_general || '';
                }
            });
            tr.querySelector('.product-search').addEventListener('input', onProductInput);
            tr.querySelector('.product-search').addEventListener('focus', (event) => {
                renderProductSuggestions(event.currentTarget.value);
            });
            tr.querySelector('.product-search').addEventListener('change', (event) => {
                const product = findProductByLabel(event.currentTarget.value) || findProductLoose(event.currentTarget.value);
                tr.querySelector('.product-id').value = product ? product.id : '';
                if (!product) {
                    return;
                }
                tr.querySelector('.product-search').value = productLabel(product);
                tr.querySelector('.unit').value = product.unit || '';
                tr.querySelector('[name="items[' + index + '][unit_price]"]').value = product.price_general || '';
            });
            tr.querySelector('.remove').addEventListener('click', () => tr.remove());
        }

        if (customerSearch) {
            const bootCustomer = customerIdField.value
                ? customers.find(c => String(c.id) === String(customerIdField.value))
                : findCustomerByLabel(customerSearch.value);
            if (bootCustomer) {
                customerIdField.value = bootCustomer.id;
            }
            const onCustomerInput = debounce(async (event) => {
                await fetchCustomerSuggestions(event.currentTarget.value);
                const customer = findCustomerByLabel(event.currentTarget.value);
                customerIdField.value = customer ? customer.id : '';
                if (customer) {
                    document.getElementById('recipient_phone').value = customer.phone || '';
                    document.getElementById('city').value = customer.city || '';
                    document.getElementById('address').value = customer.address || '';
                }
            });
            customerSearch.addEventListener('input', onCustomerInput);
            customerSearch.addEventListener('change', (event) => {
                const customer = findCustomerByLabel(event.currentTarget.value) || findCustomerLoose(event.currentTarget.value);
                customerIdField.value = customer ? customer.id : '';
                if (customer) {
                    customerSearch.value = customerLabel(customer);
                    document.getElementById('recipient_phone').value = customer.phone || '';
                    document.getElementById('city').value = customer.city || '';
                    document.getElementById('address').value = customer.address || '';
                }
            });
        }

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
