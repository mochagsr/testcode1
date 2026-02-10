@extends('layouts.app')

@section('title', __('txn.order_notes_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('txn.order_notes_title') }} {{ $note->note_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('order-notes.index') }}">{{ __('txn.back') }}</a>
            <a class="btn" target="_blank" href="{{ route('order-notes.print', $note) }}">{{ __('txn.print') }}</a>
            <a class="btn secondary" href="{{ route('order-notes.export.pdf', $note) }}">{{ __('txn.pdf') }}</a>
            <a class="btn" href="{{ route('order-notes.export.excel', $note) }}">{{ __('txn.excel') }}</a>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.order_summary') }}</h3>
            <p class="form-section-note">{{ __('txn.order_summary_note') }}</p>
            <div class="row">
                <div class="col-4"><strong>{{ __('txn.date') }}</strong><div>{{ $note->note_date->format('d-m-Y') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.customer') }}</strong><div>{{ $note->customer_name }}</div></div>
                <div class="col-4"><strong>{{ __('txn.phone') }}</strong><div>{{ $note->customer_phone ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.city') }}</strong><div>{{ $note->city ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.created_by') }}</strong><div>{{ $note->created_by_name ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.linked_customer') }}</strong><div>{{ $note->customer?->name ?: '-' }}</div></div>
                <div class="col-12"><strong>{{ __('txn.notes') }}</strong><div>{{ $note->notes ?: '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.items') }}</h3>
            <p class="form-section-note">{{ __('txn.order_items_note') }}</p>
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.code') }}</th>
                    <th>{{ __('txn.name') }}</th>
                    <th>{{ __('txn.qty') }}</th>
                    <th>{{ __('txn.notes') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($note->items as $item)
                    <tr>
                        <td>{{ $item->product_code ?: '-' }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ number_format($item->quantity) }}</td>
                        <td>{{ $item->notes ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
