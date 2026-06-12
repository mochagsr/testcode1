@php
    $isAdmin = (auth()->user()?->role ?? '') === 'admin';
    $canEditProducts = auth()->user()?->canAccess('products.edit') ?? false;
    $sortUrl = function (string $field) use ($search, $productType, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('products.index', ['search' => $search, 'product_type' => $productType, 'sort' => $field, 'direction' => $nextDir]);
    };
    $sortMark = function (string $field) use ($sort, $direction): string {
        if ($sort !== $field) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
@endphp

<div class="card">
    <div class="products-table-wrap">
    <table class="products-table">
        <thead>
        <tr>
            @if($isAdmin)
                <th class="bulk-select-col" style="width: 36px; text-align: center;">
                    <input type="checkbox" id="product-bulk-select-all" aria-label="{{ __('ui.bulk_delete_products') }}">
                </th>
            @endif
            <th class="code-col">{{ __('ui.code') }}</th>
            <th class="category-col">
                <a class="sort-link" href="{{ $sortUrl('category') }}">
                    {{ __('ui.category') }} <span class="sort-mark">{{ $sortMark('category') }}</span>
                </a>
            </th>
            <th>
                <a class="sort-link" href="{{ $sortUrl('name') }}">
                    {{ __('ui.name') }} <span class="sort-mark">{{ $sortMark('name') }}</span>
                </a>
            </th>
            <th class="stock-col">
                <a class="sort-link" href="{{ $sortUrl('stock') }}">
                    {{ __('ui.stock') }} <span class="sort-mark">{{ $sortMark('stock') }}</span>
                </a>
            </th>
            <th class="price-col">{{ __('ui.price_agent') }}</th>
            <th class="price-col">{{ __('ui.price_sales') }}</th>
            <th class="price-col">{{ __('ui.price_general') }}</th>
            <th class="action-col">{{ __('ui.actions') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($products as $product)
            <tr>
                @if($isAdmin)
                    <td class="bulk-select-col" style="text-align: center;">
                        <input type="checkbox" class="js-product-bulk-checkbox" value="{{ (int) $product->id }}" data-product-code="{{ (string) ($product->code ?? '-') }}" data-product-name="{{ (string) $product->name }}" aria-label="{{ __('ui.bulk_delete_products') }}">
                    </td>
                @endif
                <td class="code-col">
                    @if($product->code)
                        <a href="{{ route('products.mutations', ['product' => $product, 'mutation_page' => 1]) }}#stock-mutations">{{ $product->code }}</a>
                    @else
                        -
                    @endif
                </td>
                <td class="category-col">{{ $product->category?->name ?: '-' }}</td>
                <td class="name-col">{{ $product->name }}</td>
                <td class="stock-col">
                    <strong class="js-product-stock-value" data-product-id="{{ (int) $product->id }}">
                        {{ number_format((int) round($product->stock), 0, ',', '.') }}
                    </strong>
                </td>
                <td class="price-col">Rp {{ number_format((int) round($product->price_agent), 0, ',', '.') }}</td>
                <td class="price-col">Rp {{ number_format((int) round($product->price_sales), 0, ',', '.') }}</td>
                <td class="price-col">Rp {{ number_format((int) round($product->price_general), 0, ',', '.') }}</td>
                <td class="action-col">
                    <div class="product-actions">
                        @if($productType === 'raw_material')
                            <a class="btn info-btn product-action-btn" href="{{ route('products.show', $product) }}">{{ __('ui.view') }}</a>
                        @endif
                        @if($canEditProducts)
                            <a class="btn edit-btn product-action-btn" href="{{ route('products.edit', $product) }}">{{ __('ui.edit') }}</a>
                        @endif
                        @if($canEditProducts)
                            <button
                                type="button"
                                class="btn process-soft-btn product-action-btn js-open-product-stock-modal"
                                data-product-id="{{ (int) $product->id }}"
                                data-product-code="{{ (string) ($product->code ?? '') }}"
                                data-product-name="{{ (string) ($product->name ?? '') }}"
                                data-current-stock="{{ (int) round($product->stock) }}"
                                data-update-url="{{ route('products.quick-stock', $product) }}"
                            >
                                {{ __('ui.edit_stock') }}
                            </button>
                        @endif
                        <a class="btn process-btn product-action-btn" href="{{ route('products.mutations', $product) }}">{{ __('ui.stock_mutations_title') }}</a>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ $isAdmin ? 9 : 8 }}" class="muted">{{ __('ui.no_products') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <div style="margin-top: 12px;">
        {{ $products->links() }}
    </div>
</div>
