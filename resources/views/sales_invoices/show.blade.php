@extends('layouts.app')

@section('title', __('txn.invoice').' '.__('txn.detail').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.invoice') }} {{ $invoice->invoice_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('sales-invoices.index') }}">{{ __('txn.back') }}</a>
            <a class="btn secondary" target="_blank" href="{{ route('sales-invoices.print', $invoice) }}">{{ __('txn.print') }}</a>
            <a class="btn secondary" href="{{ route('sales-invoices.export.pdf', $invoice) }}">{{ __('txn.pdf') }}</a>
            <a class="btn" href="{{ route('sales-invoices.export.excel', $invoice) }}">{{ __('txn.excel') }}</a>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.invoice_summary') }}</h3>
            <p class="form-section-note">{{ __('txn.invoice_summary_note') }}</p>
            <div class="row">
                <div class="col-4"><strong>{{ __('txn.customer') }}</strong><div>{{ $invoice->customer->name }}</div></div>
                <div class="col-4"><strong>{{ __('txn.city') }}</strong><div>{{ $invoice->customer->city }}</div></div>
                <div class="col-4"><strong>{{ __('txn.phone') }}</strong><div>{{ $invoice->customer->phone ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.invoice_date') }}</strong><div>{{ $invoice->invoice_date->format('d-m-Y') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.semester_period') }}</strong><div>{{ $invoice->semester_period ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.status') }}</strong><div>{{ strtoupper($invoice->payment_status) }}</div></div>
                <div class="col-4"><strong>{{ __('txn.total') }}</strong><div>Rp {{ number_format($invoice->total, 2) }}</div></div>
                <div class="col-4"><strong>{{ __('txn.paid') }}</strong><div>Rp {{ number_format($invoice->total_paid, 2) }}</div></div>
                <div class="col-4"><strong>{{ __('txn.balance') }}</strong><div>Rp {{ number_format($invoice->balance, 2) }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.items') }}</h3>
            <p class="form-section-note">{{ __('txn.invoice_items_note') }}</p>
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.code') }}</th>
                    <th>{{ __('txn.product') }}</th>
                    <th>{{ __('txn.qty') }}</th>
                    <th>{{ __('txn.price') }}</th>
                    <th>{{ __('txn.discount') }}</th>
                    <th>{{ __('txn.line_total') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->product_code }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ number_format($item->quantity) }}</td>
                        <td>Rp {{ number_format($item->unit_price, 2) }}</td>
                        <td>Rp {{ number_format($item->discount, 2) }}</td>
                        <td>Rp {{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="form-section">
                    <h3 class="form-section-title">{{ __('txn.record_payment') }}</h3>
                    <p class="form-section-note">{{ __('txn.payments_note') }}</p>
                    <table>
                        <thead>
                        <tr>
                            <th>{{ __('txn.date') }}</th>
                            <th>{{ __('txn.method') }}</th>
                            <th>{{ __('txn.amount') }}</th>
                            <th>{{ __('txn.notes') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($invoice->payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date->format('d-m-Y') }}</td>
                                <td>{{ strtoupper($payment->method) }}</td>
                                <td>Rp {{ number_format($payment->amount, 2) }}</td>
                                <td>{{ $payment->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">{{ __('txn.no_payments_yet') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
