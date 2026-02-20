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
                    <td>{{ (int) round($product->stock) }}</td>
                    <td>Rp {{ number_format((int) round($product->price_agent), 0, ',', '.') }}</td>
                    <td>Rp {{ number_format((int) round($product->price_sales), 0, ',', '.') }}</td>
                    <td>Rp {{ number_format((int) round($product->price_general), 0, ',', '.') }}</td>
                    <td>
                        <div class="flex">
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
                <tr><td colspan="8" class="muted">{{ __('ui.no_products') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $products->links() }}
        </div>
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
    </script>
@endsection
