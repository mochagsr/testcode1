@extends('layouts.app')

@section('title', __('txn.outgoing_transactions_title').' - '.config('app.name', 'Laravel'))

@section('content')
    @php
        $totalWeight = (float) $transaction->items->sum(fn($item) => (float) ($item->weight ?? 0));
        $resolvedPermissions = auth()->user()?->resolvedPermissions() ?? [];
        $canRequestCorrection = in_array('*', $resolvedPermissions, true)
            || in_array('transactions.correction.request', $resolvedPermissions, true);
        $canEditTransactions = auth()->user()?->canAccess('outgoing_transactions.edit') ?? false;
    @endphp
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ $transaction->transaction_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('outgoing-transactions.index') }}">{{ __('txn.back') }}</a>
            @if($canRequestCorrection)
                <a class="btn warning-btn" href="{{ route('transaction-corrections.create', ['type' => 'outgoing_transaction', 'id' => $transaction->id]) }}">Wizard Koreksi</a>
            @endif
            <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('outgoing-transactions.print', $transaction) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('outgoing-transactions.export.pdf', $transaction) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('outgoing-transactions.export.excel', $transaction) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

    <style>
        #admin-outgoing-items-table th.admin-col-qty,
        #admin-outgoing-items-table td.admin-col-qty {
            width: 4.5%;
            padding-left: 4px;
            padding-right: 4px;
        }
        #admin-outgoing-items-table th.admin-col-weight,
        #admin-outgoing-items-table td.admin-col-weight {
            width: 7%;
            padding-left: 5px;
            padding-right: 5px;
        }
        #admin-outgoing-items-table th.admin-col-price,
        #admin-outgoing-items-table td.admin-col-price {
            width: 8%;
            padding-left: 5px;
            padding-right: 5px;
        }
        #admin-outgoing-items-table th.admin-col-tax,
        #admin-outgoing-items-table td.admin-col-tax {
            width: 8%;
            padding-left: 4px;
            padding-right: 4px;
        }
        #admin-outgoing-items-table th.admin-col-notes,
        #admin-outgoing-items-table td.admin-col-notes {
            width: 14%;
            padding-left: 6px;
            padding-right: 6px;
        }
        #admin-outgoing-items-table th.admin-col-action,
        #admin-outgoing-items-table td.admin-col-action {
            width: 1%;
            white-space: nowrap;
            padding-left: 4px;
            padding-right: 4px;
        }
        #admin-outgoing-items-table .admin-qty-input {
            max-width: 62px;
        }
        #admin-outgoing-items-table .admin-weight-input {
            max-width: 88px;
        }
        #admin-outgoing-items-table .admin-price-input {
            max-width: 96px;
        }
        #admin-outgoing-items-table .admin-tax-inputs {
            display: grid;
            grid-template-columns: 40px 72px;
            gap: 6px;
            min-width: 118px;
            max-width: 118px;
        }
        #admin-outgoing-items-table .admin-tax-inputs input {
            text-align: center;
        }
        #admin-outgoing-items-table .admin-notes-input {
            min-width: 136px;
            max-width: 100%;
            width: 100%;
        }
        #admin-outgoing-items-table .admin-remove-btn {
            min-height: 34px;
            padding: 7px 10px;
        }

        @media (max-width: 900px) {
            #admin-outgoing-items-table .admin-qty-input,
            #admin-outgoing-items-table .admin-weight-input,
            #admin-outgoing-items-table .admin-price-input,
            #admin-outgoing-items-table .admin-tax-inputs,
            #admin-outgoing-items-table .admin-notes-input {
                max-width: 100%;
            }
            #admin-outgoing-items-table .admin-tax-inputs {
                min-width: 112px;
            }
            #admin-outgoing-items-table .admin-notes-input {
                min-width: 120px;
            }
        }
    </style>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.outgoing_header') }}</h3>
            <div class="row">
                <div class="col-4"><strong>{{ __('txn.transaction_number') }}</strong><div>{{ $transaction->transaction_number }}</div></div>
                <div class="col-4"><strong>{{ __('txn.date') }}</strong><div>{{ optional($transaction->transaction_date)->format('d-m-Y') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.semester_period') }}</strong><div>{{ $transaction->semester_period ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.note_number') }}</strong><div>{{ $transaction->note_number ?: '-' }}</div></div>
                <div class="col-4">
                    <strong>{{ __('supplier_payable.supplier_invoice_photo') }}</strong>
                    <div>
                        @if($transaction->supplier_invoice_photo_path)
                            <div class="flex">
                                <a class="btn info-btn id-card-preview-trigger" href="#" data-image="{{ asset('storage/'.$transaction->supplier_invoice_photo_path) }}">{{ __('supplier_payable.view_photo') }}</a>
                                <a class="btn info-btn" href="{{ route('outgoing-transactions.supplier-invoice-photo.print', $transaction) }}" target="_blank">{{ __('txn.print') }}</a>
                            </div>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-4"><strong>{{ __('txn.supplier') }}</strong><div>{{ $transaction->supplier?->name ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('ui.supplier_company_name') }}</strong><div>{{ $transaction->supplier?->company_name ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.phone') }}</strong><div>{{ $transaction->supplier?->phone ?: '-' }}</div></div>
                <div class="col-8"><strong>{{ __('txn.address') }}</strong><div>{{ $transaction->supplier?->address ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.created_by') }}</strong><div>{{ $transaction->creator?->name ?: __('txn.system_user') }}</div></div>
                <div class="col-8"><strong>{{ __('txn.notes') }}</strong><div>{{ $transaction->notes ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.total_before_vat') }}</strong><div>Rp {{ number_format((int) round((float) ($transaction->subtotal_before_tax ?? $transaction->total), 0), 0, ',', '.') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.vat_total') }}</strong><div>Rp {{ number_format((int) round((float) ($transaction->total_tax ?? 0), 0), 0, ',', '.') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.grand_total') }}</strong><div>Rp {{ number_format((int) round((float) $transaction->total, 0), 0, ',', '.') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.total_weight') }}</strong><div>{{ number_format($totalWeight, 3, ',', '.') }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.outgoing_items') }}</h3>
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.no') }}</th>
                    <th>{{ __('txn.code') }}</th>
                    <th>{{ __('txn.name') }}</th>
                    <th>{{ __('txn.unit') }}</th>
                    <th>{{ __('txn.qty') }}</th>
                    <th>{{ __('txn.weight') }}</th>
                    <th>{{ __('txn.price') }}</th>
                    <th>{{ __('txn.vat_percent_short') }}</th>
                    <th>{{ __('txn.subtotal') }}</th>
                    <th>{{ __('txn.notes') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($transaction->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->product_code ?: '-' }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->unit ?: '-' }}</td>
                        <td>{{ (int) round((float) $item->quantity, 0) }}</td>
                        <td>{{ $item->weight !== null ? number_format((float) $item->weight, 3, ',', '.') : '-' }}</td>
                        <td>Rp {{ number_format((int) round((float) $item->unit_cost, 0), 0, ',', '.') }}</td>
                        <td>{{ number_format((float) ($item->tax_percent ?? 0), 0, ',', '.') }}%</td>
                        <td>Rp {{ number_format((int) round((float) $item->line_total, 0), 0, ',', '.') }}</td>
                        <td>{{ $item->notes ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="7" style="text-align: right;">{{ __('txn.total_weight') }}</th>
                    <th>{{ number_format($totalWeight, 3, ',', '.') }}</th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th colspan="7" style="text-align: right;">{{ __('txn.total_before_vat') }}</th>
                    <th>Rp {{ number_format((int) round((float) ($transaction->subtotal_before_tax ?? $transaction->total), 0), 0, ',', '.') }}</th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th colspan="7" style="text-align: right;">{{ __('txn.vat_total') }}</th>
                    <th>Rp {{ number_format((int) round((float) ($transaction->total_tax ?? 0), 0), 0, ',', '.') }}</th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th colspan="7" style="text-align: right;">{{ __('txn.grand_total') }}</th>
                    <th>Rp {{ number_format((int) round((float) $transaction->total, 0), 0, ',', '.') }}</th>
                    <th></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if($canEditTransactions)
        <div class="card">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('txn.edit_transaction') }}</h3>
                <p class="form-section-note">Gunakan hak akses edit transaksi ini untuk koreksi cepat. Jika perubahan perlu jejak approval, tetap gunakan Wizard Koreksi.</p>
                <form id="admin-outgoing-edit-form" method="post" action="{{ route('outgoing-transactions.admin-update', $transaction) }}">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-3">
                            <label>{{ __('txn.date') }}</label>
                            <input type="date" name="transaction_date" value="{{ old('transaction_date', optional($transaction->transaction_date)->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-3">
                            <label>{{ __('txn.semester_period') }}</label>
                            <input type="text" name="semester_period" value="{{ old('semester_period', $transaction->semester_period) }}">
                        </div>
                        <div class="col-3">
                            <label>{{ __('txn.note_number') }}</label>
                            <input type="text" name="note_number" value="{{ old('note_number', $transaction->note_number) }}">
                        </div>
                        <div class="col-3">
                            <label>{{ __('txn.supplier') }}</label>
                            <input type="text" value="{{ $transaction->supplier?->name ?: '-' }}" disabled>
                            <input type="hidden" name="supplier_id" value="{{ (int) $transaction->supplier_id }}">
                        </div>
                        <div class="col-12">
                            <label>{{ __('txn.notes') }}</label>
                            <textarea name="notes" rows="2">{{ old('notes', $transaction->notes) }}</textarea>
                        </div>
                    </div>

                    <table id="admin-outgoing-items-table" style="margin-top: 10px;">
                        <thead>
                        <tr>
                            <th>{{ __('txn.name') }}</th>
                            <th>{{ __('txn.unit') }}</th>
                            <th class="admin-col-qty">{{ __('txn.qty') }}</th>
                            <th class="admin-col-weight">{{ __('txn.weight') }}</th>
                            <th class="admin-col-price">{{ __('txn.price') }}</th>
                            <th class="admin-col-tax">{{ __('txn.vat_percent_short') }}</th>
                            <th class="admin-col-notes">{{ __('txn.notes') }}</th>
                            <th class="admin-col-action"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            $oldItems = old('items');
                            if (! is_array($oldItems) || $oldItems === []) {
                                $oldItems = $transaction->items->map(function ($item): array {
                                    return [
                                        'product_id' => $item->product_id,
                                        'product_name' => $item->product_name,
                                        'unit' => $item->unit,
                                        'quantity' => (int) round((float) $item->quantity),
                                        'weight' => $item->weight !== null ? (float) $item->weight : null,
                                        'unit_cost' => (int) round((float) $item->unit_cost),
                                        'tax_percent' => (float) ($item->tax_percent ?? 0),
                                        'tax_amount' => (int) round((float) ($item->tax_amount ?? 0)),
                                        'tax_input_mode' => 'percent',
                                        'notes' => $item->notes,
                                    ];
                                })->all();
                            }
                        @endphp
                        @foreach($oldItems as $idx => $item)
                            @php
                                $match = $products->firstWhere('id', (int) ($item['product_id'] ?? 0));
                                $productSearchValue = $match
                                    ? (($match->code ? $match->code.' - ' : '').$match->name)
                                    : '';
                            @endphp
                            <tr>
                                <td>
                                    <input type="text" class="admin-product-name" list="admin-outgoing-products-list" name="items[{{ $idx }}][product_name]" value="{{ $item['product_name'] ?? $productSearchValue }}" placeholder="{{ __('txn.select_product') }}" required>
                                    <input type="hidden" class="admin-product-id" name="items[{{ $idx }}][product_id]" value="{{ (int) ($item['product_id'] ?? 0) }}">
                                </td>
                                <td><input type="text" class="admin-unit" name="items[{{ $idx }}][unit]" value="{{ $item['unit'] ?? '' }}"></td>
                                <td class="admin-col-qty"><input type="number" min="1" class="admin-qty admin-qty-input" name="items[{{ $idx }}][quantity]" value="{{ (int) ($item['quantity'] ?? 1) }}" required></td>
                                <td class="admin-col-weight"><input type="number" min="0" step="0.001" class="admin-weight admin-weight-input" name="items[{{ $idx }}][weight]" value="{{ isset($item['weight']) && $item['weight'] !== null && $item['weight'] !== '' ? number_format((float) $item['weight'], 3, '.', '') : '' }}"></td>
                                <td class="admin-col-price"><input type="number" min="0" step="1" class="admin-unit-cost admin-price-input" name="items[{{ $idx }}][unit_cost]" value="{{ (isset($item['unit_cost']) && (float) $item['unit_cost'] > 0) ? (int) $item['unit_cost'] : '' }}" placeholder="0"></td>
                                <td class="admin-col-tax">
                                    <div class="dual-inline-inputs admin-tax-inputs">
                                        <input type="number" min="0" step="0.01" class="admin-tax-percent" name="items[{{ $idx }}][tax_percent]" value="{{ (isset($item['tax_percent']) && (float) $item['tax_percent'] > 0) ? number_format((float) $item['tax_percent'], 2, '.', '') : '' }}" placeholder="%">
                                        <input type="number" min="0" step="1" class="admin-tax-amount" name="items[{{ $idx }}][tax_amount]" value="{{ (isset($item['tax_amount']) && (float) $item['tax_amount'] > 0) ? (int) round((float) $item['tax_amount'], 0) : '' }}" placeholder="nilai">
                                        <input type="hidden" class="admin-tax-input-mode" name="items[{{ $idx }}][tax_input_mode]" value="{{ ($item['tax_input_mode'] ?? 'percent') === 'amount' ? 'amount' : 'percent' }}">
                                    </div>
                                </td>
                                <td class="admin-col-notes"><input type="text" class="admin-item-notes admin-notes-input" name="items[{{ $idx }}][notes]" value="{{ $item['notes'] ?? '' }}"></td>
                                <td class="admin-col-action"><button type="button" class="btn danger-btn admin-remove-item admin-remove-btn">{{ __('txn.remove') }}</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    <div class="flex" style="margin-top:10px;">
                        <button type="button" id="admin-add-item" class="btn process-soft-btn">{{ __('txn.add_row') }}</button>
                        <button type="submit" class="btn">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div id="id-card-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:9999; align-items:center; justify-content:center;">
        <img id="id-card-modal-image" src="" alt="Supplier Invoice" style="max-width:25vw; max-height:25vh; width:auto; height:auto; border:2px solid #fff; border-radius:8px; background:#fff;">
    </div>

    <script>
        (function () {
            const modal = document.getElementById('id-card-modal');
            const modalImage = document.getElementById('id-card-modal-image');
            const trigger = document.querySelector('.id-card-preview-trigger');
            if (!modal || !modalImage || !trigger) {
                return;
            }

            function closeModal() {
                modal.style.display = 'none';
                modalImage.setAttribute('src', '');
            }

            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                const image = trigger.getAttribute('data-image');
                if (!image) {
                    return;
                }
                modalImage.setAttribute('src', image);
                modal.style.display = 'flex';
            });

            modal.addEventListener('click', closeModal);
            modalImage.addEventListener('click', closeModal);
        })();
    </script>

    @if($canEditTransactions)
        <datalist id="admin-outgoing-products-list">
            @foreach($products as $product)
                <option value="{{ $product->code ? $product->code.' - '.$product->name : $product->name }}"></option>
            @endforeach
        </datalist>
        <script>
            (function () {
                const products = @json($products);
                const table = document.getElementById('admin-outgoing-items-table');
                const body = table ? table.querySelector('tbody') : null;
                const addBtn = document.getElementById('admin-add-item');
                if (!table || !body || !addBtn) return;

                const findProduct = (value) => {
                    const q = String(value || '').trim().toLowerCase();
                    return products.find((p) => (`${p.code ? p.code + ' - ' : ''}${p.name}`).toLowerCase() === q)
                        || products.find((p) => String(p.code || '').toLowerCase() === q)
                        || products.find((p) => String(p.name || '').toLowerCase() === q)
                        || null;
                };

                const reindex = () => {
                    [...body.querySelectorAll('tr')].forEach((row, idx) => {
                        row.querySelector('.admin-product-id').name = `items[${idx}][product_id]`;
                        row.querySelector('.admin-product-name').name = `items[${idx}][product_name]`;
                        row.querySelector('.admin-unit').name = `items[${idx}][unit]`;
                        row.querySelector('.admin-qty').name = `items[${idx}][quantity]`;
                        row.querySelector('.admin-weight').name = `items[${idx}][weight]`;
                        row.querySelector('.admin-unit-cost').name = `items[${idx}][unit_cost]`;
                        row.querySelector('.admin-tax-percent').name = `items[${idx}][tax_percent]`;
                        row.querySelector('.admin-tax-amount').name = `items[${idx}][tax_amount]`;
                        row.querySelector('.admin-tax-input-mode').name = `items[${idx}][tax_input_mode]`;
                        row.querySelector('.admin-item-notes').name = `items[${idx}][notes]`;
                    });
                };

                const setProductFieldError = (row, message = '') => {
                    const hasMessage = String(message || '').trim() !== '';
                    const input = row.querySelector('.admin-product-name');
                    let error = row.querySelector('.admin-product-error');
                    if (!error && input) {
                        error = document.createElement('div');
                        error.className = 'field-inline-error admin-product-error';
                        error.style.display = 'block';
                        error.style.marginTop = '4px';
                        input.insertAdjacentElement('afterend', error);
                    }
                    if (error) {
                        error.textContent = hasMessage ? message : '';
                    }
                    input?.classList.toggle('input-inline-error', hasMessage);
                };

                const bindRow = (row) => {
                    const idField = row.querySelector('.admin-product-id');
                    const nameField = row.querySelector('.admin-product-name');
                    const unitField = row.querySelector('.admin-unit');
                    const costField = row.querySelector('.admin-unit-cost');
                    const qtyField = row.querySelector('.admin-qty');
                    const taxPercentField = row.querySelector('.admin-tax-percent');
                    const taxAmountField = row.querySelector('.admin-tax-amount');
                    const taxModeField = row.querySelector('.admin-tax-input-mode');
                    const removeBtn = row.querySelector('.admin-remove-item');
                    const syncTaxFields = () => {
                        const qty = Math.max(0, Number(qtyField?.value || 0));
                        const unitCost = Math.max(0, Number(costField?.value || 0));
                        const lineSubtotal = qty * unitCost;
                        if ((taxModeField?.value || 'percent') === 'amount') {
                            const taxAmount = Math.max(0, Math.round(Number(taxAmountField?.value || 0)));
                            if (taxPercentField) {
                                taxPercentField.value = lineSubtotal > 0 ? ((taxAmount / lineSubtotal) * 100).toFixed(2) : '';
                            }
                        } else {
                            const taxPercent = Math.max(0, Number(taxPercentField?.value || 0));
                            if (taxAmountField) {
                                const computedTaxAmount = Math.round(lineSubtotal * (taxPercent / 100));
                                taxAmountField.value = computedTaxAmount > 0 ? String(computedTaxAmount) : '';
                            }
                        }
                    };
                    nameField.addEventListener('change', () => {
                        const p = findProduct(nameField.value);
                        if (!p) {
                            idField.value = '';
                            if (String(nameField.value || '').trim() !== '') {
                                setProductFieldError(row, @js(__('txn.product_not_registered')));
                            }
                            return;
                        }
                        idField.value = p.id;
                        nameField.value = p.name || '';
                        setProductFieldError(row, '');
                        unitField.value = p.unit || '';
                        if (Number(costField.value || 0) <= 0) {
                            costField.value = Math.round(Number(p.price_general || 0));
                        }
                    });
                    removeBtn.addEventListener('click', () => {
                        row.remove();
                        if (!body.querySelector('tr')) {
                            addRow();
                        }
                        reindex();
                    });
                    qtyField?.addEventListener('input', syncTaxFields);
                    costField?.addEventListener('input', syncTaxFields);
                    taxPercentField?.addEventListener('input', () => {
                        if (taxModeField) taxModeField.value = 'percent';
                        syncTaxFields();
                    });
                    taxAmountField?.addEventListener('input', () => {
                        if (taxModeField) taxModeField.value = 'amount';
                        syncTaxFields();
                    });
                    syncTaxFields();
                };

                const addRow = () => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><input type="text" class="admin-product-name" list="admin-outgoing-products-list" placeholder="{{ __('txn.select_product') }}" required><input type="hidden" class="admin-product-id"><div class="field-inline-error admin-product-error" style="display:block; margin-top:4px;"></div></td>
                        <td><input type="text" class="admin-unit"></td>
                        <td class="admin-col-qty"><input type="number" min="1" class="admin-qty admin-qty-input" value="1" required></td>
                        <td class="admin-col-weight"><input type="number" min="0" step="0.001" class="admin-weight admin-weight-input" value=""></td>
                        <td class="admin-col-price"><input type="number" min="0" step="1" class="admin-unit-cost admin-price-input" value="" placeholder="0"></td>
                        <td class="admin-col-tax">
                            <div class="dual-inline-inputs admin-tax-inputs">
                                <input type="number" min="0" step="0.01" class="admin-tax-percent" value="" placeholder="%">
                                <input type="number" min="0" step="1" class="admin-tax-amount" value="" placeholder="nilai">
                                <input type="hidden" class="admin-tax-input-mode" value="percent">
                            </div>
                        </td>
                        <td class="admin-col-notes"><input type="text" class="admin-item-notes admin-notes-input"></td>
                        <td class="admin-col-action"><button type="button" class="btn danger-btn admin-remove-item admin-remove-btn">{{ __('txn.remove') }}</button></td>
                    `;
                    body.appendChild(tr);
                    bindRow(tr);
                    reindex();
                };

                [...body.querySelectorAll('tr')].forEach(bindRow);
                reindex();
                addBtn.addEventListener('click', addRow);
                const form = document.getElementById('admin-outgoing-edit-form');
                form?.addEventListener('submit', (event) => {
                    const rows = [...body.querySelectorAll('tr')];
                    if (rows.length === 0) {
                        event.preventDefault();
                        alert(@js(__('txn.add_item_first')));
                        return;
                    }
                    const invalid = rows.some((row) => {
                        const productId = row.querySelector('.admin-product-id')?.value;
                        const qty = Number(row.querySelector('.admin-qty')?.value || 0);
                        if (!productId) {
                            setProductFieldError(row, @js(__('txn.product_not_registered')));
                        } else {
                            setProductFieldError(row, '');
                        }
                        return !productId || qty < 1;
                    });
                    if (invalid) {
                        event.preventDefault();
                        alert(@js(__('txn.fix_invalid_products')));
                    }
                });
            })();
        </script>
    @endif
@endsection

