@extends('layouts.app')

@section('title', __('supplier_stock.title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .supplier-stock-scroll-wrap {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 460px;
            border: 1px solid color-mix(in srgb, var(--border) 75%, var(--text) 25%);
            border-radius: 8px;
            scrollbar-gutter: stable both-edges;
        }
        .supplier-stock-scroll-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--card);
        }
        .supplier-stock-summary-table,
        .supplier-stock-mutation-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .supplier-stock-summary-table {
            min-width: 860px;
        }
        .supplier-stock-mutation-table {
            min-width: 960px;
        }
        .supplier-stock-summary-table td,
        .supplier-stock-summary-table th,
        .supplier-stock-mutation-table td,
        .supplier-stock-mutation-table th {
            border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            vertical-align: middle;
            padding: 10px 8px;
        }
        .supplier-stock-summary-table td.num,
        .supplier-stock-summary-table th.num,
        .supplier-stock-mutation-table td.num,
        .supplier-stock-mutation-table th.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .supplier-stock-summary-table td.action,
        .supplier-stock-summary-table th.action {
            width: 1%;
            white-space: nowrap;
        }
        .supplier-stock-toolbar-export {
            width: auto;
            min-width: 130px;
            max-width: 150px;
        }
        .sort-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
    </style>
    <h1 class="page-title">{{ __('supplier_stock.title') }}</h1>

    <div class="card">
        <form method="get" class="flex" id="supplier-stock-filter-form">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <select name="supplier_id" id="supplier-stock-supplier" style="max-width:260px;">
                <option value="">{{ __('supplier_stock.select_supplier') }}</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((int) ($selectedSupplierId ?? 0) === (int) $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
            <input id="supplier-stock-search" type="text" name="search" value="{{ $search }}" placeholder="{{ __('supplier_stock.search_placeholder') }}" style="max-width:280px;">
            <input id="supplier-stock-date-from" type="date" name="date_from" value="{{ $dateFrom }}" style="max-width:180px;">
            <input id="supplier-stock-date-to" type="date" name="date_to" value="{{ $dateTo }}" style="max-width:180px;">
            <button type="submit">{{ __('txn.search') }}</button>
            <a
                class="btn info-btn"
                data-ajax-sync
                data-href-base="{{ route('supplier-stock-cards.print') }}"
                data-href-params="supplier_id,product_id,search,date_from,date_to"
                href="{{ route('supplier-stock-cards.print', ['supplier_id' => $selectedSupplierId, 'product_id' => $selectedProductId, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                target="_blank"
            >{{ __('txn.print') }}</a>
            <x-export-menu
                class="supplier-stock-toolbar-export"
                mode="location"
                :options="[
                    [
                        'label' => 'Export PDF',
                        'url' => route('supplier-stock-cards.export.pdf', ['supplier_id' => $selectedSupplierId, 'product_id' => $selectedProductId, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo]),
                        'attributes' => [
                            'data-ajax-sync' => '1',
                            'data-href-base' => route('supplier-stock-cards.export.pdf'),
                            'data-href-params' => 'supplier_id,product_id,search,date_from,date_to',
                        ],
                    ],
                    [
                        'label' => 'Export Excel',
                        'url' => route('supplier-stock-cards.export.excel', ['supplier_id' => $selectedSupplierId, 'product_id' => $selectedProductId, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo]),
                        'attributes' => [
                            'data-ajax-sync' => '1',
                            'data-href-base' => route('supplier-stock-cards.export.excel'),
                            'data-href-params' => 'supplier_id,product_id,search,date_from,date_to',
                        ],
                    ],
                ]"
            />
            <a class="btn process-btn" href="{{ route('products.index') }}?search=&product_type=raw_material">Lihat Stok</a>
            <a class="btn secondary" href="{{ route('supplier-stock-cards.index') }}">{{ __('txn.all') }}</a>
        </form>
    </div>

    <div id="supplier-stock-results">
        @include('supplier_stock_cards.partials.results')
    </div>

    <div id="stock-edit-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1200;"></div>
    <div id="stock-edit-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:min(520px, calc(100vw - 24px)); background:var(--card); border:1px solid var(--border); border-radius:10px; padding:14px; z-index:1201;">
        <div class="flex" style="justify-content:space-between; margin-bottom:10px;">
            <strong>{{ __('supplier_stock.edit_stock') }}</strong>
            <button type="button" id="stock-edit-close" class="btn info-btn" style="min-height:30px; padding:4px 10px;">×</button>
        </div>

        <form id="stock-edit-form" method="post" action="{{ route('supplier-stock-cards.update-stock') }}">
            @csrf
            <input type="hidden" name="product_id" id="stock-edit-product-id" value="">
            <input type="hidden" name="product_code" id="stock-edit-product-code" value="">
            <input type="hidden" name="product_name" id="stock-edit-product-name" value="">
            <input type="hidden" name="supplier_id" id="stock-edit-supplier-id" value="">
            <input type="hidden" id="stock-edit-row-key" value="">
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="date_from" value="{{ $dateFrom }}">
            <input type="hidden" name="date_to" value="{{ $dateTo }}">

            <div class="row">
                <div class="col-12">
                    <label>{{ __('txn.supplier') }}</label>
                    <input type="text" id="stock-edit-supplier-name" value="" disabled>
                </div>
                <div class="col-12">
                    <label>{{ __('txn.name') }}</label>
                    <input type="text" id="stock-edit-item-name" value="" disabled>
                </div>
                <div class="col-6">
                    <label>{{ __('supplier_stock.current_stock') }}</label>
                    <input type="text" class="js-thousand-input" id="stock-edit-current-stock" value="" disabled>
                </div>
                <div class="col-6">
                    <label>{{ __('supplier_stock.new_stock') }}</label>
                    <input type="text" inputmode="numeric" class="js-thousand-input" name="stock" id="stock-edit-new-stock" value="" required>
                </div>
            </div>
            <div class="muted" id="stock-edit-status" style="margin-top:6px;">{{ __('supplier_stock.auto_save_hint') }}</div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('stock-edit-modal');
            const overlay = document.getElementById('stock-edit-modal-overlay');
            const closeBtn = document.getElementById('stock-edit-close');
            const form = document.getElementById('stock-edit-form');
            const statusText = document.getElementById('stock-edit-status');
            const productIdInput = document.getElementById('stock-edit-product-id');
            const productCodeInput = document.getElementById('stock-edit-product-code');
            const productNameInput = document.getElementById('stock-edit-product-name');
            const supplierIdInput = document.getElementById('stock-edit-supplier-id');
            const supplierNameInput = document.getElementById('stock-edit-supplier-name');
            const itemNameInput = document.getElementById('stock-edit-item-name');
            const currentStockInput = document.getElementById('stock-edit-current-stock');
            const newStockInput = document.getElementById('stock-edit-new-stock');
            const rowKeyInput = document.getElementById('stock-edit-row-key');

            let saveTimer = null;
            let isSubmitting = false;
            let originalStock = 0;
            const AUTO_SAVE_DELAY_MS = 5000;

            const openModal = (button) => {
                const productId = Number(button.getAttribute('data-product-id') || '0');
                const supplierId = Number(button.getAttribute('data-supplier-id') || '0');
                productIdInput.value = String(productId);
                productCodeInput.value = String(button.getAttribute('data-product-code') || '');
                productNameInput.value = String(button.getAttribute('data-product-name') || '');
                supplierIdInput.value = String(supplierId);
                rowKeyInput.value = String(button.getAttribute('data-row-key') || '');
                supplierNameInput.value = String(button.getAttribute('data-supplier-name') || '-');
                itemNameInput.value = String(button.getAttribute('data-product-name') || '-');
                currentStockInput.value = String(button.getAttribute('data-current-stock') || '0');
                newStockInput.value = String(button.getAttribute('data-current-stock') || '0');
                window.PgposNumberFormat.formatInput(currentStockInput);
                window.PgposNumberFormat.formatInput(newStockInput);
                originalStock = Number(button.getAttribute('data-current-stock') || '0');
                statusText.textContent = @json(__('supplier_stock.auto_save_hint'));
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
            const setStockText = (rowKey, value) => {
                if (!rowKey) return;
                const stockEls = document.querySelectorAll('.js-stock-value[data-row-key="' + rowKey + '"]');
                const displayText = Number(value).toLocaleString('id-ID', { maximumFractionDigits: 0 });
                stockEls.forEach((el) => {
                    el.textContent = displayText;
                    el.style.color = Number(value) <= 10 ? '#b91c1c' : '';
                });
                const rowButtons = document.querySelectorAll('.js-open-stock-modal[data-row-key="' + rowKey + '"]');
                rowButtons.forEach((button) => {
                    button.setAttribute('data-current-stock', String(value));
                });
            };
            const triggerAutoSave = () => {
                const current = window.PgposNumberFormat.parseInt(currentStockInput.value || '0');
                const next = window.PgposNumberFormat.parseInt(newStockInput.value || '0');
                if (Number.isNaN(next) || next < 0) return;
                if (current === next) return;
                if (isSubmitting) return;
                isSubmitting = true;
                statusText.textContent = @json(__('supplier_stock.saving'));
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
                            let errMsg = @json(__('supplier_stock.save_failed'));
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
                        currentStockInput.value = String(savedStock);
                        newStockInput.value = String(savedStock);
                        window.PgposNumberFormat.formatInput(currentStockInput);
                        window.PgposNumberFormat.formatInput(newStockInput);
                        originalStock = savedStock;
                        setStockText(rowKeyInput.value, savedStock);
                        const serverMessage = String(data.message || @json(__('supplier_stock.saved')));
                        const serverType = String(data.message_type || 'edit');
                        statusText.textContent = serverMessage;
                        if (window.PgposFlash && typeof window.PgposFlash.show === 'function') {
                            window.PgposFlash.show(serverMessage, serverType);
                        }
                        setTimeout(() => closeModal(), 250);
                    })
                    .catch((error) => {
                        setStockText(rowKeyInput.value, originalStock);
                        currentStockInput.value = String(originalStock);
                        newStockInput.value = String(originalStock);
                        window.PgposNumberFormat.formatInput(currentStockInput);
                        window.PgposNumberFormat.formatInput(newStockInput);
                        statusText.textContent = error.message || @json(__('supplier_stock.save_failed'));
                    })
                    .finally(() => {
                        isSubmitting = false;
                    });
            };

            const scheduleAutoSave = () => {
                if (saveTimer) clearTimeout(saveTimer);
                saveTimer = setTimeout(triggerAutoSave, AUTO_SAVE_DELAY_MS);
            };

            function initStockEditModalTriggers() {
                document.querySelectorAll('.js-open-stock-modal').forEach((button) => {
                    button.addEventListener('click', () => openModal(button));
                });
            }

            if (modal && overlay && closeBtn && form && newStockInput) {
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
                initStockEditModalTriggers();
            }

            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'supplier-stock-filter-form',
                container: 'supplier-stock-results',
                onSwap: () => {
                    const params = new URLSearchParams(window.location.search);
                    const supplierSelect = document.getElementById('supplier-stock-supplier');
                    if (supplierSelect) {
                        supplierSelect.value = params.get('supplier_id') || '';
                    }
                    initStockEditModalTriggers();
                },
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('supplier-stock-search'), () => ajax.submit(), 100);
            window.PgposAutoSearch.bindChangeFilters([
                document.getElementById('supplier-stock-supplier'),
                document.getElementById('supplier-stock-date-from'),
                document.getElementById('supplier-stock-date-to'),
            ], () => ajax.submit());
        });
    </script>
@endsection
