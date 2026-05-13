@extends('layouts.app')

@section('title', __('delivery_trip.title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .delivery-trip-active-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 14px;
        }
        .delivery-trip-active-card {
            display: grid;
            gap: 12px;
            padding: 16px;
            border: 1px solid color-mix(in srgb, var(--border) 84%, transparent);
            border-radius: 18px;
            background: color-mix(in srgb, var(--surface-2) 88%, transparent);
        }
        .delivery-trip-active-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .delivery-trip-active-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 12px;
        }
        .delivery-trip-active-meta strong {
            display: block;
            margin-bottom: 3px;
            font-size: 12px;
        }
        .delivery-trip-status-form {
            margin: 0;
        }
        .delivery-trip-status-toggle {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
        }
        .delivery-trip-status-input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .delivery-trip-status-track {
            position: relative;
            width: 48px;
            height: 28px;
            border-radius: 999px;
            background: #98a2b3;
            border: 1px solid #98a2b3;
            transition: background 0.18s ease, border-color 0.18s ease;
            flex: 0 0 auto;
        }
        .delivery-trip-status-track::after {
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
        .delivery-trip-status-input:checked + .delivery-trip-status-track {
            background: #12b76a;
            border-color: #12b76a;
        }
        .delivery-trip-status-input:checked + .delivery-trip-status-track::after {
            transform: translateX(20px);
        }
        .delivery-trip-status-input:focus-visible + .delivery-trip-status-track {
            outline: 3px solid color-mix(in srgb, var(--accent) 40%, transparent);
            outline-offset: 2px;
        }
        .delivery-trip-status-label {
            min-width: 76px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .delivery-trip-status-label.is-active {
            color: #12b76a;
        }
        .delivery-trip-status-label.is-inactive {
            color: #98a2b3;
        }
        @media (max-width: 720px) {
            .delivery-trip-active-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
    @php
        $canCompleteDeliveryTrips = auth()->user()?->canAccess('delivery_trips.edit') ?? false;
    @endphp
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('delivery_trip.title') }}</h1>
        @if(auth()->user()?->canAccess('delivery_trips.create'))
            <a class="btn" href="{{ route('delivery-trips.create') }}">{{ __('delivery_trip.create') }}</a>
        @endif
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
        <div style="display: grid; gap: 6px; margin-bottom: 14px;">
            <h2 style="margin: 0;">{{ __('delivery_trip.active_trips_title') }}</h2>
            <p class="muted" style="margin: 0;">{{ __('delivery_trip.active_trips_note') }}</p>
        </div>

        @forelse($activeTrips as $trip)
            @if($loop->first)
                <div class="delivery-trip-active-grid">
            @endif
                <article class="delivery-trip-active-card">
                    <div class="delivery-trip-active-head">
                        <div>
                            <strong><a href="{{ route('delivery-trips.show', $trip) }}">{{ $trip->trip_number }}</a></strong>
                            <div class="muted">{{ optional($trip->trip_date)->format('d-m-Y') }}</div>
                        </div>
                        <span class="badge success">{{ __('delivery_trip.trip_status_active') }}</span>
                    </div>

                    <div class="delivery-trip-active-meta">
                        <div>
                            <strong>{{ __('delivery_trip.driver_name') }}</strong>
                            <span>{{ $trip->driver_name }}</span>
                        </div>
                        <div>
                            <strong>{{ __('delivery_trip.assistant_name') }}</strong>
                            <span>{{ $trip->assistant_name ?: '-' }}</span>
                        </div>
                        <div>
                            <strong>{{ __('delivery_trip.vehicle_plate') }}</strong>
                            <span>{{ $trip->vehicle_plate ?: '-' }}</span>
                        </div>
                        <div>
                            <strong>{{ __('delivery_trip.total_cost') }}</strong>
                            <span>Rp {{ number_format((int) $trip->total_cost, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    @if($canCompleteDeliveryTrips)
                        <form method="post" action="{{ route('delivery-trips.complete', $trip) }}" class="delivery-trip-status-form" data-delivery-trip-status-form>
                            @csrf
                            @method('PATCH')
                            <label class="delivery-trip-status-toggle" aria-label="{{ __('delivery_trip.trip_status_active') }} {{ $trip->trip_number }}">
                                <input
                                    type="checkbox"
                                    class="delivery-trip-status-input"
                                    checked
                                    data-delivery-trip-status-toggle
                                >
                                <span class="delivery-trip-status-track" aria-hidden="true"></span>
                                <span class="delivery-trip-status-label is-active" data-delivery-trip-status-label>
                                    {{ __('delivery_trip.trip_status_active') }}
                                </span>
                            </label>
                        </form>
                    @endif
                </article>
            @if($loop->last)
                </div>
            @endif
        @empty
            <p class="muted" style="margin: 0;">{{ __('delivery_trip.no_active_trips') }}</p>
        @endforelse
    </div>

    <div class="card">
        <div class="transaction-list-scroll">
        <table>
            <thead>
                <tr>
                    <th>{{ __('delivery_trip.trip_number') }}</th>
                    <th>{{ __('txn.date') }}</th>
                    <th>{{ __('delivery_trip.driver_name') }}</th>
                    <th>{{ __('delivery_trip.assistant_name') }}</th>
                    <th>{{ __('delivery_trip.vehicle_plate') }}</th>
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

        (function () {
            const forms = document.querySelectorAll('[data-delivery-trip-status-form]');
            forms.forEach((form) => {
                const toggle = form.querySelector('[data-delivery-trip-status-toggle]');
                const label = form.querySelector('[data-delivery-trip-status-label]');

                if (!toggle || !label) {
                    return;
                }

                toggle.addEventListener('change', () => {
                    if (toggle.checked) {
                        return;
                    }

                    label.textContent = @json(__('delivery_trip.trip_status_inactive'));
                    label.classList.remove('is-active');
                    label.classList.add('is-inactive');
                    toggle.disabled = true;
                    form.submit();
                });
            });
        })();
    </script>
@endsection

