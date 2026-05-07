@extends('layouts.app')

@section('title', __('school_bulk.bulk_transaction_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .school-bulk-table {
            table-layout: fixed;
            width: 100%;
        }
        .school-bulk-table th:nth-child(1),
        .school-bulk-table td:nth-child(1) {
            width: 19%;
        }
        .school-bulk-table th:nth-child(2),
        .school-bulk-table td:nth-child(2) {
            width: 11%;
        }
        .school-bulk-table th:nth-child(3),
        .school-bulk-table td:nth-child(3) {
            width: 14%;
        }
        .school-bulk-table th:nth-child(4),
        .school-bulk-table td:nth-child(4) {
            width: 14%;
        }
        .school-bulk-table th:nth-child(5),
        .school-bulk-table td:nth-child(5),
        .school-bulk-table th:nth-child(6),
        .school-bulk-table td:nth-child(6),
        .school-bulk-table th:nth-child(7),
        .school-bulk-table td:nth-child(7) {
            width: 11%;
            text-align: center;
        }
        .school-bulk-table th:nth-child(8),
        .school-bulk-table td:nth-child(8) {
            width: 90px;
            text-align: center;
        }
        .school-bulk-table .action-menu {
            width: 82px;
            min-width: 82px;
        }
    </style>
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('school_bulk.bulk_transaction_title') }}</h1>
        @if(auth()->user()?->canAccess('school_bulk_transactions.create'))
            <a class="btn create-transaction-btn" href="{{ route('school-bulk-transactions.create') }}">{{ __('school_bulk.create_bulk_transaction') }}</a>
        @endif
    </div>

    <div class="card">
        <form method="get" class="flex">
            <select name="customer_id" style="max-width: 280px;">
                <option value="">{{ __('school_bulk.all_customers') }}</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((int) $selectedCustomerId === (int) $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
            <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('school_bulk.search_bulk_placeholder') }}" style="max-width: 320px;">
            <button type="submit">{{ __('txn.search') }}</button>
            <a class="btn secondary" href="{{ route('school-bulk-transactions.index') }}">{{ __('txn.all') }}</a>
        </form>
    </div>

    <div class="card">
        <table class="school-bulk-table">
            <thead>
            <tr>
                <th>{{ __('school_bulk.transaction_number') }}</th>
                <th>{{ __('txn.date') }}</th>
                <th>{{ __('txn.customer') }}</th>
                <th>{{ __('txn.semester_period') }}</th>
                <th>{{ __('school_bulk.total_schools') }}</th>
                <th>{{ __('school_bulk.delivery_notes_created') }}</th>
                <th>{{ __('school_bulk.delivery_notes_pending') }}</th>
                <th>{{ __('txn.action') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($transactions as $transaction)
                @php
                    $createdDeliveryNotes = min((int) $transaction->total_locations, (int) ($transaction->generated_delivery_notes_count ?? 0));
                    $pendingDeliveryNotes = max(0, (int) $transaction->total_locations - $createdDeliveryNotes);
                @endphp
                <tr>
                    <td>
                        <div class="list-doc-cell">
                            <a class="list-doc-link" href="{{ route('school-bulk-transactions.show', $transaction) }}">{{ $transaction->transaction_number }}</a>
                        </div>
                    </td>
                    <td>{{ optional($transaction->transaction_date)->format('d-m-Y') ?: '-' }}</td>
                    <td>{{ $transaction->customer?->name ?: '-' }}</td>
                    <td>{{ $transaction->semester_period ?: '-' }}</td>
                    <td>{{ (int) $transaction->total_locations }}</td>
                    <td>{{ $createdDeliveryNotes }}</td>
                    <td>{{ $pendingDeliveryNotes }}</td>
                    <td>
                        <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank');this.selectedIndex=0;}">
                            <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                            <option value="{{ route('school-bulk-transactions.show', $transaction) }}">{{ __('txn.detail') }}</option>
                            <option value="{{ route('school-bulk-transactions.print', $transaction) }}">{{ __('txn.print') }}</option>
                            <option value="{{ route('school-bulk-transactions.export.pdf', $transaction) }}">{{ __('txn.pdf') }}</option>
                            <option value="{{ route('school-bulk-transactions.export.excel', $transaction) }}">{{ __('txn.excel') }}</option>
                        </select>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">{{ __('school_bulk.no_bulk_transactions') }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $transactions->links() }}
        </div>
    </div>
@endsection

