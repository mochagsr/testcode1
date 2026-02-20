@extends('layouts.app')

@section('title', __('school_bulk.add_ship_location').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('school_bulk.add_ship_location') }}</h1>
    <form method="post" action="{{ route('customer-ship-locations.store') }}">
        @csrf
        @include('customer_ship_locations.partials.form', ['location' => $location ?? null])
    </form>
@endsection

