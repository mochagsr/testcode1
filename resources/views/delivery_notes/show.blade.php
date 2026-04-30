@extends('layouts.app')

@section('title', __('txn.delivery_notes_title').' - '.config('app.name', 'Laravel'))

@section('content')
    @php
        $canEditTransactions = auth()->user()?->canAccess('delivery_notes.edit') ?? false;
        $canCancelTransactions = auth()->user()?->canAccess('delivery_notes.cancel') ?? false;
    @endphp
    <style>
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
            width: min(1100px, 100%);
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
        #admin-delivery-items-table input[type=number].qty-input::-webkit-outer-spin-button,
        #admin-delivery-items-table input[type=number].qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #admin-delivery-items-table input[type=number].qty-input {
            -moz-appearance: textfield;
            appearance: textfield;
        }
    </style>

    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.delivery_notes_title') }} {{ $note->note_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('delivery-notes.index') }}">{{ __('txn.back') }}</a>
            @if($canEditTransactions)
                <button type="button" class="btn edit-btn" id="open-admin-edit-modal">{{ __('txn.edit_transaction') }}</button>
            @endif
            <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('delivery-notes.print', $note) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('delivery-notes.export.pdf', $note) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('delivery-notes.export.excel', $note) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.delivery_summary') }}</h3>
            <p class="form-section-note">{{ __('txn.delivery_summary_note') }}</p>
            <div class="row">
                <div class="col-4"><strong>{{ __('txn.date') }}</strong><div>{{ $note->note_date->format('d-m-Y') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.recipient') }}</strong><div>{{ $note->recipient_name }}</div></div>
                <div class="col-4"><strong>{{ __('txn.phone') }}</strong><div>{{ $note->recipient_phone ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.city') }}</strong><div>{{ $note->city ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.created_by') }}</strong><div>{{ $note->created_by_name ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.linked_customer') }}</strong><div>{{ $note->customer?->name ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('school_bulk.ship_to_school') }}</strong><div>{{ $note->shipLocation?->school_name ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.status') }}</strong><div>{{ $note->is_canceled ? __('txn.status_canceled') : __('txn.status_active') }}</div></div>
                <div class="col-12"><strong>{{ __('txn.address') }}</strong><div>{{ $note->address ?: '-' }}</div></div>
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
            <p class="form-section-note">{{ __('txn.delivery_items_note') }}</p>
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.name') }}</th>
                    <th>{{ __('txn.unit') }}</th>
                    <th>{{ __('txn.qty') }}</th>
                    <th>{{ __('txn.notes') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($note->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->unit ?: '-' }}</td>
                        <td>{{ (int) round($item->quantity) }}</td>
                        <td>{{ $item->notes ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div style="margin-top:10px; text-align:right; font-size:16px;">
                <strong>{{ __('txn.summary_total_qty') }}: {{ number_format((int) $note->items->sum('quantity'), 0, ',', '.') }}</strong>
            </div>
        </div>
    </div>

    @if($canEditTransactions)
        <div id="admin-edit-modal" class="txn-modal" aria-hidden="true">
            <div id="admin-edit-transaction" class="card txn-modal-card">
                <div class="form-section">
                    <div class="txn-modal-header">
                        <h3 class="form-section-title" style="margin: 0;">{{ __('txn.edit_transaction') }}</h3>
                        <button type="button" class="btn secondary" id="close-admin-edit-modal">{{ __('txn.cancel') }}</button>
                    </div>
                    <p class="form-section-note">Gunakan hak akses edit transaksi ini untuk koreksi cepat. Jika perubahan perlu jejak approval, tetap gunakan Wizard Koreksi.</p>
                <form id="admin-delivery-edit-form" method="post" action="{{ route('delivery-notes.admin-update', $note) }}" class="row" style="margin-bottom: 12px;">
                    @csrf
                    @method('PUT')
                    <div class="col-4">
                        <label>{{ __('txn.date') }}</label>
                        <input type="date" name="note_date" value="{{ old('note_date', optional($note->note_date)->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.recipient') }}</label>
                        <input type="text" name="recipient_name" value="{{ old('recipient_name', $note->recipient_name) }}" required>
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.phone') }}</label>
                        <input type="text" name="recipient_phone" value="{{ old('recipient_phone', $note->recipient_phone) }}">
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.city') }}</label>
                        <input type="text" name="city" value="{{ old('city', $note->city) }}">
                    </div>
                    <div class="col-4">
                        <label>{{ __('txn.transaction_type') }}</label>
                        <select id="admin-delivery-note-transaction-type" name="transaction_type">
                            <option value="product" @selected(old('transaction_type', $note->transaction_type ?? 'product') === 'product')>{{ __('txn.transaction_type_product') }}</option>
                            <option value="printing" @selected(old('transaction_type', $note->transaction_type) === 'printing')>{{ __('txn.transaction_type_printing') }}</option>
                        </select>
                    </div>
                    <input type="hidden" id="admin-delivery-note-customer-id" value="{{ (int) $note->customer_id }}">
                    @include('partials.printing_subtype_fields', [
                        'customerFieldId' => 'admin-delivery-note-customer-id',
                        'transactionTypeFieldId' => 'admin-delivery-note-transaction-type',
                        'subtypeFieldId' => 'admin-delivery-note-printing-subtype-id',
                        'selectedSubtypeId' => old('customer_printing_subtype_id', $note->customer_printing_subtype_id),
                        'selectedSubtypeName' => old('printing_subtype_name', $note->printing_subtype_name),
                        'colClass' => 'col-4',
                    ])
                    <div class="col-12">
                        <div class="flex" style="justify-content: space-between; margin-top: 6px; margin-bottom: 8px;">
                            <strong>{{ __('txn.items') }}</strong>
                            <button type="button" id="admin-add-delivery-item" class="btn process-soft-btn">{{ __('txn.add_row') }}</button>
                        </div>
                        <table id="admin-delivery-items-table">
                            <thead>
                            <tr>
                                <th style="width: 42%;">{{ __('txn.product') }}</th>
                                <th style="width: 12%;">{{ __('txn.qty') }}</th>
                                <th style="width: 10%;">{{ __('txn.unit') }}</th>
                                <th style="width: 24%;">{{ __('txn.notes') }}</th>
                                <th style="width: 12%;">{{ __('txn.action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($note->items as $index => $item)
                                <tr>
                                    <td>
                                        <input type="text" name="items[{{ $index }}][product_name]" class="admin-delivery-item-search" list="admin-delivery-products-list" value="{{ $item->product_name }}" style="min-width: 280px; width: 100%;" required>
                                        <input type="hidden" class="admin-delivery-item-product-id" name="items[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                                    </td>
                                    <td><input type="number" min="1" name="items[{{ $index }}][quantity]" class="admin-delivery-item-qty qty-input" value="{{ (int) round($item->quantity) }}" style="max-width: 104px;" required></td>
                                    <td><input type="text" name="items[{{ $index }}][unit]" class="admin-delivery-item-unit" value="{{ $item->unit }}" style="max-width: 72px;"></td>
                                    <td><input type="text" name="items[{{ $index }}][notes]" class="admin-delivery-item-notes" value="{{ $item->notes }}" style="max-width: 130px;"></td>
                                    <td><button type="button" class="btn danger-btn admin-remove-delivery-item">{{ __('txn.remove') }}</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <datalist id="admin-delivery-products-list">
                            @foreach($products as $productOption)
                                <option value="{{ $productOption->code ? $productOption->code.' - '.$productOption->name : $productOption->name }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-12">
                        <label>{{ __('txn.address') }}</label>
                        <textarea name="address" rows="2">{{ old('address', $note->address) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label>{{ __('txn.notes') }}</label>
                        <textarea name="notes" rows="2">{{ old('notes', $note->notes) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn" type="submit">{{ __('txn.save_changes') }}</button>
                    </div>
                </form>
                @if($canCancelTransactions && !$note->is_canceled)
                    <form method="post" action="{{ route('delivery-notes.cancel', $note) }}" class="row">
                        @csrf
                        <div class="col-12">
                            <label>{{ __('txn.cancel_reason') }}</label>
                            <textarea name="cancel_reason" rows="2" required></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn danger-btn" type="submit">{{ __('txn.cancel_transaction') }}</button>
                        </div>
                    </form>
                @endif
                </div>
            </div>
        </div>

        <script>
            (function () {
                const modal = document.getElementById('admin-edit-modal');
                const openBtn = document.getElementById('open-admin-edit-modal');
                const closeBtn = document.getElementById('close-admin-edit-modal');
                const modalCard = modal?.querySelector('.txn-modal-card');
                if (modal && openBtn && closeBtn) {
                    const closeModal = () => {
                        modal.classList.remove('open');
                        modal.setAttribute('aria-hidden', 'true');
                    };
                    const openModal = () => {
                        modal.classList.add('open');
                        modal.setAttribute('aria-hidden', 'false');
                    };
                    openBtn.addEventListener('click', openModal);
                    closeBtn.addEventListener('click', closeModal);
                    modal.addEventListener('click', (event) => {
                        if (!modalCard || modalCard.contains(event.target)) {
                            return;
                        }
                        closeModal();
                    });
                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') {
                            closeModal();
                        }
                    });
                }

                const table = document.getElementById('admin-delivery-items-table');
                const tbody = table?.querySelector('tbody');
                const addButton = document.getElementById('admin-add-delivery-item');
                if (!table || !tbody || !addButton) {
                    return;
                }

                @php
                    $adminProducts = $products->map(function ($product): array {
                        return [
                            'id' => (int) $product->id,
                            'code' => (string) ($product->code ?? ''),
                            'name' => (string) $product->name,
                            'unit' => (string) ($product->unit ?? ''),
                            'price_agent' => (int) round((float) ($product->price_agent ?? 0)),
                            'price_sales' => (int) round((float) ($product->price_sales ?? 0)),
                            'price_general' => (int) round((float) ($product->price_general ?? 0)),
                        ];
                    })->values()->all();
                @endphp
                let products = @json($adminProducts);
                const customerLevelCode = @json(strtolower(trim((string) ($note->customer?->level?->code ?? ''))));
                const customerLevelName = @json(strtolower(trim((string) ($note->customer?->level?->name ?? ''))));
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

                function getPriceKeyForCustomer() {
                    const combined = `${customerLevelCode} ${customerLevelName}`.trim();
                    if (combined.includes('agent') || combined.includes('agen')) {
                        return 'price_agent';
                    }
                    if (combined.includes('sales')) {
                        return 'price_sales';
                    }
                    return 'price_general';
                }

                function getProductPriceByCustomerLevel(product) {
                    const key = getPriceKeyForCustomer();
                    if (key === 'price_agent') {
                        return Number(product.price_agent ?? product.price_general ?? 0);
                    }
                    if (key === 'price_sales') {
                        return Number(product.price_sales ?? product.price_general ?? 0);
                    }
                    return Number(product.price_general ?? 0);
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
                    const list = document.getElementById('admin-delivery-products-list');
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
                        row.querySelector('.admin-delivery-item-search').name = `items[${index}][product_name]`;
                        row.querySelector('.admin-delivery-item-product-id').name = `items[${index}][product_id]`;
                        row.querySelector('.admin-delivery-item-unit').name = `items[${index}][unit]`;
                        row.querySelector('.admin-delivery-item-qty').name = `items[${index}][quantity]`;
                        row.querySelector('.admin-delivery-item-notes').name = `items[${index}][notes]`;
                    });
                }

                function setProductFieldError(row, message = '') {
                    const hasMessage = String(message || '').trim() !== '';
                    const input = row.querySelector('.admin-delivery-item-search');
                    let error = row.querySelector('.admin-delivery-item-error');
                    if (!error && input) {
                        error = document.createElement('div');
                        error.className = 'field-inline-error admin-delivery-item-error';
                        error.style.display = 'block';
                        error.style.marginTop = '4px';
                        input.insertAdjacentElement('afterend', error);
                    }
                    if (error) {
                        error.textContent = hasMessage ? message : '';
                    }
                    input?.classList.toggle('input-inline-error', hasMessage);
                }

                function bindRow(row) {
                    const searchInput = row.querySelector('.admin-delivery-item-search');
                    const productIdInput = row.querySelector('.admin-delivery-item-product-id');
                    const onSearchInput = debounce(async (event) => {
                        setProductFieldError(row, '');
                        await fetchProductSuggestions(event.currentTarget);
                        const selected = findProductByLabel(event.currentTarget.value);
                        if (!selected) {
                            productIdInput.value = '';
                            return;
                        }
                        productIdInput.value = selected.id;
                        if (!row.querySelector('.admin-delivery-item-unit').value) {
                            row.querySelector('.admin-delivery-item-unit').value = selected.unit || '';
                        }
                    });
                    searchInput?.addEventListener('input', onSearchInput);
                    searchInput?.addEventListener('focus', (event) => {
                        renderProductSuggestions(event.currentTarget);
                    });
                    searchInput?.addEventListener('change', (event) => {
                        const selected = findProductByLabel(event.currentTarget.value) || findProductLoose(event.currentTarget.value);
                        if (!selected) {
                            productIdInput.value = '';
                            if (String(event.currentTarget.value || '').trim() !== '') {
                                setProductFieldError(row, @js(__('txn.product_not_registered')));
                            }
                            return;
                        }
                        productIdInput.value = selected.id;
                        searchInput.value = productLabel(selected);
                        setProductFieldError(row, '');
                        if (!row.querySelector('.admin-delivery-item-unit').value) {
                            row.querySelector('.admin-delivery-item-unit').value = selected.unit || '';
                        }
                    });
                    row.querySelector('.admin-remove-delivery-item')?.addEventListener('click', () => {
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
                            <input type="text" name="items[${index}][product_name]" class="admin-delivery-item-search" list="admin-delivery-products-list" value="" style="min-width: 280px; width: 100%;" required>
                            <input type="hidden" class="admin-delivery-item-product-id" name="items[${index}][product_id]" value="">
                        </td>
                        <td><input type="number" min="1" name="items[${index}][quantity]" class="admin-delivery-item-qty qty-input" value="1" style="max-width: 104px;" required></td>
                        <td><input type="text" name="items[${index}][unit]" class="admin-delivery-item-unit" value="" style="max-width: 72px;"></td>
                        <td><input type="text" name="items[${index}][notes]" class="admin-delivery-item-notes" value="" style="max-width: 130px;"></td>
                        <td><button type="button" class="btn danger-btn admin-remove-delivery-item">{{ __('txn.remove') }}</button></td>
                    `;
                    tbody.appendChild(tr);
                    bindRow(tr);
                    reindexRows();
                }

                rebuildProductIndexes();
                Array.from(tbody.querySelectorAll('tr')).forEach(bindRow);
                reindexRows();
                addButton.addEventListener('click', addRow);

                const form = document.getElementById('admin-delivery-edit-form');
                form?.addEventListener('submit', (event) => {
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    if (rows.length === 0) {
                        event.preventDefault();
                        window.PgposDialog.showMessage(@js(__('txn.add_item_first')));
                        return;
                    }
                    const invalid = rows.some((row) => {
                        const productId = row.querySelector('.admin-delivery-item-product-id')?.value;
                        const qty = Number(row.querySelector('.admin-delivery-item-qty')?.value || 0);
                        if (!productId) {
                            setProductFieldError(row, @js(__('txn.product_not_registered')));
                        } else {
                            setProductFieldError(row, '');
                        }
                        return !productId || qty < 1;
                    });
                    if (invalid) {
                        event.preventDefault();
                        window.PgposDialog.showMessage(@js(__('txn.fix_invalid_products')));
                    }
                });
            })();
        </script>
        @include('partials.printing_subtype_script')
    @endif
@endsection

