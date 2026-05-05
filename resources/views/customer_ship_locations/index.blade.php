@extends('layouts.app')

@section('title', __('school_bulk.ship_location_master_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .ship-location-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .ship-location-toolbar .toolbar-left {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .ship-location-toolbar .toolbar-left {
            flex: 1 1 480px;
        }
        .ship-location-toolbar .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0;
        }
        .ship-location-toolbar .search-form {
            width: 100%;
            max-width: 760px;
        }
        .ship-location-toolbar .search-form select {
            width: 260px;
            max-width: min(260px, 100%);
        }
        .ship-location-toolbar .search-form input[type="text"] {
            width: 320px;
            max-width: min(320px, 100%);
            flex: 1 1 240px;
            min-width: 0;
        }
        @media (max-width: 1400px) {
            .ship-location-toolbar .toolbar-left {
                flex: 1 1 100%;
            }
        }
        @media (max-width: 1280px) {
            .ship-location-toolbar {
                align-items: flex-start;
            }
            .ship-location-toolbar .search-form {
                width: 100%;
            }
            .ship-location-toolbar .search-form select,
            .ship-location-toolbar .search-form input[type="text"] {
                width: min(100%, 280px);
                max-width: min(100%, 280px);
            }
        }
    </style>
    @php
        $canManageShipLocations = auth()->user()?->canAccess('customer_ship_locations.create') ?? false;
    @endphp
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('school_bulk.ship_location_master_title') }}</h1>
        @if($canManageShipLocations)
            <a class="btn" href="{{ route('customer-ship-locations.create') }}">{{ __('school_bulk.add_ship_location') }}</a>
        @endif
    </div>

    <div class="card">
        <div class="ship-location-toolbar">
            <div class="toolbar-left">
                <form method="get" class="search-form">
                    <select name="customer_id">
                        <option value="">{{ __('school_bulk.all_customers') }}</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) $selectedCustomerId === (int) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('school_bulk.search_ship_location_placeholder') }}">
                    <button type="submit">{{ __('txn.search') }}</button>
                    <a class="btn secondary" href="{{ route('customer-ship-locations.index') }}">{{ __('txn.all') }}</a>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>{{ __('txn.customer') }}</th>
                <th>{{ __('school_bulk.school_name') }}</th>
                <th>{{ __('txn.phone') }}</th>
                <th>{{ __('txn.city') }}</th>
                <th>{{ __('txn.address') }}</th>
                <th>{{ __('txn.status') }}</th>
                <th>{{ __('txn.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($locations as $location)
                <tr>
                    <td>{{ $location->customer?->name ?: '-' }}</td>
                    <td>{{ $location->school_name }}</td>
                    <td>{{ $location->recipient_phone ?: '-' }}</td>
                    <td>{{ $location->city ?: '-' }}</td>
                    <td>{{ $location->address ?: '-' }}</td>
                    <td>
                        @if($location->is_active)
                            <span class="badge success">{{ __('txn.status_active') }}</span>
                        @else
                            <span class="badge danger">{{ __('txn.status_canceled') }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex">
                            <a class="btn edit-btn" href="{{ route('customer-ship-locations.edit', $location) }}">{{ __('ui.edit') }}</a>
                            <form method="post" action="{{ route('customer-ship-locations.destroy', $location) }}" data-confirm-modal data-confirm-message="{{ __('school_bulk.confirm_delete_ship_location') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">{{ __('school_bulk.no_ship_locations') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $locations->links() }}
        </div>
    </div>
@endsection

