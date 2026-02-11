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
            </div>
        </div>
    </div>
@endsection


