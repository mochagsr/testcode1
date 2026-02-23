@extends('layouts.app')

@section('title', __('supplier_stock.title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('supplier_stock.title') }}</h1>

    <div class="card">
        <form method="get" class="flex" id="supplier-stock-filter-form">
            <select name="supplier_id" id="supplier-stock-supplier" style="max-width:260px;">
                <option value="">{{ __('supplier_stock.select_supplier') }}</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((int) ($selectedSupplierId ?? 0) === (int) $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
            @if($selectedProductId)
                <input type="hidden" name="product_id" value="{{ (int) $selectedProductId }}">
            @endif
            <input id="supplier-stock-search" type="text" name="search" value="{{ $search }}" placeholder="{{ __('supplier_stock.search_placeholder') }}" style="max-width:280px;">
            <input type="date" name="date_from" value="{{ $dateFrom }}" style="max-width:180px;">
            <input type="date" name="date_to" value="{{ $dateTo }}" style="max-width:180px;">
            <button type="submit">{{ __('txn.search') }}</button>
            <a class="btn secondary" href="{{ route('supplier-stock-cards.index') }}">{{ __('txn.all') }}</a>
        </form>
    </div>

    @if($selectedProductId)
        <div class="card" style="padding:10px 12px;">
            <span class="muted">{{ __('txn.product') }} ID: {{ (int) $selectedProductId }}</span>
            <a class="btn secondary" style="margin-left:8px;" href="{{ route('supplier-stock-cards.index', array_merge(request()->except('product_id'), ['supplier_id' => $selectedSupplierId])) }}">{{ __('txn.all') }}</a>
        </div>
    @endif

    @if(!$selectedSupplier)
        <div class="card">
            <h3 style="margin-top:0;">{{ __('supplier_stock.product_summary') }}</h3>
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.supplier') }}</th>
                    <th>{{ __('txn.name') }}</th>
                    <th>{{ __('ui.stock') }}</th>
                    <th>{{ __('txn.action') }}</th>
                </tr>
                </thead>
                <tbody>
                @php($lastSupplierId = null)
                @forelse($summaryPaginator as $row)
                    @php($supplierId = (int) ($row['supplier_id'] ?? 0))
                    @php($stockBalance = (int) ($row['balance'] ?? 0))
                    @php($editableProductId = (int) ($row['editable_product_id'] ?? 0))
                    @php($rowKey = md5(($row['product_code'] ?? '').'|'.($row['product_name'] ?? '').'|'.$supplierId.'|'.$editableProductId))
                    <tr>
                        <td>
                            @if($supplierId > 0 && $lastSupplierId !== $supplierId)
                                <a href="{{ route('supplier-stock-cards.index', array_merge(request()->query(), ['supplier_id' => $supplierId])) }}">
                                    {{ $row['supplier_name'] ?? '-' }}
                                </a>
                                @php($lastSupplierId = $supplierId)
                            @endif
                        </td>
                        <td>{{ $row['product_name'] }}</td>
                        <td>
                            <strong
                                class="js-stock-value"
                                data-row-key="{{ $rowKey }}"
                                style="{{ $stockBalance <= 10 ? 'color:#b91c1c;' : '' }}"
                            >
                                {{ number_format($stockBalance, 0, ',', '.') }}
                            </strong>
                        </td>
                        <td>
                            <button
                                type="button"
                                class="btn secondary js-open-stock-modal"
                                style="min-height:32px; padding:6px 10px; margin-left:12px; position:relative; z-index:1;"
                                data-row-key="{{ $rowKey }}"
                                data-product-id="{{ $editableProductId }}"
                                data-product-code="{{ $row['product_code'] ?? '' }}"
                                data-product-name="{{ $row['product_name'] ?? '' }}"
                                data-supplier-id="{{ $supplierId }}"
                                data-supplier-name="{{ $row['supplier_name'] ?? '-' }}"
                                data-current-stock="{{ $stockBalance }}"
                            >
                                {{ __('supplier_stock.edit_stock') }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('supplier_stock.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            <div style="margin-top:12px;">{{ $summaryPaginator->links() }}</div>
        </div>
    @endif

    @if($selectedSupplier)
        <div class="card">
            <h3 style="margin-top:0;">{{ __('supplier_stock.mutation_title') }} ({{ $selectedSupplier->name }})</h3>
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.date') }}</th>
                    <th>{{ __('txn.product') }}</th>
                    <th>{{ __('supplier_stock.description') }}</th>
                    <th>{{ __('supplier_stock.in') }}</th>
                    <th>{{ __('supplier_stock.out') }}</th>
                    <th>{{ __('supplier_stock.balance') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($movementPaginator as $row)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($row['event_date'])->format('d-m-Y') }}</td>
                        <td>
                            <div>{{ $row['product_name'] }}</div>
                            <div class="muted">{{ $row['product_code'] !== '' ? $row['product_code'] : '-' }}</div>
                        </td>
                        <td>
                            {{ $row['description'] }}
                            @if((int) ($row['reference_id'] ?? 0) > 0 && (string) ($row['reference_number'] ?? '') !== '' && (string) ($row['reference_route'] ?? '') !== '')
                                <div>
                                    <a href="{{ route($row['reference_route'], $row['reference_id']) }}" target="_blank">{{ $row['reference_number'] }}</a>
                                </div>
                            @endif
                        </td>
                        <td style="color:#1f6b3d;">{{ (int) $row['qty_in'] > 0 ? number_format((int) $row['qty_in'], 0, ',', '.') : '-' }}</td>
                        <td style="color:#8d1f1f;">{{ (int) $row['qty_out'] > 0 ? number_format((int) $row['qty_out'], 0, ',', '.') : '-' }}</td>
                        <td><strong>{{ number_format((int) $row['balance_after'], 0, ',', '.') }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">{{ __('supplier_stock.no_mutation') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            <div style="margin-top:12px;">{{ $movementPaginator->links() }}</div>
        </div>
    @endif

    <div id="stock-edit-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1200;"></div>
    <div id="stock-edit-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:min(520px, calc(100vw - 24px)); background:var(--card); border:1px solid var(--border); border-radius:10px; padding:14px; z-index:1201;">
        <div class="flex" style="justify-content:space-between; margin-bottom:10px;">
            <strong>{{ __('supplier_stock.edit_stock') }}</strong>
            <button type="button" id="stock-edit-close" class="btn secondary" style="min-height:30px; padding:4px 10px;">×</button>
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
                    <input type="number" id="stock-edit-current-stock" value="" disabled>
                </div>
                <div class="col-6">
                    <label>{{ __('supplier_stock.new_stock') }}</label>
                    <input type="number" min="0" name="stock" id="stock-edit-new-stock" value="" required>
                </div>
            </div>
            <div class="muted" id="stock-edit-status" style="margin-top:6px;">{{ __('supplier_stock.auto_save_hint') }}</div>
        </form>
    </div>

    <script>
        (function () {
            const form = document.getElementById('supplier-stock-filter-form');
            const searchInput = document.getElementById('supplier-stock-search');
            const supplierSelect = document.getElementById('supplier-stock-supplier');
            if (!form || !searchInput || !supplierSelect) return;

            const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                ? window.PgposAutoSearch.debounce
                : (fn, wait = 100) => { let t = null; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), wait); }; };

            const onInput = debounce(() => {
                if (window.PgposAutoSearch && !window.PgposAutoSearch.canSearchInput(searchInput)) return;
                form.requestSubmit();
            }, 100);

            searchInput.addEventListener('input', onInput);
            supplierSelect.addEventListener('change', () => form.requestSubmit());
        })();

        (function () {
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
            if (!modal || !overlay || !closeBtn || !form || !newStockInput) return;

            let saveTimer = null;
            let isSubmitting = false;
            let originalStock = 0;
            const AUTO_SAVE_DELAY_MS = 5000;

            const openButtons = document.querySelectorAll('.js-open-stock-modal');
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
                const current = Number(currentStockInput.value || '0');
                const next = Number(newStockInput.value || '0');
                if (Number.isNaN(next) || next < 0) return;
                if (current === next) return;
                if (isSubmitting) return;
                isSubmitting = true;
                statusText.textContent = @json(__('supplier_stock.saving'));
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

            openButtons.forEach((button) => {
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
