@extends('layouts.app')

@section('title', __('txn.return').' '.__('txn.detail').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.return') }} {{ $salesReturn->return_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('sales-returns.index') }}">{{ __('txn.back') }}</a>
            <a class="btn secondary" target="_blank" href="{{ route('sales-returns.print', $salesReturn) }}">{{ __('txn.print') }}</a>
            <a class="btn secondary" href="{{ route('sales-returns.export.pdf', $salesReturn) }}">{{ __('txn.pdf') }}</a>
            <a class="btn" href="{{ route('sales-returns.export.excel', $salesReturn) }}">{{ __('txn.excel') }}</a>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.return_summary') }}</h3>
            <p class="form-section-note">{{ __('txn.return_summary_note') }}</p>
            <div class="row">
                <div class="col-4"><strong>{{ __('txn.customer') }}</strong><div>{{ $salesReturn->customer->name }}</div></div>
                <div class="col-4"><strong>{{ __('txn.city') }}</strong><div>{{ $salesReturn->customer->city }}</div></div>
                <div class="col-4"><strong>{{ __('txn.phone') }}</strong><div>{{ $salesReturn->customer->phone ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.return_date') }}</strong><div>{{ $salesReturn->return_date->format('d-m-Y') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.semester_period') }}</strong><div>{{ $salesReturn->semester_period ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.total') }}</strong><div>Rp {{ number_format($salesReturn->total, 2) }}</div></div>
                <div class="col-12"><strong>{{ __('txn.reason') }}</strong><div>{{ $salesReturn->reason ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.return_items') }}</h3>
            <p class="form-section-note">{{ __('txn.return_items_note') }}</p>
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.code') }}</th>
                    <th>{{ __('txn.product') }}</th>
                    <th>{{ __('txn.qty') }}</th>
                    <th>{{ __('txn.line_total') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($salesReturn->items as $item)
                    <tr>
                        <td>{{ $item->product_code }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ number_format($item->quantity) }}</td>
                        <td>Rp {{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
