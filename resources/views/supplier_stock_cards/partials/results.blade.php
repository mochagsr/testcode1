@php
    $sortUrl = function (string $field) use ($search, $selectedSupplierId, $selectedProductId, $dateFrom, $dateTo, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('supplier-stock-cards.index', array_filter(['supplier_id' => $selectedSupplierId, 'product_id' => $selectedProductId, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'sort' => $field, 'direction' => $nextDir], fn ($v) => $v !== null && $v !== '' && $v !== 0));
    };
    $sortMark = function (string $field) use ($sort, $direction): string {
        if ($sort !== $field) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
@endphp

@if($selectedProductId)
    <input type="hidden" name="product_id" form="supplier-stock-filter-form" value="{{ (int) $selectedProductId }}">
    <div class="card" style="padding:10px 12px;">
        <span class="muted">{{ __('txn.product') }} ID: {{ (int) $selectedProductId }}</span>
        <a class="btn secondary" style="margin-left:8px;" href="{{ route('supplier-stock-cards.index', array_merge(request()->except('product_id'), ['supplier_id' => $selectedSupplierId])) }}">{{ __('txn.all') }}</a>
    </div>
@endif

@if(!$selectedSupplier)
    <div class="card">
        <h3 style="margin-top:0;">{{ __('supplier_stock.product_summary') }}</h3>
        <div class="supplier-stock-scroll-wrap">
        <table class="supplier-stock-summary-table">
            <colgroup>
                <col style="width: 14%;">
                <col style="width: 26%;">
                <col style="width: 20%;">
                <col style="width: 14%;">
                <col style="width: 14%;">
                <col style="width: 12%;">
            </colgroup>
            <thead>
            <tr>
                <th><a class="sort-link" href="{{ $sortUrl('category') }}">{{ __('ui.category') }} <span class="sort-mark">{{ $sortMark('category') }}</span></a></th>
                <th><a class="sort-link" href="{{ $sortUrl('name') }}">{{ __('txn.name') }} <span class="sort-mark">{{ $sortMark('name') }}</span></a></th>
                <th><a class="sort-link" href="{{ $sortUrl('supplier') }}">{{ __('txn.supplier') }} <span class="sort-mark">{{ $sortMark('supplier') }}</span></a></th>
                <th class="num"><a class="sort-link" style="justify-content:flex-end;" href="{{ $sortUrl('balance') }}">{{ __('supplier_stock.stock_from_supplier') }} <span class="sort-mark">{{ $sortMark('balance') }}</span></a></th>
                <th class="num">{{ __('supplier_stock.master_stock') }}</th>
                <th class="action">{{ __('txn.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($summaryPaginator as $row)
                @php
                    $supplierId = (int) ($row['supplier_id'] ?? 0);
                    $stockBalance = (int) ($row['balance'] ?? 0);
                    $editableProductId = (int) ($row['editable_product_id'] ?? 0);
                    $rowKey = md5(($row['product_code'] ?? '').'|'.($row['product_name'] ?? '').'|'.$supplierId.'|'.$editableProductId);
                @endphp
                <tr>
                    <td>{{ $row['category_name'] ?? '-' }}</td>
                    <td>
                        @if($editableProductId > 0)
                            <a href="{{ route('products.mutations', ['product' => $editableProductId]) }}#stock-mutations">{{ $row['product_name'] }}</a>
                        @else
                            {{ $row['product_name'] }}
                        @endif
                    </td>
                    <td>
                        @if($supplierId > 0)
                            <a href="{{ route('supplier-stock-cards.index', array_merge(request()->query(), ['supplier_id' => $supplierId])) }}">
                                {{ $row['supplier_name'] ?? '-' }}
                            </a>
                        @else
                            {{ $row['supplier_name'] ?? '-' }}
                        @endif
                    </td>
                    <td class="num">
                        <strong
                            class="js-stock-value"
                            data-row-key="{{ $rowKey }}"
                            style="{{ $stockBalance <= 10 ? 'color:#b91c1c;' : '' }}"
                        >
                            {{ number_format($stockBalance, 0, ',', '.') }}
                        </strong>
                    </td>
                    <td class="num">
                        {{ number_format((int) ($row['master_stock'] ?? 0), 0, ',', '.') }}
                        @if((int) ($row['unattributed_stock'] ?? 0) > 0)
                            <div class="muted" style="font-size:11px; white-space:nowrap;">
                                {{ __('supplier_stock.unattributed_stock_note', ['qty' => number_format((int) $row['unattributed_stock'], 0, ',', '.')]) }}
                            </div>
                        @endif
                    </td>
                    <td class="action">
                        <button
                            type="button"
                            class="btn process-soft-btn js-open-stock-modal"
                            style="min-height:30px; padding:5px 9px; margin-left:4px; font-size:11px; position:relative; z-index:1;"
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
                <tr><td colspan="6" class="muted">{{ __('supplier_stock.no_data') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div style="margin-top:12px;">{{ $summaryPaginator->links() }}</div>
    </div>
@endif

@if($selectedSupplier)
    <div class="card">
        <h3 style="margin-top:0;">{{ __('supplier_stock.mutation_title') }} ({{ $selectedSupplier->name }})</h3>
        <div class="supplier-stock-scroll-wrap">
        <table class="supplier-stock-mutation-table">
            <colgroup>
                <col style="width: 11%;">
                <col style="width: 24%;">
                <col style="width: 33%;">
                <col style="width: 10%;">
                <col style="width: 10%;">
                <col style="width: 12%;">
            </colgroup>
            <thead>
            <tr>
                <th>{{ __('txn.date') }}</th>
                <th>{{ __('txn.product') }}</th>
                <th>{{ __('supplier_stock.description') }}</th>
                <th class="num">{{ __('supplier_stock.in') }}</th>
                <th class="num">{{ __('supplier_stock.out') }}</th>
                <th class="num">{{ __('supplier_stock.balance') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($movementPaginator as $row)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['event_date'])->format('d-m-Y') }}</td>
                    <td>
                        <div class="muted">{{ $row['category_name'] ?? '-' }}</div>
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
                    <td class="num" style="color:#1f6b3d;">{{ (int) $row['qty_in'] > 0 ? number_format((int) $row['qty_in'], 0, ',', '.') : '-' }}</td>
                    <td class="num" style="color:#8d1f1f;">{{ (int) $row['qty_out'] > 0 ? number_format((int) $row['qty_out'], 0, ',', '.') : '-' }}</td>
                    <td class="num"><strong>{{ number_format((int) $row['balance_after'], 0, ',', '.') }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">{{ __('supplier_stock.no_mutation') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div style="margin-top:12px;">{{ $movementPaginator->links() }}</div>
    </div>
@endif
