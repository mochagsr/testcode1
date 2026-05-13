@extends('layouts.app')

@section('title', __('ui.stock_mutations_title').' - '.config('app.name', 'Laravel'))

@section('content')
    @php
        $canManageProducts = auth()->user()?->canAccessAny(['products.create', 'products.edit', 'products.delete', 'products.import']) ?? false;
        $stockUnit = $product->unit ?: '-';
    @endphp
    <style>
        .product-mutation-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }

        .product-mutation-pill {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 14px;
            background: color-mix(in srgb, var(--card) 86%, var(--background) 14%);
        }

        .product-mutation-pill strong {
            display: block;
            color: var(--muted);
            font-size: 12px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .product-mutation-pill span {
            display: block;
            font-size: 18px;
            font-weight: 900;
            margin-top: 4px;
        }

        @media (max-width: 720px) {
            .product-mutation-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.stock_mutations_title') }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('products.index') }}">{{ __('txn.back') }}</a>
            @if($canManageProducts)
                <a class="btn edit-btn" href="{{ route('products.edit', $product) }}">{{ __('ui.edit_product') }}</a>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="row">
            <div class="col-3"><strong>{{ __('ui.code') }}</strong><div>{{ $product->code ?: '-' }}</div></div>
            <div class="col-6"><strong>{{ __('ui.name') }}</strong><div>{{ $product->name }}</div></div>
            <div class="col-3"><strong>{{ __('ui.unit') }}</strong><div>{{ $stockUnit }}</div></div>
        </div>
        <div class="product-mutation-summary">
            <div class="product-mutation-pill">
                <strong>Stok Total</strong>
                <span>{{ number_format((int) round((float) $product->stock), 0, ',', '.') }} {{ $stockUnit }}</span>
            </div>
            <div class="product-mutation-pill">
                <strong>Stok Awal</strong>
                <span>{{ number_format((int) ($initialStock ?? 0), 0, ',', '.') }} {{ $stockUnit }}</span>
            </div>
        </div>
    </div>

    <div id="stock-mutations" class="card">
        <h3 style="margin-top: 0;">{{ __('ui.stock_mutations_title') }}</h3>
        <p class="muted" style="margin-top: 0;">{{ __('ui.stock_mutations_note') }}</p>
        @include('products.partials.stock_mutations_table', [
            'stockMutations' => $stockMutations,
            'mutationReferenceMap' => $mutationReferenceMap,
        ])

        <div style="margin-top: 12px;">
            {{ $stockMutations->fragment('stock-mutations')->links() }}
        </div>
    </div>
@endsection

