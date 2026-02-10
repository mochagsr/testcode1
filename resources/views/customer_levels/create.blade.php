@extends('layouts.app')

@section('title', __('ui.add_customer_level').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.add_customer_level') }}</h1>
    <form method="post" action="{{ route('customer-levels-web.store') }}">
        @csrf
        @include('customer_levels.partials.form', ['level' => null])
    </form>
@endsection
