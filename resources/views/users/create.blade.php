@extends('layouts.app')

@section('title', __('ui.add_user').' - '.config('app.name', 'Laravel'))

@section('content')
    <h1 class="page-title">{{ __('ui.add_user') }}</h1>
    <form method="post" action="{{ route('users.store') }}">
        @csrf
        @include('users.partials.form', ['user' => null])
    </form>
@endsection

