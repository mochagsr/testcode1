@extends('layouts.app')

@section('title', __('ui.dashboard_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.dashboard_title') }}</h1>

    <div class="grid">
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_total_products') }}</div>
            <div class="stat-value">{{ (int) round($summary['total_products']) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_total_customers') }}</div>
            <div class="stat-value">{{ (int) round($summary['total_customers']) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_global_receivable') }}</div>
            <div class="stat-value">Rp {{ number_format((int) round($summary['total_receivable']), 0, ',', '.') }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_invoice_this_month') }}</div>
            <div class="stat-value">Rp {{ number_format((int) round($summary['invoice_this_month']), 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="row" style="margin-top: 8px;">
        <div class="col-6">
            <div class="card">
                <h3>{{ __('ui.dashboard_uncollected_receivables') }}</h3>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('ui.customer') }}</th>
                        <th>{{ __('ui.city') }}</th>
                        <th>{{ __('ui.dashboard_global_receivable') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($uncollectedCustomers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->city ?: '-' }}</td>
                            <td>Rp {{ number_format((int) round($customer->outstanding_receivable), 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="muted">{{ __('ui.dashboard_no_uncollected_receivables') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                @if(method_exists($uncollectedCustomers, 'links'))
                    <div style="margin-top: 12px;">
                        {{ $uncollectedCustomers->links() }}
                    </div>
                @endif
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <h3>{{ __('ui.dashboard_pending_order_notes') }}</h3>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('ui.dashboard_order_note_number') }}</th>
                        <th>{{ __('ui.customer') }}</th>
                        <th>{{ __('txn.date') }}</th>
                        <th>{{ __('ui.dashboard_order_note_progress') }}</th>
                        <th>{{ __('ui.dashboard_order_note_remaining') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($pendingOrderNotes as $note)
                        @php
                            $orderedTotal = (int) round((float) ($note->ordered_total ?? 0));
                            $fulfilledTotal = (int) round((float) ($note->fulfilled_total ?? 0));
                            $remainingTotal = max(0, (int) round((float) ($note->remaining_total ?? 0)));
                            $progressPercent = $orderedTotal > 0 ? min(100, round(($fulfilledTotal / $orderedTotal) * 100, 2)) : 0;
                            $progressLabel = rtrim(rtrim(number_format($progressPercent, 2, '.', ''), '0'), '.');
                        @endphp
                        <tr>
                            <td><a href="{{ route('order-notes.show', $note->id) }}">{{ $note->note_number }}</a></td>
                            <td>{{ $note->customer_name ?: '-' }}</td>
                            <td>{{ optional($note->note_date)->format('d-m-Y') ?: '-' }}</td>
                            <td>{{ $progressLabel }}%</td>
                            <td>{{ number_format($remainingTotal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">{{ __('ui.dashboard_no_pending_order_notes') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                @if(method_exists($pendingOrderNotes, 'links'))
                    <div style="margin-top: 12px;">
                        {{ $pendingOrderNotes->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
