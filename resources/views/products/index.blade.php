@extends('layouts.app')

@section('title', __('ui.products_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .products-toolbar {
            display: grid;
            grid-template-columns: minmax(420px, 540px) minmax(0, 1fr);
            align-items: start;
            gap: 10px 14px;
        }
        .products-toolbar .toolbar-left,
        .products-toolbar .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            min-width: 0;
        }
        .products-toolbar .toolbar-left {
            justify-content: flex-start;
        }
        .products-toolbar .toolbar-right {
            justify-content: flex-end;
            gap: 10px;
        }
        .products-toolbar .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }
        .products-toolbar .search-form {
            width: 100%;
            max-width: 520px;
            justify-content: flex-start;
        }
        .products-toolbar .search-form input[type="text"],
        .products-toolbar .search-form select {
            width: 260px;
            max-width: min(260px, 100%);
        }
        .products-toolbar .search-form input[type="text"] {
            flex: 1 1 210px;
            min-width: 0;
        }
        .products-toolbar .search-form select {
            flex: 0 0 170px;
            min-width: 160px;
        }
        .products-toolbar .report-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding-left: 10px;
            border-left: 1px solid var(--border);
            justify-content: flex-end;
            flex: 0 1 auto;
        }
        .product-import-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10020;
            background: rgba(15, 23, 42, 0.58);
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .product-import-overlay.is-open {
            display: flex;
        }
        .product-import-modal {
            width: min(520px, 96vw);
            max-height: 90vh;
            overflow: auto;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
            padding: 18px;
        }
        .product-import-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 12px;
        }
        .product-import-modal-title {
            font-size: 18px;
            font-weight: 800;
        }
        .product-import-file-wrap {
            padding: 12px;
            border: 1px dashed var(--border);
            border-radius: 12px;
            background: color-mix(in srgb, var(--card) 92%, var(--background) 8%);
        }
        .product-import-file-wrap input[type="file"] {
            width: 100%;
        }
        .product-import-modal-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 14px;
        }
        .products-table-wrap {
            overflow-x: auto;
        }
        .products-table {
            table-layout: auto;
            min-width: 1080px;
        }
        .products-table th,
        .products-table td {
            vertical-align: middle;
        }
        .products-table th.code-col,
        .products-table td.code-col {
            width: 100px;
        }
        .products-table th.category-col,
        .products-table td.category-col {
            width: 104px;
        }
        .products-table th.stock-col,
        .products-table td.stock-col {
            width: 68px;
        }
        .products-table th.price-col,
        .products-table td.price-col {
            width: 112px;
            white-space: nowrap;
        }
        .products-table th.action-col,
        .products-table td.action-col {
            width: 300px;
        }
        .products-table td.name-col {
            min-width: 250px;
            white-space: normal;
            word-break: normal;
            overflow-wrap: anywhere;
        }
        .products-table td.stock-col,
        .products-table td.price-col {
            text-align: left;
        }
        .sort-link {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            white-space: nowrap;
        }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
        .product-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            justify-content: flex-start;
        }
        .product-action-btn {
            min-height: 30px;
            padding: 5px 9px;
            line-height: 1.2;
            border-radius: 7px;
            white-space: nowrap;
        }
        @media (max-width: 1280px) {
            .products-toolbar {
                grid-template-columns: minmax(400px, 500px) minmax(0, 1fr);
                gap: 8px 12px;
            }
            .products-toolbar .toolbar-right {
                gap: 8px;
            }
            .products-toolbar .search-form {
                max-width: 500px;
            }
            .products-toolbar .search-form input[type="text"] {
                flex-basis: 190px;
            }
            .products-toolbar .search-form select {
                flex-basis: 160px;
                min-width: 150px;
            }
            .product-action-btn {
                padding: 5px 8px;
            }
        }
        @media (max-width: 1100px) {
            .products-toolbar {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
            }
            .products-toolbar .toolbar-left,
            .products-toolbar .toolbar-right {
                flex: 1 1 100%;
                margin-left: 0;
            }
            .products-toolbar .search-form {
                width: 100%;
                flex-wrap: wrap;
                justify-content: flex-start;
            }
            .products-toolbar .search-form input[type="text"],
            .products-toolbar .search-form select {
                width: min(100%, 280px);
                max-width: min(100%, 280px);
            }
            .products-toolbar .search-form select {
                flex: 0 1 280px;
            }
            .products-toolbar .toolbar-right,
            .products-toolbar .report-actions {
                flex: 1 1 100%;
            }
            .products-toolbar .toolbar-right {
                gap: 12px;
            }
            .products-toolbar .report-actions {
                border-left: none;
                padding-left: 0;
            }
        }
    </style>
    @php
        $currentUser = auth()->user();
        $canCreateProducts = $currentUser?->canAccess('products.create') ?? false;
        $canEditProducts = $currentUser?->canAccess('products.edit') ?? false;
        $canImportProducts = $currentUser?->canAccess('products.import') ?? false;
        $sortUrl = function (string $field) use ($search, $productType, $sort, $direction): string {
            $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
            return route('products.index', ['search' => $search, 'product_type' => $productType, 'sort' => $field, 'direction' => $nextDir]);
        };
        $sortMark = function (string $field) use ($sort, $direction): string {
            if ($sort !== $field) return '↕';
            return $direction === 'asc' ? '↑' : '↓';
        };
    @endphp
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.products_title') }}</h1>
        @if($canCreateProducts)
            <a class="btn" href="{{ route('products.create') }}">{{ __('ui.add_product') }}</a>
        @endif
    </div>

    <div class="card">
        <div class="products-toolbar">
            <div class="toolbar-left">
                <form id="products-search-form" method="get" class="search-form">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input id="products-search-input" type="text" name="search" placeholder="{{ __('ui.search_products_placeholder') }}" value="{{ $search }}">
                    <select id="products-type-filter" name="product_type" aria-label="{{ __('ui.product_type_label') }}">
                        @foreach($productTypeOptions as $typeValue => $typeLabel)
                            <option value="{{ $typeValue }}" @selected($productType === $typeValue)>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                    <button type="submit">{{ __('ui.search') }}</button>
                </form>
            </div>
            <div class="toolbar-right">
                @if($canImportProducts)
                    <button type="button" class="btn process-btn product-action-btn" id="product-import-open">Import Data</button>
                @endif
                <div class="report-actions">
                    <a class="btn info-btn product-action-btn" href="{{ route('products.print', ['search' => $search, 'product_type' => $productType]) }}" target="_blank">{{ __('txn.print') }}</a>
                    <select class="action-menu action-menu-md product-action-btn" aria-label="Export" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                        <option value="" selected disabled>Export</option>
                        <option value="{{ route('products.export.pdf', ['search' => $search, 'product_type' => $productType]) }}">Export PDF</option>
                        <option value="{{ route('products.export.csv', ['search' => $search, 'product_type' => $productType]) }}">Export Excel</option>
                    </select>
                </div>
            </div>
        </div>
        @if(session('import_errors'))
            <div class="card" style="margin-top:8px; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.4);">
                <strong>Error Import:</strong>
                <ul style="margin:8px 0 0 18px;">
                    @foreach(array_slice((array) session('import_errors'), 0, 20) as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    @if($canImportProducts)
        <div class="product-import-overlay" id="product-import-modal" aria-hidden="true">
            <div class="product-import-modal" role="dialog" aria-modal="true" aria-labelledby="product-import-title">
                <div class="product-import-modal-head">
                    <div>
                        <div class="product-import-modal-title" id="product-import-title">Import Data Barang</div>
                        <div class="muted">Upload file Excel/CSV. Gunakan template kalau format file belum sesuai.</div>
                    </div>
                    <button type="button" class="btn info-btn product-action-btn" id="product-import-close" style="min-height:32px; padding:5px 11px;">{{ __('ui.cancel') }}</button>
                </div>
                <form method="post" action="{{ route('products.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="product-import-file-wrap">
                        <label for="product-import-file">File Import</label>
                        <input id="product-import-file" type="file" name="import_file" accept=".xlsx,.xls,.csv,.txt" required>
                    </div>
                    <div class="product-import-modal-actions">
                        <a class="btn info-btn product-action-btn" href="{{ route('products.import.template') }}">Download Template</a>
                        <button type="submit" class="btn process-btn product-action-btn">Import</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="products-table-wrap">
        <table class="products-table">
            <thead>
            <tr>
                <th class="code-col">{{ __('ui.code') }}</th>
                <th class="category-col">
                    <a class="sort-link" href="{{ $sortUrl('category') }}">
                        {{ __('ui.category') }} <span class="sort-mark">{{ $sortMark('category') }}</span>
                    </a>
                </th>
                <th>
                    <a class="sort-link" href="{{ $sortUrl('name') }}">
                        {{ __('ui.name') }} <span class="sort-mark">{{ $sortMark('name') }}</span>
                    </a>
                </th>
                <th class="stock-col">
                    <a class="sort-link" href="{{ $sortUrl('stock') }}">
                        {{ __('ui.stock') }} <span class="sort-mark">{{ $sortMark('stock') }}</span>
                    </a>
                </th>
                <th class="price-col">{{ __('ui.price_agent') }}</th>
                <th class="price-col">{{ __('ui.price_sales') }}</th>
                <th class="price-col">{{ __('ui.price_general') }}</th>
                <th class="action-col">{{ __('ui.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($products as $product)
                <tr>
                    <td class="code-col">
                        @if($product->code)
                            <a href="{{ route('products.mutations', ['product' => $product, 'mutation_page' => 1]) }}#stock-mutations">{{ $product->code }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td class="category-col">{{ $product->category?->name ?: '-' }}</td>
                    <td class="name-col">{{ $product->name }}</td>
                    <td class="stock-col">
                        <strong class="js-product-stock-value" data-product-id="{{ (int) $product->id }}">
                            {{ number_format((int) round($product->stock), 0, ',', '.') }}
                        </strong>
                    </td>
                    <td class="price-col">Rp {{ number_format((int) round($product->price_agent), 0, ',', '.') }}</td>
                    <td class="price-col">Rp {{ number_format((int) round($product->price_sales), 0, ',', '.') }}</td>
                    <td class="price-col">Rp {{ number_format((int) round($product->price_general), 0, ',', '.') }}</td>
                    <td class="action-col">
                        <div class="product-actions">
                            @if($productType === 'raw_material')
                                <a class="btn info-btn product-action-btn" href="{{ route('products.show', $product) }}">{{ __('ui.view') }}</a>
                            @endif
                            @if($canEditProducts)
                                <a class="btn edit-btn product-action-btn" href="{{ route('products.edit', $product) }}">{{ __('ui.edit') }}</a>
                            @endif
                            @if($canEditProducts)
                                <button
                                    type="button"
                                    class="btn process-soft-btn product-action-btn js-open-product-stock-modal"
                                    data-product-id="{{ (int) $product->id }}"
                                    data-product-code="{{ (string) ($product->code ?? '') }}"
                                    data-product-name="{{ (string) ($product->name ?? '') }}"
                                    data-current-stock="{{ (int) round($product->stock) }}"
                                    data-update-url="{{ route('products.quick-stock', $product) }}"
                                >
                                    {{ __('ui.edit_stock') }}
                                </button>
                            @endif
                            <a class="btn process-btn product-action-btn" href="{{ route('products.mutations', $product) }}">{{ __('ui.stock_mutations_title') }}</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">{{ __('ui.no_products') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>

        <div style="margin-top: 12px;">
            {{ $products->links() }}
        </div>
    </div>

    <div id="product-stock-edit-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1200;"></div>
    <div id="product-stock-edit-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:min(520px, calc(100vw - 24px)); background:var(--card); border:1px solid var(--border); border-radius:10px; padding:14px; z-index:1201;">
        <div class="flex" style="justify-content:space-between; margin-bottom:10px;">
            <strong>{{ __('ui.edit_stock') }}</strong>
            <button type="button" id="product-stock-edit-close" class="btn info-btn product-action-btn" style="min-height:30px; padding:4px 10px;">&times;</button>
        </div>
        <form id="product-stock-edit-form" method="post" action="">
            @csrf
            <div class="row">
                <div class="col-12">
                    <label>{{ __('ui.code') }}</label>
                    <input type="text" id="product-stock-edit-code" value="" disabled>
                </div>
                <div class="col-12">
                    <label>{{ __('ui.name') }}</label>
                    <input type="text" id="product-stock-edit-name" value="" disabled>
                </div>
                <div class="col-6">
                    <label>{{ __('ui.current_stock') }}</label>
                    <input type="text" class="js-thousand-input" id="product-stock-edit-current-stock" value="" disabled>
                </div>
                <div class="col-6">
                    <label>{{ __('ui.new_stock') }}</label>
                    <input type="text" inputmode="numeric" class="js-thousand-input" name="stock" id="product-stock-edit-new-stock" value="" required>
                </div>
            </div>
            <div class="muted" id="product-stock-edit-status" style="margin-top:6px;">{{ __('ui.auto_save_hint') }}</div>
        </form>
    </div>

    <script>
        (function () {
            const form = document.getElementById('products-search-form');
            const searchInput = document.getElementById('products-search-input');
            const typeFilter = document.getElementById('products-type-filter');

            if (!form || !searchInput) {
                return;
            }

            const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                ? window.PgposAutoSearch.debounce
                : (fn, wait = 100) => {
                    let timeoutId = null;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => fn(...args), wait);
                    };
                };
            const onSearchInput = debounce(() => {
                if (window.PgposAutoSearch && !window.PgposAutoSearch.canSearchInput(searchInput)) {
                    return;
                }
                form.requestSubmit();
            }, 100);
            searchInput.addEventListener('input', onSearchInput);
            typeFilter?.addEventListener('change', () => form.requestSubmit());
        })();

        (function () {
            const importModal = document.getElementById('product-import-modal');
            const importOpen = document.getElementById('product-import-open');
            const importClose = document.getElementById('product-import-close');
            if (!importModal || !importOpen || !importClose) {
                return;
            }

            const closeImportModal = () => {
                importModal.classList.remove('is-open');
                importModal.setAttribute('aria-hidden', 'true');
            };

            importOpen.addEventListener('click', () => {
                importModal.classList.add('is-open');
                importModal.setAttribute('aria-hidden', 'false');
                document.getElementById('product-import-file')?.focus();
            });
            importClose.addEventListener('click', closeImportModal);
            importModal.addEventListener('click', (event) => {
                if (event.target === importModal) {
                    closeImportModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && importModal.classList.contains('is-open')) {
                    closeImportModal();
                }
            });
        })();

        (function () {
            const modal = document.getElementById('product-stock-edit-modal');
            const overlay = document.getElementById('product-stock-edit-modal-overlay');
            const closeBtn = document.getElementById('product-stock-edit-close');
            const form = document.getElementById('product-stock-edit-form');
            const statusText = document.getElementById('product-stock-edit-status');
            const codeInput = document.getElementById('product-stock-edit-code');
            const nameInput = document.getElementById('product-stock-edit-name');
            const currentStockInput = document.getElementById('product-stock-edit-current-stock');
            const newStockInput = document.getElementById('product-stock-edit-new-stock');
            if (!modal || !overlay || !closeBtn || !form || !newStockInput) {
                return;
            }

            let saveTimer = null;
            let isSubmitting = false;
            let originalStock = 0;
            let selectedProductId = 0;
            const AUTO_SAVE_DELAY_MS = 5000;

            const setRowStock = (productId, value) => {
                const stockValue = Number(value);
                const stockEl = document.querySelector('.js-product-stock-value[data-product-id="' + String(productId) + '"]');
                if (stockEl) {
                    stockEl.textContent = stockValue.toLocaleString('id-ID', { maximumFractionDigits: 0 });
                }

                const editBtn = document.querySelector('.js-open-product-stock-modal[data-product-id="' + String(productId) + '"]');
                if (editBtn) {
                    editBtn.setAttribute('data-current-stock', String(stockValue));
                }
            };

            const openModal = (button) => {
                selectedProductId = Number(button.getAttribute('data-product-id') || '0');
                originalStock = Number(button.getAttribute('data-current-stock') || '0');
                form.action = String(button.getAttribute('data-update-url') || '');
                codeInput.value = String(button.getAttribute('data-product-code') || '-');
                nameInput.value = String(button.getAttribute('data-product-name') || '-');
                currentStockInput.value = String(originalStock);
                newStockInput.value = String(originalStock);
                window.PgposNumberFormat.formatInput(currentStockInput);
                window.PgposNumberFormat.formatInput(newStockInput);
                statusText.textContent = @json(__('ui.auto_save_hint'));
                modal.style.display = 'block';
                overlay.style.display = 'block';
                setTimeout(() => newStockInput.focus(), 50);
            };

            const closeModal = () => {
                modal.style.display = 'none';
                overlay.style.display = 'none';
                if (saveTimer) {
                    clearTimeout(saveTimer);
                    saveTimer = null;
                }
            };
            const hasPendingStockChange = () => {
                const current = window.PgposNumberFormat.parseInt(currentStockInput.value || '0');
                const next = window.PgposNumberFormat.parseInt(newStockInput.value || '0');
                return !Number.isNaN(next) && next >= 0 && current !== next;
            };
            const requestCloseModal = () => {
                if (saveTimer) {
                    clearTimeout(saveTimer);
                    saveTimer = null;
                }
                if (isSubmitting) {
                    return;
                }
                if (hasPendingStockChange()) {
                    triggerAutoSave();
                    return;
                }
                closeModal();
            };

            const triggerAutoSave = () => {
                const current = window.PgposNumberFormat.parseInt(currentStockInput.value || '0');
                const next = window.PgposNumberFormat.parseInt(newStockInput.value || '0');
                if (Number.isNaN(next) || next < 0) return;
                if (current === next) return;
                if (isSubmitting) return;
                if (!form.action) return;

                isSubmitting = true;
                statusText.textContent = @json(__('ui.saving'));
                const payload = new FormData(form);
                payload.set('stock', String(next));
                fetch(form.action, {
                    method: 'POST',
                    body: payload,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                    .then(async (response) => {
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data || data.ok !== true) {
                            let errMsg = @json(__('ui.save_failed'));
                            if (data && data.errors && typeof data.errors === 'object') {
                                const firstKey = Object.keys(data.errors)[0];
                                const firstErr = firstKey ? data.errors[firstKey] : null;
                                if (Array.isArray(firstErr) && firstErr.length > 0) {
                                    errMsg = String(firstErr[0]);
                                }
                            } else if (data && data.message) {
                                errMsg = String(data.message);
                            }
                            throw new Error(errMsg);
                        }

                        const savedStock = Number(data.stock ?? next);
                        originalStock = savedStock;
                        currentStockInput.value = String(savedStock);
                        newStockInput.value = String(savedStock);
                        window.PgposNumberFormat.formatInput(currentStockInput);
                        window.PgposNumberFormat.formatInput(newStockInput);
                        setRowStock(selectedProductId, savedStock);
                        const serverMessage = String(data.message || @json(__('ui.saved')));
                        const serverType = String(data.message_type || 'edit');
                        statusText.textContent = serverMessage;
                        if (window.PgposFlash && typeof window.PgposFlash.show === 'function') {
                            window.PgposFlash.show(serverMessage, serverType);
                        }
                        setTimeout(() => closeModal(), 250);
                    })
                    .catch((error) => {
                        setRowStock(selectedProductId, originalStock);
                        currentStockInput.value = String(originalStock);
                        newStockInput.value = String(originalStock);
                        window.PgposNumberFormat.formatInput(currentStockInput);
                        window.PgposNumberFormat.formatInput(newStockInput);
                        statusText.textContent = error.message || @json(__('ui.save_failed'));
                    })
                    .finally(() => {
                        isSubmitting = false;
                    });
            };

            const scheduleAutoSave = () => {
                if (saveTimer) clearTimeout(saveTimer);
                saveTimer = setTimeout(triggerAutoSave, AUTO_SAVE_DELAY_MS);
            };

            document.querySelectorAll('.js-open-product-stock-modal').forEach((button) => {
                button.addEventListener('click', () => openModal(button));
            });

            closeBtn.addEventListener('click', requestCloseModal);
            overlay.addEventListener('click', requestCloseModal);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.style.display === 'block') {
                    requestCloseModal();
                }
                if (event.key === 'Enter' && modal.style.display === 'block') {
                    event.preventDefault();
                    scheduleAutoSave();
                }
            });
            newStockInput.addEventListener('input', scheduleAutoSave);
            newStockInput.addEventListener('change', scheduleAutoSave);
        })();
    </script>
@endsection

