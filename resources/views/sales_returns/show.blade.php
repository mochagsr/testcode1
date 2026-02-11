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
            <select style="max-width: 170px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="">{{ __('txn.action') }}</option>
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
                        <input type="text" name="semester_period" value="{{ old('semester_period', $salesReturn->semester_period) }}">
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
                                        <select name="items[{{ $index }}][product_id]" class="admin-return-item-product" required style="max-width: 280px;">
                                            @foreach($products as $productOption)
                                                <option value="{{ $productOption->id }}" @selected((int) $item->product_id === (int) $productOption->id)>
                                                    {{ $productOption->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="number" min="1" class="admin-return-item-qty" name="items[{{ $index }}][quantity]" value="{{ (int) round($item->quantity) }}" style="max-width: 90px;" required></td>
                                    <td><input type="number" min="0" step="1" class="admin-return-item-price" name="items[{{ $index }}][unit_price]" value="{{ (int) round($item->unit_price) }}" style="max-width: 120px;" required></td>
                                    <td>Rp <span class="admin-return-item-line-total">{{ number_format((int) round($item->line_total), 0, ',', '.') }}</span></td>
                                    <td><button type="button" class="btn secondary admin-remove-return-item">{{ __('txn.remove') }}</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
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

                const products = @json($products->map(fn ($product) => [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'price_general' => (int) round((float) ($product->price_general ?? 0)),
                ])->values()->all());

                function numberFormat(value) {
                    return new Intl.NumberFormat('id-ID').format(Math.round(Number(value || 0)));
                }

                function buildProductOptions(selectedId) {
                    return products.map((product) => {
                        const selected = Number(selectedId) === Number(product.id) ? 'selected' : '';
                        return `<option value="${product.id}" ${selected}>${product.name}</option>`;
                    }).join('');
                }

                function reindexRows() {
                    Array.from(tbody.querySelectorAll('tr')).forEach((row, index) => {
                        const product = row.querySelector('.admin-return-item-product');
                        const qty = row.querySelector('.admin-return-item-qty');
                        const price = row.querySelector('.admin-return-item-price');
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
                    row.querySelector('.admin-return-item-product')?.addEventListener('change', (event) => {
                        const selected = products.find((product) => Number(product.id) === Number(event.currentTarget.value));
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
                            <select name="items[${index}][product_id]" class="admin-return-item-product" required style="max-width: 280px;">
                                ${buildProductOptions(defaultProduct ? defaultProduct.id : null)}
                            </select>
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


