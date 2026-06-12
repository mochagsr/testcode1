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
