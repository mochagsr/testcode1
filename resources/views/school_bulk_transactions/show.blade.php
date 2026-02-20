@extends('layouts.app')

@section('title', __('school_bulk.bulk_transaction_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('school_bulk.bulk_transaction_title') }} {{ $transaction->transaction_number }}</h1>
        <div class="flex">
            <a class="btn secondary" href="{{ route('school-bulk-transactions.index') }}">{{ __('txn.back') }}</a>
            <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank');this.selectedIndex=0;}">
                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                <option value="{{ route('school-bulk-transactions.print', $transaction) }}">{{ __('txn.print') }}</option>
                <option value="{{ route('school-bulk-transactions.export.pdf', $transaction) }}">{{ __('txn.pdf') }}</option>
                <option value="{{ route('school-bulk-transactions.export.excel', $transaction) }}">{{ __('txn.excel') }}</option>
            </select>
        </div>
    </div>

    <div class="card">
        <div class="row">
            <div class="col-4"><strong>{{ __('school_bulk.transaction_number') }}</strong><div>{{ $transaction->transaction_number }}</div></div>
            <div class="col-4"><strong>{{ __('txn.date') }}</strong><div>{{ optional($transaction->transaction_date)->format('d-m-Y') ?: '-' }}</div></div>
            <div class="col-4"><strong>{{ __('txn.semester_period') }}</strong><div>{{ $transaction->semester_period ?: '-' }}</div></div>
            <div class="col-4"><strong>{{ __('txn.customer') }}</strong><div>{{ $transaction->customer?->name ?: '-' }}</div></div>
            <div class="col-4"><strong>{{ __('school_bulk.total_schools') }}</strong><div>{{ (int) $transaction->locations->count() }}</div></div>
            <div class="col-4"><strong>{{ __('school_bulk.total_items') }}</strong><div>{{ (int) $transaction->items->count() }}</div></div>
            <div class="col-12"><strong>{{ __('txn.notes') }}</strong><div>{{ $transaction->notes ?: '-' }}</div></div>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">{{ __('school_bulk.bulk_locations_title') }}</h3>
        <table>
            <thead>
            <tr>
                <th>{{ __('school_bulk.school_name') }}</th>
                <th>{{ __('txn.phone') }}</th>
                <th>{{ __('txn.city') }}</th>
                <th>{{ __('txn.address') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($transaction->locations as $location)
                <tr>
                    <td>{{ $location->school_name }}</td>
                    <td>{{ $location->recipient_phone ?: '-' }}</td>
                    <td>{{ $location->city ?: '-' }}</td>
                    <td>{{ $location->address ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">{{ __('txn.items') }}</h3>
        <table>
            <thead>
            <tr>
                <th>{{ __('txn.name') }}</th>
                <th>{{ __('txn.qty') }}</th>
                <th>{{ __('txn.unit') }}</th>
                <th>{{ __('txn.price') }}</th>
                <th>{{ __('txn.subtotal') }}</th>
            </tr>
            </thead>
            <tbody>
            @php
                $perSchoolTotal = 0;
            @endphp
            @foreach($transaction->items as $item)
                @php
                    $lineTotal = ((int) $item->quantity) * ((int) ($item->unit_price ?? 0));
                    $perSchoolTotal += $lineTotal;
                @endphp
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ (int) $item->quantity }}</td>
                    <td>{{ $item->unit ?: '-' }}</td>
                    <td>Rp {{ number_format((int) ($item->unit_price ?? 0), 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($lineTotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top: 12px; text-align: right;">
            <strong>{{ __('school_bulk.total_per_school') }}: Rp {{ number_format($perSchoolTotal, 0, ',', '.') }}</strong><br>
            <strong>{{ __('school_bulk.grand_total_all_schools') }}: Rp {{ number_format($perSchoolTotal * (int) $transaction->locations->count(), 0, ',', '.') }}</strong>
        </div>
    </div>
@endsection

