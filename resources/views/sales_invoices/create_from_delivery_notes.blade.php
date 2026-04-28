@extends('layouts.app')

@section('title', __('txn.create_invoice_from_delivery_notes').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        #items-table {
            table-layout: fixed;
            width: 100%;
        }
        #items-table .date-col {
            width: 9%;
        }
        #items-table .delivery-col {
            width: 13%;
        }
        #items-table .product-col {
            width: 37%;
        }
        #items-table .remaining-col {
            width: 7%;
        }
        #items-table .qty-col {
            width: 6%;
        }
        #items-table .price-col {
            width: 10%;
        }
        #items-table .discount-col {
            width: 7%;
        }
        #items-table .total-col {
            width: 11%;
        }
        #items-table input.qty,
        #items-table input.discount {
            max-width: 62px;
            min-width: 0;
        }
        #items-table input.price {
            max-width: 92px;
            min-width: 0;
        }
    </style>

    <h1 class="page-title">{{ __('txn.create_invoice_from_delivery_notes') }}</h1>

    <form method="post" action="{{ route('sales-invoices.store-from-delivery-notes') }}">
        @csrf
        <input type="hidden" name="customer_id" value="{{ $customer->id }}">

        <div class="card">
            <div class="row">
                <div class="col-4">
                    <label>{{ __('txn.customer') }}</label>
                    <input type="text" value="{{ $customer->name }}{{ $customer->city ? ' ('.$customer->city.')' : '' }}" disabled>
                </div>
                <div class="col-4">
                    <label>{{ __('txn.invoice_date') }} <span class="label-required">*</span></label>
                    <input type="date" name="invoice_date" value="{{ old('invoice_date', now()->format('Y-m-d')) }}" required>
                </div>
                <div class="col-4">
                    <label>{{ __('txn.due_date') }}</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}">
                </div>
                <div class="col-4">
                    <label>{{ __('txn.semester_period') }}</label>
                    <select name="semester_period">
                        @foreach($semesterOptions as $semester)
                            <option value="{{ $semester }}" @selected(old('semester_period', $defaultSemesterPeriod) === $semester)>{{ $semester }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-4">
                    <label>{{ __('txn.payment_method') }}</label>
                    <select name="payment_method" required>
                        <option value="kredit" @selected(old('payment_method', 'kredit') === 'kredit')>{{ __('txn.credit') }}</option>
                        <option value="tunai" @selected(old('payment_method') === 'tunai')>{{ __('txn.cash') }}</option>
                    </select>
                </div>
                <div class="col-12">
                    <label>{{ __('txn.delivery_notes_title') }}</label>
                    <div class="muted">
                        {{ $deliveryNotes->pluck('note_number')->implode(', ') }}
                    </div>
                </div>
                <div class="col-12">
                    <label>{{ __('txn.notes') }}</label>
                    <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">{{ __('txn.invoice_items') }}</h3>
            <p class="muted">{{ __('txn.invoice_from_delivery_items_note') }}</p>
            <div class="table-mobile-scroll">
                @php
                    $deliveryRowspans = collect($rows)
                        ->groupBy(fn ($row) => (int) $row['delivery_note']->id)
                        ->map(fn ($group) => $group->count());
                    $renderedDeliveryNotes = [];
                @endphp
                <table id="items-table">
                    <thead>
                    <tr>
                        <th class="date-col">{{ __('txn.date') }}</th>
                        <th class="delivery-col">{{ __('txn.delivery_notes_title') }}</th>
                        <th class="product-col">{{ __('txn.product') }}</th>
                        <th class="num remaining-col">{{ __('txn.uninvoiced_quantity') }}</th>
                        <th class="qty-col">{{ __('txn.invoice_quantity') }}</th>
                        <th class="price-col">{{ __('txn.price') }}</th>
                        <th class="discount-col">{{ __('txn.discount') }} (%)</th>
                        <th class="num total-col">{{ __('txn.line_total') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $index => $row)
                        @php
                            $item = $row['item'];
                            $note = $row['delivery_note'];
                            $noteId = (int) $note->id;
                            $shouldRenderDeliveryNote = ! isset($renderedDeliveryNotes[$noteId]);
                            $remaining = (int) ($row['remaining_qty'] ?? 0);
                            $oldPrefix = 'items.'.$index.'.';
                            $price = old($oldPrefix.'unit_price', $row['default_price'] ?? 0);
                            $qty = old($oldPrefix.'quantity', $remaining);
                            $discount = old($oldPrefix.'discount', 0);
                        @endphp
                        <tr>
                            @if($shouldRenderDeliveryNote)
                                @php
                                    $renderedDeliveryNotes[$noteId] = true;
                                    $rowspan = (int) ($deliveryRowspans[$noteId] ?? 1);
                                @endphp
                                <td class="date-col" rowspan="{{ $rowspan }}">{{ $note->note_date?->format('d-m-Y') ?: '-' }}</td>
                                <td class="delivery-col" rowspan="{{ $rowspan }}">
                                    {{ $note->note_number }}
                                </td>
                            @endif
                            <td class="product-col">
                                <input type="hidden" name="items[{{ $index }}][delivery_note_item_id]" value="{{ $item->id }}">
                                <strong>{{ $item->product_name }}</strong>
                                <div class="muted">{{ $item->product_code }}</div>
                            </td>
                            <td class="num remaining-col">{{ number_format($remaining, 0, ',', '.') }}</td>
                            <td class="qty-col">
                                <input class="qty" type="number" name="items[{{ $index }}][quantity]" value="{{ $qty }}" min="1" max="{{ $remaining }}" required>
                            </td>
                            <td class="price-col">
                                <input class="price" type="number" name="items[{{ $index }}][unit_price]" value="{{ $price }}" min="1" step="1" required>
                            </td>
                            <td class="discount-col">
                                <input class="discount" type="number" name="items[{{ $index }}][discount]" value="{{ $discount }}" min="0" max="100" step="1">
                            </td>
                            <td class="num total-col">Rp <span class="line-total">0</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top:10px; text-align:right;">
                <strong>{{ __('txn.total') }}: Rp <span id="grand-total">0</span></strong>
            </div>
        </div>

        <div class="form-submit-actions">
            <button class="btn" type="submit">{{ __('txn.save_invoice') }}</button>
            <a class="btn secondary" href="{{ route('sales-invoices.pending-delivery-notes') }}">{{ __('txn.cancel') }}</a>
        </div>
    </form>

    <script>
        function formatNumber(value) {
            return Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
        }

        function recalc() {
            let total = 0;
            document.querySelectorAll('#items-table tbody tr').forEach((row) => {
                const qty = Number(row.querySelector('.qty')?.value || 0);
                const price = Number(row.querySelector('.price')?.value || 0);
                const discountPercent = Math.max(0, Math.min(100, Number(row.querySelector('.discount')?.value || 0)));
                const gross = qty * price;
                const lineTotal = Math.max(0, gross - Math.round(gross * discountPercent / 100));
                total += lineTotal;
                const target = row.querySelector('.line-total');
                if (target) {
                    target.textContent = formatNumber(lineTotal);
                }
            });
            const grandTotal = document.getElementById('grand-total');
            if (grandTotal) {
                grandTotal.textContent = formatNumber(total);
            }
        }

        document.querySelectorAll('.qty,.price,.discount').forEach((input) => {
            input.addEventListener('input', recalc);
        });
        recalc();
    </script>
@endsection
