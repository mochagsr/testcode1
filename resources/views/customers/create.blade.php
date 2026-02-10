@extends('layouts.app')

@section('title', __('ui.add_customer').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.add_customer') }}</h1>
    <form method="post" action="{{ route('customers-web.store') }}" enctype="multipart/form-data">
        @csrf
        @include('customers.partials.form', ['customer' => null])
    </form>
@endsection
