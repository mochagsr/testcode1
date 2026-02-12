@extends('layouts.app')

@section('title', __('receivable.title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('receivable.title') }}</h1>

    <div class="card">
        <div style="display:flex; gap:16px; justify-content:space-between; align-items:flex-start; flex-wrap:wrap;">
            <div style="flex:1 1 620px; min-width:320px;">
                <form id="receivable-filter-form" method="get" class="flex">
                    <select id="receivable-semester" name="semester" style="max-width: 180px;">
                        <option value="">{{ __('receivable.all_semesters') }}</option>
                        @foreach($semesterOptions as $semester)
                            <option value="{{ $semester }}" @selected($selectedSemester === $semester)>{{ $semester }}</option>
                        @endforeach
                    </select>
                    <select id="receivable-customer-id" name="customer_id" style="max-width: 220px;">
                        <option value="">{{ __('receivable.all_customers') }}</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected($selectedCustomerId === $customer->id)>
                                {{ $customer->name }}
                            </option>
                        @endforeach
                    </select>
                    <input id="receivable-search-input" type="text" name="search" value="{{ $search }}" placeholder="{{ __('receivable.search_placeholder') }}" style="max-width: 320px;">
                    <button type="submit">{{ __('txn.search') }}</button>
                </form>
                <div class="flex" style="margin-top: 10px; gap: 8px;">
                    <a class="btn secondary" href="{{ route('receivables.index', ['search' => $search, 'customer_id' => $selectedCustomerId]) }}">{{ __('receivable.all') }}</a>
                    <a class="btn secondary" href="{{ route('receivables.index', ['search' => $search, 'customer_id' => $selectedCustomerId, 'semester' => $currentSemester]) }}">{{ __('receivable.current_semester') }} ({{ $currentSemester }})</a>
                    <a class="btn secondary" href="{{ route('receivables.index', ['search' => $search, 'customer_id' => $selectedCustomerId, 'semester' => $previousSemester]) }}">{{ __('receivable.previous_semester') }} ({{ $previousSemester }})</a>
                </div>
                @if($selectedSemester)
                    <div class="flex" style="margin-top: 10px; gap: 8px;">
                        <span class="badge">{{ __('txn.semester_period') }}: {{ $selectedSemester }}</span>
                        <span class="badge {{ $selectedSemesterActive ? 'success' : 'warning' }}">
                            {{ $selectedSemesterActive ? __('ui.active') : __('ui.inactive') }}
                        </span>
                        <span class="badge {{ $selectedSemesterGlobalClosed ? 'danger' : 'success' }}">
                            {{ $selectedSemesterGlobalClosed ? __('ui.semester_closed') : __('ui.semester_open') }}
                        </span>
                        @if($selectedCustomerId > 0)
                            <span class="badge {{ $selectedCustomerSemesterClosed ? 'danger' : 'success' }}">
                                {{ __('receivable.customer_semester_status') }}:
                                @if($selectedCustomerSemesterClosed)
                                    @if(($customerSemesterAutoClosedMap[$selectedCustomerId] ?? false) === true)
                                        {{ __('receivable.customer_semester_locked_auto') }}
                                    @elseif(($customerSemesterManualClosedMap[$selectedCustomerId] ?? false) === true)
                                        {{ __('receivable.customer_semester_locked_manual') }}
                                    @else
                                        {{ __('receivable.customer_semester_closed') }}
                                    @endif
                                @else
                                    {{ __('receivable.customer_semester_unlocked') }}
                                @endif
                            </span>
                        @endif
                    </div>
                @endif
            </div>
            @php
                $activeSemesterForReport = $selectedSemester ?: $currentSemester;
            @endphp
            <div style="flex:1 1 460px; min-width:320px;">
                <div class="muted" style="margin-bottom:8px; font-weight:600;">{{ __('receivable.print_options_title') }}</div>
                <div style="display:grid; gap:8px;">
                    <div class="flex" style="justify-content:space-between; align-items:center; gap:8px;">
                        <span>{{ __('receivable.report_all_unpaid') }}</span>
                        <span class="flex" style="gap:6px;">
                            <select style="max-width: 200px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                <option value=""></option>
                                <option value="{{ route('reports.print', ['dataset' => 'receivables']) }}">{{ __('txn.print') }}</option>
                                <option value="{{ route('reports.export.pdf', ['dataset' => 'receivables']) }}">{{ __('txn.pdf') }}</option>
                                <option value="{{ route('reports.export.csv', ['dataset' => 'receivables']) }}">{{ __('txn.excel') }}</option>
                            </select>
                        </span>
                    </div>
                    <div class="flex" style="justify-content:space-between; align-items:center; gap:8px;">
                        <span>{{ __('receivable.report_by_semester') }} ({{ $activeSemesterForReport }})</span>
                        <span class="flex" style="gap:6px;">
                            <select style="max-width: 200px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                <option value=""></option>
                                <option value="{{ route('reports.print', ['dataset' => 'receivables', 'semester' => $activeSemesterForReport]) }}">{{ __('txn.print') }}</option>
                                <option value="{{ route('reports.export.pdf', ['dataset' => 'receivables', 'semester' => $activeSemesterForReport]) }}">{{ __('txn.pdf') }}</option>
                                <option value="{{ route('reports.export.csv', ['dataset' => 'receivables', 'semester' => $activeSemesterForReport]) }}">{{ __('txn.excel') }}</option>
                            </select>
                        </span>
                    </div>
                    <div class="flex" style="justify-content:space-between; align-items:center; gap:8px;">
                        <span>
                            {{ __('receivable.report_by_customer_semester') }}
                            @if($selectedCustomerId > 0)
                                ({{ ($selectedCustomerName ?? null) ?: __('receivable.customer_id').' '.$selectedCustomerId }} / {{ $activeSemesterForReport }})
                            @endif
                        </span>
                        <span class="flex" style="gap:6px;">
                            @if($selectedCustomerId > 0)
                                <select style="max-width: 200px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                    <option value=""></option>
                                    <option value="{{ route('reports.print', ['dataset' => 'receivables', 'customer_id' => $selectedCustomerId, 'semester' => $activeSemesterForReport]) }}">{{ __('txn.print') }}</option>
                                    <option value="{{ route('reports.export.pdf', ['dataset' => 'receivables', 'customer_id' => $selectedCustomerId, 'semester' => $activeSemesterForReport]) }}">{{ __('txn.pdf') }}</option>
                                    <option value="{{ route('reports.export.csv', ['dataset' => 'receivables', 'customer_id' => $selectedCustomerId, 'semester' => $activeSemesterForReport]) }}">{{ __('txn.excel') }}</option>
                                </select>
                            @else
                                <span class="muted">{{ __('receivable.select_customer_first') }}</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-4">
            <div class="card">
                <h3>{{ __('receivable.customer') }}</h3>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('receivable.customer') }}</th>
                        <th>{{ __('receivable.city') }}</th>
                        <th>{{ __('receivable.outstanding') }}</th>
                        <th>{{ __('receivable.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($customers as $customer)
                        @php
                            $rowCustomerSemesterClosed = $selectedSemester ? ((bool) ($customerSemesterClosedMap[$customer->id] ?? false)) : false;
                            $rowCustomerSemesterAutoClosed = $selectedSemester ? ((bool) ($customerSemesterAutoClosedMap[$customer->id] ?? false)) : false;
                            $rowCustomerSemesterManualClosed = $selectedSemester ? ((bool) ($customerSemesterManualClosedMap[$customer->id] ?? false)) : false;
                        @endphp
                        <tr>
                            <td>
                                {{ $customer->name }}
                                @if($selectedSemester)
                                    <div style="margin-top: 4px;">
                                        <span class="badge {{ $rowCustomerSemesterClosed ? 'danger' : 'success' }}">
                                            @if($rowCustomerSemesterClosed)
                                                @if($rowCustomerSemesterAutoClosed)
                                                    {{ __('receivable.customer_semester_locked_auto') }}
                                                @elseif($rowCustomerSemesterManualClosed)
                                                    {{ __('receivable.customer_semester_locked_manual') }}
                                                @else
                                                    {{ __('receivable.customer_semester_closed') }}
                                                @endif
                                            @else
                                                {{ __('receivable.customer_semester_unlocked') }}
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td>{{ $customer->city ?: '-' }}</td>
                            <td>Rp {{ number_format((int) round($customer->outstanding_receivable), 0, ',', '.') }}</td>
                            <td>
                                <a class="btn secondary" href="{{ route('receivables.index', ['customer_id' => $customer->id, 'search' => $search, 'semester' => $selectedSemester]) }}">
                                    {{ __('receivable.ledger') }}
                                </a>
                                @if((float) $customer->outstanding_receivable > 0 && ! $rowCustomerSemesterClosed)
                                    <a
                                        class="btn"
                                        href="{{ route('receivable-payments.create', ['customer_id' => $customer->id, 'amount' => (int) round((float) $customer->outstanding_receivable), 'payment_date' => now()->format('Y-m-d'), 'return_to' => request()->getRequestUri()]) }}"
                                    >
                                        {{ __('receivable.create_payment') }}
                                    </a>
                                @elseif((float) $customer->outstanding_receivable > 0 && $rowCustomerSemesterClosed)
                                    <span class="badge danger">
                                        {{ $rowCustomerSemesterAutoClosed ? __('receivable.customer_semester_locked_auto') : __('receivable.customer_semester_locked_manual') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ __('receivable.no_customers') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <div style="margin-top: 12px;">
                    {{ $customers->links() }}
                </div>
            </div>
        </div>
        <div class="col-8">
            <div class="card">
                <h3>{{ __('receivable.ledger_entries') }} @if($selectedCustomerId > 0) ({{ __('receivable.customer_id') }}: {{ $selectedCustomerId }}) @endif</h3>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('receivable.date') }}</th>
                        <th>{{ __('receivable.description') }}</th>
                        <th>{{ __('receivable.debit') }}</th>
                        <th>{{ __('receivable.credit') }}</th>
                        <th>{{ __('receivable.balance') }}</th>
                        <th>{{ __('receivable.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if($ledgerRows->isEmpty())
                        <tr><td colspan="6" class="muted">{{ __('receivable.select_customer') }}</td></tr>
                    @else
                        @php
                            $paymentRefsWithAlloc = [];
                            foreach ($ledgerRows as $scanRow) {
                                $scanDescription = (string) ($scanRow->description ?? '');
                                if (preg_match('/\b(?:KWT|PYT)-\d{8}-\d{4}\b/i', $scanDescription, $scanMatch) !== 1) {
                                    continue;
                                }
                                $scanRef = strtoupper((string) $scanMatch[0]);
                                $isAllocation = $scanRow->invoice_id !== null
                                    || str_contains(strtolower($scanDescription), ' untuk ')
                                    || str_contains(strtolower($scanDescription), ' for ');
                                if ($isAllocation) {
                                    $paymentRefsWithAlloc[$scanRef] = true;
                                }
                            }
                        @endphp
                        <?php $shownPayInvoices = []; ?>
                        @foreach($ledgerRows as $row)
                            <?php
                                $invoiceId = $row->invoice?->id;
                                $canPay = $row->invoice
                                    && (float) $row->invoice->balance > 0
                                    && !in_array($invoiceId, $shownPayInvoices, true);
                                if ($canPay && $invoiceId !== null) {
                                    $shownPayInvoices[] = $invoiceId;
                                }
                                $canPay = $canPay && !($selectedCustomerSemesterClosed ?? false);
                                $rowDescription = (string) ($row->description ?? '');
                                $rowPaymentRef = null;
                                if (preg_match('/\b(?:KWT|PYT)-\d{8}-\d{4}\b/i', $rowDescription, $rowMatch) === 1) {
                                    $rowPaymentRef = strtoupper((string) $rowMatch[0]);
                                }
                                $isSummaryOnlyPaymentRow = $rowPaymentRef !== null
                                    && isset($paymentRefsWithAlloc[$rowPaymentRef])
                                    && $row->invoice_id === null
                                    && !str_contains(strtolower($rowDescription), ' untuk ')
                                    && !str_contains(strtolower($rowDescription), ' for ');
                            ?>
                        @continue($isSummaryOnlyPaymentRow)
                        <tr>
                            <td>{{ $row->entry_date->format('d-m-Y') }}</td>
                            <td>
                                @php
                                    $descriptionText = trim((string) ($row->description ?? ''));
                                    $invoiceNumber = $row->invoice?->invoice_number ? (string) $row->invoice->invoice_number : '';
                                    $descriptionWithoutInvoice = $invoiceNumber !== ''
                                        ? trim(str_ireplace($invoiceNumber, '', $descriptionText))
                                        : $descriptionText;
                                @endphp

                                @if($row->invoice)
                                    @if($descriptionWithoutInvoice !== '')
                                        {{ $descriptionWithoutInvoice }}
                                    @endif
                                    <a href="{{ route('sales-invoices.show', $row->invoice) }}" target="_blank" rel="noopener noreferrer">
                                        {{ $invoiceNumber }}
                                    </a>
                                @else
                                    {{ $descriptionText !== '' ? $descriptionText : '-' }}
                                @endif
                                @php
                                    $descriptionUpper = strtoupper($descriptionText);
                                    $hasLegacyAdminEditText = str_contains($descriptionUpper, 'ADMIN EDIT INVOICE');
                                    $isAdminEditIncrease = str_contains($descriptionUpper, '[ADMIN EDIT FAKTUR +]')
                                        || str_contains($descriptionUpper, '[ADMIN INVOICE EDIT +]')
                                        || ($hasLegacyAdminEditText && str_contains($descriptionUpper, '(+)'));
                                    $isAdminEditDecrease = str_contains($descriptionUpper, '[ADMIN EDIT FAKTUR -]')
                                        || str_contains($descriptionUpper, '[ADMIN INVOICE EDIT -]')
                                        || ($hasLegacyAdminEditText && str_contains($descriptionUpper, '(-)'));
                                    $isAdminCancelInvoice = str_contains($descriptionUpper, '[ADMIN BATAL FAKTUR]')
                                        || str_contains($descriptionUpper, '[ADMIN INVOICE CANCEL]')
                                        || str_contains($descriptionUpper, 'PEMBATALAN FAKTUR')
                                        || str_contains($descriptionUpper, 'INVOICE CANCELLATION');
                                @endphp
                            </td>
                            <td>Rp {{ number_format((int) round($row->debit), 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((int) round($row->credit), 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((int) round($row->balance_after), 0, ',', '.') }}</td>
                            <td>
                                @if($isAdminCancelInvoice)
                                    <span class="badge danger">{{ __('txn.admin_badge_cancel') }}</span>
                                @elseif($isAdminEditIncrease)
                                    <span class="badge warning">{{ __('txn.admin_badge_edit_plus') }}</span>
                                @elseif($isAdminEditDecrease)
                                    <span class="badge warning">{{ __('txn.admin_badge_edit_minus') }}</span>
                                @elseif($canPay)
                                    <a
                                        class="btn secondary"
                                        href="{{ route('receivable-payments.create', ['customer_id' => $selectedCustomerId ?: $row->invoice->customer_id, 'amount' => (int) round((float) $row->invoice->balance), 'payment_date' => now()->format('Y-m-d'), 'preferred_invoice_id' => $row->invoice->id, 'return_to' => request()->getRequestUri()]) }}"
                                    >
                                        {{ __('receivable.pay') }}
                                    </a>
                                @elseif(($selectedCustomerSemesterClosed ?? false) === true)
                                    <span class="badge danger">{{ __('receivable.customer_semester_closed') }}</span>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @endif
                    </tbody>
                </table>
                @if($selectedCustomerId > 0)
                    @if((auth()->user()?->role ?? '') === 'admin' && $selectedSemester)
                        <div style="margin-bottom: 10px; padding: 10px; border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; display:flex; gap:10px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                            <div>
                                <strong>{{ __('receivable.customer_semester_book_title') }}</strong><br>
                                <span class="muted">
                                    {{ __('receivable.customer_semester_status') }}:
                                    @if($selectedCustomerSemesterClosed)
                                        @if(($customerSemesterAutoClosedMap[$selectedCustomerId] ?? false) === true)
                                            {{ __('receivable.customer_semester_locked_auto') }}
                                        @elseif(($customerSemesterManualClosedMap[$selectedCustomerId] ?? false) === true)
                                            {{ __('receivable.customer_semester_locked_manual') }}
                                        @else
                                            {{ __('receivable.customer_semester_closed') }}
                                        @endif
                                    @else
                                        {{ __('receivable.customer_semester_unlocked') }}
                                    @endif
                                    ({{ $selectedSemester }})
                                </span>
                            </div>
                            <div>
                                @if($selectedCustomerSemesterClosed)
                                    <form method="post" action="{{ route('receivables.customer-semester.open', ['customer' => $selectedCustomerId]) }}">
                                        @csrf
                                        <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                                        <input type="hidden" name="search" value="{{ $search }}">
                                        <input type="hidden" name="customer_id" value="{{ $selectedCustomerId }}">
                                        <button type="submit" class="btn secondary">{{ __('receivable.customer_semester_open_button') }}</button>
                                    </form>
                                @else
                                    <form method="post" action="{{ route('receivables.customer-semester.close', ['customer' => $selectedCustomerId]) }}">
                                        @csrf
                                        <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                                        <input type="hidden" name="search" value="{{ $search }}">
                                        <input type="hidden" name="customer_id" value="{{ $selectedCustomerId }}">
                                        <button type="submit" class="btn">{{ __('receivable.customer_semester_close_button') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endif
                    <div style="margin-bottom: 10px; text-align: right;">
                        <select style="max-width: 240px;" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                            <option value=""></option>
                            <option value="{{ route('receivables.print-customer-bill', ['customer' => $selectedCustomerId, 'semester' => $selectedSemester]) }}">{{ __('receivable.print_customer_bill') }}</option>
                            <option value="{{ route('receivables.export-customer-bill-pdf', ['customer' => $selectedCustomerId, 'semester' => $selectedSemester]) }}">{{ __('txn.pdf') }}</option>
                            <option value="{{ route('receivables.export-customer-bill-excel', ['customer' => $selectedCustomerId, 'semester' => $selectedSemester]) }}">{{ __('txn.excel') }}</option>
                        </select>
                    </div>
                    @if(($billStatementRows ?? collect())->isNotEmpty())
                        <div style="margin-top: 8px;">
                            <h4 style="margin: 0 0 8px 0;">{{ __('receivable.customer_bill_title') }}</h4>
                            <table>
                                <thead>
                                <tr>
                                    <th>{{ __('receivable.bill_date') }}</th>
                                    <th>{{ __('receivable.bill_proof_number') }}</th>
                                    <th>{{ __('receivable.bill_credit_sales') }}</th>
                                    <th>{{ __('receivable.bill_installment_payment') }}</th>
                                    <th>{{ __('receivable.bill_sales_return') }}</th>
                                    <th>{{ __('receivable.bill_running_balance') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($billStatementRows as $billRow)
                                    @php $isOpening = ($billRow['date_label'] ?? '') === __('receivable.bill_opening_balance'); @endphp
                                    @if($isOpening)
                                        <tr>
                                            <td>{{ $billRow['date_label'] ?? '' }}</td>
                                            <td colspan="4"></td>
                                            <td>{{ number_format((int) round((float) ($billRow['running_balance'] ?? 0)), 0, ',', '.') }}</td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td>{{ $billRow['date_label'] ?? '' }}</td>
                                            <td>
                                                @if(!empty($billRow['invoice_id']))
                                                    <a href="{{ route('sales-invoices.show', (int) $billRow['invoice_id']) }}" target="_blank" rel="noopener noreferrer">
                                                        {{ $billRow['proof_number'] ?? '' }}
                                                    </a>
                                                @else
                                                    {{ $billRow['proof_number'] ?? '' }}
                                                @endif
                                            </td>
                                            <td>{{ number_format((int) round((float) ($billRow['credit_sales'] ?? 0)), 0, ',', '.') }}</td>
                                            <td>{{ number_format((int) round((float) ($billRow['installment_payment'] ?? 0)), 0, ',', '.') }}</td>
                                            <td>{{ number_format((int) round((float) ($billRow['sales_return'] ?? 0)), 0, ',', '.') }}</td>
                                            <td>{{ number_format((int) round((float) ($billRow['running_balance'] ?? 0)), 0, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                <tr style="font-weight:700;">
                                    <td colspan="2" style="text-align:center;">{{ __('receivable.bill_total') }}</td>
                                    <td>{{ number_format((int) round((float) (($billStatementTotals['credit_sales'] ?? 0))), 0, ',', '.') }}</td>
                                    <td>{{ number_format((int) round((float) (($billStatementTotals['installment_payment'] ?? 0))), 0, ',', '.') }}</td>
                                    <td>{{ number_format((int) round((float) (($billStatementTotals['sales_return'] ?? 0))), 0, ',', '.') }}</td>
                                    <td>{{ number_format((int) round((float) (($billStatementTotals['running_balance'] ?? 0))), 0, ',', '.') }}</td>
                                </tr>
                                <tr style="font-weight:700;">
                                    <td colspan="3"></td>
                                    <td colspan="2" style="text-align:right;">{{ __('receivable.bill_total_receivable') }}</td>
                                    <td>{{ number_format((int) round((float) (($billStatementTotals['running_balance'] ?? 0))), 0, ',', '.') }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    @endif
                    <div style="margin-top: 10px; text-align: right; font-weight: 700;">
                        {{ __('receivable.final_uncollected_balance') }}:
                        Rp {{ number_format((int) round(max(0, (float) ($customerOutstandingTotal ?? 0))), 0, ',', '.') }}
                    </div>
                    @if((float) ($customerOutstandingTotal ?? 0) > 0 && !($selectedCustomerSemesterClosed ?? false))
                        <div style="margin-top: 8px; text-align: right;">
                            <a
                                class="btn secondary"
                                href="{{ route('receivable-payments.create', ['customer_id' => $selectedCustomerId, 'amount' => (int) round((float) ($customerOutstandingTotal ?? 0)), 'payment_date' => now()->format('Y-m-d'), 'return_to' => request()->getRequestUri()]) }}"
                            >
                                {{ __('receivable.create_payment') }}
                            </a>
                        </div>
                    @elseif((float) ($customerOutstandingTotal ?? 0) > 0 && ($selectedCustomerSemesterClosed ?? false))
                        <div style="margin-top: 8px; text-align: right;">
                            <span class="badge danger">{{ __('receivable.customer_semester_closed') }}</span>
                        </div>
                    @endif
                    <div style="margin-top: 4px; text-align: right;" class="muted">
                        {{ __('receivable.ledger_mutation_balance') }}:
                        Rp {{ number_format((int) round(max(0, (float) ($ledgerOutstandingTotal ?? 0))), 0, ',', '.') }}
                    </div>
                    @if((auth()->user()?->role ?? '') === 'admin' && (float) ($customerOutstandingTotal ?? 0) > 0)
                        <div style="margin-top: 12px; display:flex; justify-content:flex-end;">
                            <form
                                id="customer-adjustment-form"
                                method="post"
                                action="{{ route('receivables.customer-writeoff', $selectedCustomerId) }}"
                                data-writeoff-action="{{ route('receivables.customer-writeoff', $selectedCustomerId) }}"
                                data-discount-action="{{ route('receivables.customer-discount', $selectedCustomerId) }}"
                                style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; justify-content:flex-end;"
                            >
                                @csrf
                                <input type="hidden" name="search" value="{{ $search }}">
                                <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                                <input type="hidden" name="customer_id" value="{{ $selectedCustomerId }}">
                                <div>
                                    <label style="display:block; margin-bottom:4px;">{{ __('receivable.payment_method') }}</label>
                                    <select id="customer-adjustment-method" name="method" required style="max-width:170px;">
                                        <option value="writeoff">{{ __('receivable.method_writeoff') }}</option>
                                        <option value="discount">{{ __('receivable.method_discount') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:4px;">{{ __('receivable.payment_amount') }}</label>
                                    <input
                                        id="customer-adjustment-amount"
                                        type="number"
                                        name="amount"
                                        value="{{ (int) round((float) ($customerOutstandingTotal ?? 0)) }}"
                                        min="1"
                                        max="{{ (int) round((float) ($customerOutstandingTotal ?? 0)) }}"
                                        step="1"
                                        required
                                        style="max-width:170px;"
                                    >
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:4px;">{{ __('receivable.payment_date') }}</label>
                                    <input type="date" name="payment_date" value="{{ now()->format('Y-m-d') }}" style="max-width:170px;">
                                </div>
                                <button type="submit" class="btn">{{ __('receivable.save_payment') }}</button>
                            </form>
                        </div>
                    @endif
                    <div style="margin-top: 12px;">
                        <h4 style="margin: 0 0 8px 0;">{{ __('receivable.outstanding_invoice_details') }}</h4>
                        <table>
                            <thead>
                            <tr>
                                <th>{{ __('txn.invoice') }}</th>
                                <th>{{ __('txn.date') }}</th>
                                <th>{{ __('txn.semester_period') }}</th>
                                <th>{{ __('txn.total') }}</th>
                                <th>{{ __('txn.paid') }}</th>
                                <th>{{ __('txn.balance') }}</th>
                                <th>{{ __('receivable.action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($outstandingInvoices as $invoice)
                                <tr>
                                    <td>
                                        <a href="{{ route('sales-invoices.show', $invoice) }}" target="_blank" rel="noopener noreferrer">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td>{{ $invoice->invoice_date?->format('d-m-Y') }}</td>
                                    <td>{{ $invoice->semester_period ?: '-' }}</td>
                                    <td>Rp {{ number_format((int) round($invoice->total), 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format((int) round($invoice->total_paid), 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format((int) round($invoice->balance), 0, ',', '.') }}</td>
                                    <td>
                                        @if(!($selectedCustomerSemesterClosed ?? false))
                                            <a
                                                class="btn secondary"
                                                href="{{ route('receivable-payments.create', ['customer_id' => $selectedCustomerId ?: $invoice->customer_id, 'amount' => (int) round((float) $invoice->balance), 'payment_date' => now()->format('Y-m-d'), 'preferred_invoice_id' => $invoice->id, 'return_to' => request()->getRequestUri()]) }}"
                                            >
                                                {{ __('receivable.pay') }}
                                            </a>
                                        @else
                                            <span class="badge danger">{{ __('receivable.customer_semester_closed') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="muted">{{ __('receivable.no_outstanding_invoices') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('receivable-filter-form');
            const searchInput = document.getElementById('receivable-search-input');
            const customerSelect = document.getElementById('receivable-customer-id');
            const semesterSelect = document.getElementById('receivable-semester');

            if (!form || !searchInput || !customerSelect || !semesterSelect) {
                return;
            }

            let debounceTimer = null;
            searchInput.addEventListener('input', () => {
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }
                debounceTimer = setTimeout(() => {
                    form.requestSubmit();
                }, 100);
            });

            customerSelect.addEventListener('change', () => {
                form.requestSubmit();
            });

            semesterSelect.addEventListener('change', () => {
                form.requestSubmit();
            });

            const customerAdjustmentForm = document.getElementById('customer-adjustment-form');
            if (customerAdjustmentForm) {
                const methodSelect = document.getElementById('customer-adjustment-method');
                const amountInput = document.getElementById('customer-adjustment-amount');
                const writeoffAction = customerAdjustmentForm.getAttribute('data-writeoff-action');
                const discountAction = customerAdjustmentForm.getAttribute('data-discount-action');

                const syncCustomerAction = () => {
                    if (!methodSelect) {
                        return;
                    }
                    if (methodSelect.value === 'discount' && discountAction) {
                        customerAdjustmentForm.setAttribute('action', discountAction);
                        return;
                    }
                    if (writeoffAction) {
                        customerAdjustmentForm.setAttribute('action', writeoffAction);
                    }
                };

                syncCustomerAction();
                methodSelect?.addEventListener('change', syncCustomerAction);

                customerAdjustmentForm.addEventListener('submit', (event) => {
                    if (!amountInput) {
                        return;
                    }
                    const actual = Math.round(Number(amountInput.value || 0));
                    const max = Math.round(Number(amountInput.max || 0));
                    if (actual < 1 || (max > 0 && actual > max)) {
                        event.preventDefault();
                        amountInput.setCustomValidity(@json(__('receivable.payment_exceeds_balance')));
                        amountInput.reportValidity();
                        return;
                    }
                    amountInput.setCustomValidity('');
                });
            }
        })();
    </script>
@endsection



