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
        const CUSTOMER_LOOKUP_URL = @json(route('api.customers.index'));
        const PRODUCT_LOOKUP_URL = @json(route('api.products.index'));
        const LOOKUP_LIMIT = 20;
        const selectProductLabel = @json(__('txn.select_product'));
        const tbody = document.querySelector('#items-table tbody');
        const productsList = document.getElementById('products-list');
        const customersList = document.getElementById('customers-list');
        const customerSearch = document.getElementById('customer-search');
        const customerIdField = document.getElementById('customer-id');
        const grandTotal = document.getElementById('grand-total');
        const addBtn = document.getElementById('add-item');
        const invoiceDateInput = document.getElementById('invoice-date');
        const semesterPeriodSelect = document.getElementById('semester-period');
        const form = document.querySelector('form');
        const SEARCH_DEBOUNCE_MS = 100;
        let currentCustomer = null;
        let customerLookupAbort = null;
        let productLookupAbort = null;

        function upsertCustomers(rows) {
            const byId = new Map(customers.map((row) => [String(row.id), row]));
            (rows || []).forEach((row) => {
                byId.set(String(row.id), row);
            });
            customers = Array.from(byId.values());
        }

        function upsertProducts(rows) {
            const byId = new Map(products.map((row) => [String(row.id), row]));
            (rows || []).forEach((row) => {
                byId.set(String(row.id), row);
            });
            products = Array.from(byId.values());
        }

        function debounce(fn, wait = SEARCH_DEBOUNCE_MS) {
            let timeoutId = null;
            return (...args) => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => fn(...args), wait);
            };
        }

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

        function setCurrentCustomer(customer) {
            currentCustomer = customer;
            if (customer) {
                customerIdField.value = customer.id;
            } else {
                customerIdField.value = '';
            }
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
                const product = products.find(p => String(p.id) === String(productId));
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

        function addRow() {
            const index = tbody.children.length;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="text" class="product-search" list="products-list" placeholder="${selectProductLabel}" required>
                    <input type="hidden" name="items[${index}][product_id]" class="product-id">
                </td>
                <td class="stock">-</td>
                <td><input class="qty" type="number" min="1" name="items[${index}][quantity]" value="1" required style="max-width: 88px;"></td>
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
                renderProductSuggestions(event.currentTarget.value);
                const product = findProductByLabel(event.currentTarget.value);
                tr.querySelector('.product-id').value = product ? product.id : '';
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
            if (!invoiceDateInput || !semesterPeriodSelect) {
                return;
            }

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

        addBtn.addEventListener('click', addRow);
        renderCustomerSuggestions('');
        renderProductSuggestions('');
        if (customerSearch) {
            const bootCustomer = customerIdField.value
                ? customers.find(c => String(c.id) === String(customerIdField.value))
                : findCustomerByLabel(customerSearch.value);
            setCurrentCustomer(bootCustomer);
            const onCustomerInput = debounce(async (event) => {
                await fetchCustomerSuggestions(event.currentTarget.value);
                const customer = findCustomerByLabel(event.currentTarget.value);
                setCurrentCustomer(customer);
                applyCustomerPricing();
            });
            customerSearch.addEventListener('input', onCustomerInput);
            customerSearch.addEventListener('change', (event) => {
                const customer = findCustomerByLabel(event.currentTarget.value) || findCustomerLoose(event.currentTarget.value);
                setCurrentCustomer(customer);
                if (customer) {
                    customerSearch.value = customerLabel(customer);
                }
                applyCustomerPricing();
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
        addRow();
        autoSelectSemesterByDate();
        invoiceDateInput?.addEventListener('change', autoSelectSemesterByDate);
    </script>

    <datalist id="products-list">
        @foreach($products as $product)
            <option value="{{ $product->code ? $product->code.' - '.$product->name : $product->name }}"></option>
        @endforeach
    </datalist>
@endsection
