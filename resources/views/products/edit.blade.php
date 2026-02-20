@extends('layouts.app')

@section('title', __('ui.edit_product').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.edit_product') }}</h1>

    <form method="post" action="{{ route('products.update', $product) }}">
        @csrf
        @method('PUT')
        @include('products.partials.form', ['product' => $product])
    </form>

    <div id="stock-mutations" class="card" style="margin-top: 12px;">
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
