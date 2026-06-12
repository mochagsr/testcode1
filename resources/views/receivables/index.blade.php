@extends('layouts.app')

@section('title', __('receivable.title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .receivable-ledger-table,
        .receivable-bill-table,
        .receivable-outstanding-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }
        .receivable-ledger-table thead th,
        .receivable-bill-table thead th,
        .receivable-outstanding-table thead th {
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .receivable-ledger-table tbody td,
        .receivable-bill-table tbody td,
        .receivable-outstanding-table tbody td {
            border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            vertical-align: middle;
            padding: 10px 8px;
            background: var(--table-bg);
        }
        .receivable-ledger-table td.num,
        .receivable-ledger-table th.num,
        .receivable-bill-table td.num,
        .receivable-bill-table th.num,
        .receivable-outstanding-table td.num,
        .receivable-outstanding-table th.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            overflow: visible;
            text-overflow: clip;
        }
        .receivable-ledger-table td.action,
        .receivable-ledger-table th.action,
        .receivable-outstanding-table td.action,
        .receivable-outstanding-table th.action {
            text-align: center;
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
        }
        .receivable-ledger-table th,
        .receivable-ledger-table td,
        .receivable-bill-table th,
        .receivable-bill-table td,
        .receivable-outstanding-table th,
        .receivable-outstanding-table td {
            font-variant-numeric: tabular-nums;
        }
        .receivable-ledger-table td,
        .receivable-bill-table td,
        .receivable-outstanding-table td {
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .receivable-bill-table th,
        .receivable-bill-table td,
        .receivable-outstanding-table th,
        .receivable-outstanding-table td {
            white-space: nowrap;
        }
        .receivable-bill-table td.num,
        .receivable-outstanding-table td.num {
            overflow: visible;
            text-overflow: clip;
        }
        .receivable-bill-table thead th,
        .receivable-bill-table thead th.num {
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
            line-height: 1.2;
            vertical-align: middle;
        }
        .receivable-ledger-table td.action,
        .receivable-ledger-table th.action {
            width: 78px;
            min-width: 78px;
        }
        .receivable-outstanding-table td.action,
        .receivable-outstanding-table th.action {
            width: 78px;
            min-width: 78px;
        }
        .receivable-pay-btn {
            min-width: 66px;
            padding: 4px 7px;
            font-size: 11px;
            line-height: 1.2;
            white-space: nowrap;
        }
        .receivable-customer-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .receivable-customer-table th,
        .receivable-customer-table td {
            border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            vertical-align: middle;
            padding: 8px 6px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .receivable-customer-table td.num,
        .receivable-customer-table th.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .receivable-customer-table td.customer-col,
        .receivable-customer-table th.customer-col {
            white-space: normal;
            max-width: 0;
            min-width: 180px;
        }
        .receivable-customer-table td.city-col,
        .receivable-customer-table th.city-col {
            white-space: nowrap;
            max-width: 0;
            min-width: 110px;
        }
        .receivable-customer-table td.outstanding-col,
        .receivable-customer-table th.outstanding-col {
            white-space: nowrap;
            max-width: 0;
            min-width: 140px;
        }
        .receivable-customer-table td.action-cell,
        .receivable-customer-table th.action-cell {
            white-space: nowrap;
            width: 30%;
            min-width: 240px;
            padding-right: 4px;
        }
        .receivable-customer-actions {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 6px;
            width: 100%;
        }
        .receivable-customer-actions .btn {
            min-height: 28px;
            min-width: 104px;
            padding: 4px 8px;
            font-size: 11px;
            line-height: 1.2;
            white-space: nowrap;
            text-align: center;
        }
        .receivable-customer-name {
            display: block;
            font-weight: 600;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .receivable-customer-lock {
            display: inline-flex;
            align-items: center;
            margin-top: 4px;
            max-width: 100%;
        }
        .receivable-summary-slider {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }
        .receivable-summary-item {
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
        }
        .receivable-subcard {
            border: 1px solid color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            border-radius: 8px;
            padding: 10px;
            background: color-mix(in srgb, var(--surface) 96%, var(--border) 4%);
        }
        .receivable-scroll-wrap {
            overflow-x: scroll;
            overflow-y: scroll;
            max-height: 340px;
            scrollbar-gutter: stable;
            border: 1px solid color-mix(in srgb, var(--border) 75%, var(--text) 25%);
            border-radius: 8px;
        }
        .receivable-scroll-wrap.customer {
            max-height: 360px;
            overflow-x: auto;
            overflow-y: auto;
            scrollbar-gutter: auto;
        }
        .receivable-scroll-wrap.customer table {
            min-width: 760px;
            width: 100%;
            table-layout: fixed;
        }
        .receivable-scroll-wrap.ledger table {
            min-width: 1060px;
        }
        .receivable-scroll-wrap.bill table {
            min-width: 1280px;
        }
        .receivable-scroll-wrap.outstanding table {
            min-width: 1120px;
        }
        .receivable-main-grid .receivable-col-customer {
            grid-column: span 5;
        }
        .receivable-main-grid .receivable-col-ledger {
            grid-column: span 7;
        }
        .receivable-scroll-wrap::-webkit-scrollbar {
            width: 9px;
            height: 9px;
        }
        .receivable-scroll-wrap::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--border) 65%, var(--text) 35%);
            border-radius: 999px;
        }
        .receivable-scroll-wrap::-webkit-scrollbar-track {
            background: color-mix(in srgb, var(--surface) 80%, var(--border) 20%);
        }
        .receivable-scroll-wrap.ledger thead th,
        .receivable-scroll-wrap.bill thead th,
        .receivable-scroll-wrap.outstanding thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            background: var(--table-header-bg);
            box-shadow: inset 0 -1px 0 color-mix(in srgb, var(--border) 75%, var(--text) 25%);
        }
        .receivable-scroll-wrap tbody tr:hover td {
            background: color-mix(in srgb, var(--card) 92%, var(--text) 8%);
        }
        .receivable-adjustment-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 1400;
        }
        .receivable-adjustment-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: min(560px, calc(100vw - 24px));
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            z-index: 1401;
        }
        .receivable-adjustment-modal .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 8px 0;
        }
        .receivable-adjustment-modal label {
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            color: var(--muted);
        }
        .receivable-adjustment-modal input[disabled] {
            opacity: 0.9;
            cursor: not-allowed;
            background: color-mix(in srgb, var(--surface) 92%, var(--border) 8%);
        }
        .receivable-adjustment-modal .footer {
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }
        @media (max-width: 720px) {
            .receivable-adjustment-modal .row {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 900px) {
            .receivable-summary-slider {
                display: flex;
                overflow-x: auto;
                overflow-y: hidden;
                flex-wrap: nowrap;
                padding-bottom: 4px;
                margin-bottom: 12px;
                scroll-snap-type: x mandatory;
                -webkit-overflow-scrolling: touch;
            }
            .receivable-summary-slider::-webkit-scrollbar {
                height: 6px;
            }
            .receivable-summary-slider::-webkit-scrollbar-thumb {
                background: color-mix(in srgb, var(--border) 70%, var(--text) 30%);
                border-radius: 999px;
            }
            .receivable-summary-item {
                min-width: 220px;
                flex: 0 0 220px;
                scroll-snap-align: start;
            }
        }
        @media (max-width: 1366px) {
            .receivable-main-grid .col-4,
            .receivable-main-grid .col-8 {
                grid-column: span 12;
            }
            .receivable-main-grid .receivable-col-customer,
            .receivable-main-grid .receivable-col-ledger {
                grid-column: span 12;
            }
        }
        @media (max-width: 1280px) {
            .receivable-scroll-wrap.customer table {
                min-width: 700px;
            }
            .receivable-customer-table td.customer-col,
            .receivable-customer-table th.customer-col {
                min-width: 170px;
            }
            .receivable-customer-table td.city-col,
            .receivable-customer-table th.city-col {
                min-width: 95px;
            }
            .receivable-customer-table td.outstanding-col,
            .receivable-customer-table th.outstanding-col {
                min-width: 130px;
            }
            .receivable-customer-table td.action-cell,
            .receivable-customer-table th.action-cell {
                min-width: 220px;
            }
            .receivable-customer-actions .btn {
                min-width: 98px;
                font-size: 10px;
                padding: 4px 7px;
            }
        }
        .sort-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
    </style>
    <h1 class="page-title">{{ __('receivable.title') }}</h1>

    <div class="card">
        <form id="receivable-filter-form" method="get" class="flex">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <select id="receivable-semester" name="semester" style="max-width: 180px;">
                <option value="">{{ __('receivable.all_semesters') }}</option>
                @foreach($semesterOptions as $semester)
                    <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                @endforeach
            </select>
            <select id="receivable-transaction-type" name="transaction_type" style="max-width: 180px;">
                <option value="">{{ __('txn.all') }}</option>
                <option value="product" @selected(($selectedTransactionType ?? '') === 'product')>{{ __('receivable.transaction_type_product') }}</option>
                <option value="printing" @selected(($selectedTransactionType ?? '') === 'printing')>{{ __('receivable.transaction_type_printing') }}</option>
            </select>
            <select id="receivable-customer-id" name="customer_id" style="max-width: 180px;">
                <option value="">{{ __('receivable.all_customers') }}</option>
                @if($selectedCustomerId > 0 && isset($selectedCustomerOption) && $selectedCustomerOption && !$customers->contains('id', $selectedCustomerId))
                    <option value="{{ $selectedCustomerOption['id'] }}" selected>
                        {{ $selectedCustomerOption['name'] }}
                    </option>
                @endif
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected($selectedCustomerId === $customer->id)>
                        {{ $customer->name }}
                    </option>
                @endforeach
            </select>
            <input id="receivable-search-input" type="text" name="search" value="{{ $search }}" placeholder="{{ __('receivable.search_placeholder') }}" style="max-width: 320px;">
            <button type="submit">{{ __('txn.search') }}</button>
        </form>
    </div>

    <div id="receivable-results">
        @include('receivables.partials.results')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let receivableAdjustmentIsSubmitting = false;

            const closeReceivableAdjustmentModal = () => {
                const modal = document.getElementById('receivable-adjustment-modal');
                const overlay = document.getElementById('receivable-adjustment-modal-overlay');
                if (modal) modal.style.display = 'none';
                if (overlay) overlay.style.display = 'none';
            };

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape' || receivableAdjustmentIsSubmitting) {
                    return;
                }
                const modal = document.getElementById('receivable-adjustment-modal');
                if (!modal || modal.style.display !== 'block') {
                    return;
                }
                closeReceivableAdjustmentModal();
            });

            function initReceivableAdjustmentModal() {
                const modal = document.getElementById('receivable-adjustment-modal');
                const overlay = document.getElementById('receivable-adjustment-modal-overlay');
                const openWriteoffBtn = document.getElementById('open-receivable-writeoff-modal');
                const openDiscountBtn = document.getElementById('open-receivable-discount-modal');
                const closeBtn = document.getElementById('receivable-adjustment-close');
                const saveNowBtn = document.getElementById('receivable-adjustment-save-now');
                const titleEl = document.getElementById('receivable-adjustment-title');
                const statusEl = document.getElementById('receivable-adjustment-status');
                const totalInput = document.getElementById('receivable-adjustment-total');
                const amountLabel = document.getElementById('receivable-adjustment-amount-label');
                const amountInput = document.getElementById('receivable-adjustment-amount');
                const discountWrap = document.getElementById('receivable-adjustment-discount-extra');
                const discountPercentInput = document.getElementById('receivable-adjustment-discount-percent');
                const payAmountInput = document.getElementById('receivable-adjustment-pay-amount');

                if (
                    !modal || !overlay || !openWriteoffBtn || !openDiscountBtn || !closeBtn
                    || !saveNowBtn || !titleEl || !statusEl || !totalInput || !amountLabel
                    || !amountInput || !discountWrap || !discountPercentInput || !payAmountInput
                ) {
                    return;
                }

                receivableAdjustmentIsSubmitting = false;
                let mode = 'writeoff';

                const totalOutstanding = Math.max(0, Math.round(Number(modal.getAttribute('data-total-outstanding') || '0')));
                const writeoffAction = String(modal.getAttribute('data-writeoff-action') || '');
                const discountAction = String(modal.getAttribute('data-discount-action') || '');
                const searchValue = String(modal.getAttribute('data-search') || '');
                const semesterValue = String(modal.getAttribute('data-semester') || '');
                const customerIdValue = String(modal.getAttribute('data-customer-id') || '');
                const paymentDateValue = String(modal.getAttribute('data-payment-date') || '');
                const csrfToken = String(modal.getAttribute('data-csrf') || '');

                const formatRupiah = (value) => 'Rp ' + Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
                const setStatus = (text) => {
                    statusEl.textContent = String(text || '');
                };

                const clamp = (num, min, max) => Math.min(max, Math.max(min, num));
                const toInt = (value) => Math.round(window.PgposNumberFormat.parseInt(value || 0));

                const updateDiscountFromPercent = () => {
                    const percent = clamp(Number(discountPercentInput.value || 0), 0, 100);
                    const discountAmount = Math.round((totalOutstanding * percent) / 100);
                    const payAmount = Math.max(0, totalOutstanding - discountAmount);
                    discountPercentInput.value = Number(percent.toFixed(2)).toString();
                    amountInput.value = String(discountAmount);
                    payAmountInput.value = String(payAmount);
                    window.PgposNumberFormat.formatInput(amountInput);
                    window.PgposNumberFormat.formatInput(payAmountInput);
                };

                const updateDiscountFromPay = () => {
                    const payAmount = clamp(toInt(payAmountInput.value), 0, totalOutstanding);
                    const discountAmount = Math.max(0, totalOutstanding - payAmount);
                    const percent = totalOutstanding > 0 ? (discountAmount / totalOutstanding) * 100 : 0;
                    payAmountInput.value = String(payAmount);
                    amountInput.value = String(discountAmount);
                    discountPercentInput.value = Number(percent.toFixed(2)).toString();
                    window.PgposNumberFormat.formatInput(amountInput);
                    window.PgposNumberFormat.formatInput(payAmountInput);
                };

                const updateDiscountFromAmount = () => {
                    const discountAmount = clamp(toInt(amountInput.value), 0, totalOutstanding);
                    const payAmount = Math.max(0, totalOutstanding - discountAmount);
                    const percent = totalOutstanding > 0 ? (discountAmount / totalOutstanding) * 100 : 0;
                    amountInput.value = String(discountAmount);
                    payAmountInput.value = String(payAmount);
                    discountPercentInput.value = Number(percent.toFixed(2)).toString();
                    window.PgposNumberFormat.formatInput(amountInput);
                    window.PgposNumberFormat.formatInput(payAmountInput);
                };

                const openModal = (nextMode) => {
                    mode = nextMode === 'discount' ? 'discount' : 'writeoff';
                    totalInput.value = formatRupiah(totalOutstanding);
                    setStatus(@json(__('receivable.adjustment_auto_save_hint')));

                    if (mode === 'discount') {
                        titleEl.textContent = @json(__('receivable.method_discount'));
                        amountLabel.textContent = @json(__('receivable.discount_amount'));
                        discountWrap.style.display = 'grid';
                        discountPercentInput.value = '0';
                        payAmountInput.value = String(totalOutstanding);
                        updateDiscountFromPercent();
                    } else {
                        titleEl.textContent = @json(__('receivable.method_writeoff'));
                        amountLabel.textContent = @json(__('receivable.method_writeoff'));
                        discountWrap.style.display = 'none';
                        amountInput.value = String(totalOutstanding);
                        window.PgposNumberFormat.formatInput(amountInput);
                    }

                    modal.style.display = 'block';
                    overlay.style.display = 'block';
                    setTimeout(() => amountInput.focus(), 50);
                };

                const closeModal = () => {
                    modal.style.display = 'none';
                    overlay.style.display = 'none';
                };

                const resolveAmount = () => {
                    const raw = toInt(amountInput.value);
                    if (mode === 'discount') {
                        return clamp(raw, 0, totalOutstanding);
                    }
                    return clamp(raw, 1, totalOutstanding);
                };

                const submitAdjustment = async () => {
                    if (receivableAdjustmentIsSubmitting) {
                        return;
                    }

                    const amount = resolveAmount();
                    if ((mode === 'writeoff' && amount < 1) || (mode === 'discount' && amount < 1)) {
                        setStatus(@json(__('receivable.payment_exceeds_balance')));
                        return;
                    }

                    const action = mode === 'discount' ? discountAction : writeoffAction;
                    if (!action || !csrfToken) {
                        setStatus(@json(__('ui.save_failed')));
                        return;
                    }

                    receivableAdjustmentIsSubmitting = true;
                    saveNowBtn.disabled = true;
                    setStatus(@json(__('ui.saving')));

                    const params = new URLSearchParams();
                    params.set('_token', csrfToken);
                    params.set('amount', String(amount));
                    params.set('payment_date', paymentDateValue);
                    params.set('search', searchValue);
                    params.set('semester', semesterValue);
                    params.set('customer_id', customerIdValue);

                    try {
                        const response = await fetch(action, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            },
                            body: params.toString(),
                        });
                        const data = await response.json().catch(() => ({}));

                        if (!response.ok || !data || data.ok !== true) {
                            let errMsg = @json(__('ui.save_failed'));
                            if (data && data.errors && typeof data.errors === 'object') {
                                const firstKey = Object.keys(data.errors)[0];
                                const firstErr = firstKey ? data.errors[firstKey] : null;
                                if (Array.isArray(firstErr) && firstErr.length > 0) {
                                    errMsg = String(firstErr[0]);
                                }
                            } else if (data && data.message) {
                                errMsg = String(data.message);
                            }
                            throw new Error(errMsg);
                        }

                        const serverMessage = String(data.message || @json(__('receivable.payment_saved')));
                        setStatus(serverMessage);
                        if (window.PgposFlash && typeof window.PgposFlash.show === 'function') {
                            window.PgposFlash.show(serverMessage, 'increase');
                        }
                        closeModal();
                        window.location.reload();
                    } catch (error) {
                        setStatus(error && error.message ? error.message : @json(__('ui.save_failed')));
                    } finally {
                        receivableAdjustmentIsSubmitting = false;
                        saveNowBtn.disabled = false;
                    }
                };

                const requestCloseModal = () => {
                    if (receivableAdjustmentIsSubmitting) {
                        return;
                    }
                    closeModal();
                };

                openWriteoffBtn.addEventListener('click', () => openModal('writeoff'));
                openDiscountBtn.addEventListener('click', () => openModal('discount'));
                closeBtn.addEventListener('click', requestCloseModal);
                overlay.addEventListener('click', requestCloseModal);
                saveNowBtn.addEventListener('click', submitAdjustment);

                amountInput.addEventListener('input', () => {
                    if (mode === 'discount') {
                        updateDiscountFromAmount();
                    }
                });
                amountInput.addEventListener('change', () => {
                    if (mode === 'discount') {
                        updateDiscountFromAmount();
                    }
                });

                discountPercentInput.addEventListener('input', () => {
                    if (mode !== 'discount') return;
                    updateDiscountFromPercent();
                });
                discountPercentInput.addEventListener('change', () => {
                    if (mode !== 'discount') return;
                    updateDiscountFromPercent();
                });

                payAmountInput.addEventListener('input', () => {
                    if (mode !== 'discount') return;
                    updateDiscountFromPay();
                });
                payAmountInput.addEventListener('change', () => {
                    if (mode !== 'discount') return;
                    updateDiscountFromPay();
                });
            }

            initReceivableAdjustmentModal();

            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'receivable-filter-form',
                container: 'receivable-results',
                onSwap: () => {
                    initReceivableAdjustmentModal();
                },
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('receivable-search-input'), () => ajax.submit(), 100);
            window.PgposAutoSearch.bindChangeFilters([
                document.getElementById('receivable-semester'),
                document.getElementById('receivable-transaction-type'),
                document.getElementById('receivable-customer-id'),
            ], () => ajax.submit());
        });
    </script>

@endsection


