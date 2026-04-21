@extends('layouts.app')

@section('title', __('ui.add_product_unit').' - '.config('app.name', 'Laravel'))

@section('content')
    <h1 class="page-title">{{ __('ui.add_product_unit') }}</h1>
    <form method="post" action="{{ route('product-units.store') }}">
        @csrf
        @include('product_units.partials.form', ['unit' => null])
    </form>
@endsection

