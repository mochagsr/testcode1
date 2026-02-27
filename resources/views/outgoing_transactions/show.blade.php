@extends('layouts.app')

@section('title', __('txn.outgoing_transactions_title').' - PgPOS ERP')

@section('content')
    @php
        $totalWeight = (float) $transaction->items->sum(fn($item) => (float) ($item->weight ?? 0));
    @endphp
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ $transaction->transaction_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('outgoing-transactions.index') }}">{{ __('txn.back') }}</a>
            <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('outgoing-transactions.print', $transaction) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('outgoing-transactions.export.pdf', $transaction) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('outgoing-transactions.export.excel', $transaction) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

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
                            <a class="btn secondary id-card-preview-trigger" href="#" data-image="{{ asset('storage/'.$transaction->supplier_invoice_photo_path) }}">{{ __('supplier_payable.view_photo') }}</a>
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
                        <td>Rp {{ number_format((int) round((float) $item->line_total, 0), 0, ',', '.') }}</td>
                        <td>{{ $item->notes ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="6" style="text-align: right;">{{ __('txn.total_weight') }}</th>
                    <th>{{ number_format($totalWeight, 3, ',', '.') }}</th>
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

    @if((auth()->user()?->role ?? '') === 'admin')
        <div class="card">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('txn.admin_edit_outgoing_title') }}</h3>
                <form method="post" action="{{ route('outgoing-transactions.admin-update', $transaction) }}">
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
                            <th>{{ __('txn.qty') }}</th>
                            <th>{{ __('txn.weight') }}</th>
                            <th>{{ __('txn.price') }}</th>
                            <th>{{ __('txn.notes') }}</th>
                            <th></th>
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
                                <td><input type="number" min="1" class="admin-qty w-xs" name="items[{{ $idx }}][quantity]" value="{{ (int) ($item['quantity'] ?? 1) }}" required></td>
                                <td><input type="number" min="0" step="0.001" class="admin-weight w-xs" name="items[{{ $idx }}][weight]" value="{{ isset($item['weight']) && $item['weight'] !== null && $item['weight'] !== '' ? number_format((float) $item['weight'], 3, '.', '') : '' }}"></td>
                                <td><input type="number" min="0" step="1" class="admin-unit-cost w-xs" name="items[{{ $idx }}][unit_cost]" value="{{ (int) ($item['unit_cost'] ?? 0) }}"></td>
                                <td><input type="text" class="admin-item-notes" name="items[{{ $idx }}][notes]" value="{{ $item['notes'] ?? '' }}"></td>
                                <td><button type="button" class="btn secondary admin-remove-item">{{ __('txn.remove') }}</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    <div class="flex" style="margin-top:10px;">
                        <button type="button" id="admin-add-item" class="btn secondary">{{ __('txn.add_row') }}</button>
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

    @if((auth()->user()?->role ?? '') === 'admin')
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
                        row.querySelector('.admin-item-notes').name = `items[${idx}][notes]`;
                    });
                };

                const bindRow = (row) => {
                    const idField = row.querySelector('.admin-product-id');
                    const nameField = row.querySelector('.admin-product-name');
                    const unitField = row.querySelector('.admin-unit');
                    const costField = row.querySelector('.admin-unit-cost');
                    const removeBtn = row.querySelector('.admin-remove-item');
                    nameField.addEventListener('change', () => {
                        const p = findProduct(nameField.value);
                        if (!p) {
                            idField.value = '';
                            return;
                        }
                        idField.value = p.id;
                        nameField.value = p.name || '';
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
                };

                const addRow = () => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><input type="text" class="admin-product-name" list="admin-outgoing-products-list" placeholder="{{ __('txn.select_product') }}" required><input type="hidden" class="admin-product-id"></td>
                        <td><input type="text" class="admin-unit"></td>
                        <td><input type="number" min="1" class="admin-qty w-xs" value="1" required></td>
                        <td><input type="number" min="0" step="0.001" class="admin-weight w-xs" value=""></td>
                        <td><input type="number" min="0" step="1" class="admin-unit-cost w-xs" value="0"></td>
                        <td><input type="text" class="admin-item-notes"></td>
                        <td><button type="button" class="btn secondary admin-remove-item">{{ __('txn.remove') }}</button></td>
                    `;
                    body.appendChild(tr);
                    bindRow(tr);
                    reindex();
                };

                [...body.querySelectorAll('tr')].forEach(bindRow);
                reindex();
                addBtn.addEventListener('click', addRow);
            })();
        </script>
    @endif
@endsection
