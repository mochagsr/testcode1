@extends('layouts.app')

@section('title', __('receivable.create_payment').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('receivable.create_payment') }}</h1>

    <form method="post" action="{{ route('receivable-payments.store') }}">
        @csrf

        <div class="card">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('receivable.payment_header') }}</h3>
                <p class="form-section-note">{{ __('receivable.payment_header_note') }}</p>
                @php
                    $customerMap = $customers->keyBy('id');
                    $oldCustomerId = old('customer_id');
                    $oldCustomer = $oldCustomerId && $customerMap->has((int) $oldCustomerId) ? $customerMap[(int) $oldCustomerId] : null;
                    $oldCustomerLabel = $oldCustomer ? $oldCustomer->name.' ('.$oldCustomer->city.')' : '';
                @endphp
                <div class="row">
                    <div class="col-6">
                        <label>{{ __('receivable.customer') }} <span class="label-required">*</span></label>
                        <input type="text" id="customer-search" list="customers-list" value="{{ $oldCustomerLabel }}" placeholder="{{ __('txn.select_customer') }}" required>
                        <input type="hidden" id="customer-id" name="customer_id" value="{{ old('customer_id') }}" required>
                        <datalist id="customers-list">
                            @foreach($customers as $customer)
                                <option value="{{ $customer->name }} ({{ $customer->city }})"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-6">
                        <label>{{ __('txn.date') }} <span class="label-required">*</span></label>
                        <input type="date" name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-6">
                        <label>{{ __('txn.address') }}</label>
                        <textarea id="customer-address" name="customer_address" rows="2">{{ old('customer_address') }}</textarea>
                    </div>
                    <div class="col-6">
                        <label>{{ __('receivable.outstanding') }}</label>
                        <input type="text" id="customer-outstanding" value="Rp 0.00" readonly>
                    </div>
                    <div class="col-6">
                        <label>{{ __('receivable.amount_paid') }} <span class="label-required">*</span></label>
                        <input type="number" min="0.01" step="0.01" name="amount" id="payment-amount" value="{{ old('amount') }}" required>
                    </div>
                    <div class="col-6">
                        <label>{{ __('receivable.amount_in_words') }}</label>
                        <textarea id="amount-in-words" rows="2" readonly></textarea>
                    </div>
                    <div class="col-6">
                        <label>{{ __('receivable.customer_signature') }} <span class="label-required">*</span></label>
                        <input type="text" name="customer_signature" value="{{ old('customer_signature') }}" required>
                    </div>
                    <div class="col-6">
                        <label>{{ __('receivable.user_signature') }} <span class="label-required">*</span></label>
                        <input type="text" name="user_signature" value="{{ old('user_signature', auth()->user()->name ?? '') }}" required>
                    </div>
                    <div class="col-12">
                        <label>{{ __('txn.notes') }}</label>
                        <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <button class="btn" type="submit">{{ __('receivable.save_receivable_payment') }}</button>
        <a class="btn secondary" href="{{ route('receivable-payments.index') }}">{{ __('txn.cancel') }}</a>
    </form>

    <script>
        const customers = @json($customers->values());
        const customerSearch = document.getElementById('customer-search');
        const customerIdField = document.getElementById('customer-id');
        const customerAddressField = document.getElementById('customer-address');
        const customerOutstandingField = document.getElementById('customer-outstanding');
        const paymentAmountField = document.getElementById('payment-amount');
        const amountInWordsField = document.getElementById('amount-in-words');

        function customerLabel(customer) {
            return `${customer.name} (${customer.city})`;
        }

        function findCustomerByLabel(label) {
            if (!label) {
                return null;
            }
            const normalized = label.trim().toLowerCase();
            return customers.find((customer) => customerLabel(customer).toLowerCase() === normalized)
                || customers.find((customer) => customer.name.toLowerCase() === normalized)
                || customers.find((customer) => customerLabel(customer).toLowerCase().includes(normalized))
                || customers.find((customer) => customer.name.toLowerCase().includes(normalized))
                || null;
        }

        function money(value) {
            const amount = Number(value || 0);
            return `Rp ${amount.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }

        function terbilangNumber(num) {
            const words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
            const n = Math.abs(Math.floor(num));
            if (n < 12) return words[n];
            if (n < 20) return `${terbilangNumber(n - 10)} belas`;
            if (n < 100) return `${terbilangNumber(Math.floor(n / 10))} puluh ${terbilangNumber(n % 10)}`.trim();
            if (n < 200) return `seratus ${terbilangNumber(n - 100)}`.trim();
            if (n < 1000) return `${terbilangNumber(Math.floor(n / 100))} ratus ${terbilangNumber(n % 100)}`.trim();
            if (n < 2000) return `seribu ${terbilangNumber(n - 1000)}`.trim();
            if (n < 1000000) return `${terbilangNumber(Math.floor(n / 1000))} ribu ${terbilangNumber(n % 1000)}`.trim();
            if (n < 1000000000) return `${terbilangNumber(Math.floor(n / 1000000))} juta ${terbilangNumber(n % 1000000)}`.trim();
            if (n < 1000000000000) return `${terbilangNumber(Math.floor(n / 1000000000))} miliar ${terbilangNumber(n % 1000000000)}`.trim();
            return `${terbilangNumber(Math.floor(n / 1000000000000))} triliun ${terbilangNumber(n % 1000000000000)}`.trim();
        }

        function toTerbilang(amount) {
            const parsed = Number(amount || 0);
            const integerPart = Math.floor(parsed);
            const decimalPart = Math.round((parsed - integerPart) * 100);
            let output = `${terbilangNumber(integerPart)} rupiah`;
            if (decimalPart > 0) {
                output += ` koma ${decimalPart.toString().split('').map(d => terbilangNumber(Number(d))).join(' ')}`;
            }
            return output.charAt(0).toUpperCase() + output.slice(1);
        }

        function updateAmountWords() {
            amountInWordsField.value = toTerbilang(paymentAmountField.value || 0);
        }

        function bindCustomer(customer) {
            if (!customer) {
                customerIdField.value = '';
                customerOutstandingField.value = money(0);
                return;
            }
            customerIdField.value = customer.id;
            customerAddressField.value = customerAddressField.value || customer.address || '';
            customerOutstandingField.value = money(customer.outstanding_receivable || 0);
            customerSearch.value = customerLabel(customer);
        }

        if (customerSearch) {
            const bootCustomer = customerIdField.value
                ? customers.find(c => String(c.id) === String(customerIdField.value))
                : findCustomerByLabel(customerSearch.value);
            bindCustomer(bootCustomer);
            customerSearch.addEventListener('input', (event) => {
                bindCustomer(findCustomerByLabel(event.currentTarget.value));
            });
            customerSearch.addEventListener('change', (event) => {
                bindCustomer(findCustomerByLabel(event.currentTarget.value));
            });
        }

        paymentAmountField.addEventListener('input', updateAmountWords);
        updateAmountWords();
    </script>
@endsection
