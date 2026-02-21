@extends('layouts.app')

@section('title', __('delivery_trip.create').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('delivery_trip.create') }}</h1>
    <form method="post" action="{{ route('delivery-trips.store') }}">
        @csrf
        @include('delivery_trips.partials.form', [
            'trip' => null,
            'users' => $users,
            'prefillDate' => $prefillDate,
            'selectedUserIds' => collect(old('member_user_ids', []))->map(fn ($id) => (int) $id),
            'extraMemberNames' => old('extra_member_names', ''),
        ])
    </form>
@endsection
