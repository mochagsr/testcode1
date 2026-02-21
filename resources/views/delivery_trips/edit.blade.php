@extends('layouts.app')

@section('title', __('delivery_trip.edit').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('delivery_trip.edit') }}: {{ $trip->trip_number }}</h1>
    <form method="post" action="{{ route('delivery-trips.update', $trip) }}">
        @csrf
        @method('PUT')
        @include('delivery_trips.partials.form', [
            'trip' => $trip,
            'users' => $users,
            'prefillDate' => optional($trip->trip_date)->format('Y-m-d'),
            'selectedUserIds' => collect(old('member_user_ids', $selectedUserIds ?? []))->map(fn ($id) => (int) $id),
            'extraMemberNames' => old('extra_member_names', $extraMemberNames ?? ''),
        ])
    </form>
@endsection
