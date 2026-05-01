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
        .ship-location-toolbar .toolbar-left,
        .ship-location-toolbar .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .ship-location-toolbar .toolbar-left {
            flex: 1 1 480px;
        }
        .ship-location-toolbar .toolbar-right {
            justify-content: flex-end;
            flex: 1 1 520px;
        }
        .ship-location-toolbar .search-form,
        .ship-location-toolbar .import-form {
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
        .ship-location-toolbar .import-form {
            justify-content: flex-end;
            width: 100%;
            gap: 12px;
        }
        .ship-location-toolbar .import-file-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: color-mix(in srgb, var(--card) 92%, var(--background) 8%);
            flex: 0 1 320px;
            min-width: 280px;
        }
        .ship-location-toolbar .import-file-wrap input[type="file"] {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            flex: 1 1 auto;
        }
        .ship-location-toolbar .import-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        @media (max-width: 1400px) {
            .ship-location-toolbar .toolbar-left,
            .ship-location-toolbar .toolbar-right {
                flex: 1 1 100%;
            }
            .ship-location-toolbar .toolbar-right,
            .ship-location-toolbar .import-form {
                justify-content: flex-start;
            }
        }
        @media (max-width: 1280px) {
            .ship-location-toolbar {
                align-items: flex-start;
            }
            .ship-location-toolbar .search-form,
            .ship-location-toolbar .import-form {
                width: 100%;
            }
            .ship-location-toolbar .search-form select,
            .ship-location-toolbar .search-form input[type="text"],
            .ship-location-toolbar .import-file-wrap {
                width: min(100%, 280px);
                max-width: min(100%, 280px);
            }
            .ship-location-toolbar .import-actions {
                flex: 1 1 100%;
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
            @if($canManageShipLocations)
                <div class="toolbar-right">
                    <form method="post" action="{{ route('customer-ship-locations.import') }}" enctype="multipart/form-data" class="import-form">
                        @csrf
                        <div class="import-file-wrap">
                            <input type="file" name="import_file" accept=".xlsx,.xls,.csv,.txt" required>
                        </div>
                        <div class="import-actions">
                            <button type="submit" class="btn process-btn">Import</button>
                            <a class="btn info-btn" href="{{ route('customer-ship-locations.import.template') }}">Template Import</a>
                        </div>
                    </form>
                </div>
            @endif
        </div>
        @if(session('import_errors'))
            <div class="card" style="margin-top:8px; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.4);">
                <strong>Error Import:</strong>
                <ul style="margin:8px 0 0 18px;">
                    @foreach(array_slice((array) session('import_errors'), 0, 20) as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
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

