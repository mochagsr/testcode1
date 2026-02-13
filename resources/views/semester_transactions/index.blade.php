@extends('layouts.app')

@section('title', __('menu.semester_transactions'))

@section('content')
    <h1 class="page-title">{{ __('menu.semester_transactions') }}</h1>

    <div class="card">
        <form method="get" class="row inline" style="align-items:end;">
            <div class="col-3">
                <label for="semester">{{ __('txn.semester_period') }}</label>
                <select id="semester" name="semester">
                    @foreach($semesterOptions as $option)
                        <option value="{{ $option }}" @selected($selectedSemester === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-3">
                <label for="type">{{ __('txn.type') }}</label>
                <select id="type" name="type">
                    <option value="all" @selected($selectedType === 'all')>{{ __('txn.all') }}</option>
                    <option value="sales_invoice" @selected($selectedType === 'sales_invoice')>{{ __('menu.sales_invoices') }}</option>
                    <option value="sales_return" @selected($selectedType === 'sales_return')>{{ __('menu.sales_returns') }}</option>
                    <option value="delivery_note" @selected($selectedType === 'delivery_note')>{{ __('menu.delivery_notes') }}</option>
                    <option value="order_note" @selected($selectedType === 'order_note')>{{ __('menu.order_notes') }}</option>
                    <option value="outgoing_transaction" @selected($selectedType === 'outgoing_transaction')>{{ __('menu.outgoing_transactions') }}</option>
                    <option value="receivable_payment" @selected($selectedType === 'receivable_payment')>{{ __('menu.receivable_payments') }}</option>
                </select>
            </div>
            <div class="col-4">
                <label for="search">{{ __('ui.search') }}</label>
                <input
                    id="search"
                    name="search"
                    type="text"
                    value="{{ $search }}"
                    placeholder="{{ __('ui.search_placeholder') }}"
                    maxlength="120"
                >
            </div>
            <div class="col-2 flex" style="justify-content:flex-end;">
                <button type="submit">{{ __('ui.search') }}</button>
                <a class="btn secondary" href="{{ route('semester-transactions.index') }}">{{ __('ui.reset') }}</a>
            </div>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>{{ __('txn.date') }}</th>
                    <th>{{ __('txn.type') }}</th>
                    <th>{{ __('txn.note_number') }}</th>
                    <th>{{ __('txn.customer') }}/{{ __('menu.suppliers') }}</th>
                    <th>{{ __('txn.city') }}</th>
                    <th>{{ __('txn.total') }}</th>
                    <th>{{ __('txn.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $row)
                    @php
                        $txDate = $row->tx_date ? \Carbon\Carbon::parse($row->tx_date)->format('d-m-Y') : '-';
                    @endphp
                    <tr>
                        <td>{{ $txDate }}</td>
                        <td>{{ $row->type_label }}</td>
                        <td>
                            @if($row->detail_url !== '#')
                                <a href="{{ $row->detail_url }}">{{ $row->tx_number }}</a>
                            @else
                                {{ $row->tx_number }}
                            @endif
                        </td>
                        <td>{{ $row->party_name }}</td>
                        <td>{{ $row->city }}</td>
                        <td>
                            @if($row->amount === null)
                                -
                            @else
                                Rp {{ number_format((int) round((float) $row->amount), 0, ',', '.') }}
                            @endif
                        </td>
                        <td>
                            @if((bool) $row->is_canceled)
                                <span class="badge danger">{{ __('txn.status_canceled') }}</span>
                            @else
                                <span class="badge success">{{ __('txn.status_active') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">{{ __('ui.no_data') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div style="margin-top:10px;">{{ $transactions->links() }}</div>
    </div>
@endsection
