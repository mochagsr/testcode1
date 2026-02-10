@extends('layouts.app')

@section('title', __('ui.products_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.products_title') }}</h1>
        <a class="btn" href="{{ route('products.create') }}">{{ __('ui.add_product') }}</a>
    </div>

    <div class="card">
        <form method="get" class="flex">
            <input type="text" name="search" placeholder="{{ __('ui.search_products_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <button type="submit">{{ __('ui.search') }}</button>
        </form>
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
                <th>{{ __('ui.status') }}</th>
                <th>{{ __('ui.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($products as $product)
                <tr>
                    <td>{{ $product->code }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category?->code ?: '-' }}</td>
                    <td>{{ number_format($product->stock) }}</td>
                    <td>Rp {{ number_format($product->price_agent, 2) }}</td>
                    <td>Rp {{ number_format($product->price_sales, 2) }}</td>
                    <td>Rp {{ number_format($product->price_general, 2) }}</td>
                    <td>{{ $product->is_active ? __('ui.active') : __('ui.inactive') }}</td>
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
                <tr><td colspan="9" class="muted">{{ __('ui.no_products') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $products->links() }}
        </div>
    </div>
@endsection
