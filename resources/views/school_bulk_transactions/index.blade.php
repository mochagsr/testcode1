@extends('layouts.app')

@section('title', __('school_bulk.bulk_transaction_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .school-bulk-table {
            table-layout: fixed;
            min-width: 1040px;
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
            width: 190px;
            text-align: center;
        }
        .school-bulk-table .action-menu {
            width: 82px;
            min-width: 82px;
        }
        .school-bulk-actions {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-wrap: nowrap;
            gap: 6px;
            width: 100%;
        }
        .school-bulk-actions .danger-btn {
            min-height: 34px;
            padding: 6px 10px;
        }
        .school-bulk-actions .danger-btn[disabled] {
            cursor: help;
            opacity: .55;
        }
        .sort-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
    </style>
    @php
        $sortUrl = function (string $field) use ($search, $selectedCustomerId, $sort, $direction): string {
            $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
            return route('school-bulk-transactions.index', array_filter(['search' => $search, 'customer_id' => $selectedCustomerId, 'sort' => $field, 'direction' => $nextDir], fn ($v) => $v !== null && $v !== '' && $v !== 0));
        };
        $sortMark = function (string $field) use ($sort, $direction): string {
            if ($sort !== $field) return '↕';
            return $direction === 'asc' ? '↑' : '↓';
        };
    @endphp
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('school_bulk.bulk_transaction_title') }}</h1>
        @if(auth()->user()?->canAccess('school_bulk_transactions.create'))
            <a class="btn create-transaction-btn" href="{{ route('school-bulk-transactions.create') }}">{{ __('school_bulk.create_bulk_transaction') }}</a>
        @endif
    </div>

    <div class="card">
        <form method="get" class="flex">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
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
        <div class="transaction-list-scroll">
            <table class="school-bulk-table">
                <thead>
                <tr>
                    <th>{{ __('school_bulk.transaction_number') }}</th>
                    <th><a class="sort-link" href="{{ $sortUrl('date') }}">{{ __('txn.date') }} <span class="sort-mark">{{ $sortMark('date') }}</span></a></th>
                    <th><a class="sort-link" href="{{ $sortUrl('customer') }}">{{ __('ui.customer_name') }} <span class="sort-mark">{{ $sortMark('customer') }}</span></a></th>
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
                        $canDeleteDraft = auth()->user()?->canAccess('school_bulk_transactions.delete')
                            && (int) ($transaction->generated_delivery_documents_count ?? 0) === 0
                            && (int) ($transaction->generated_invoice_documents_count ?? 0) === 0;
                        $showDeleteBlockedAction = auth()->user()?->canAccess('school_bulk_transactions.delete') && ! $canDeleteDraft;
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
                            <div class="school-bulk-actions">
                                <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank');this.selectedIndex=0;}">
                                    <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                                    <option value="{{ route('school-bulk-transactions.show', $transaction) }}">{{ __('txn.detail') }}</option>
                                    <option value="{{ route('school-bulk-transactions.print', $transaction) }}">{{ __('txn.print') }}</option>
                                    <option value="{{ route('school-bulk-transactions.export.pdf', $transaction) }}">{{ __('txn.pdf') }}</option>
                                    <option value="{{ route('school-bulk-transactions.export.excel', $transaction) }}">{{ __('txn.excel') }}</option>
                                </select>
                                @if($canDeleteDraft)
                                    <form method="post" action="{{ route('school-bulk-transactions.destroy', $transaction) }}" data-confirm-modal data-confirm-message="{{ __('school_bulk.confirm_delete_bulk_transaction') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
                                    </form>
                                @endif
                                @if($showDeleteBlockedAction)
                                    <button type="button" class="btn danger-btn" disabled title="{{ __('school_bulk.bulk_transaction_delete_hint') }}">{{ __('ui.delete') }}</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted">{{ __('school_bulk.no_bulk_transactions') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 12px;">
            {{ $transactions->links() }}
        </div>
    </div>
@endsection

