@extends('layouts.app')

@section('title', __('ui.add_product').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.add_product') }}</h1>

    <form method="post" action="{{ route('products.store') }}">
        @csrf
        @include('products.partials.form', ['product' => null])
    </form>
@endsection
