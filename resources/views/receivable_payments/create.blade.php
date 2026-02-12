@extends('layouts.app')

@section('title', __('receivable.create_payment').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('receivable.create_payment') }}</h1>

    <form method="post" action="{{ route('receivable-payments.store') }}">
        @csrf
        <input type="hidden" name="return_to" value="{{ old('return_to', $returnTo ?? null) }}">

        <div class="card">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('receivable.payment_header') }}</h3>
                <p class="form-section-note">{{ __('receivable.payment_header_note') }}</p>
                <p class="muted" style="margin-top: -6px;">{{ __('receivable.overpayment_note') }}</p>
                @php
                    $customerMap = $customers->keyBy('id');
                    $oldCustomerId = old('customer_id', $prefillCustomerId ?? null);
                    $oldCustomer = $oldCustomerId && $customerMap->has((int) $oldCustomerId) ? $customerMap[(int) $oldCustomerId] : null;
                    $oldCustomerLabel = $oldCustomer ? $oldCustomer->name.' ('.($oldCustomer->city ?: '-').')' : '';
                @endphp
                @if(!empty($preferredInvoice))
                    <p class="muted" style="margin-top: -2px; margin-bottom: 10px;">
                        {{ __('receivable.preferred_invoice_hint', ['invoice' => $preferredInvoice->invoice_number, 'balance' => number_format((int) round($preferredInvoice->balance), 0, ',', '.')]) }}
                    </p>
                @endif
                <div class="row">
                    <div class="col-6">
                        <label>{{ __('receivable.customer') }} <span class="label-required">*</span></label>
                        <input type="text" id="customer-search" list="customers-list" value="{{ $oldCustomerLabel }}" placeholder="{{ __('txn.select_customer') }}" required>
                        <input type="hidden" id="customer-id" name="customer_id" value="{{ old('customer_id', $prefillCustomerId ?? null) }}" required>
                        <datalist id="customers-list">
                            @foreach($customers as $customer)
                                <option value="{{ $customer->name }} ({{ $customer->city ?: '-' }})"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-6">
                        <label>{{ __('txn.date') }} <span class="label-required">*</span></label>
                        <input type="date" name="payment_date" value="{{ old('payment_date', $prefillDate ?? now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-6">
                        <label>{{ __('txn.address') }}</label>
                        <textarea id="customer-address" name="customer_address" rows="2">{{ old('customer_address') }}</textarea>
                    </div>
                    <div class="col-6">
                        <label>{{ __('receivable.outstanding') }}</label>
                        <input type="text" id="customer-outstanding" value="Rp 0" readonly>
                    </div>
                    <div class="col-6">
                        <label>{{ __('receivable.amount_paid') }} <span class="label-required">*</span></label>
                        @php $oldAmountValue = old('amount', $prefillAmount ?? null); @endphp
                        <input type="text" inputmode="numeric" id="payment-amount-display" value="{{ $oldAmountValue !== null ? number_format((int) round((float) $oldAmountValue), 0, ',', '.') : '' }}" placeholder="0" required>
                        <input type="hidden" name="amount" id="payment-amount" value="{{ $oldAmountValue !== null ? (int) round((float) $oldAmountValue) : '' }}" required>
                        <div id="payment-amount-feedback" class="muted" style="margin-top: 4px;"></div>
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
                        <input type="hidden" name="preferred_invoice_id" value="{{ old('preferred_invoice_id', $preferredInvoice?->id) }}">
                        <label>{{ __('txn.notes') }}</label>
                        <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <button class="btn" type="submit">{{ __('receivable.save_receivable_payment') }}</button>
        <a class="btn secondary" href="{{ old('return_to', $returnTo ?? route('receivable-payments.index')) }}">{{ __('txn.cancel') }}</a>
    </form>

    <script>
        const customers = @json($customers->values());
        const customerSearch = document.getElementById('customer-search');
        const customerIdField = document.getElementById('customer-id');
        const customerAddressField = document.getElementById('customer-address');
        const customerOutstandingField = document.getElementById('customer-outstanding');
        const paymentAmountDisplayField = document.getElementById('payment-amount-display');
        const paymentAmountField = document.getElementById('payment-amount');
        const paymentAmountFeedback = document.getElementById('payment-amount-feedback');
        const amountInWordsField = document.getElementById('amount-in-words');
        const SEARCH_DEBOUNCE_MS = 100;

        function debounce(fn, wait = SEARCH_DEBOUNCE_MS) {
            let timeoutId = null;
            return (...args) => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => fn(...args), wait);
            };
        }

        function customerLabel(customer) {
            const city = customer.city || '-';
            return `${customer.name} (${city})`;
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
            return `Rp ${new Intl.NumberFormat('id-ID').format(Math.round(amount))}`;
        }

        function digitsOnly(value) {
            return (value || '').toString().replace(/\D/g, '');
        }

        function formatThousands(value) {
            if (!value) {
                return '';
            }

            return value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function setAmountValue(value) {
            const normalized = Math.max(0, Math.round(Number(value || 0)));
            paymentAmountField.value = String(normalized);
            if (paymentAmountDisplayField) {
                paymentAmountDisplayField.value = formatThousands(String(normalized));
            }
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
            const integerPart = Math.round(Number(amount || 0));
            const output = `${terbilangNumber(integerPart)} rupiah`;
            return output.charAt(0).toUpperCase() + output.slice(1);
        }

        function updateAmountWords() {
            amountInWordsField.value = toTerbilang(paymentAmountField.value || 0);
        }

        function validatePaymentAmount() {
            const selectedCustomer = customers.find((customer) => String(customer.id) === String(customerIdField.value));
            const outstanding = Math.round(Number(selectedCustomer?.outstanding_receivable || 0));
            const amount = Math.round(Number(paymentAmountField.value || 0));

            if (!selectedCustomer) {
                paymentAmountField.setCustomValidity('');
                if (paymentAmountFeedback) {
                    paymentAmountFeedback.textContent = '';
                }
                return;
            }

            if (amount < 1) {
                const message = @json(__('validation.min.numeric', ['attribute' => __('receivable.amount_paid'), 'min' => 1]));
                paymentAmountField.setCustomValidity(message);
                if (paymentAmountFeedback) {
                    paymentAmountFeedback.textContent = message;
                }
                return;
            }

            paymentAmountField.setCustomValidity('');
            if (paymentAmountFeedback) {
                if (amount > outstanding) {
                    const overpaid = amount - outstanding;
                    paymentAmountFeedback.textContent = @json(__('receivable.overpayment_becomes_balance')).replace(':amount', money(overpaid));
                } else if (outstanding > 0) {
                    paymentAmountFeedback.textContent = @json(__('receivable.remaining_after_payment')).replace(':amount', money(outstanding - amount));
                } else {
                    paymentAmountFeedback.textContent = @json(__('receivable.customer_has_no_outstanding'));
                }
            }
        }

        function bindCustomer(customer, preserveAmount = false) {
            if (!customer) {
                customerIdField.value = '';
                customerOutstandingField.value = money(0);
                customerAddressField.value = '';
                if (!preserveAmount) {
                    paymentAmountField.value = '';
                    if (paymentAmountDisplayField) {
                        paymentAmountDisplayField.value = '';
                    }
                }
                updateAmountWords();
                return;
            }
            customerIdField.value = customer.id;
            customerAddressField.value = customer.address || '';
            const outstanding = Number(customer.outstanding_receivable || 0);
            customerOutstandingField.value = money(outstanding);
            if (!preserveAmount) {
                setAmountValue(outstanding > 0 ? outstanding : 0);
            }
            updateAmountWords();
            validatePaymentAmount();
            customerSearch.value = customerLabel(customer);
        }

        if (customerSearch) {
            const bootCustomer = customerIdField.value
                ? customers.find(c => String(c.id) === String(customerIdField.value))
                : findCustomerByLabel(customerSearch.value);
            const hasPresetAmount = String(paymentAmountField.value || '').trim() !== '';
            bindCustomer(bootCustomer, hasPresetAmount);
            const onCustomerInput = debounce((event) => {
                bindCustomer(findCustomerByLabel(event.currentTarget.value));
            });
            customerSearch.addEventListener('input', onCustomerInput);
            customerSearch.addEventListener('change', (event) => {
                bindCustomer(findCustomerByLabel(event.currentTarget.value));
            });
        }

        paymentAmountDisplayField?.addEventListener('input', () => {
            const digits = digitsOnly(paymentAmountDisplayField.value);
            paymentAmountField.value = digits;
            paymentAmountDisplayField.value = formatThousands(digits);
            updateAmountWords();
            validatePaymentAmount();
        });
        paymentAmountDisplayField?.addEventListener('focus', () => {
            if ((paymentAmountDisplayField.value || '').trim() === '0') {
                paymentAmountDisplayField.value = '';
                paymentAmountField.value = '';
            }
        });
        paymentAmountDisplayField?.addEventListener('blur', () => {
            const digits = digitsOnly(paymentAmountDisplayField.value);
            if (digits === '') {
                paymentAmountDisplayField.value = '0';
                paymentAmountField.value = '0';
            } else {
                paymentAmountDisplayField.value = formatThousands(digits);
                paymentAmountField.value = digits;
            }
            updateAmountWords();
            validatePaymentAmount();
        });
        paymentAmountField.addEventListener('change', validatePaymentAmount);
        document.querySelector('form')?.addEventListener('submit', (event) => {
            validatePaymentAmount();
            if (!paymentAmountField.checkValidity()) {
                event.preventDefault();
                if (paymentAmountDisplayField) {
                    paymentAmountDisplayField.setCustomValidity(paymentAmountField.validationMessage || '');
                    paymentAmountDisplayField.reportValidity();
                    paymentAmountDisplayField.setCustomValidity('');
                } else {
                    paymentAmountField.reportValidity();
                }
            }
        });
        if (paymentAmountField.value !== '') {
            setAmountValue(paymentAmountField.value);
        }
        updateAmountWords();
        validatePaymentAmount();
    </script>
@endsection

