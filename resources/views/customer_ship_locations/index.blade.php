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
        .ship-location-status-form {
            margin: 0;
        }
        .ship-location-status-toggle {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
        }
        .ship-location-status-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .ship-location-status-track {
            position: relative;
            width: 48px;
            height: 28px;
            border-radius: 999px;
            background: #98a2b3;
            border: 1px solid #98a2b3;
            transition: background 0.18s ease, border-color 0.18s ease;
            flex: 0 0 auto;
        }
        .ship-location-status-track::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(16, 24, 40, 0.22);
            transition: transform 0.18s ease;
        }
        .ship-location-status-input:checked + .ship-location-status-track {
            background: #12b76a;
            border-color: #12b76a;
        }
        .ship-location-status-input:checked + .ship-location-status-track::after {
            transform: translateX(20px);
        }
        .ship-location-status-input:focus-visible + .ship-location-status-track {
            outline: 3px solid color-mix(in srgb, var(--accent) 40%, transparent);
            outline-offset: 2px;
        }
        .ship-location-status-label {
            min-width: 74px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .ship-location-status-label.is-active {
            color: #12b76a;
        }
        .ship-location-status-label.is-inactive {
            color: #98a2b3;
        }
        .ship-location-table {
            table-layout: fixed;
            width: 100%;
        }
        .ship-location-table th:nth-child(1),
        .ship-location-table td:nth-child(1) {
            width: 15%;
        }
        .ship-location-table th:nth-child(2),
        .ship-location-table td:nth-child(2) {
            width: 21%;
        }
        .ship-location-table th:nth-child(3),
        .ship-location-table td:nth-child(3) {
            width: 15%;
        }
        .ship-location-table th:nth-child(4),
        .ship-location-table td:nth-child(4) {
            width: 10%;
        }
        .ship-location-table th:nth-child(5),
        .ship-location-table td:nth-child(5) {
            width: auto;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        .ship-location-table th:nth-child(6),
        .ship-location-table td:nth-child(6) {
            width: 104px;
        }
        .ship-location-table th:nth-child(7),
        .ship-location-table td:nth-child(7) {
            width: 136px;
        }
        .ship-location-action {
            display: flex;
            gap: 4px;
            justify-content: flex-start;
            flex-wrap: nowrap;
        }
        .ship-location-action .btn {
            min-width: 0;
            padding-left: 10px;
            padding-right: 10px;
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
        .sort-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
    </style>
    @php
        $canManageShipLocations = auth()->user()?->canAccess('customer_ship_locations.create') ?? false;
        $sortUrl = function (string $field) use ($search, $selectedCustomerId, $sort, $direction): string {
            $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
            return route('customer-ship-locations.index', array_filter(['search' => $search, 'customer_id' => $selectedCustomerId, 'sort' => $field, 'direction' => $nextDir], fn ($v) => $v !== null && $v !== '' && $v !== 0));
        };
        $sortMark = function (string $field) use ($sort, $direction): string {
            if ($sort !== $field) return '↕';
            return $direction === 'asc' ? '↑' : '↓';
        };
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
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
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
        <table class="ship-location-table">
            <thead>
            <tr>
                <th><a class="sort-link" href="{{ $sortUrl('customer') }}">{{ __('ui.customer_name') }} <span class="sort-mark">{{ $sortMark('customer') }}</span></a></th>
                <th><a class="sort-link" href="{{ $sortUrl('school_name') }}">{{ __('school_bulk.school_name') }} <span class="sort-mark">{{ $sortMark('school_name') }}</span></a></th>
                <th>{{ __('txn.phone') }}</th>
                <th><a class="sort-link" href="{{ $sortUrl('city') }}">{{ __('txn.city') }} <span class="sort-mark">{{ $sortMark('city') }}</span></a></th>
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
                        @if($canManageShipLocations)
                            <form method="post" action="{{ route('customer-ship-locations.update-status', $location) }}" class="ship-location-status-form">
                                @csrf
                                @method('PATCH')
                                <label class="ship-location-status-toggle" aria-label="{{ __('txn.status') }} {{ $location->school_name }}">
                                    <input type="hidden" name="is_active" value="0">
                                    <input
                                        type="checkbox"
                                        name="is_active"
                                        value="1"
                                        class="ship-location-status-input"
                                        @checked($location->is_active)
                                        onchange="this.form.submit()"
                                    >
                                    <span class="ship-location-status-track" aria-hidden="true"></span>
                                    <span class="ship-location-status-label {{ $location->is_active ? 'is-active' : 'is-inactive' }}">
                                        {{ $location->is_active ? __('txn.status_active') : __('school_bulk.status_inactive') }}
                                    </span>
                                </label>
                            </form>
                        @else
                            @if($location->is_active)
                                <span class="badge success">{{ __('txn.status_active') }}</span>
                            @else
                                <span class="badge danger">{{ __('school_bulk.status_inactive') }}</span>
                            @endif
                        @endif
                    </td>
                    <td>
                        <div class="ship-location-action">
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

