@extends('layouts.app')

@section('title', __('ui.stock_mutations_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.stock_mutations_title') }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('products.index') }}">{{ __('txn.back') }}</a>
            <a class="btn secondary" href="{{ route('products.edit', $product) }}">{{ __('ui.edit_product') }}</a>
        </div>
    </div>

    <div class="card">
        <div class="row">
            <div class="col-3"><strong>{{ __('ui.code') }}</strong><div>{{ $product->code ?: '-' }}</div></div>
            <div class="col-6"><strong>{{ __('ui.name') }}</strong><div>{{ $product->name }}</div></div>
            <div class="col-3"><strong>{{ __('ui.stock') }}</strong><div>{{ (int) round((float) $product->stock) }}</div></div>
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
