@extends('layouts.app')

@section('title', __('txn.invoice').' '.__('txn.detail').' - PgPOS ERP')

@section('content')
    @php
        $hasCashOnCreate = $invoice->payments->contains(function ($payment) use ($invoice): bool {
            return strtolower((string) $payment->method) === 'cash'
                && optional($payment->payment_date)->format('Y-m-d') === optional($invoice->invoice_date)->format('Y-m-d')
                && (float) $payment->amount >= (float) $invoice->total;
        });
        $paidFromCustomerBalance = (float) $invoice->payments
            ->where('method', 'customer_balance')
            ->sum('amount');
        $paidCash = max(0, (float) $invoice->total_paid - $paidFromCustomerBalance);
        $displayPaymentMethod = $hasCashOnCreate ? __('txn.cash') : __('txn.credit');
        $paymentStatusLabel = match ($invoice->payment_status) {
            'paid' => __('txn.status_paid'),
            default => __('txn.status_unpaid'),
        };
        $transactionStatusLabel = $invoice->is_canceled ? __('txn.status_canceled') : $paymentStatusLabel;
        $isCustomerSemesterLocked = (bool) ($customerSemesterLockState['locked'] ?? false);
        $isCreditReceivableTransaction = ! $hasCashOnCreate;
        $isPaidTransactionLocked = $hasCashOnCreate || $invoice->payment_status === 'paid';
        $isCreditSemesterLocked = $isCreditReceivableTransaction && $isCustomerSemesterLocked;
        $requiresAdminToEdit = $isPaidTransactionLocked || $isCreditSemesterLocked;
        $isAdminUser = (auth()->user()?->role ?? '') === 'admin';
        $adminProducts = $products->map(function ($product): array {
            return [
                'id' => (int) $product->id,
                'code' => (string) ($product->code ?? ''),
                'name' => (string) $product->name,
                'price_agent' => (int) round((float) ($product->price_agent ?? 0)),
                'price_sales' => (int) round((float) ($product->price_sales ?? 0)),
                'price_general' => (int) round((float) ($product->price_general ?? 0)),
            ];
        })->values()->all();
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
    </style>
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.invoice') }} {{ $invoice->invoice_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('sales-invoices.index') }}">{{ __('txn.back') }}</a>
            @if($isAdminUser)
                <button type="button" class="btn secondary" id="open-admin-edit-modal">{{ __('txn.edit_transaction') }}</button>
            @elseif($requiresAdminToEdit)
                <button type="button" class="btn secondary" onclick="alert(@js(__('txn.contact_admin_to_edit_locked')))">{{ __('txn.edit_transaction') }}</button>
            @endif
            <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('sales-invoices.print', $invoice) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('sales-invoices.export.pdf', $invoice) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('sales-invoices.export.excel', $invoice) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.invoice_summary') }}</h3>
            <p class="form-section-note">{{ __('txn.invoice_summary_note') }}</p>
            <div class="row">
                <div class="col-4"><strong>{{ __('txn.customer') }}</strong><div>{{ $invoice->customer->name }}</div></div>
                <div class="col-4"><strong>{{ __('txn.city') }}</strong><div>{{ $invoice->customer->city }}</div></div>
                <div class="col-4"><strong>{{ __('txn.phone') }}</strong><div>{{ $invoice->customer->phone ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.invoice_date') }}</strong><div>{{ $invoice->invoice_date->format('d-m-Y') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.semester_period') }}</strong><div>{{ $invoice->semester_period ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.status') }}</strong><div>{{ $transactionStatusLabel }}</div></div>
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
                <div class="col-4"><strong>{{ __('txn.payment_method') }}</strong><div>{{ $displayPaymentMethod }}</div></div>
                <div class="col-4"><strong>{{ __('txn.total') }}</strong><div>Rp {{ number_format((int) round($invoice->total), 0, ',', '.') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.paid') }}</strong><div>Rp {{ number_format((int) round($invoice->total_paid), 0, ',', '.') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.balance') }}</strong><div>Rp {{ number_format((int) round($invoice->balance), 0, ',', '.') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.locked_paid_transaction') }}</strong><div>{{ $isPaidTransactionLocked ? __('txn.status_paid') : '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.paid_cash') }}</strong><div>Rp {{ number_format((int) round($paidCash), 0, ',', '.') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.paid_customer_balance') }}</strong><div>Rp {{ number_format((int) round($paidFromCustomerBalance), 0, ',', '.') }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.items') }}</h3>
            <p class="form-section-note">{{ __('txn.invoice_items_note') }}</p>
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.product') }}</th>
                    <th>{{ __('txn.qty') }}</th>
                    <th>{{ __('txn.price') }}</th>
                    <th>{{ __('txn.discount') }}</th>
                    <th>{{ __('txn.line_total') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($invoice->items as $item)
                    @php
                        $gross = (float) $item->quantity * (float) $item->unit_price;
                        $discountPercent = $gross > 0 ? (float) $item->discount / $gross * 100 : 0;
                    @endphp
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ (int) round($item->quantity) }}</td>
                        <td>Rp {{ number_format((int) round($item->unit_price), 0, ',', '.') }}</td>
                        <td>{{ (int) round($discountPercent) }}%</td>
                        <td>Rp {{ number_format((int) round($item->line_total), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="form-section">
                    <h3 class="form-section-title">{{ __('txn.record_payment') }}</h3>
                    <p class="form-section-note">{{ __('txn.payments_note') }}</p>
                    <table>
                        <thead>
                        <tr>
                            <th>{{ __('txn.date') }}</th>
                            <th>{{ __('txn.method') }}</th>
                            <th>{{ __('txn.amount') }}</th>
                            <th>{{ __('txn.notes') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($invoice->payments as $payment)
                            @php
                                $paymentMethodLabel = match (strtolower((string) $payment->method)) {
                                    'customer_balance' => __('txn.customer_balance'),
                                    'cash' => __('txn.cash'),
                                    'writeoff' => __('txn.writeoff'),
                                    'discount' => __('receivable.method_discount'),
                                    default => __('txn.credit'),
                                };
                            @endphp
                            <tr>
                                <td>{{ $payment->payment_date->format('d-m-Y') }}</td>
                                <td>{{ $paymentMethodLabel }}</td>
                                <td>Rp {{ number_format((int) round($payment->amount), 0, ',', '.') }}</td>
                                <td>{{ $payment->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">{{ __('txn.no_payments_yet') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($isAdminUser)
        <div id="admin-edit-modal" class="txn-modal" aria-hidden="true">
            <div class="card txn-modal-card" id="admin-edit-transaction">
                <div class="form-section">
                    <div class="txn-modal-header">
                        <h3 class="form-section-title" style="margin: 0;">{{ __('txn.admin_actions') }}</h3>
                        <button type="button" class="btn secondary" id="close-admin-edit-modal">{{ __('txn.cancel') }}</button>
                    </div>
                    <p class="form-section-note">{{ __('txn.edit_transaction') }}</p>
                    @if($invoice->is_canceled)
                        <p class="muted">{{ __('txn.canceled_info') }}: {{ $invoice->cancel_reason ?: '-' }}</p>
                    @endif
                    <form id="admin-invoice-edit-form" method="post" action="{{ route('sales-invoices.admin-update', $invoice) }}" class="row" style="margin-bottom: 12px;">
                        @csrf
                        @method('PUT')
                        <div class="col-3">
                            <label>{{ __('txn.invoice_date') }}</label>
                            <input type="date" name="invoice_date" value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-3">
                            <label>{{ __('txn.due_date') }}</label>
                            <input type="date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}">
                        </div>
                        <div class="col-3">
                            <label>{{ __('txn.semester_period') }}</label>
                            <select name="semester_period">
                                @foreach($semesterOptions as $semester)
                                    <option value="{{ $semester }}" @selected(old('semester_period', $invoice->semester_period) === $semester)>{{ $semester }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="flex" style="justify-content: space-between; margin-top: 6px; margin-bottom: 8px;">
                                <strong>{{ __('txn.items') }}</strong>
                                <button type="button" id="admin-add-item" class="btn secondary">{{ __('txn.add_row') }}</button>
                            </div>
                            <table id="admin-items-table">
                                <thead>
                                <tr>
                                    <th>{{ __('txn.product') }}</th>
                                    <th>{{ __('txn.qty') }}</th>
                                    <th>{{ __('txn.price') }}</th>
                                    <th>{{ __('txn.discount') }} (%)</th>
                                    <th>{{ __('txn.subtotal') }}</th>
                                    <th>{{ __('txn.action') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($invoice->items as $index => $item)
                                    @php
                                        $gross = (float) $item->quantity * (float) $item->unit_price;
                                        $discountPercent = $gross > 0 ? (float) $item->discount / $gross * 100 : 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            @php
                                                $itemProduct = $products->firstWhere('id', (int) $item->product_id);
                                                $itemCode = trim((string) ($itemProduct?->code ?? ''));
                                                $itemName = trim((string) ($itemProduct?->name ?? $item->product_name));
                                                $itemLabel = $itemCode !== '' ? $itemCode.' - '.$itemName : $itemName;
                                            @endphp
                                            <input type="text" class="admin-item-product-search" list="admin-invoice-products-list" name="items[{{ $index }}][product_name]" value="{{ $itemLabel }}" required style="max-width: 280px;">
                                            <input type="hidden" class="admin-item-product" name="items[{{ $index }}][product_id]" value="{{ (int) $item->product_id }}">
                                        </td>
                                        <td><input type="number" min="1" class="admin-item-qty" name="items[{{ $index }}][quantity]" value="{{ (int) round($item->quantity) }}" style="max-width: 90px;" required></td>
                                        <td><input type="number" min="0" step="1" class="admin-item-price" name="items[{{ $index }}][unit_price]" value="{{ (int) round($item->unit_price) }}" style="max-width: 120px;" required></td>
                                        <td><input type="number" min="0" max="100" step="1" class="admin-item-discount" name="items[{{ $index }}][discount]" value="{{ (int) round($discountPercent) }}" style="max-width: 85px;"></td>
                                        <td>Rp <span class="admin-item-line-total">{{ number_format((int) round($item->line_total), 0, ',', '.') }}</span></td>
                                        <td><button type="button" class="btn secondary admin-remove-item">{{ __('txn.remove') }}</button></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <datalist id="admin-invoice-products-list">
                                @foreach($products as $productOption)
                                    <option value="{{ $productOption->code ? $productOption->code.' - '.$productOption->name : $productOption->name }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-12">
                            <label>{{ __('txn.notes') }}</label>
                            <textarea name="notes" rows="2">{{ old('notes', $invoice->notes) }}</textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn" type="submit">{{ __('txn.save_changes') }}</button>
                        </div>
                    </form>
                    @if(!$invoice->is_canceled)
                        <form method="post" action="{{ route('sales-invoices.cancel', $invoice) }}" class="row">
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

                const table = document.getElementById('admin-items-table');
                const tbody = table?.querySelector('tbody');
                const addButton = document.getElementById('admin-add-item');
                if (!table || !tbody || !addButton) {
                    return;
                }

                const products = @json($adminProducts);
                const SEARCH_DEBOUNCE_MS = 100;

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
                    const list = document.getElementById('admin-invoice-products-list');
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

                function reindexRows() {
                    Array.from(tbody.querySelectorAll('tr')).forEach((row, index) => {
                        const productSearch = row.querySelector('.admin-item-product-search');
                        const product = row.querySelector('.admin-item-product');
                        const qty = row.querySelector('.admin-item-qty');
                        const price = row.querySelector('.admin-item-price');
                        const discount = row.querySelector('.admin-item-discount');
                        if (productSearch) productSearch.name = `items[${index}][product_name]`;
                        if (product) product.name = `items[${index}][product_id]`;
                        if (qty) qty.name = `items[${index}][quantity]`;
                        if (price) price.name = `items[${index}][unit_price]`;
                        if (discount) discount.name = `items[${index}][discount]`;
                    });
                }

                function recalcRow(row) {
                    const qty = Math.max(0, Number(row.querySelector('.admin-item-qty')?.value || 0));
                    const price = Math.max(0, Number(row.querySelector('.admin-item-price')?.value || 0));
                    const discount = Math.max(0, Math.min(100, Number(row.querySelector('.admin-item-discount')?.value || 0)));
                    const gross = qty * price;
                    const discountAmount = gross * (discount / 100);
                    const lineTotal = Math.max(0, gross - discountAmount);
                    const totalNode = row.querySelector('.admin-item-line-total');
                    if (totalNode) {
                        totalNode.textContent = numberFormat(lineTotal);
                    }
                }

                function bindRow(row) {
                    row.querySelectorAll('.admin-item-qty, .admin-item-price, .admin-item-discount').forEach((input) => {
                        input.addEventListener('input', () => recalcRow(row));
                    });
                    const searchInput = row.querySelector('.admin-item-product-search');
                    const productIdInput = row.querySelector('.admin-item-product');
                    const onSearchInput = debounce((event) => {
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
                        const priceInput = row.querySelector('.admin-item-price');
                        if (selected && priceInput && Number(priceInput.value || 0) <= 0) {
                            priceInput.value = selected.price_general || 0;
                        }
                        recalcRow(row);
                    });
                    row.querySelector('.admin-remove-item')?.addEventListener('click', () => {
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
                            <input type="text" class="admin-item-product-search" list="admin-invoice-products-list" name="items[${index}][product_name]" value="${defaultProduct ? escapeAttribute(productLabel(defaultProduct)) : ''}" required style="max-width: 280px;">
                            <input type="hidden" class="admin-item-product" name="items[${index}][product_id]" value="${defaultProduct ? defaultProduct.id : ''}">
                        </td>
                        <td><input type="number" min="1" class="admin-item-qty" name="items[${index}][quantity]" value="1" style="max-width: 90px;" required></td>
                        <td><input type="number" min="0" step="1" class="admin-item-price" name="items[${index}][unit_price]" value="${defaultProduct ? (defaultProduct.price_general || 0) : 0}" style="max-width: 120px;" required></td>
                        <td><input type="number" min="0" max="100" step="1" class="admin-item-discount" name="items[${index}][discount]" value="0" style="max-width: 85px;"></td>
                        <td>Rp <span class="admin-item-line-total">0</span></td>
                        <td><button type="button" class="btn secondary admin-remove-item">{{ __('txn.remove') }}</button></td>
                    `;
                    tbody.appendChild(tr);
                    bindRow(tr);
                    reindexRows();
                }

                Array.from(tbody.querySelectorAll('tr')).forEach(bindRow);
                renderProductSuggestions(null);
                reindexRows();
                addButton.addEventListener('click', addRow);

                const form = document.getElementById('admin-invoice-edit-form');
                form?.addEventListener('submit', (event) => {
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    if (rows.length === 0) {
                        event.preventDefault();
                        alert(@js(__('txn.no_data_found')));
                        return;
                    }
                    const invalid = rows.some((row) => {
                        const productId = row.querySelector('.admin-item-product')?.value;
                        const qty = Number(row.querySelector('.admin-item-qty')?.value || 0);
                        const price = Number(row.querySelector('.admin-item-price')?.value || 0);
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







