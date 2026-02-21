@extends('layouts.app')

@section('title', $trip->trip_number.' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('delivery_trip.detail_title', ['number' => $trip->trip_number]) }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('delivery-trips.index') }}">{{ __('txn.back') }}</a>
            <a class="btn secondary" href="{{ route('delivery-trips.edit', $trip) }}">{{ __('ui.edit') }}</a>
            <a class="btn secondary" href="{{ route('delivery-trips.print', $trip) }}" target="_blank">{{ __('txn.print') }}</a>
            <a class="btn secondary" href="{{ route('delivery-trips.export.pdf', $trip) }}">{{ __('txn.pdf') }}</a>
            <a class="btn secondary" href="{{ route('delivery-trips.export.excel', $trip) }}">{{ __('txn.excel') }}</a>
        </div>
    </div>

    <div class="card">
        <div class="row inline">
            <div class="col-3"><strong>{{ __('delivery_trip.trip_number') }}</strong><div>{{ $trip->trip_number }}</div></div>
            <div class="col-3"><strong>{{ __('txn.date') }}</strong><div>{{ optional($trip->trip_date)->format('d-m-Y') }}</div></div>
            <div class="col-3"><strong>{{ __('delivery_trip.driver_name') }}</strong><div>{{ $trip->driver_name }}</div></div>
            <div class="col-3"><strong>{{ __('delivery_trip.vehicle_plate') }}</strong><div>{{ $trip->vehicle_plate ?: '-' }}</div></div>
            <div class="col-3"><strong>{{ __('delivery_trip.member_count') }}</strong><div>{{ (int) $trip->member_count }}</div></div>
            <div class="col-3"><strong>{{ __('delivery_trip.fuel_cost') }}</strong><div>Rp {{ number_format((int) $trip->fuel_cost, 0, ',', '.') }}</div></div>
            <div class="col-3"><strong>{{ __('delivery_trip.toll_cost') }}</strong><div>Rp {{ number_format((int) $trip->toll_cost, 0, ',', '.') }}</div></div>
            <div class="col-3"><strong>{{ __('delivery_trip.meal_cost') }}</strong><div>Rp {{ number_format((int) $trip->meal_cost, 0, ',', '.') }}</div></div>
            <div class="col-3"><strong>{{ __('delivery_trip.other_cost') }}</strong><div>Rp {{ number_format((int) $trip->other_cost, 0, ',', '.') }}</div></div>
            <div class="col-3"><strong>{{ __('delivery_trip.total_cost') }}</strong><div><strong>Rp {{ number_format((int) $trip->total_cost, 0, ',', '.') }}</strong></div></div>
            <div class="col-3"><strong>{{ __('txn.created_by') }}</strong><div>{{ $trip->creator?->name ?: '-' }}</div></div>
            <div class="col-3"><strong>{{ __('delivery_trip.updated_by') }}</strong><div>{{ $trip->updater?->name ?: '-' }}</div></div>
            <div class="col-12"><strong>{{ __('txn.notes') }}</strong><div>{{ $trip->notes ?: '-' }}</div></div>
        </div>
    </div>

    <div class="card">
        <strong>{{ __('delivery_trip.members') }}</strong>
        <table style="margin-top: 10px;">
            <thead>
                <tr>
                    <th>{{ __('txn.no') }}</th>
                    <th>{{ __('ui.name') }}</th>
                    <th>{{ __('ui.role') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($trip->members as $member)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $member->member_name }}</td>
                    <td>{{ $member->user?->role ? strtoupper((string) $member->user->role) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">{{ __('delivery_trip.no_member') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
