@extends('layouts.app')

@section('title', __('ui.edit_customer').' - '.config('app.name', 'Laravel'))

@section('content')
    <h1 class="page-title">{{ __('ui.edit_customer') }}</h1>
    <form method="post" action="{{ route('customers-web.update', $customer) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('customers.partials.form', ['customer' => $customer])
    </form>
@endsection

