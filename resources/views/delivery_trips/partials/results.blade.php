@php
    $sortUrl = function (string $field) use ($search, $selectedTripDate, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('delivery-trips.index', array_filter(['search' => $search, 'trip_date' => $selectedTripDate, 'sort' => $field, 'direction' => $nextDir], fn ($v) => $v !== null && $v !== ''));
    };
    $sortMark = function (string $field) use ($sort, $direction): string {
        if ($sort !== $field) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
@endphp

<div class="card">
    <div class="transaction-list-scroll">
    <table>
        <thead>
            <tr>
                <th>{{ __('delivery_trip.trip_number') }}</th>
                <th><a class="sort-link" href="{{ $sortUrl('date') }}">{{ __('txn.date') }} <span class="sort-mark">{{ $sortMark('date') }}</span></a></th>
                <th><a class="sort-link" href="{{ $sortUrl('driver_name') }}">{{ __('delivery_trip.driver_name') }} <span class="sort-mark">{{ $sortMark('driver_name') }}</span></a></th>
                <th><a class="sort-link" href="{{ $sortUrl('assistant_name') }}">{{ __('delivery_trip.assistant_name') }} <span class="sort-mark">{{ $sortMark('assistant_name') }}</span></a></th>
                <th><a class="sort-link" href="{{ $sortUrl('vehicle_plate') }}">{{ __('delivery_trip.vehicle_plate') }} <span class="sort-mark">{{ $sortMark('vehicle_plate') }}</span></a></th>
                <th>{{ __('delivery_trip.total_cost') }}</th>
                <th>{{ __('delivery_trip.completed_at') }}</th>
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
                <td>{{ $trip->completed_at?->format('d-m-Y H:i') ?: '-' }}</td>
                <td>
                    <select class="action-menu action-menu-sm" onchange="if(this.value){window.location.href=this.value; this.selectedIndex=0;}">
                        <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                        <option value="{{ route('delivery-trips.show', $trip) }}">{{ __('txn.detail') }}</option>
                        @if(auth()->user()?->canAccess('delivery_trips.edit'))
                            <option value="{{ route('delivery-trips.edit', $trip) }}">{{ __('ui.edit') }}</option>
                        @endif
                        <option value="{{ route('delivery-trips.print', $trip) }}">{{ __('txn.print') }}</option>
                        <option value="{{ route('delivery-trips.export.pdf', $trip) }}">{{ __('txn.pdf') }}</option>
                        <option value="{{ route('delivery-trips.export.excel', $trip) }}">{{ __('txn.excel') }}</option>
                    </select>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="muted">{{ __('delivery_trip.empty') }}</td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <div style="margin-top: 12px;">{{ $trips->links() }}</div>
</div>
