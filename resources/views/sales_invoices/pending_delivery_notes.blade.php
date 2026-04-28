@extends('layouts.app')

@section('title', __('txn.pending_delivery_notes_invoice').' - '.config('app.name', 'Laravel'))

@section('content')
    @php
        $sortUrl = function (string $field) use ($search, $sort, $direction): string {
            $nextDirection = $sort === $field && $direction === 'asc' ? 'desc' : 'asc';

            return route('sales-invoices.pending-delivery-notes', array_filter([
                'search' => $search !== '' ? $search : null,
                'sort' => $field,
                'direction' => $nextDirection,
            ], fn ($value) => $value !== null && $value !== ''));
        };
        $sortMark = function (string $field) use ($sort, $direction): string {
            if ($sort !== $field) {
                return '↕';
            }

            return $direction === 'asc' ? '↑' : '↓';
        };
    @endphp
    <style>
        .sort-link {
            color: inherit;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .sort-mark {
            color: var(--muted);
            font-size: 0.86em;
        }
    </style>

    <div class="page-header-actions">
        <h1 class="page-title">{{ __('txn.pending_delivery_notes_invoice') }}</h1>
        <div class="actions">
            <a class="btn secondary" href="{{ route('sales-invoices.index') }}">{{ __('txn.back') }}</a>
        </div>
    </div>

    <div class="card">
        <form method="get" class="filter-toolbar">
            <div class="filter-field">
                <label>{{ __('txn.search') }}</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('txn.search_delivery_placeholder') }}" style="max-width:320px;">
            </div>
            <button type="submit">{{ __('txn.search') }}</button>
        </form>
    </div>

    <form method="get" action="{{ route('sales-invoices.create-from-delivery-notes') }}">
        <div class="card">
            <p class="muted" style="margin-top:0;">{{ __('txn.pending_delivery_notes_help') }}</p>
            <div class="table-mobile-scroll">
                <table>
                    <thead>
                    <tr>
                        <th style="width:42px;"></th>
                        <th>{{ __('txn.note_number') }}</th>
                        <th>
                            <a class="sort-link" href="{{ $sortUrl('date') }}">
                                {{ __('txn.date') }} <span class="sort-mark">{{ $sortMark('date') }}</span>
                            </a>
                        </th>
                        <th>
                            <a class="sort-link" href="{{ $sortUrl('customer') }}">
                                {{ __('txn.customer') }} <span class="sort-mark">{{ $sortMark('customer') }}</span>
                            </a>
                        </th>
                        <th>
                            <a class="sort-link" href="{{ $sortUrl('city') }}">
                                {{ __('txn.city') }} <span class="sort-mark">{{ $sortMark('city') }}</span>
                            </a>
                        </th>
                        <th class="num">{{ __('txn.delivery_qty') }}</th>
                        <th class="num">{{ __('txn.invoiced_qty') }}</th>
                        <th class="num">{{ __('txn.uninvoiced_quantity') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        @php
                            $delivered = (int) round((float) ($row->delivered_qty ?? 0));
                            $invoiced = (int) round((float) ($row->invoiced_qty ?? 0));
                            $remaining = max(0, $delivered - $invoiced);
                        @endphp
                        <tr>
                            <td><input type="checkbox" name="delivery_note_ids[]" value="{{ $row->id }}"></td>
                            <td><a href="{{ route('delivery-notes.show', $row) }}">{{ $row->note_number }}</a></td>
                            <td>{{ $row->note_date?->format('d-m-Y') }}</td>
                            <td>{{ $row->customer?->name ?: $row->recipient_name }}</td>
                            <td>{{ $row->city ?: ($row->customer?->city ?: '-') }}</td>
                            <td class="num">{{ number_format($delivered, 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($invoiced, 0, ',', '.') }}</td>
                            <td class="num"><strong>{{ number_format($remaining, 0, ',', '.') }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">{{ __('txn.no_delivery_note_ready_for_invoice') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="form-submit-actions">
                <button class="btn" type="submit">{{ __('txn.create_invoice_from_selected_delivery_notes') }}</button>
            </div>
        </div>
    </form>

    {{ $rows->links() }}
@endsection
