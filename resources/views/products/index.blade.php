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
        /* Reconciliation modal: compact rows + smaller checkboxes */
        #product-reconcile-body table { font-size: 12px; }
        #product-reconcile-body th,
        #product-reconcile-body td { padding: 5px 8px; }
        #product-reconcile-body input[type="checkbox"] {
            width: 16px;
            height: 16px;
            min-width: 16px;
            margin: 0;
            vertical-align: middle;
        }
        #product-reconcile-body select.action-menu-sm {
            min-height: 28px;
            padding: 3px 6px;
            font-size: 12px;
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
        $isAdmin = ($currentUser?->role ?? '') === 'admin';
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
                @if($isAdmin)
                    <button type="button" class="btn danger-btn product-action-btn" id="product-bulk-delete-open" disabled>
                        {{ __('ui.bulk_delete_products') }} (<span id="product-bulk-delete-count">0</span>)
                    </button>
                @endif
                @if($isAdmin)
                    <a class="btn info-btn product-action-btn" href="{{ route('products.export.full') }}">Export Data (.xlsx)</a>
                @endif
                @if($canImportProducts)
                    <button type="button" class="btn process-btn product-action-btn" id="product-import-open">Import Data</button>
                @endif
                <div class="report-actions">
                    <a
                        class="btn info-btn product-action-btn"
                        data-ajax-sync
                        data-href-base="{{ route('products.print') }}"
                        data-href-params="search,product_type"
                        href="{{ route('products.print', ['search' => $search, 'product_type' => $productType]) }}"
                        target="_blank"
                    >{{ __('txn.print') }}</a>
                    <x-export-menu
                        class="product-action-btn"
                        :options="[
                            [
                                'label' => 'Export PDF',
                                'url' => route('products.export.pdf', ['search' => $search, 'product_type' => $productType]),
                                'attributes' => [
                                    'data-ajax-sync' => '1',
                                    'data-href-base' => route('products.export.pdf'),
                                    'data-href-params' => 'search,product_type',
                                ],
                            ],
                            [
                                'label' => 'Export Excel',
                                'url' => route('products.export.csv', ['search' => $search, 'product_type' => $productType]),
                                'attributes' => [
                                    'data-ajax-sync' => '1',
                                    'data-href-base' => route('products.export.csv'),
                                    'data-href-params' => 'search,product_type',
                                ],
                            ],
                        ]"
                    />
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
                <form id="product-import-form" method="post"
                    data-analyze-url="{{ route('products.import.analyze') }}"
                    data-apply-url="{{ route('products.import.apply') }}"
                    data-problems-base="{{ url('products/import/problems') }}"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="product-import-file-wrap">
                        <label for="product-import-file">File Import</label>
                        <input id="product-import-file" type="file" name="import_file" accept=".xlsx,.xls,.csv,.txt" required>
                    </div>
                    <div class="muted" id="product-import-analyze-status" style="margin-top:8px; min-height:18px;"></div>
                    <div class="product-import-modal-actions">
                        <a class="btn info-btn product-action-btn" href="{{ route('products.import.template') }}">Download Template</a>
                        <button type="button" id="product-import-analyze-btn" class="btn process-btn product-action-btn">Cek &amp; Update Data</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="product-import-overlay" id="product-reconcile-modal" aria-hidden="true">
            <div class="product-import-modal" role="dialog" aria-modal="true" style="width:min(960px,97vw);">
                <div class="product-import-modal-head">
                    <div>
                        <div class="product-import-modal-title">Cek Kesamaan Data Barang</div>
                        <div class="muted" id="product-reconcile-summary">—</div>
                    </div>
                    <button type="button" class="btn info-btn product-action-btn" id="product-reconcile-close" style="min-height:32px; padding:5px 11px;">{{ __('ui.cancel') }}</button>
                </div>

                <div class="flex" style="gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:10px;">
                    <label class="flex" style="gap:6px; align-items:center;">
                        <input type="checkbox" id="product-reconcile-update-prices" checked>
                        <span>Perbarui harga sesuai file</span>
                    </label>
                    <label class="flex" style="gap:6px; align-items:center;">
                        <span class="muted">Set semua yang cocok:</span>
                        <select id="product-reconcile-bulk" class="action-menu action-menu-md">
                            <option value="">— pilih —</option>
                            <option value="update">Update terbaru</option>
                            <option value="add">Tambah stok</option>
                            <option value="subtract">Kurangi stok</option>
                            <option value="skip">Lewati</option>
                        </select>
                    </label>
                    <a id="product-reconcile-problems-link" class="btn info-btn product-action-btn" href="#" style="display:none;" target="_blank" rel="noopener">Download .xlsx bermasalah</a>
                </div>

                <div id="product-reconcile-body" style="max-height:60vh; overflow:auto;"></div>

                <div class="product-import-modal-actions">
                    <span class="muted" id="product-reconcile-apply-status" style="margin-right:auto;"></span>
                    <button type="button" class="btn info-btn product-action-btn" id="product-reconcile-cancel">{{ __('ui.cancel') }}</button>
                    <button type="button" class="btn process-btn product-action-btn" id="product-reconcile-apply">Proses</button>
                </div>
            </div>
        </div>
    @endif

    <div id="products-results">
        @include('products.partials.results')
    </div>

    @if($isAdmin)
        <div id="product-bulk-delete-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1220;"></div>
        <div id="product-bulk-delete-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:min(560px, calc(100vw - 24px)); max-height: 86vh; overflow:auto; background:var(--card); border:1px solid var(--border); border-radius:10px; padding:14px; z-index:1221;">
            <div class="flex" style="justify-content:space-between; margin-bottom:10px;">
                <strong>{{ __('ui.bulk_delete_products_title') }}</strong>
                <button type="button" id="product-bulk-delete-close" class="btn info-btn product-action-btn" style="min-height:30px; padding:4px 10px;">&times;</button>
            </div>
            <p class="muted" style="margin-top:0;">{{ __('ui.bulk_delete_products_modal_note') }}</p>
            <ul id="product-bulk-delete-list" style="margin: 0 0 12px 18px; max-height: 260px; overflow:auto;"></ul>
            <form id="product-bulk-delete-form" method="post" action="{{ route('products.bulk-destroy') }}">
                @csrf
                <div id="product-bulk-delete-inputs"></div>
                <div class="flex" style="gap:8px; justify-content:flex-end;">
                    <button type="button" id="product-bulk-delete-cancel" class="btn secondary">{{ __('ui.cancel') }}</button>
                    <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
                </div>
            </form>
        </div>
    @endif

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
        document.addEventListener('DOMContentLoaded', function () {
            let initProductBulkSelect = function () {};
            let initProductStockModalTriggers = function () {};

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
                const openBtn = document.getElementById('product-bulk-delete-open');
                const countEl = document.getElementById('product-bulk-delete-count');
                const modal = document.getElementById('product-bulk-delete-modal');
                const overlay = document.getElementById('product-bulk-delete-modal-overlay');
                const closeBtn = document.getElementById('product-bulk-delete-close');
                const cancelBtn = document.getElementById('product-bulk-delete-cancel');
                const list = document.getElementById('product-bulk-delete-list');
                const inputsWrap = document.getElementById('product-bulk-delete-inputs');

                if (!openBtn || !countEl || !modal || !overlay || !closeBtn || !cancelBtn || !list || !inputsWrap) {
                    return;
                }

                initProductBulkSelect = function () {
                    const selectAll = document.getElementById('product-bulk-select-all');
                    const checkboxes = Array.from(document.querySelectorAll('.js-product-bulk-checkbox'));

                    const refreshState = () => {
                        const selected = checkboxes.filter((checkbox) => checkbox.checked);
                        countEl.textContent = String(selected.length);
                        openBtn.disabled = selected.length === 0;
                        if (selectAll) {
                            selectAll.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
                            selectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
                        }
                    };

                    selectAll?.addEventListener('change', () => {
                        checkboxes.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
                        refreshState();
                    });
                    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', refreshState));
                    refreshState();
                };

                const escapeHtml = (value) => String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

                const closeModal = () => {
                    modal.style.display = 'none';
                    overlay.style.display = 'none';
                };

                openBtn.addEventListener('click', () => {
                    const selected = Array.from(document.querySelectorAll('.js-product-bulk-checkbox')).filter((checkbox) => checkbox.checked);
                    if (selected.length === 0) {
                        window.PgposDialog?.showMessage(@json(__('ui.bulk_delete_products_none_selected')));
                        return;
                    }

                    list.innerHTML = selected.map((checkbox) => {
                        const code = escapeHtml(checkbox.getAttribute('data-product-code') || '-');
                        const name = escapeHtml(checkbox.getAttribute('data-product-name') || '-');
                        return `<li>${code} — ${name}</li>`;
                    }).join('');

                    inputsWrap.innerHTML = selected.map((checkbox) =>
                        `<input type="hidden" name="product_ids[]" value="${escapeHtml(checkbox.value)}">`
                    ).join('');

                    modal.style.display = 'block';
                    overlay.style.display = 'block';
                    setTimeout(() => cancelBtn.focus(), 50);
                });

                closeBtn.addEventListener('click', closeModal);
                cancelBtn.addEventListener('click', closeModal);
                overlay.addEventListener('click', closeModal);
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal.style.display === 'block') {
                        closeModal();
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

                initProductStockModalTriggers = function () {
                    document.querySelectorAll('.js-open-product-stock-modal').forEach((button) => {
                        button.addEventListener('click', () => openModal(button));
                    });
                };
                initProductStockModalTriggers();

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

            initProductBulkSelect();

            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'products-search-form',
                container: 'products-results',
                onSwap: () => {
                    initProductBulkSelect();
                    initProductStockModalTriggers();
                },
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('products-search-input'), () => ajax.submit(), 420);
            window.PgposAutoSearch.bindChangeFilters([document.getElementById('products-type-filter')], () => ajax.submit());
        });
    </script>

    @if($canImportProducts)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('product-import-form');
            const analyzeBtn = document.getElementById('product-import-analyze-btn');
            const analyzeStatus = document.getElementById('product-import-analyze-status');
            const uploadModal = document.getElementById('product-import-modal');
            const reconcileModal = document.getElementById('product-reconcile-modal');
            const body = document.getElementById('product-reconcile-body');
            const summaryEl = document.getElementById('product-reconcile-summary');
            const problemsLink = document.getElementById('product-reconcile-problems-link');
            const bulkSelect = document.getElementById('product-reconcile-bulk');
            const updatePricesEl = document.getElementById('product-reconcile-update-prices');
            const applyBtn = document.getElementById('product-reconcile-apply');
            const applyStatus = document.getElementById('product-reconcile-apply-status');
            const cancelBtn = document.getElementById('product-reconcile-cancel');
            const closeBtn = document.getElementById('product-reconcile-close');
            if (!form || !analyzeBtn || !reconcileModal || !body) {
                return;
            }

            let currentToken = '';
            const csrf = () => (form.querySelector('input[name=_token]') || {}).value || '';
            const esc = (value) => String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const rupiah = (value) => 'Rp ' + Number(value || 0).toLocaleString('id-ID');

            const openReconcile = () => { reconcileModal.classList.add('is-open'); reconcileModal.setAttribute('aria-hidden', 'false'); };
            const closeReconcile = () => { reconcileModal.classList.remove('is-open'); reconcileModal.setAttribute('aria-hidden', 'true'); };

            function actionSelect(extraNew) {
                return '<select class="action-menu action-menu-sm js-recon-action">'
                    + '<option value="update">Update terbaru</option>'
                    + '<option value="add">Tambah stok</option>'
                    + '<option value="subtract">Kurangi stok</option>'
                    + (extraNew ? '<option value="new">Buat baru</option>' : '')
                    + '<option value="skip">Lewati</option>'
                    + '</select>';
            }

            function render(data) {
                let html = '';

                if (data.matched.length) {
                    html += '<h4 style="margin:6px 0;">Nama Cocok (' + data.matched.length + ')</h4>';
                    html += '<div class="products-table-wrap"><table class="products-table" style="min-width:0;"><thead><tr>'
                        + '<th>Kode</th><th>Nama</th><th>Stok DB → File</th><th>Harga umum DB → File</th><th>Aksi</th></tr></thead><tbody>';
                    data.matched.forEach((m) => {
                        html += '<tr class="js-recon-matched" data-row="' + m.row + '" data-product-id="' + m.product_id + '">'
                            + '<td>' + esc(m.code) + '</td>'
                            + '<td>' + esc(m.name_db) + '</td>'
                            + '<td>' + m.stock_db + ' → <strong>' + m.stock_file + '</strong></td>'
                            + '<td>' + rupiah(m.price_db.general) + ' → <strong>' + rupiah(m.price_file.general) + '</strong></td>'
                            + '<td>' + actionSelect(false) + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }

                if (data.new.length) {
                    html += '<h4 style="margin:14px 0 6px;">Barang Baru (' + data.new.length + ')</h4>';
                    html += '<div class="products-table-wrap"><table class="products-table" style="min-width:0;"><thead><tr>'
                        + '<th>Buat?</th><th>Nama</th><th>Kategori</th><th>Stok</th></tr></thead><tbody>';
                    data.new.forEach((n) => {
                        html += '<tr class="js-recon-new" data-row="' + n.row + '">'
                            + '<td><input type="checkbox" class="js-recon-new-check" checked></td>'
                            + '<td>' + esc(n.name) + '</td><td>' + esc(n.category) + '</td><td>' + n.stock_file + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }

                if (data.problems.length) {
                    html += '<h4 style="margin:14px 0 6px; color:#b45309;">Perlu Perhatian (' + data.problems.length + ')</h4>';
                    html += '<div class="products-table-wrap"><table class="products-table" style="min-width:0;"><thead><tr>'
                        + '<th>Baris</th><th>Nama di File</th><th>Masalah</th><th>Pilih Barang</th><th>Aksi</th></tr></thead><tbody>';
                    data.problems.forEach((p) => {
                        let pick = '<span class="muted">—</span>';
                        let action = '<span class="muted">perbaiki file</span>';
                        if (p.candidates && p.candidates.length) {
                            pick = '<select class="action-menu action-menu-sm js-recon-candidate">'
                                + p.candidates.map((c) => '<option value="' + c.id + '">' + esc(c.code) + ' · ' + esc(c.category) + ' · stok ' + c.stock + '</option>').join('')
                                + '</select>';
                            action = actionSelect(true);
                        }
                        html += '<tr class="js-recon-problem" data-row="' + p.row + '">'
                            + '<td>' + p.row + '</td><td>' + esc(p.name_file) + '</td>'
                            + '<td style="white-space:normal;">' + esc(p.reason) + '</td>'
                            + '<td>' + pick + '</td><td>' + action + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }

                if (!html) {
                    html = '<p class="muted">Tidak ada data untuk diproses.</p>';
                }
                body.innerHTML = html;

                // problem rows: default action skip
                body.querySelectorAll('.js-recon-problem .js-recon-action').forEach((sel) => { sel.value = 'skip'; });
            }

            analyzeBtn.addEventListener('click', function () {
                const fileInput = document.getElementById('product-import-file');
                if (!fileInput || !fileInput.files || !fileInput.files.length) {
                    analyzeStatus.textContent = 'Pilih file dulu.';
                    return;
                }
                const fd = new FormData();
                fd.append('import_file', fileInput.files[0]);
                fd.append('_token', csrf());
                analyzeBtn.disabled = true;
                analyzeStatus.textContent = 'Memeriksa file…';
                fetch(form.getAttribute('data-analyze-url'), {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd,
                })
                    .then((r) => r.json().then((j) => ({ ok: r.ok, j })))
                    .then(({ ok, j }) => {
                        if (!ok) { analyzeStatus.textContent = j.message || 'Gagal memeriksa file.'; return; }
                        currentToken = j.token;
                        summaryEl.textContent = 'Baru: ' + j.summary.new + ' · Cocok: ' + j.summary.matched + ' · Perlu Perhatian: ' + j.summary.problems;
                        render(j);
                        if (j.summary.problems > 0) {
                            problemsLink.href = form.getAttribute('data-problems-base') + '/' + encodeURIComponent(currentToken);
                            problemsLink.style.display = '';
                        } else {
                            problemsLink.style.display = 'none';
                        }
                        analyzeStatus.textContent = '';
                        uploadModal.classList.remove('is-open');
                        uploadModal.setAttribute('aria-hidden', 'true');
                        openReconcile();
                    })
                    .catch(() => { analyzeStatus.textContent = 'Terjadi kesalahan jaringan.'; })
                    .finally(() => { analyzeBtn.disabled = false; });
            });

            bulkSelect.addEventListener('change', function () {
                if (!this.value) { return; }
                body.querySelectorAll('.js-recon-matched .js-recon-action').forEach((sel) => { sel.value = this.value; });
            });

            applyBtn.addEventListener('click', function () {
                const decisions = [];
                body.querySelectorAll('.js-recon-matched').forEach((tr) => {
                    const action = tr.querySelector('.js-recon-action').value;
                    if (action === 'skip') { return; }
                    decisions.push({ row: Number(tr.dataset.row), action: action, target_product_id: Number(tr.dataset.productId) });
                });
                body.querySelectorAll('.js-recon-new').forEach((tr) => {
                    if (tr.querySelector('.js-recon-new-check').checked) {
                        decisions.push({ row: Number(tr.dataset.row), action: 'new' });
                    }
                });
                body.querySelectorAll('.js-recon-problem').forEach((tr) => {
                    const sel = tr.querySelector('.js-recon-action');
                    if (!sel) { return; }
                    const action = sel.value;
                    if (action === 'skip') { return; }
                    if (action === 'new') { decisions.push({ row: Number(tr.dataset.row), action: 'new' }); return; }
                    const cand = tr.querySelector('.js-recon-candidate');
                    if (!cand) { return; }
                    decisions.push({ row: Number(tr.dataset.row), action: action, target_product_id: Number(cand.value) });
                });

                if (!decisions.length) { applyStatus.textContent = 'Tidak ada aksi dipilih.'; return; }
                applyBtn.disabled = true;
                applyStatus.textContent = 'Memproses…';
                fetch(form.getAttribute('data-apply-url'), {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ token: currentToken, update_prices: updatePricesEl.checked, decisions: decisions }),
                })
                    .then((r) => r.json().then((j) => ({ ok: r.ok, j })))
                    .then(({ ok, j }) => {
                        if (!ok) { applyStatus.textContent = j.message || 'Gagal memproses.'; applyBtn.disabled = false; return; }
                        applyStatus.textContent = j.message || 'Selesai.';
                        setTimeout(() => { window.location.reload(); }, 700);
                    })
                    .catch(() => { applyStatus.textContent = 'Terjadi kesalahan jaringan.'; applyBtn.disabled = false; });
            });

            [cancelBtn, closeBtn].forEach((btn) => btn && btn.addEventListener('click', closeReconcile));
            reconcileModal.addEventListener('click', (e) => { if (e.target === reconcileModal) { closeReconcile(); } });
        });
    </script>
    @endif
@endsection
