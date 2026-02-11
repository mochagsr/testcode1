@extends('layouts.app')

@section('title', __('ui.products_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.products_title') }}</h1>
        <a class="btn" href="{{ route('products.create') }}">{{ __('ui.add_product') }}</a>
    </div>

    <div class="card">
        <form id="products-search-form" method="get" class="flex">
            <input id="products-search-input" type="text" name="search" placeholder="{{ __('ui.search_products_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <button type="submit">{{ __('ui.search') }}</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
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
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category?->name ?: '-' }}</td>
                    <td>{{ (int) round($product->stock) }}</td>
                    <td>Rp {{ number_format((int) round($product->price_agent), 0, ',', '.') }}</td>
                    <td>Rp {{ number_format((int) round($product->price_sales), 0, ',', '.') }}</td>
                    <td>Rp {{ number_format((int) round($product->price_general), 0, ',', '.') }}</td>
                    <td>
                        <div class="flex">
                            <a class="btn secondary" href="{{ route('products.edit', $product) }}">{{ __('ui.edit') }}</a>
                            <form method="post" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('{{ __('ui.confirm_delete_product') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ __('ui.no_products') }}</td></tr>
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

            let debounceTimer = null;
            searchInput.addEventListener('input', () => {
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }

                debounceTimer = setTimeout(() => {
                    form.requestSubmit();
                }, 100);
            });
        })();
    </script>
@endsection


