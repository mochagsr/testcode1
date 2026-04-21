@extends('layouts.app')

@section('title', __('ui.edit_category').' - '.config('app.name', 'Laravel'))

@section('content')
    <h1 class="page-title">{{ __('ui.edit_category') }}</h1>
    <form method="post" action="{{ route('item-categories.update', $category) }}">
        @csrf
        @method('PUT')
        @include('item_categories.partials.form', ['category' => $category])
    </form>
@endsection

