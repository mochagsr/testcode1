@extends('layouts.app')

@section('title', __('ui.edit_product').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.edit_product') }}</h1>

    <form method="post" action="{{ route('products.update', $product) }}">
        @csrf
        @method('PUT')
        @include('products.partials.form', ['product' => $product])
    </form>
@endsection
