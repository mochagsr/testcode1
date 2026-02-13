@extends('layouts.app')

@section('title', __('txn.return').' '.__('txn.detail').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.return') }} {{ $salesReturn->return_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('sales-returns.index') }}">{{ __('txn.back') }}</a>
            @if((auth()->user()?->role ?? '') === 'admin')
                <a class="btn secondary" href="#admin-edit-transaction">{{ __('txn.edit_transaction') }}</a>
            @endif
            <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('sales-returns.print', $salesReturn) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('sales-returns.export.pdf', $salesReturn) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('sales-returns.export.excel', $salesReturn) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.return_summary') }}</h3>
            <p class="form-section-note">{{ __('txn.return_summary_note') }}</p>
            <div class="row">
                <div class="col-4"><strong>{{ __('txn.customer') }}</strong><div>{{ $salesReturn->customer->name }}</div></div>
                <div class="col-4"><strong>{{ __('txn.city') }}</strong><div>{{ $salesReturn->customer->city }}</div></div>
                <div class="col-4"><strong>{{ __('txn.phone') }}</strong><div>{{ $salesReturn->customer->phone ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.return_date') }}</strong><div>{{ $salesReturn->return_date->format('d-m-Y') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.semester_period') }}</strong><div>{{ $salesReturn->semester_period ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.status') }}</strong><div>{{ $salesReturn->is_canceled ? __('txn.status_canceled') : __('txn.status_active') }}</div></div>
                <div class="col-4">
                    <strong>{{ __('receivable.customer_semester_status') }}</strong>
                    <div>
                        @if((bool) ($customerSemesterLockState['locked'] ?? false))
                            <span class="badge danger">
                                @if((bool) ($customerSemesterLockState['auto'] ?? false))
                                    {{ __('receivable.customer_semester_locked_auto') }}
                                @elseif((bool) ($customerSemesterLockState['manual'] ?? false))
                                    {{ __('receivable.customer_semester_locked_manual') }}
                                @else
                                    {{ __('receivable.customer_semester_closed') }}
                                @endif
                            </span>
                        @else
                            <span class="badge success">{{ __('receivable.customer_semester_unlocked') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-4"><strong>{{ __('txn.total') }}</strong><div>Rp {{ number_format((int) round($salesReturn->total), 0, ',', '.') }}</div></div>
                <div class="col-12"><strong>{{ __('txn.reason') }}</strong><div>{{ $salesReturn->reason ?: '-' }}</div></div>
                @if($salesReturn->is_canceled)
                    <div class="col-12"><strong>{{ __('txn.cancel_reason') }}</strong><div>{{ $salesReturn->cancel_reason ?: '-' }}</div></div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.return_items') }}</h3>
            <p class="form-section-note">{{ __('txn.return_items_note') }}</p>
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.product') }}</th>
                    <th>{{ __('txn.qty') }}</th>
                    <th>{{ __('txn.price') }}</th>
                    <th>{{ __('txn.line_total') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($salesReturn->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ (int) round($item->quantity) }}</td>
                        <td>Rp {{ number_format((int) round($item->unit_price), 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((int) round($item->line_total), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if((auth()->user()?->role ?? '') === 'admin')
        <div id="admin-edit-transaction" class="card">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('txn.admin_actions') }}</h3>
                <p class="form-section-note">{{ __('txn.edit_transaction') }}</p>
                <form id="admin-return-edit-form" method="post" action="{{ route('sales-returns.admin-update', $salesReturn) }}" class="row" style="margin-bottom: 12px;">
                    @csrf
                    @method('PUT')
                    <div class="col-4">
                        <label>{{ __('txn.return_date') }}</label>
                        <input type="date" name="return_date" value="{{ old('return_date', optional($salesReturn->return_date)->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.semester_period') }}</label>
                        <select name="semester_period">
                            @foreach($semesterOptions as $semester)
                                <option value="{{ $semester }}" @selected(old('semester_period', $salesReturn->semester_period) === $semester)>{{ $semester }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="flex" style="justify-content: space-between; margin-top: 6px; margin-bottom: 8px;">
                            <strong>{{ __('txn.items') }}</strong>
                            <button type="button" id="admin-add-return-item" class="btn secondary">{{ __('txn.add_row') }}</button>
                        </div>
                        <table id="admin-return-items-table">
                            <thead>
                            <tr>
                                <th>{{ __('txn.product') }}</th>
                                <th>{{ __('txn.qty') }}</th>
                                <th>{{ __('txn.price') }}</th>
                                <th>{{ __('txn.subtotal') }}</th>
                                <th>{{ __('txn.action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($salesReturn->items as $index => $item)
                                <tr>
                                    <td>
                                        @php
                                            $itemProduct = $products->firstWhere('id', (int) $item->product_id);
                                            $itemCode = trim((string) ($itemProduct?->code ?? ''));
                                            $itemName = trim((string) ($itemProduct?->name ?? $item->product_name));
                                            $itemLabel = $itemCode !== '' ? $itemCode.' - '.$itemName : $itemName;
                                        @endphp
                                        <input type="text" class="admin-return-item-product-search" list="admin-return-products-list" name="items[{{ $index }}][product_name]" value="{{ $itemLabel }}" required style="max-width: 280px;">
                                        <input type="hidden" class="admin-return-item-product" name="items[{{ $index }}][product_id]" value="{{ (int) $item->product_id }}">
                                    </td>
                                    <td><input type="number" min="1" class="admin-return-item-qty" name="items[{{ $index }}][quantity]" value="{{ (int) round($item->quantity) }}" style="max-width: 90px;" required></td>
                                    <td><input type="number" min="0" step="1" class="admin-return-item-price" name="items[{{ $index }}][unit_price]" value="{{ (int) round($item->unit_price) }}" style="max-width: 120px;" required></td>
                                    <td>Rp <span class="admin-return-item-line-total">{{ number_format((int) round($item->line_total), 0, ',', '.') }}</span></td>
                                    <td><button type="button" class="btn secondary admin-remove-return-item">{{ __('txn.remove') }}</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <datalist id="admin-return-products-list">
                            @foreach($products as $productOption)
                                <option value="{{ $productOption->code ? $productOption->code.' - '.$productOption->name : $productOption->name }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-12">
                        <label>{{ __('txn.reason') }}</label>
                        <textarea name="reason" rows="2">{{ old('reason', $salesReturn->reason) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn" type="submit">{{ __('txn.save_changes') }}</button>
                    </div>
                </form>
                @if(!$salesReturn->is_canceled)
                    <form method="post" action="{{ route('sales-returns.cancel', $salesReturn) }}" class="row">
                        @csrf
                        <div class="col-12">
                            <label>{{ __('txn.cancel_reason') }}</label>
                            <textarea name="cancel_reason" rows="2" required></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn secondary" type="submit">{{ __('txn.cancel_transaction') }}</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        <script>
            (function () {
                const table = document.getElementById('admin-return-items-table');
                const tbody = table?.querySelector('tbody');
                const addButton = document.getElementById('admin-add-return-item');
                if (!table || !tbody || !addButton) {
                    return;
                }

                let products = @json($products->map(fn ($product) => [
                    'id' => (int) $product->id,
                    'code' => (string) ($product->code ?? ''),
                    'name' => (string) $product->name,
                    'price_general' => (int) round((float) ($product->price_general ?? 0)),
                ])->values()->all());
                const PRODUCT_LOOKUP_URL = @json(route('api.products.index'));
                const LOOKUP_LIMIT = 20;
                const SEARCH_DEBOUNCE_MS = 100;
                let productLookupAbort = null;

                function numberFormat(value) {
                    return new Intl.NumberFormat('id-ID').format(Math.round(Number(value || 0)));
                }

                function debounce(fn, wait = SEARCH_DEBOUNCE_MS) {
                    let timeoutId = null;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => fn(...args), wait);
                    };
                }

                function escapeAttribute(value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/"/g, '&quot;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');
                }

                function productLabel(product) {
                    const code = String(product.code || '').trim();
                    const name = String(product.name || '').trim();
                    return code !== '' ? `${code} - ${name}` : name;
                }

                function upsertProducts(rows) {
                    const byId = new Map(products.map((row) => [String(row.id), row]));
                    (rows || []).forEach((row) => byId.set(String(row.id), row));
                    products = Array.from(byId.values());
                }

                function findProductByLabel(label) {
                    if (!label) {
                        return null;
                    }
                    const normalized = String(label).trim().toLowerCase();
                    return products.find((product) => productLabel(product).toLowerCase() === normalized)
                        || products.find((product) => String(product.code || '').toLowerCase() === normalized)
                        || products.find((product) => String(product.name || '').toLowerCase() === normalized)
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

                function renderProductSuggestions(input) {
                    const list = document.getElementById('admin-return-products-list');
                    if (!list) {
                        return;
                    }
                    const normalized = String(input?.value || '').trim().toLowerCase();
                    const matches = products.filter((product) => {
                        const label = productLabel(product).toLowerCase();
                        const code = String(product.code || '').toLowerCase();
                        const name = String(product.name || '').toLowerCase();
                        return normalized === '' || label.includes(normalized) || code.includes(normalized) || name.includes(normalized);
                    }).slice(0, 80);
                    list.innerHTML = matches
                        .map((product) => `<option value="${escapeAttribute(productLabel(product))}"></option>`)
                        .join('');
                }

                async function fetchProductSuggestions(input) {
                    const query = String(input?.value || '');
                    if (!(window.PgposAutoSearch && window.PgposAutoSearch.canSearchInput({ value: query }))) {
                        renderProductSuggestions(input);
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
                        renderProductSuggestions(input);
                    } catch (error) {
                        if (error && error.name === 'AbortError') {
                            return;
                        }
                    }
                }

                function reindexRows() {
                    Array.from(tbody.querySelectorAll('tr')).forEach((row, index) => {
                        const productSearch = row.querySelector('.admin-return-item-product-search');
                        const product = row.querySelector('.admin-return-item-product');
                        const qty = row.querySelector('.admin-return-item-qty');
                        const price = row.querySelector('.admin-return-item-price');
                        if (productSearch) productSearch.name = `items[${index}][product_name]`;
                        if (product) product.name = `items[${index}][product_id]`;
                        if (qty) qty.name = `items[${index}][quantity]`;
                        if (price) price.name = `items[${index}][unit_price]`;
                    });
                }

                function recalcRow(row) {
                    const qty = Math.max(0, Number(row.querySelector('.admin-return-item-qty')?.value || 0));
                    const price = Math.max(0, Number(row.querySelector('.admin-return-item-price')?.value || 0));
                    const lineTotal = qty * price;
                    const totalNode = row.querySelector('.admin-return-item-line-total');
                    if (totalNode) {
                        totalNode.textContent = numberFormat(lineTotal);
                    }
                }

                function bindRow(row) {
                    row.querySelectorAll('.admin-return-item-qty, .admin-return-item-price').forEach((input) => {
                        input.addEventListener('input', () => recalcRow(row));
                    });
                    const searchInput = row.querySelector('.admin-return-item-product-search');
                    const productIdInput = row.querySelector('.admin-return-item-product');
                    const onSearchInput = debounce(async (event) => {
                        await fetchProductSuggestions(event.currentTarget);
                        renderProductSuggestions(event.currentTarget);
                        const selected = findProductByLabel(event.currentTarget.value);
                        if (!selected) {
                            productIdInput.value = '';
                            return;
                        }
                        productIdInput.value = selected.id;
                    });
                    searchInput?.addEventListener('input', onSearchInput);
                    searchInput?.addEventListener('focus', (event) => {
                        renderProductSuggestions(event.currentTarget);
                    });
                    searchInput?.addEventListener('change', (event) => {
                        const selected = findProductByLabel(event.currentTarget.value) || findProductLoose(event.currentTarget.value);
                        if (!selected) {
                            productIdInput.value = '';
                            return;
                        }
                        productIdInput.value = selected.id;
                        searchInput.value = productLabel(selected);
                        const priceInput = row.querySelector('.admin-return-item-price');
                        if (selected && priceInput && Number(priceInput.value || 0) <= 0) {
                            priceInput.value = selected.price_general || 0;
                        }
                        recalcRow(row);
                    });
                    row.querySelector('.admin-remove-return-item')?.addEventListener('click', () => {
                        row.remove();
                        if (tbody.querySelectorAll('tr').length === 0) {
                            addRow();
                        }
                        reindexRows();
                    });
                    recalcRow(row);
                }

                function addRow() {
                    const index = tbody.querySelectorAll('tr').length;
                    const defaultProduct = products[0] || null;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <input type="text" class="admin-return-item-product-search" list="admin-return-products-list" name="items[${index}][product_name]" value="${defaultProduct ? escapeAttribute(productLabel(defaultProduct)) : ''}" required style="max-width: 280px;">
                            <input type="hidden" class="admin-return-item-product" name="items[${index}][product_id]" value="${defaultProduct ? defaultProduct.id : ''}">
                        </td>
                        <td><input type="number" min="1" class="admin-return-item-qty" name="items[${index}][quantity]" value="1" style="max-width: 90px;" required></td>
                        <td><input type="number" min="0" step="1" class="admin-return-item-price" name="items[${index}][unit_price]" value="${defaultProduct ? (defaultProduct.price_general || 0) : 0}" style="max-width: 120px;" required></td>
                        <td>Rp <span class="admin-return-item-line-total">0</span></td>
                        <td><button type="button" class="btn secondary admin-remove-return-item">{{ __('txn.remove') }}</button></td>
                    `;
                    tbody.appendChild(tr);
                    bindRow(tr);
                    reindexRows();
                }

                Array.from(tbody.querySelectorAll('tr')).forEach(bindRow);
                renderProductSuggestions(null);
                reindexRows();
                addButton.addEventListener('click', addRow);

                const form = document.getElementById('admin-return-edit-form');
                form?.addEventListener('submit', (event) => {
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    if (rows.length === 0) {
                        event.preventDefault();
                        alert(@js(__('txn.no_data_found')));
                        return;
                    }
                    const invalid = rows.some((row) => {
                        const productId = row.querySelector('.admin-return-item-product')?.value;
                        const qty = Number(row.querySelector('.admin-return-item-qty')?.value || 0);
                        const price = Number(row.querySelector('.admin-return-item-price')?.value || 0);
                        return !productId || qty < 1 || price < 0;
                    });
                    if (invalid) {
                        event.preventDefault();
                        alert(@js(__('txn.select_product')));
                    }
                });
            })();
        </script>
    @endif
@endsection






