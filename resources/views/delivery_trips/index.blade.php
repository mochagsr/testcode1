@extends('layouts.app')

@section('title', __('delivery_trip.title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('delivery_trip.title') }}</h1>
        <a class="btn" href="{{ route('delivery-trips.create') }}">{{ __('delivery_trip.create') }}</a>
    </div>

    <div class="card">
        <form id="delivery-trip-filter-form" method="get" class="flex">
            <input id="delivery-trip-search-input" type="text" name="search" value="{{ $search }}" placeholder="{{ __('delivery_trip.search_placeholder') }}" style="max-width: 320px;">
            <input id="delivery-trip-date-input" type="date" name="trip_date" value="{{ $selectedTripDate ?? '' }}" style="max-width: 180px;">
            <button type="submit">{{ __('txn.search') }}</button>
            <a class="btn secondary" href="{{ route('delivery-trips.index') }}">{{ __('txn.all') }}</a>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('delivery_trip.trip_number') }}</th>
                    <th>{{ __('txn.date') }}</th>
                    <th>{{ __('delivery_trip.driver_name') }}</th>
                    <th>{{ __('delivery_trip.assistant_name') }}</th>
                    <th>{{ __('delivery_trip.vehicle_plate') }}</th>
                    <th>{{ __('delivery_trip.total_cost') }}</th>
                    <th>{{ __('txn.action') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($trips as $trip)
                <tr>
                    <td><a href="{{ route('delivery-trips.show', $trip) }}">{{ $trip->trip_number }}</a></td>
                    <td>{{ optional($trip->trip_date)->format('d-m-Y') }}</td>
                    <td>{{ $trip->driver_name }}</td>
                    <td>{{ $trip->assistant_name ?: '-' }}</td>
                    <td>{{ $trip->vehicle_plate ?: '-' }}</td>
                    <td>Rp {{ number_format((int) $trip->total_cost, 0, ',', '.') }}</td>
                    <td>
                        <select class="action-menu action-menu-sm" onchange="if(this.value){window.location.href=this.value; this.selectedIndex=0;}">
                            <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                            <option value="{{ route('delivery-trips.show', $trip) }}">{{ __('txn.detail') }}</option>
                            <option value="{{ route('delivery-trips.edit', $trip) }}">{{ __('ui.edit') }}</option>
                            <option value="{{ route('delivery-trips.print', $trip) }}">{{ __('txn.print') }}</option>
                            <option value="{{ route('delivery-trips.export.pdf', $trip) }}">{{ __('txn.pdf') }}</option>
                            <option value="{{ route('delivery-trips.export.excel', $trip) }}">{{ __('txn.excel') }}</option>
                        </select>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">{{ __('delivery_trip.empty') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">{{ $trips->links() }}</div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('delivery-trip-filter-form');
            const searchInput = document.getElementById('delivery-trip-search-input');
            const dateInput = document.getElementById('delivery-trip-date-input');
            if (!form || !searchInput || !dateInput) {
                return;
            }

            const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                ? window.PgposAutoSearch.debounce
                : (fn, wait = 100) => {
                    let timeoutId = null;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => fn(...args), wait);
                    };
                };

            const onSearchInput = debounce(() => {
                if (window.PgposAutoSearch && !window.PgposAutoSearch.canSearchInput(searchInput)) {
                    return;
                }
                form.requestSubmit();
            }, 100);

            searchInput.addEventListener('input', onSearchInput);
            dateInput.addEventListener('change', () => form.requestSubmit());
        })();
    </script>
@endsection
