@extends('layouts.app')

@section('title', __('txn.create_sales_return_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('txn.create_sales_return_title') }}</h1>

    <form method="post" action="{{ route('sales-returns.store') }}">
        @csrf

        <div class="card">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('txn.return_header') }}</h3>
                <p class="form-section-note">{{ __('txn.return_summary_note') }}</p>
                <div class="row">
                    <div class="col-4">
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
                    <div class="col-4">
                        <label>{{ __('txn.return_date') }} <span class="label-required">*</span></label>
                        <input type="date" name="return_date" value="{{ old('return_date', now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.semester_period') }}</label>
                        <select name="semester_period">
                            @foreach($semesterOptions as $semester)
                                <option value="{{ $semester }}" @selected(old('semester_period', $defaultSemesterPeriod) === $semester)>{{ $semester }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label>{{ __('txn.reason') }}</label>
                        <textarea name="reason" rows="2">{{ old('reason') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex" style="justify-content: space-between;">
                <h3 style="margin: 0;">{{ __('txn.return_items') }}</h3>
                <button type="button" id="add-item" class="btn secondary">{{ __('txn.add_row') }}</button>
            </div>
            <table id="items-table" style="margin-top: 12px;">
                <thead>
                <tr>
                    <th style="width: 36%">{{ __('txn.product') }} *</th>
                    <th style="width: 10%">{{ __('txn.current_stock') }}</th>
                    <th style="width: 8%">{{ __('txn.qty') }} *</th>
                    <th style="width: 18%">{{ __('txn.price') }}</th>
                    <th style="width: 20%">{{ __('txn.subtotal') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
            <div style="margin-top: 10px; text-align: right;">
                <strong>{{ __('txn.total_return') }}: Rp <span id="grand-total">0</span></strong>
            </div>
        </div>

        <button class="btn" type="submit">{{ __('txn.save_return') }}</button>
        <a class="btn secondary" href="{{ route('sales-returns.index') }}">{{ __('txn.cancel') }}</a>
    </form>

    <script>
        const products = @json($products);
        const customers = @json($customers);
        const tbody = document.querySelector('#items-table tbody');
        const productsList = document.getElementById('products-list');
        const grandTotal = document.getElementById('grand-total');
        const addBtn = document.getElementById('add-item');
        const form = document.querySelector('form');
        const customerSearch = document.getElementById('customer-search');
        const customerIdField = document.getElementById('customer-id');
        let currentCustomer = null;

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
            customerIdField.value = customer ? customer.id : '';
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

        function recalc() {
            let total = 0;
            document.querySelectorAll('#items-table tbody tr').forEach((row) => {
                const qty = parseInt(row.querySelector('.qty').value || 0, 10);
                const productId = row.querySelector('.product-id')?.value;
                const product = products.find(p => String(p.id) === String(productId));
                const key = getPriceKeyForCustomer();
                let price = 0;
                if (product) {
                    if (key === 'price_agent') {
                        price = Math.round(Number(product.price_agent ?? product.price_general ?? 0));
                    } else if (key === 'price_sales') {
                        price = Math.round(Number(product.price_sales ?? product.price_general ?? 0));
                    } else {
                        price = Math.round(Number(product.price_general ?? 0));
                    }
                }
                const line = Math.max(0, qty * price);
                row.querySelector('.line-price').textContent = product ? new Intl.NumberFormat('id-ID').format(price) : '-';
                row.querySelector('.line-total').textContent = new Intl.NumberFormat('id-ID').format(Math.round(line));
                total += line;
            });
            grandTotal.textContent = new Intl.NumberFormat('id-ID').format(Math.round(total));
        }

        function updateRowMeta(row, product) {
            row.querySelector('.stock').textContent = product ? product.stock : '-';
            recalc();
        }

        function addRow() {
            const index = tbody.children.length;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="text" class="product-search" list="products-list" placeholder="{{ __('txn.select_product') }}" required>
                    <input type="hidden" name="items[${index}][product_id]" class="product-id">
                </td>
                <td class="stock">-</td>
                <td><input class="qty" type="number" min="1" name="items[${index}][quantity]" value="1" required style="max-width: 88px;"></td>
                <td style="white-space: nowrap; text-align: right;">Rp <span class="line-price">-</span></td>
                <td style="white-space: nowrap; text-align: right;">Rp <span class="line-total">0</span></td>
                <td><button type="button" class="btn secondary remove">{{ __('txn.remove') }}</button></td>
            `;
            tbody.appendChild(tr);

            tr.querySelector('.product-search').addEventListener('input', (event) => {
                renderProductSuggestions(event.currentTarget.value);
                const product = findProductByLabel(event.currentTarget.value);
                tr.querySelector('.product-id').value = product ? product.id : '';
                updateRowMeta(tr, product);
            });
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
            tr.querySelectorAll('.qty').forEach((el) => el.addEventListener('input', recalc));
            tr.querySelector('.remove').addEventListener('click', () => {
                tr.remove();
                recalc();
            });
        }

        addBtn.addEventListener('click', addRow);
        renderProductSuggestions('');
        if (customerSearch) {
            const bootCustomer = customerIdField.value
                ? customers.find(c => String(c.id) === String(customerIdField.value))
                : findCustomerByLabel(customerSearch.value);
            setCurrentCustomer(bootCustomer);
            customerSearch.addEventListener('input', (event) => {
                const customer = findCustomerByLabel(event.currentTarget.value);
                setCurrentCustomer(customer);
                recalc();
            });
            customerSearch.addEventListener('change', (event) => {
                const customer = findCustomerByLabel(event.currentTarget.value) || findCustomerLoose(event.currentTarget.value);
                setCurrentCustomer(customer);
                if (customer) {
                    customerSearch.value = customerLabel(customer);
                }
                recalc();
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
    </script>

    <datalist id="products-list">
        @foreach($products as $product)
            <option value="{{ $product->code ? $product->code.' - '.$product->name : $product->name }}"></option>
        @endforeach
    </datalist>
@endsection

