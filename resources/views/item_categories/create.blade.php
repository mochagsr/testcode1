@extends('layouts.app')

@section('title', __('ui.add_category').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.add_category') }}</h1>
    <form method="post" action="{{ route('item-categories.store') }}">
        @csrf
        @include('item_categories.partials.form', ['category' => null])
    </form>
@endsection
