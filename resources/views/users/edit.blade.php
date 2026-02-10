@extends('layouts.app')

@section('title', __('ui.edit_user').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.edit_user') }}</h1>
    <form method="post" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')
        @include('users.partials.form', ['user' => $user])
    </form>
@endsection
