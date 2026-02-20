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

    @php
        $generatedLocationIds = $transaction->generatedInvoices
            ->pluck('school_bulk_location_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique();
        $generatedCount = $generatedLocationIds->count();
        $totalSchools = (int) $transaction->locations->count();
        $pendingCount = max(0, $totalSchools - $generatedCount);
    @endphp
    <div class="card">
        <div class="flex" style="justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">{{ __('school_bulk.generate_invoices') }}</h3>
            <div class="flex">
                <span class="badge success">{{ $generatedCount }} / {{ $totalSchools }} {{ __('school_bulk.total_schools') }}</span>
                <span class="badge warning">{{ __('school_bulk.pending_schools') }}: {{ $pendingCount }}</span>
            </div>
        </div>
        <form method="post" action="{{ route('school-bulk-transactions.generate-invoices', $transaction) }}" class="row" style="margin-top: 10px;">
            @csrf
            <input type="hidden" name="_idempotency_key" value="{{ 'bulk-generate-'.$transaction->id.'-'.now()->timestamp }}">
            <div class="col-3">
                <label>{{ __('txn.invoice_date') }}</label>
                <input type="date" name="invoice_date" value="{{ old('invoice_date', optional($transaction->transaction_date)->format('Y-m-d')) }}">
                @error('invoice_date')
                <small style="color:#b42318;">{{ $message }}</small>
                @enderror
            </div>
            <div class="col-3">
                <label>{{ __('txn.due_date') }}</label>
                <input type="date" name="due_date" value="{{ old('due_date') }}">
                @error('due_date')
                <small style="color:#b42318;">{{ $message }}</small>
                @enderror
            </div>
            <div class="col-6" style="display:flex; align-items:flex-end; justify-content:flex-end;">
                <button type="submit" class="btn">{{ __('school_bulk.generate_invoices') }}</button>
            </div>
        </form>
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

    <div class="card">
        <h3 style="margin-top: 0;">{{ __('school_bulk.generated_invoices_title') }}</h3>
        @if($transaction->generatedInvoices->isEmpty())
            <p class="muted" style="margin: 8px 0 0;">{{ __('school_bulk.no_generated_invoices') }}</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>{{ __('txn.date') }}</th>
                    <th>{{ __('txn.invoice') }}</th>
                    <th>{{ __('school_bulk.school_name') }}</th>
                    <th>{{ __('txn.status') }}</th>
                    <th>{{ __('txn.balance') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($transaction->generatedInvoices as $invoice)
                    <tr>
                        <td>{{ optional($invoice->invoice_date)->format('d-m-Y') ?: '-' }}</td>
                        <td><a href="{{ route('sales-invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                        <td>{{ $invoice->schoolBulkLocation?->school_name ?: '-' }}</td>
                        <td>
                            @if((bool) $invoice->is_canceled)
                                <span class="badge danger">{{ __('txn.status_canceled') }}</span>
                            @elseif((string) $invoice->payment_status === 'paid')
                                <span class="badge success">{{ __('txn.status_paid') }}</span>
                            @else
                                <span class="badge warning">{{ __('txn.status_unpaid') }}</span>
                            @endif
                        </td>
                        <td>Rp {{ number_format((int) round((float) $invoice->balance), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
