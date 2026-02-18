@extends('layouts.app')

@section('title', __('supplier_payable.add_payment').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('supplier_payable.add_payment') }}</h1>

    <div class="card">
        <form method="post" action="{{ route('supplier-payables.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row inline">
                <div class="col-4">
                    <label>{{ __('txn.supplier') }}</label>
                    <select name="supplier_id" required>
                        <option value="">{{ __('txn.select_supplier') }}</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', (int) ($prefillSupplierId ?? 0)) === (int) $supplier->id)>
                                {{ $supplier->name }}{{ $supplier->company_name ? ' ('.$supplier->company_name.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-4">
                    <label>{{ __('txn.date') }}</label>
                    <input type="date" name="payment_date" value="{{ old('payment_date', $prefillDate) }}" required>
                </div>
                <div class="col-4">
                    <label>{{ __('supplier_payable.proof_number') }}</label>
                    <input type="text" name="proof_number" value="{{ old('proof_number') }}" maxlength="80">
                </div>
                <div class="col-4">
                    <label>{{ __('supplier_payable.payment_proof_photo') }}</label>
                    <input type="file" name="payment_proof_photo" accept="image/*">
                </div>
                <div class="col-4">
                    <label>{{ __('txn.amount') }}</label>
                    <input type="number" min="1" step="1" name="amount" value="{{ old('amount') }}" required>
                </div>
                <div class="col-4">
                    <label>{{ __('supplier_payable.supplier_signature') }}</label>
                    <input type="text" name="supplier_signature" value="{{ old('supplier_signature') }}" maxlength="120">
                </div>
                <div class="col-4">
                    <label>{{ __('supplier_payable.user_signature') }}</label>
                    <input type="text" name="user_signature" value="{{ old('user_signature', auth()->user()->name) }}" maxlength="120">
                </div>
                <div class="col-12">
                    <label>{{ __('txn.notes') }}</label>
                    <textarea name="notes">{{ old('notes') }}</textarea>
                </div>
            </div>
            <button type="submit">{{ __('supplier_payable.save_payment') }}</button>
        </form>
    </div>
@endsection
