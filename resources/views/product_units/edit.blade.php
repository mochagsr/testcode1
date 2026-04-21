@extends('layouts.app')

@section('title', __('ui.edit_product_unit').' - '.config('app.name', 'Laravel'))

@section('content')
    <h1 class="page-title">{{ __('ui.edit_product_unit') }}</h1>
    <form method="post" action="{{ route('product-units.update', $unit) }}">
        @csrf
        @method('PUT')
        @include('product_units.partials.form', ['unit' => $unit])
    </form>
@endsection

