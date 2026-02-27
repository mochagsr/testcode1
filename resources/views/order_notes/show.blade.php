@extends('layouts.app')

@section('title', __('txn.order_notes_title').' - PgPOS ERP')

@section('content')
    <style>
        #admin-order-items-table input[type=number].qty-input::-webkit-outer-spin-button,
        #admin-order-items-table input[type=number].qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #admin-order-items-table input[type=number].qty-input {
            -moz-appearance: textfield;
            appearance: textfield;
        }
        .txn-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
        }
        .txn-modal.open {
            display: flex;
        }
        .txn-modal-card {
            width: min(1180px, 100%);
            max-height: calc(100vh - 32px);
            overflow: auto;
            border-radius: 12px;
        }
        .txn-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        #admin-order-items-table {
            table-layout: fixed;
            width: 100%;
        }
        #admin-order-items-table .product-col {
            width: 58%;
        }
        #admin-order-items-table .qty-col {
            width: 10%;
        }
        #admin-order-items-table .notes-col {
            width: 22%;
        }
        #admin-order-items-table .action-col {
            width: 10%;
        }
        #admin-order-items-table .admin-order-item-search {
            width: 100%;
            min-width: 460px;
            max-width: none;
        }
        #admin-order-items-table .admin-order-item-qty {
            width: 100%;
            min-width: 80px;
            max-width: 110px;
        }
        #admin-order-items-table .admin-order-item-notes {
            width: 100%;
            min-width: 210px;
            max-width: none;
        }
    </style>

    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.order_notes_title') }} {{ $note->note_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('order-notes.index') }}">{{ __('txn.back') }}</a>
            <button type="button" class="btn secondary" id="open-admin-edit-modal">{{ __('txn.edit_transaction') }}</button>
            <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('order-notes.print', $note) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('order-notes.export.pdf', $note) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('order-notes.export.excel', $note) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.order_summary') }}</h3>
            <p class="form-section-note">{{ __('txn.order_summary_note') }}</p>
            <div class="row">
                <div class="col-4"><strong>{{ __('txn.date') }}</strong><div>{{ $note->note_date->format('d-m-Y') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.customer') }}</strong><div>{{ $note->customer_name }}</div></div>
                <div class="col-4"><strong>{{ __('txn.phone') }}</strong><div>{{ $note->customer_phone ?: '-' }}</div></div>
                <div class="col-12"><strong>{{ __('txn.address') }}</strong><div>{{ $note->address ?: $note->customer?->address ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.city') }}</strong><div>{{ $note->city ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.created_by') }}</strong><div>{{ $note->created_by_name ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.linked_customer') }}</strong><div>{{ $note->customer?->name ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.status') }}</strong><div>{{ $note->is_canceled ? __('txn.status_canceled') : __('txn.status_active') }}</div></div>
                @php
                    $progressLabel = rtrim(rtrim(number_format((float) ($noteProgress['progress_percent'] ?? 0), 2, '.', ''), '0'), '.');
                    $progressStatusLabel = ($noteProgress['status'] ?? 'open') === 'finished' ? __('txn.order_note_status_finished') : __('txn.order_note_status_open');
                @endphp
                <div class="col-4"><strong>{{ __('txn.order_note_progress') }}</strong><div>{{ $progressLabel }}%</div></div>
                <div class="col-4"><strong>{{ __('txn.order_note_qty_ordered') }}</strong><div>{{ number_format((int) ($noteProgress['ordered_total'] ?? 0), 0, ',', '.') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.order_note_qty_fulfilled') }}</strong><div>{{ number_format((int) ($noteProgress['fulfilled_total'] ?? 0), 0, ',', '.') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.order_note_qty_remaining') }}</strong><div>{{ number_format((int) ($noteProgress['remaining_total'] ?? 0), 0, ',', '.') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.order_note_status_open') }}/{{ __('txn.order_note_status_finished') }}</strong><div>{{ $progressStatusLabel }}</div></div>
                <div class="col-12"><strong>{{ __('txn.notes') }}</strong><div>{{ $note->notes ?: '-' }}</div></div>
                @if($note->is_canceled)
                    <div class="col-12"><strong>{{ __('txn.cancel_reason') }}</strong><div>{{ $note->cancel_reason ?: '-' }}</div></div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.items') }}</h3>
            <p class="form-section-note">{{ __('txn.order_items_note') }}</p>
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.name') }}</th>
                    <th>{{ __('txn.qty') }}</th>
                    <th>{{ __('txn.notes') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($note->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ (int) round($item->quantity) }}</td>
                        <td>{{ $item->notes ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div id="admin-edit-modal" class="txn-modal" aria-hidden="true">
        <div id="admin-edit-transaction" class="card txn-modal-card">
            <div class="form-section">
                <div class="txn-modal-header">
                    <h3 class="form-section-title" style="margin: 0;">{{ __('txn.edit_transaction') }}</h3>
                    <button type="button" class="btn secondary" id="close-admin-edit-modal">{{ __('txn.cancel') }}</button>
                </div>
                <p class="form-section-note">{{ __('txn.edit_transaction') }}</p>
                <form id="admin-order-edit-form" method="post" action="{{ route('order-notes.admin-update', $note) }}" class="row" style="margin-bottom: 12px;">
                    @csrf
                    @method('PUT')
                    <div class="col-4">
                        <label>{{ __('txn.date') }}</label>
                        <input type="date" name="note_date" value="{{ old('note_date', optional($note->note_date)->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.customer') }}</label>
                        <input type="text" name="customer_name" value="{{ old('customer_name', $note->customer_name) }}" required>
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.phone') }}</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone', $note->customer_phone) }}">
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.city') }}</label>
                        <input type="text" name="city" value="{{ old('city', $note->city) }}">
                    </div>
                    <div class="col-12">
                        <label>{{ __('txn.address') }}</label>
                        <textarea name="address" rows="2">{{ old('address', $note->address ?: $note->customer?->address) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="flex" style="justify-content: space-between; margin-top: 6px; margin-bottom: 8px;">
                            <strong>{{ __('txn.items') }}</strong>
                            <button type="button" id="admin-add-order-item" class="btn secondary">{{ __('txn.add_row') }}</button>
                        </div>
                        <table id="admin-order-items-table">
                            <thead>
                            <tr>
                                <th class="product-col">{{ __('txn.product') }}</th>
                                <th class="qty-col">{{ __('txn.qty') }}</th>
                                <th class="notes-col">{{ __('txn.notes') }}</th>
                                <th class="action-col">{{ __('txn.action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($note->items as $index => $item)
                                <tr>
                                    <td>
                                        <input type="text" name="items[{{ $index }}][product_name]" class="admin-order-item-search" list="admin-order-products-list" value="{{ $item->product_name }}" required>
                                        <input type="hidden" class="admin-order-item-product-id" name="items[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                                    </td>
                                    <td><input type="number" min="1" name="items[{{ $index }}][quantity]" class="admin-order-item-qty qty-input" value="{{ (int) round($item->quantity) }}" required></td>
                                    <td><input type="text" name="items[{{ $index }}][notes]" class="admin-order-item-notes" value="{{ $item->notes }}"></td>
                                    <td><button type="button" class="btn secondary admin-remove-order-item">{{ __('txn.remove') }}</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <datalist id="admin-order-products-list">
                            @foreach($products as $productOption)
                                <option value="{{ $productOption->code ? $productOption->code.' - '.$productOption->name : $productOption->name }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-12">
                        <label>{{ __('txn.notes') }}</label>
                        <textarea name="notes" rows="2">{{ old('notes', $note->notes) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn" type="submit">{{ __('txn.save_changes') }}</button>
                    </div>
                </form>
                @if((auth()->user()?->role ?? '') === 'admin' && !$note->is_canceled)
                    <form method="post" action="{{ route('order-notes.cancel', $note) }}" class="row">
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
    </div>

    <script>
        (function () {
            const openModalBtn = document.getElementById('open-admin-edit-modal');
            const closeModalBtn = document.getElementById('close-admin-edit-modal');
            const modal = document.getElementById('admin-edit-modal');
            const modalCard = document.getElementById('admin-edit-transaction');
            if (openModalBtn && closeModalBtn && modal && modalCard) {
                const openModal = () => {
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                };
                openModalBtn.addEventListener('click', () => {
                    openModal();
                });
                const closeModal = () => {
                    modal.classList.remove('open');
                    modal.setAttribute('aria-hidden', 'true');
                };
                window.__openOrderEditModal = openModal;
                closeModalBtn.addEventListener('click', closeModal);
                modal.addEventListener('click', (event) => {
                    if (!modalCard.contains(event.target)) {
                        closeModal();
                    }
                });
                @if($errors->any())
                openModal();
                @endif
            }
        })();
    </script>

    <script>
        (function () {
                const table = document.getElementById('admin-order-items-table');
                const tbody = table?.querySelector('tbody');
                const addButton = document.getElementById('admin-add-order-item');
                if (!table || !tbody || !addButton) {
                    return;
                }

                @php
                    $adminProducts = $products->map(function ($product): array {
                        return [
                            'id' => (int) $product->id,
                            'code' => (string) ($product->code ?? ''),
                            'name' => (string) $product->name,
                        ];
                    })->values()->all();
                @endphp
                let products = @json($adminProducts);
                let productByLabel = new Map();
                let productByCode = new Map();
                let productByName = new Map();
                const PRODUCT_LOOKUP_URL = @json(route('api.products.index'));
                const LOOKUP_LIMIT = 20;
                const SEARCH_DEBOUNCE_MS = 100;
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
                    const code = String(product.code || '').trim();
                    if (code !== '') {
                        return `${code} - ${product.name}`;
                    }
                    return String(product.name || '');
                }

                function upsertProducts(rows) {
                    const byId = new Map(products.map((row) => [String(row.id), row]));
                    (rows || []).forEach((row) => byId.set(String(row.id), row));
                    products = Array.from(byId.values());
                    rebuildProductIndexes();
                }

                function rebuildProductIndexes() {
                    productByLabel = new Map();
                    productByCode = new Map();
                    productByName = new Map();
                    products.forEach((product) => {
                        const byLabel = normalizeLookup(productLabel(product));
                        const byCode = normalizeLookup(product.code);
                        const byName = normalizeLookup(product.name);
                        if (byLabel !== '' && !productByLabel.has(byLabel)) {
                            productByLabel.set(byLabel, product);
                        }
                        if (byCode !== '' && !productByCode.has(byCode)) {
                            productByCode.set(byCode, product);
                        }
                        if (byName !== '' && !productByName.has(byName)) {
                            productByName.set(byName, product);
                        }
                    });
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
                        || products.find((product) => product.name.toLowerCase().includes(normalized))
                        || null;
                }

                function renderProductSuggestions(input) {
                    const list = document.getElementById('admin-order-products-list');
                    if (!list) {
                        return;
                    }
                    const normalized = String(input?.value || '').trim().toLowerCase();
                    const matches = products.filter((product) => {
                        const label = productLabel(product).toLowerCase();
                        const code = String(product.code || '').toLowerCase();
                        const name = product.name.toLowerCase();
                        return normalized === '' || label.includes(normalized) || code.includes(normalized) || name.includes(normalized);
                    }).slice(0, 60);
                    list.innerHTML = matches
                        .map((product) => `<option value="${escapeAttribute(productLabel(product))}"></option>`)
                        .join('');
                }

                async function fetchProductSuggestions(input) {
                    const query = String(input?.value || '');
                    const normalizedQuery = normalizeLookup(query);
                    if (!(window.PgposAutoSearch && window.PgposAutoSearch.canSearchInput({ value: query }))) {
                        lastProductLookupQuery = '';
                        renderProductSuggestions(input);
                        return;
                    }
                    if (normalizedQuery !== '' && normalizedQuery === lastProductLookupQuery) {
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
                        lastProductLookupQuery = normalizedQuery;
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
                        row.querySelector('.admin-order-item-search').name = `items[${index}][product_name]`;
                        row.querySelector('.admin-order-item-product-id').name = `items[${index}][product_id]`;
                        row.querySelector('.admin-order-item-qty').name = `items[${index}][quantity]`;
                        row.querySelector('.admin-order-item-notes').name = `items[${index}][notes]`;
                    });
                }

                function bindRow(row) {
                    const searchInput = row.querySelector('.admin-order-item-search');
                    const productIdInput = row.querySelector('.admin-order-item-product-id');
                    const onSearchInput = debounce(async (event) => {
                        await fetchProductSuggestions(event.currentTarget);
                        const selected = findProductByLabel(event.currentTarget.value);
                        if (selected) {
                            productIdInput.value = selected.id;
                            return;
                        }
                        productIdInput.value = '';
                    });
                    searchInput?.addEventListener('input', onSearchInput);
                    searchInput?.addEventListener('focus', async (event) => {
                        await fetchProductSuggestions(event.currentTarget);
                    });
                    searchInput?.addEventListener('change', (event) => {
                        const selected = findProductByLabel(event.currentTarget.value) || findProductLoose(event.currentTarget.value);
                        if (!selected) {
                            productIdInput.value = '';
                            return;
                        }
                        productIdInput.value = selected.id;
                        searchInput.value = productLabel(selected);
                    });
                    row.querySelector('.admin-remove-order-item')?.addEventListener('click', () => {
                        row.remove();
                        if (tbody.querySelectorAll('tr').length === 0) {
                            addRow();
                        }
                        reindexRows();
                    });
                }

                function addRow() {
                    const index = tbody.querySelectorAll('tr').length;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <input type="text" name="items[${index}][product_name]" class="admin-order-item-search" list="admin-order-products-list" value="" required>
                            <input type="hidden" class="admin-order-item-product-id" name="items[${index}][product_id]" value="">
                        </td>
                        <td><input type="number" min="1" name="items[${index}][quantity]" class="admin-order-item-qty qty-input" value="1" required></td>
                        <td><input type="text" name="items[${index}][notes]" class="admin-order-item-notes" value=""></td>
                        <td><button type="button" class="btn secondary admin-remove-order-item">{{ __('txn.remove') }}</button></td>
                    `;
                    tbody.appendChild(tr);
                    bindRow(tr);
                    reindexRows();
                }

                rebuildProductIndexes();
                Array.from(tbody.querySelectorAll('tr')).forEach(bindRow);
                reindexRows();
                addButton.addEventListener('click', addRow);

                const form = document.getElementById('admin-order-edit-form');
                form?.addEventListener('submit', (event) => {
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    if (rows.length === 0) {
                        event.preventDefault();
                        alert(@js(__('txn.no_data_found')));
                        return;
                    }
                    const invalid = rows.some((row) => {
                        const productName = (row.querySelector('.admin-order-item-search')?.value || '').trim();
                        const qty = Number(row.querySelector('.admin-order-item-qty')?.value || 0);
                        return productName === '' || qty < 1;
                    });
                    if (invalid) {
                        event.preventDefault();
                        alert(@js(__('txn.select_product')));
                    }
                });
        })();
    </script>
@endsection
