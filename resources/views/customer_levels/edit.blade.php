@extends('layouts.app')

@section('title', __('ui.edit_customer_level').' - '.config('app.name', 'Laravel'))

@section('content')
    <h1 class="page-title">{{ __('ui.edit_customer_level') }}</h1>
    <form method="post" action="{{ route('customer-levels-web.update', $level) }}">
        @csrf
        @method('PUT')
        @include('customer_levels.partials.form', ['level' => $level])
    </form>
@endsection

