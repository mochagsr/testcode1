@extends('layouts.app')

@section('title', __('txn.delivery_notes_title').' - PgPOS ERP')

@section('content')
    <style>
        #admin-delivery-items-table input[type=number].qty-input::-webkit-outer-spin-button,
        #admin-delivery-items-table input[type=number].qty-input::-webkit-inner-spin-button,
        #admin-delivery-items-table input[type=number].price-input::-webkit-outer-spin-button,
        #admin-delivery-items-table input[type=number].price-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #admin-delivery-items-table input[type=number].qty-input,
        #admin-delivery-items-table input[type=number].price-input {
            -moz-appearance: textfield;
            appearance: textfield;
        }
    </style>

    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.delivery_notes_title') }} {{ $note->note_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('delivery-notes.index') }}">{{ __('txn.back') }}</a>
            @if((auth()->user()?->role ?? '') === 'admin')
                <a class="btn secondary" href="#admin-edit-transaction">{{ __('txn.edit_transaction') }}</a>
            @endif
            <select style="max-width: 170px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="">{{ __('txn.action') }}</option>
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
                    <th>{{ __('txn.price') }}</th>
                    <th>{{ __('txn.notes') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($note->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->unit ?: '-' }}</td>
                        <td>{{ (int) round($item->quantity) }}</td>
                        <td>{{ $item->unit_price !== null ? 'Rp '.number_format((int) round($item->unit_price), 0, ',', '.') : '-' }}</td>
                        <td>{{ $item->notes ?: '-' }}</td>
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
                    <div class="col-12">
                        <div class="flex" style="justify-content: space-between; margin-top: 6px; margin-bottom: 8px;">
                            <strong>{{ __('txn.items') }}</strong>
                            <button type="button" id="admin-add-delivery-item" class="btn secondary">{{ __('txn.add_row') }}</button>
                        </div>
                        <table id="admin-delivery-items-table">
                            <thead>
                            <tr>
                                <th>{{ __('txn.product') }}</th>
                                <th>{{ __('txn.qty') }}</th>
                                <th>{{ __('txn.unit') }}</th>
                                <th>{{ __('txn.price') }}</th>
                                <th>{{ __('txn.notes') }}</th>
                                <th>{{ __('txn.action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($note->items as $index => $item)
                                <tr>
                                    <td>
                                        <input type="text" name="items[{{ $index }}][product_name]" class="admin-delivery-item-search" list="admin-delivery-products-list" value="{{ $item->product_name }}" style="max-width: 260px;" required>
                                        <input type="hidden" class="admin-delivery-item-product-id" name="items[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                                    </td>
                                    <td><input type="number" min="1" name="items[{{ $index }}][quantity]" class="admin-delivery-item-qty qty-input" value="{{ (int) round($item->quantity) }}" style="max-width: 104px;" required></td>
                                    <td><input type="text" name="items[{{ $index }}][unit]" class="admin-delivery-item-unit" value="{{ $item->unit }}" style="max-width: 72px;"></td>
                                    <td><input type="number" min="0" step="1" name="items[{{ $index }}][unit_price]" class="admin-delivery-item-price price-input" value="{{ $item->unit_price !== null ? (int) round((float) $item->unit_price) : '' }}" style="max-width: 104px;"></td>
                                    <td><input type="text" name="items[{{ $index }}][notes]" class="admin-delivery-item-notes" value="{{ $item->notes }}" style="max-width: 160px;"></td>
                                    <td><button type="button" class="btn secondary admin-remove-delivery-item">{{ __('txn.remove') }}</button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <datalist id="admin-delivery-products-list">
                            @foreach($products as $productOption)
                                <option value="{{ $productOption->name }}"></option>
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
                @if(!$note->is_canceled)
                    <form method="post" action="{{ route('delivery-notes.cancel', $note) }}" class="row">
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
                const table = document.getElementById('admin-delivery-items-table');
                const tbody = table?.querySelector('tbody');
                const addButton = document.getElementById('admin-add-delivery-item');
                if (!table || !tbody || !addButton) {
                    return;
                }

                const products = @json($products->map(fn ($product) => [
                    'id' => (int) $product->id,
                    'code' => (string) ($product->code ?? ''),
                    'name' => (string) $product->name,
                    'unit' => (string) ($product->unit ?? ''),
                    'price_general' => (int) round((float) ($product->price_general ?? 0)),
                ])->values()->all());

                function findProductByLabel(label) {
                    if (!label) {
                        return null;
                    }
                    const normalized = String(label).trim().toLowerCase();
                    return products.find((product) => product.name.toLowerCase() === normalized)
                        || products.find((product) => String(product.code || '').toLowerCase() === normalized)
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
                        const name = product.name.toLowerCase();
                        const code = String(product.code || '').toLowerCase();
                        return normalized === '' || name.includes(normalized) || code.includes(normalized);
                    }).slice(0, 60);
                    list.innerHTML = matches
                        .map((product) => `<option value="${String(product.name).replace(/"/g, '&quot;')}"></option>`)
                        .join('');
                }

                function reindexRows() {
                    Array.from(tbody.querySelectorAll('tr')).forEach((row, index) => {
                        row.querySelector('.admin-delivery-item-search').name = `items[${index}][product_name]`;
                        row.querySelector('.admin-delivery-item-product-id').name = `items[${index}][product_id]`;
                        row.querySelector('.admin-delivery-item-unit').name = `items[${index}][unit]`;
                        row.querySelector('.admin-delivery-item-qty').name = `items[${index}][quantity]`;
                        row.querySelector('.admin-delivery-item-price').name = `items[${index}][unit_price]`;
                        row.querySelector('.admin-delivery-item-notes').name = `items[${index}][notes]`;
                    });
                }

                function bindRow(row) {
                    const searchInput = row.querySelector('.admin-delivery-item-search');
                    const productIdInput = row.querySelector('.admin-delivery-item-product-id');
                    searchInput?.addEventListener('input', (event) => {
                        renderProductSuggestions(event.currentTarget);
                        const selected = findProductByLabel(event.currentTarget.value);
                        if (!selected) {
                            productIdInput.value = '';
                            return;
                        }
                        searchInput.value = selected.name;
                        productIdInput.value = selected.id;
                        if (!row.querySelector('.admin-delivery-item-unit').value) {
                            row.querySelector('.admin-delivery-item-unit').value = selected.unit || '';
                        }
                        if (!row.querySelector('.admin-delivery-item-price').value) {
                            row.querySelector('.admin-delivery-item-price').value = selected.price_general || 0;
                        }
                    });
                    searchInput?.addEventListener('focus', (event) => {
                        renderProductSuggestions(event.currentTarget);
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
                            <input type="text" name="items[${index}][product_name]" class="admin-delivery-item-search" list="admin-delivery-products-list" value="" style="max-width: 260px;" required>
                            <input type="hidden" class="admin-delivery-item-product-id" name="items[${index}][product_id]" value="">
                        </td>
                        <td><input type="number" min="1" name="items[${index}][quantity]" class="admin-delivery-item-qty qty-input" value="1" style="max-width: 104px;" required></td>
                        <td><input type="text" name="items[${index}][unit]" class="admin-delivery-item-unit" value="" style="max-width: 72px;"></td>
                        <td><input type="number" min="0" step="1" name="items[${index}][unit_price]" class="admin-delivery-item-price price-input" value="" style="max-width: 104px;"></td>
                        <td><input type="text" name="items[${index}][notes]" class="admin-delivery-item-notes" value="" style="max-width: 160px;"></td>
                        <td><button type="button" class="btn secondary admin-remove-delivery-item">{{ __('txn.remove') }}</button></td>
                    `;
                    tbody.appendChild(tr);
                    bindRow(tr);
                    reindexRows();
                }

                Array.from(tbody.querySelectorAll('tr')).forEach(bindRow);
                reindexRows();
                addButton.addEventListener('click', addRow);

                const form = document.getElementById('admin-delivery-edit-form');
                form?.addEventListener('submit', (event) => {
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    if (rows.length === 0) {
                        event.preventDefault();
                        alert(@js(__('txn.no_data_found')));
                        return;
                    }
                    const invalid = rows.some((row) => {
                        const productName = (row.querySelector('.admin-delivery-item-search')?.value || '').trim();
                        const qty = Number(row.querySelector('.admin-delivery-item-qty')?.value || 0);
                        return productName === '' || qty < 1;
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


