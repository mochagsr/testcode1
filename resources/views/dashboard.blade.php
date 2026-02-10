@extends('layouts.app')

@section('title', __('ui.dashboard_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.dashboard_title') }}</h1>

    <div class="grid">
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_total_products') }}</div>
            <div class="stat-value">{{ number_format($summary['total_products']) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_total_customers') }}</div>
            <div class="stat-value">{{ number_format($summary['total_customers']) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_global_receivable') }}</div>
            <div class="stat-value">Rp {{ number_format($summary['total_receivable'], 2) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">{{ __('ui.dashboard_invoice_this_month') }}</div>
            <div class="stat-value">Rp {{ number_format($summary['invoice_this_month'], 2) }}</div>
        </div>
    </div>

    <div class="card">
        <h3>{{ __('ui.dashboard_recent_invoices') }}</h3>
        <table>
            <thead>
            <tr>
                <th>{{ __('txn.no') }}</th>
                <th>{{ __('txn.date') }}</th>
                <th>{{ __('txn.customer') }}</th>
                <th>{{ __('txn.total') }}</th>
                <th>{{ __('txn.balance') }}</th>
                <th>{{ __('txn.status') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($recentInvoices as $invoice)
                <tr>
                    <td><a href="{{ route('sales-invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                    <td>{{ $invoice->invoice_date->format('d-m-Y') }}</td>
                    <td>{{ $invoice->customer->name }}</td>
                    <td>Rp {{ number_format($invoice->total, 2) }}</td>
                    <td>Rp {{ number_format($invoice->balance, 2) }}</td>
                    <td>{{ strtoupper($invoice->payment_status) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">{{ __('ui.dashboard_no_invoices') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
