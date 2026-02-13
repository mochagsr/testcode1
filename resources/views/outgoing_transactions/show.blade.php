@extends('layouts.app')

@section('title', __('txn.outgoing_transactions_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ $transaction->transaction_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('outgoing-transactions.index') }}">{{ __('txn.back') }}</a>
            <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('outgoing-transactions.print', $transaction) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('outgoing-transactions.export.pdf', $transaction) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('outgoing-transactions.export.excel', $transaction) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.outgoing_header') }}</h3>
            <div class="row">
                <div class="col-4"><strong>{{ __('txn.transaction_number') }}</strong><div>{{ $transaction->transaction_number }}</div></div>
                <div class="col-4"><strong>{{ __('txn.date') }}</strong><div>{{ optional($transaction->transaction_date)->format('d-m-Y') }}</div></div>
                <div class="col-4"><strong>{{ __('txn.semester_period') }}</strong><div>{{ $transaction->semester_period ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.note_number') }}</strong><div>{{ $transaction->note_number ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.supplier') }}</strong><div>{{ $transaction->supplier?->name ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('ui.supplier_company_name') }}</strong><div>{{ $transaction->supplier?->company_name ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.phone') }}</strong><div>{{ $transaction->supplier?->phone ?: '-' }}</div></div>
                <div class="col-8"><strong>{{ __('txn.address') }}</strong><div>{{ $transaction->supplier?->address ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.created_by') }}</strong><div>{{ $transaction->creator?->name ?: __('txn.system_user') }}</div></div>
                <div class="col-8"><strong>{{ __('txn.notes') }}</strong><div>{{ $transaction->notes ?: '-' }}</div></div>
                <div class="col-4"><strong>{{ __('txn.grand_total') }}</strong><div>Rp {{ number_format((int) round((float) $transaction->total, 0), 0, ',', '.') }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="form-section">
            <h3 class="form-section-title">{{ __('txn.outgoing_items') }}</h3>
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.no') }}</th>
                    <th>{{ __('txn.code') }}</th>
                    <th>{{ __('txn.name') }}</th>
                    <th>{{ __('txn.unit') }}</th>
                    <th>{{ __('txn.qty') }}</th>
                    <th>{{ __('txn.price') }}</th>
                    <th>{{ __('txn.subtotal') }}</th>
                    <th>{{ __('txn.notes') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($transaction->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->product_code ?: '-' }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->unit ?: '-' }}</td>
                        <td>{{ (int) round((float) $item->quantity, 0) }}</td>
                        <td>Rp {{ number_format((int) round((float) $item->unit_cost, 0), 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((int) round((float) $item->line_total, 0), 0, ',', '.') }}</td>
                        <td>{{ $item->notes ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="6" style="text-align: right;">{{ __('txn.grand_total') }}</th>
                    <th>Rp {{ number_format((int) round((float) $transaction->total, 0), 0, ',', '.') }}</th>
                    <th></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
