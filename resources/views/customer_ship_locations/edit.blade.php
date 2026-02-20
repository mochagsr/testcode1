@extends('layouts.app')

@section('title', __('school_bulk.edit_ship_location').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('school_bulk.edit_ship_location') }}</h1>
    <form method="post" action="{{ route('customer-ship-locations.update', $location) }}">
        @csrf
        @method('PUT')
        @include('customer_ship_locations.partials.form', ['location' => $location])
    </form>
@endsection

