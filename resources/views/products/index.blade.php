@extends('layouts.app')

@section('title', __('ui.products_title').' - PgPOS ERP')

@section('content')
    <style>
        .product-action-btn {
            padding: 4px 8px;
            font-size: 12px;
            line-height: 1.2;
        }
    </style>
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.products_title') }}</h1>
        <a class="btn" href="{{ route('products.create') }}">{{ __('ui.add_product') }}</a>
    </div>

    <div class="card">
        <form id="products-search-form" method="get" class="flex">
            <input id="products-search-input" type="text" name="search" placeholder="{{ __('ui.search_products_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <button type="submit">{{ __('ui.search') }}</button>
            <div style="margin-left: auto;">
                <a class="btn secondary product-action-btn" href="{{ route('products.export.csv', ['search' => $search]) }}">{{ __('txn.excel') }}</a>
                <a class="btn secondary product-action-btn" href="{{ route('products.import.template') }}">Template Import</a>
            </div>
        </form>
        <form method="post" action="{{ route('products.import') }}" enctype="multipart/form-data" class="flex" style="margin-top:8px;">
            @csrf
            <input type="file" name="import_file" accept=".xlsx,.xls,.csv,.txt" required style="max-width:320px;">
            <button type="submit" class="btn secondary product-action-btn">Import</button>
        </form>
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

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>{{ __('ui.code') }}</th>
                <th>{{ __('ui.name') }}</th>
                <th>{{ __('ui.category') }}</th>
                <th>{{ __('ui.stock') }}</th>
                <th>{{ __('ui.stock_alert') }}</th>
                <th>{{ __('ui.price_agent') }}</th>
                <th>{{ __('ui.price_sales') }}</th>
                <th>{{ __('ui.price_general') }}</th>
                <th>{{ __('ui.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($products as $product)
                <tr>
                    <td>
                        @if($product->code)
                            <a href="{{ route('products.mutations', ['product' => $product, 'mutation_page' => 1]) }}#stock-mutations">{{ $product->code }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category?->name ?: '-' }}</td>
                    <td>
                        <strong class="js-product-stock-value" data-product-id="{{ (int) $product->id }}">
                            {{ number_format((int) round($product->stock), 0, ',', '.') }}
                        </strong>
                    </td>
                    <td>
                        @if((int) round($product->stock) <= 0)
                            <span class="badge danger js-product-stock-alert" data-product-id="{{ (int) $product->id }}">{{ __('ui.stock_alert_low') }}</span>
                        @else
                            <span class="badge success js-product-stock-alert" data-product-id="{{ (int) $product->id }}">{{ __('ui.stock_alert_ok') }}</span>
                        @endif
                    </td>
                    <td>Rp {{ number_format((int) round($product->price_agent), 0, ',', '.') }}</td>
                    <td>Rp {{ number_format((int) round($product->price_sales), 0, ',', '.') }}</td>
                    <td>Rp {{ number_format((int) round($product->price_general), 0, ',', '.') }}</td>
                    <td>
                        <div class="flex">
                            <button
                                type="button"
                                class="btn secondary product-action-btn js-open-product-stock-modal"
                                data-product-id="{{ (int) $product->id }}"
                                data-product-code="{{ (string) ($product->code ?? '') }}"
                                data-product-name="{{ (string) ($product->name ?? '') }}"
                                data-current-stock="{{ (int) round($product->stock) }}"
                                data-update-url="{{ route('products.quick-stock', $product) }}"
                            >
                                {{ __('ui.edit_stock') }}
                            </button>
                            <a class="btn secondary product-action-btn" href="{{ route('products.mutations', $product) }}">{{ __('ui.stock_mutations_title') }}</a>
                            <a class="btn secondary product-action-btn" href="{{ route('products.edit', $product) }}">{{ __('ui.edit') }}</a>
                            <form method="post" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('{{ __('ui.confirm_delete_product') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn product-action-btn">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">{{ __('ui.no_products') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $products->links() }}
        </div>
    </div>

    <div id="product-stock-edit-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1200;"></div>
    <div id="product-stock-edit-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:min(520px, calc(100vw - 24px)); background:var(--card); border:1px solid var(--border); border-radius:10px; padding:14px; z-index:1201;">
        <div class="flex" style="justify-content:space-between; margin-bottom:10px;">
            <strong>{{ __('ui.edit_stock') }}</strong>
            <button type="button" id="product-stock-edit-close" class="btn secondary product-action-btn" style="min-height:30px; padding:4px 10px;">&times;</button>
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
                    <input type="number" id="product-stock-edit-current-stock" value="" disabled>
                </div>
                <div class="col-6">
                    <label>{{ __('ui.new_stock') }}</label>
                    <input type="number" min="0" name="stock" id="product-stock-edit-new-stock" value="" required>
                </div>
            </div>
            <div class="muted" id="product-stock-edit-status" style="margin-top:6px;">{{ __('ui.auto_save_hint') }}</div>
        </form>
    </div>

    <script>
        (function () {
            const form = document.getElementById('products-search-form');
            const searchInput = document.getElementById('products-search-input');

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

                const alertEl = document.querySelector('.js-product-stock-alert[data-product-id="' + String(productId) + '"]');
                if (alertEl) {
                    const isLow = stockValue <= 0;
                    alertEl.textContent = isLow ? @json(__('ui.stock_alert_low')) : @json(__('ui.stock_alert_ok'));
                    alertEl.classList.toggle('danger', isLow);
                    alertEl.classList.toggle('success', !isLow);
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
                const current = Number(currentStockInput.value || '0');
                const next = Number(newStockInput.value || '0');
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
                const current = Number(currentStockInput.value || '0');
                const next = Number(newStockInput.value || '0');
                if (Number.isNaN(next) || next < 0) return;
                if (current === next) return;
                if (isSubmitting) return;
                if (!form.action) return;

                isSubmitting = true;
                statusText.textContent = @json(__('ui.saving'));
                const payload = new FormData(form);
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
