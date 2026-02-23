@extends('layouts.app')

@section('title', __('school_bulk.ship_location_master_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('school_bulk.ship_location_master_title') }}</h1>
        <a class="btn" href="{{ route('customer-ship-locations.create') }}">{{ __('school_bulk.add_ship_location') }}</a>
    </div>

    <div class="card">
        <form method="get" class="flex">
            <select name="customer_id" style="max-width: 280px;">
                <option value="">{{ __('school_bulk.all_customers') }}</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((int) $selectedCustomerId === (int) $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('school_bulk.search_ship_location_placeholder') }}" style="max-width: 320px;">
            <button type="submit">{{ __('txn.search') }}</button>
            <a class="btn secondary" href="{{ route('customer-ship-locations.index') }}">{{ __('txn.all') }}</a>
        </form>
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
                            <a class="btn secondary" href="{{ route('customer-ship-locations.edit', $location) }}">{{ __('ui.edit') }}</a>
                            <form method="post" action="{{ route('customer-ship-locations.destroy', $location) }}" onsubmit="return confirm('{{ __('school_bulk.confirm_delete_ship_location') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn">{{ __('ui.delete') }}</button>
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
