@php
    $sortUrl = function (string $field) use ($search, $selectedSemester, $selectedTransactionType, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('receivables.index', array_filter(['search' => $search, 'semester' => $selectedSemester, 'transaction_type' => $selectedTransactionType, 'sort' => $field, 'direction' => $nextDir], fn ($v) => $v !== null && $v !== ''));
    };
    $sortMark = function (string $field) use ($sort, $direction): string {
        if ($sort !== $field) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
@endphp

<div class="card">
        @if($selectedSemester)
            <div class="muted" style="margin-top: 10px;">
                {{ __('receivable.semester_filter_status_note') }}
                <strong style="margin-left: 6px;">{{ $selectedSemester }}</strong>
                <span style="margin-left: 6px;">|</span>
                <span style="margin-left: 6px;">
                    {{ __('receivable.customer_semester_status') }} global:
                    <strong>{{ $selectedSemesterGlobalClosed ? __('receivable.customer_semester_closed') : __('receivable.customer_semester_open') }}</strong>
                </span>
                @if($selectedSemesterActive)
                    <span style="margin-left: 6px;">|</span>
                    <span style="margin-left: 6px;" class="badge success">Aktif</span>
                @endif
            </div>
        @endif

        @if((auth()->user()?->canAccess('settings.admin') ?? false) && $selectedSemester && isset($semesterClosingState) && is_array($semesterClosingState))
            <div style="margin-top: 12px; padding: 10px 12px; border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                <div>
                    <strong>{{ __('receivable.semester_lock_readiness_title') }}</strong><br>
                    <span class="muted">
                        {{ __('receivable.semester_lock_readiness_summary', [
                            'semester' => $selectedSemester,
                            'paid' => (int) ($semesterClosingState['paid_customer_count'] ?? 0),
                            'total' => (int) ($semesterClosingState['customer_count'] ?? 0),
                            'open' => (int) ($semesterClosingState['open_customer_count'] ?? 0),
                        ]) }}
                    </span>
                    <div class="muted" style="margin-top: 4px;">
                        {{ __('receivable.semester_lock_readiness_total_outstanding') }}:
                        <strong>Rp {{ number_format((int) ($semesterClosingState['total_outstanding'] ?? 0), 0, ',', '.') }}</strong>
                    </div>
                </div>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    @if(($semesterClosingState['ready_to_close'] ?? false) === true)
                        <span class="badge success">{{ __('receivable.semester_lock_ready_badge') }}</span>
                        <form method="post" action="{{ route('settings.semester.close') }}">
                            @csrf
                            <input type="hidden" name="semester_period" value="{{ $selectedSemester }}">
                            <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                            <button type="submit" class="btn">{{ __('ui.semester_close_button') }}</button>
                        </form>
                    @elseif(($semesterClosingState['already_closed'] ?? false) === true)
                        <span class="badge danger">{{ __('receivable.semester_lock_already_closed_badge') }}</span>
                    @else
                        <span class="badge warning">{{ __('receivable.semester_lock_waiting_badge') }}</span>
                    @endif
                </div>
            </div>
        @endif

        @if($selectedCustomerId > 0)
            @php
                $selectedCustomerLabel = trim((string) ($selectedCustomerName ?? '')) !== ''
                    ? (string) $selectedCustomerName
                    : (($selectedCustomerOption['name'] ?? null) ?: __('receivable.customer_id').' '.$selectedCustomerId);
            @endphp
            <div style="margin-top: 12px; padding: 10px 12px; border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                <div>
                    <strong>{{ __('receivable.customer_bill_title') }}</strong><br>
                    <span class="muted">
                        {{ $selectedCustomerLabel }}
                        @if($selectedSemester)
                            | {{ $selectedSemester }}
                        @endif
                        @if(($selectedTransactionType ?? '') === 'product')
                            | {{ __('receivable.transaction_type_product') }}
                        @elseif(($selectedTransactionType ?? '') === 'printing')
                            | {{ __('receivable.transaction_type_printing') }}
                        @else
                            | {{ __('txn.all') }}
                        @endif
                    </span>
                </div>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <a
                        class="btn info-btn"
                        href="{{ route('receivables.print-customer-bill', ['customer' => $selectedCustomerId, 'semester' => $selectedSemester, 'transaction_type' => ($selectedTransactionType ?? '')]) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ __('receivable.print_customer_bill') }}
                    </a>
                    <select class="action-menu action-menu-md" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                        <option value="" selected disabled>Export</option>
                        <option value="{{ route('receivables.export-customer-bill-excel', ['customer' => $selectedCustomerId, 'semester' => $selectedSemester, 'transaction_type' => ($selectedTransactionType ?? '')]) }}">Export Excel</option>
                        <option value="{{ route('receivables.export-customer-bill-pdf', ['customer' => $selectedCustomerId, 'semester' => $selectedSemester, 'transaction_type' => ($selectedTransactionType ?? '')]) }}">Export PDF</option>
                    </select>
                </div>
            </div>
        @endif

        @if((auth()->user()?->canAccess('receivables.lock') ?? false) && $selectedCustomerId > 0 && $selectedSemester)
            <div style="margin-top: 12px; padding: 10px 12px; border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                <div>
                    <strong>{{ __('receivable.customer_semester_book_title') }}</strong><br>
                    <span class="muted">
                        {{ ($selectedCustomerName ?? null) ?: __('receivable.customer_id').' '.$selectedCustomerId }}
                        | {{ $selectedSemester }}
                        |
                        @if($selectedCustomerSemesterClosed)
                            @if(($customerSemesterManualClosedMap[$selectedCustomerId] ?? false) === true)
                                {{ __('receivable.customer_semester_locked_manual') }}
                            @else
                                {{ __('receivable.customer_semester_closed') }}
                            @endif
                        @else
                            {{ __('receivable.customer_semester_unlocked') }}
                        @endif
                    </span>
                    <div class="muted" style="margin-top: 4px;">
                        {{ __('receivable.customer_semester_lock_help') }}
                    </div>
                </div>
                <div>
                    @if($selectedCustomerSemesterClosed)
                        <form method="post" action="{{ route('receivables.customer-semester.open', ['customer' => $selectedCustomerId]) }}">
                            @csrf
                            <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                            <input type="hidden" name="search" value="{{ $search }}">
                            <input type="hidden" name="customer_id" value="{{ $selectedCustomerId }}">
                            <button type="submit" class="btn warning-btn">{{ __('receivable.customer_semester_open_button') }}</button>
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
</div>

    <div class="row receivable-main-grid">
        <div class="col-4 receivable-col-customer">
            <div class="card">
                <h3>{{ __('receivable.customer') }}</h3>
                <div class="receivable-scroll-wrap customer">
                <table class="receivable-customer-table mobile-stack-table">
                    <colgroup>
                        <col style="width: 25%;">
                        <col style="width: 15%;">
                        <col style="width: 25%;">
                        <col style="width: 30%;">
                    </colgroup>
                    <thead>
                    <tr>
                        <th class="customer-col"><a class="sort-link" href="{{ $sortUrl('name') }}">{{ __('ui.customer_name') }} <span class="sort-mark">{{ $sortMark('name') }}</span></a></th>
                        <th class="city-col"><a class="sort-link" href="{{ $sortUrl('city') }}">{{ __('receivable.city') }} <span class="sort-mark">{{ $sortMark('city') }}</span></a></th>
                        <th class="num outstanding-col">{{ __('receivable.outstanding') }}</th>
                        <th class="action-cell">{{ __('receivable.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($customers as $customer)
                        @php
                            $rowCustomerSemesterClosed = $selectedSemester ? ((bool) ($customerSemesterClosedMap[$customer->id] ?? false)) : false;
                            $rowCustomerSemesterManualClosed = $selectedSemester ? ((bool) ($customerSemesterManualClosedMap[$customer->id] ?? false)) : false;
                        @endphp
                        <tr>
                            <td data-label="{{ __('ui.customer_name') }}" class="customer-col">
                                <span class="receivable-customer-name">{{ $customer->name }}</span>
                                @if($selectedSemester)
                                    <span class="badge receivable-customer-lock {{ $rowCustomerSemesterClosed ? 'danger' : 'success' }}">
                                        @if($rowCustomerSemesterClosed)
                                            @if($rowCustomerSemesterManualClosed)
                                                {{ __('receivable.customer_semester_locked_manual') }}
                                            @else
                                                {{ __('receivable.customer_semester_closed') }}
                                            @endif
                                        @else
                                            {{ __('receivable.customer_semester_unlocked') }}
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td data-label="{{ __('receivable.city') }}" class="city-col">{{ $customer->city ?: '-' }}</td>
                            <td data-label="{{ __('receivable.outstanding') }}" class="num outstanding-col">Rp {{ number_format((int) round($customer->outstanding_receivable), 0, ',', '.') }}</td>
                            <td data-label="{{ __('receivable.action') }}" class="action-cell">
                                @php
                                    $ledgerUrl = route('receivables.index', [
                                        'customer_id' => $customer->id,
                                        'search' => $search,
                                        'semester' => $selectedSemester,
                                        'transaction_type' => ($selectedTransactionType ?? ''),
                                    ]);
                                    $paymentUrl = route('receivable-payments.create', [
                                        'customer_id' => $customer->id,
                                        'amount' => (int) round((float) $customer->outstanding_receivable),
                                        'payment_date' => now()->format('Y-m-d'),
                                        'return_to' => request()->getRequestUri(),
                                    ]);
                                @endphp
                                <div class="receivable-customer-actions">
                                    <a class="btn process-btn" href="{{ $ledgerUrl }}">{{ __('receivable.ledger') }}</a>
                                    @if((float) $customer->outstanding_receivable > 0 && ! $rowCustomerSemesterClosed)
                                        <a class="btn payment-btn" href="{{ $paymentUrl }}">{{ __('receivable.create_payment') }}</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ __('receivable.no_customers') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                </div>
                <div style="margin-top: 12px;">
                    {{ $customers->links() }}
                </div>
            </div>
        </div>
        <div class="col-8 receivable-col-ledger">
            <div class="card">
                <h3>
                    {{ __('receivable.ledger_entries') }}
                    @if($selectedCustomerId > 0)
                        @php
                            $selectedCustomerLedgerLabel = trim((string) ($selectedCustomerName ?? '')) !== ''
                                ? (string) $selectedCustomerName
                                : (($selectedCustomerOption['name'] ?? null) ?: __('receivable.customer_id').' '.$selectedCustomerId);
                        @endphp
                        ({{ $selectedCustomerLedgerLabel }})
                    @endif
                </h3>
                <div class="receivable-subcard">
                
                @if($selectedCustomerId > 0 && $ledgerRows->isNotEmpty())
                    <div class="receivable-summary-slider">
                        <div class="receivable-summary-item" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);">
                            <div style="font-size: 10px; opacity: 0.9; margin-bottom: 6px;">{{ __('receivable.total_debit') }}</div>
                            <div style="font-size: 16px; font-weight: 700;">Rp {{ number_format($ledgerRows->sum('debit'), 0, ',', '.') }}</div>
                        </div>
                        <div class="receivable-summary-item" style="background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);">
                            <div style="font-size: 10px; opacity: 0.9; margin-bottom: 6px;">{{ __('receivable.total_credit') }}</div>
                            <div style="font-size: 16px; font-weight: 700;">Rp {{ number_format($ledgerRows->sum('credit'), 0, ',', '.') }}</div>
                        </div>
                        <div class="receivable-summary-item" style="background: linear-gradient(135deg, #4c6ef5 0%, #5577ff 100%);">
                            <div style="font-size: 10px; opacity: 0.9; margin-bottom: 6px;">{{ __('receivable.outstanding') }}</div>
                            <div style="font-size: 16px; font-weight: 700;">Rp {{ number_format($ledgerOutstandingTotal ?? 0, 0, ',', '.') }}</div>
                        </div>
                    </div>
                @endif
                <div class="receivable-scroll-wrap ledger">
                    <table class="receivable-ledger-table">
                        <colgroup>
                            <col style="width: 11%;">
                            <col style="width: 24%;">
                            <col style="width: 22%;">
                            <col style="width: 12%;">
                            <col style="width: 11%;">
                            <col style="width: 11%;">
                            <col style="width: 9%;">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>{{ __('receivable.date') }}</th>
                            <th>{{ __('receivable.description') }}</th>
                            <th>{{ __('receivable.transaction_type') }}</th>
                            <th class="num">{{ __('receivable.debit') }}</th>
                            <th class="num">{{ __('receivable.credit') }}</th>
                            <th class="num">{{ __('receivable.balance') }}</th>
                            <th class="action">{{ __('receivable.action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if($ledgerRows->isEmpty())
                            <tr><td colspan="7" class="muted">{{ __('receivable.select_customer') }}</td></tr>
                        @else
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
                                        && $row->sales_invoice_id === null
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
                                            $returnNumber = '';
                                            if (preg_match('/\bRTR-\d{8}-\d{4}\b/i', $descriptionText, $returnMatch) === 1) {
                                                $returnNumber = strtoupper((string) $returnMatch[0]);
                                            }
                                            $returnId = ($returnNumber !== '' && isset($salesReturnLinkMap[$returnNumber]))
                                                ? (int) $salesReturnLinkMap[$returnNumber]
                                                : null;
                                            $descriptionWithoutReturn = $returnNumber !== ''
                                                ? trim(str_ireplace($returnNumber, '', $descriptionText))
                                                : $descriptionText;
                                        @endphp

                                        @if($row->invoice)
                                            @if($descriptionWithoutInvoice !== '')
                                                {{ $descriptionWithoutInvoice }}
                                            @endif
                                            <a href="{{ route('sales-invoices.show', $row->invoice) }}" target="_blank" rel="noopener noreferrer">
                                                {{ $invoiceNumber }}
                                            </a>
                                        @elseif($returnId !== null)
                                            @if($descriptionWithoutReturn !== '')
                                                {{ $descriptionWithoutReturn }}
                                            @endif
                                            <a href="{{ route('sales-returns.show', $returnId) }}" target="_blank" rel="noopener noreferrer">
                                                {{ $returnNumber }}
                                            </a>
                                        @else
                                            {{ $descriptionText !== '' ? $descriptionText : '-' }}
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $rowTransactionType = trim((string) ($row->transaction_type ?? '')) !== '' ? (string) $row->transaction_type : (string) ($row->invoice?->transaction_type ?? '');
                                            $rowPrintingSubtype = trim((string) ($row->printing_subtype_name ?? '')) !== '' ? trim((string) $row->printing_subtype_name) : trim((string) ($row->invoice?->printing_subtype_name ?? ''));
                                            $descriptionLower = mb_strtolower(trim((string) ($row->description ?? '')), 'UTF-8');
                                            $isReturnEntry = str_contains($descriptionLower, 'retur') || str_contains($descriptionLower, 'return');
                                            $isCreditEntry = (float) $row->credit > 0;
                                            if ($isReturnEntry) {
                                                $rowTransactionTypeLabel = __('receivable.transaction_type_return');
                                                $rowTransactionSubtypeLabel = __('receivable.printing_subtype_none');
                                            } elseif ($isCreditEntry) {
                                                $rowTransactionTypeLabel = __('receivable.transaction_type_payment');
                                                $rowTransactionSubtypeLabel = __('receivable.printing_subtype_none');
                                            } else {
                                                $rowTransactionTypeLabel = __('receivable.transaction_type_sale');
                                                $rowTransactionSubtypeLabel = $rowTransactionType === 'printing'
                                                    ? ($rowPrintingSubtype !== '' ? __('receivable.transaction_subtype_printing_named', ['name' => $rowPrintingSubtype]) : __('receivable.transaction_type_printing'))
                                                    : __('receivable.transaction_subtype_product');
                                            }
                                        @endphp
                                        {{ $rowTransactionTypeLabel }}
                                        @if($rowTransactionSubtypeLabel !== __('receivable.printing_subtype_none'))
                                            <span class="muted" style="display:block; font-size:11px;">{{ $rowTransactionSubtypeLabel }}</span>
                                        @endif
                                    </td>
                                    <td class="num">
                                        @if($row->debit > 0)
                                            Rp {{ number_format((int) round($row->debit), 0, ',', '.') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="num">
                                        @if($row->credit > 0)
                                            Rp {{ number_format((int) round($row->credit), 0, ',', '.') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="num">
                                        Rp {{ number_format((int) round($row->balance_after), 0, ',', '.') }}
                                    </td>
                                    <td class="action">
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

                                        @if($isAdminCancelInvoice)
                                            <span style="display:inline-block; background:#ffcdd2; color:#c62828; padding:2px 6px; border-radius:3px; font-size:10px; font-weight:500;">{{ __('txn.admin_badge_cancel') }}</span>
                                        @elseif($isAdminEditIncrease)
                                            <span style="display:inline-block; background:#e3f2fd; color:#0d47a1; padding:2px 6px; border-radius:3px; font-size:10px; font-weight:500;">{{ __('txn.admin_badge_edit_plus') }}</span>
                                        @elseif($isAdminEditDecrease)
                                            <span style="display:inline-block; background:#fff3e0; color:#e65100; padding:2px 6px; border-radius:3px; font-size:10px; font-weight:500;">{{ __('txn.admin_badge_edit_minus') }}</span>
                                        @elseif($canPay)
                                            <a
                                                class="btn payment-btn receivable-pay-btn"
                                                href="{{ route('receivable-payments.create', ['customer_id' => $selectedCustomerId ?: $row->invoice->customer_id, 'amount' => (int) round((float) $row->invoice->balance), 'payment_date' => now()->format('Y-m-d'), 'preferred_invoice_id' => $row->invoice->id, 'return_to' => request()->getRequestUri()]) }}"
                                            >
                                                {{ __('receivable.pay') }}
                                            </a>
                                        @elseif(($selectedCustomerSemesterClosed ?? false) === true)
                                            <span style="display:inline-block; background:#ffcdd2; color:#c62828; padding:2px 6px; border-radius:3px; font-size:10px; font-weight:500;">{{ __('receivable.customer_semester_closed') }}</span>
                                        @else
                                            <span class="muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>
                </div>
                @if($selectedCustomerId > 0)
                    <div class="receivable-subcard" style="margin-top: 8px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom: 10px;">
                        <h4 style="margin: 0;">{{ __('receivable.customer_bill_title') }}</h4>
                        <div style="text-align: right;">
                        <select class="action-menu action-menu-lg" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                            <option value="" selected disabled>Export</option>
                            <option value="{{ route('receivables.export-customer-bill-excel', ['customer' => $selectedCustomerId, 'semester' => $selectedSemester, 'transaction_type' => ($selectedTransactionType ?? '')]) }}">Export Excel</option>
                            <option value="{{ route('receivables.export-customer-bill-pdf', ['customer' => $selectedCustomerId, 'semester' => $selectedSemester, 'transaction_type' => ($selectedTransactionType ?? '')]) }}">Export PDF</option>
                        </select>
                    </div>
                    </div>
                    @if(($billStatementRows ?? collect())->isNotEmpty())
                        <div style="margin-top: 8px;">
                            <div class="receivable-scroll-wrap bill">
                            <table class="receivable-bill-table">
                                <colgroup>
                                    <col style="width: 10%;">
                                    <col style="width: 20%;">
                                    <col style="width: 20%;">
                                    <col style="width: 10%;">
                                    <col style="width: 14%;">
                                    <col style="width: 8%;">
                                    <col style="width: 10%;">
                                </colgroup>
                                <thead>
                                <tr>
                                    <th>{{ __('receivable.bill_date') }}</th>
                                    <th>{{ __('receivable.bill_proof_number') }}</th>
                                    <th>{{ __('receivable.bill_transaction_note') }}</th>
                                    <th class="num">{{ __('receivable.bill_credit_sales') }}</th>
                                    <th class="num">{{ __('receivable.bill_payment_or_deduction') }}</th>
                                    <th class="num">{{ __('receivable.bill_sales_return') }}</th>
                                    <th class="num">{{ __('receivable.bill_running_balance') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($billStatementRows as $billRow)
                                    @php $isOpening = ($billRow['date_label'] ?? '') === __('receivable.bill_opening_balance'); @endphp
                                    @if($isOpening)
                                        <tr>
                                            <td>{{ $billRow['date_label'] ?? '' }}</td>
                                            <td colspan="5"></td>
                                            <td class="num">Rp {{ number_format((int) round((float) ($billRow['running_balance'] ?? 0)), 0, ',', '.') }}</td>
                                        </tr>
                                    @else
                                        @php
                                            $entryType = (string) ($billRow['entry_type'] ?? '');
                                            $isAdjustment = $entryType === 'adjustment';
                                            $proofBadge = !empty($billRow['receivable_payment_id'])
                                                ? 'KWT'
                                                : (!empty($billRow['sales_return_id']) ? 'RTR' : (!empty($billRow['invoice_id']) ? 'INV' : 'DOC'));
                                            $proofBadgeStyle = !empty($billRow['receivable_payment_id'])
                                                ? 'display:inline-block; margin-right:6px; padding:2px 6px; border-radius:999px; background:#e8f1ff; color:#0d47a1; font-size:10px; font-weight:700;'
                                                : (!empty($billRow['sales_return_id'])
                                                    ? 'display:inline-block; margin-right:6px; padding:2px 6px; border-radius:999px; background:#fff0e0; color:#b45309; font-size:10px; font-weight:700;'
                                                    : 'display:inline-block; margin-right:6px; padding:2px 6px; border-radius:999px; background:#e8f7ed; color:#166534; font-size:10px; font-weight:700;');
                                        @endphp
                                        <tr>
                                            <td>{{ $billRow['date_label'] ?? '' }}</td>
                                            <td>
                                                @unless($isAdjustment)
                                                    <span style="{{ $proofBadgeStyle }}">{{ $proofBadge }}</span>
                                                @endunless
                                                @if(!$isAdjustment && !empty($billRow['receivable_payment_id']))
                                                    <a href="{{ route('receivable-payments.show', (int) $billRow['receivable_payment_id']) }}" target="_blank" rel="noopener noreferrer">
                                                        {{ $billRow['proof_number'] ?? '' }}
                                                    </a>
                                                @elseif(!$isAdjustment && !empty($billRow['sales_return_id']))
                                                    <a href="{{ route('sales-returns.show', (int) $billRow['sales_return_id']) }}" target="_blank" rel="noopener noreferrer">
                                                        {{ $billRow['proof_number'] ?? '' }}
                                                    </a>
                                                @elseif(!$isAdjustment && !empty($billRow['invoice_id']))
                                                    <a href="{{ route('sales-invoices.show', (int) $billRow['invoice_id']) }}" target="_blank" rel="noopener noreferrer">
                                                        {{ $billRow['proof_number'] ?? '' }}
                                                    </a>
                                                @else
                                                    {{ $billRow['proof_number'] ?? '' }}
                                                @endif
                                                @if(!$isAdjustment && (int) ($billRow['adjustment_amount'] ?? 0) !== 0)
                                                    <span class="muted" style="display:block; font-size:11px;">
                                                        ({{ (int) ($billRow['adjustment_amount'] ?? 0) > 0 ? '+' : '-' }}Rp {{ number_format(abs((int) ($billRow['adjustment_amount'] ?? 0)), 0, ',', '.') }})
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $billRow['transaction_type_label'] ?? __('receivable.transaction_type_none') }}
                                                @if(($billRow['transaction_subtype_label'] ?? __('receivable.printing_subtype_none')) !== __('receivable.printing_subtype_none'))
                                                    <span class="muted" style="display:block; font-size:11px;">{{ $billRow['transaction_subtype_label'] }}</span>
                                                @endif
                                            </td>
                                            <td class="num">Rp {{ number_format((int) round((float) ($billRow['credit_sales'] ?? 0)), 0, ',', '.') }}</td>
                                            <td class="num">Rp {{ number_format((int) round((float) ($billRow['installment_payment'] ?? 0) + (float) ($billRow['deduction_discount'] ?? 0)), 0, ',', '.') }}</td>
                                            <td class="num">Rp {{ number_format((int) round((float) ($billRow['sales_return'] ?? 0)), 0, ',', '.') }}</td>
                                            <td class="num">Rp {{ number_format((int) round((float) ($billRow['running_balance'] ?? 0)), 0, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                <tr style="font-weight:700;">
                                    <td colspan="3" style="text-align:center;">{{ __('receivable.bill_total') }}</td>
                                    <td class="num">Rp {{ number_format((int) round((float) (($billStatementTotals['credit_sales'] ?? 0))), 0, ',', '.') }}</td>
                                    <td class="num">Rp {{ number_format((int) round((float) (($billStatementTotals['installment_payment'] ?? 0)) + (float) (($billStatementTotals['deduction_discount'] ?? 0))), 0, ',', '.') }}</td>
                                    <td class="num">Rp {{ number_format((int) round((float) (($billStatementTotals['sales_return'] ?? 0))), 0, ',', '.') }}</td>
                                    <td class="num">Rp {{ number_format((int) round((float) (($billStatementTotals['running_balance'] ?? 0))), 0, ',', '.') }}</td>
                                </tr>
                                @php
                                    $billRunningBalanceTotal = (int) round((float) (($billStatementTotals['running_balance'] ?? 0)));
                                    $billHasCreditBalance = $billRunningBalanceTotal < 0;
                                @endphp
                                <tr style="font-weight:700;">
                                    <td colspan="4"></td>
                                    <td colspan="2" style="text-align:right;">{{ $billHasCreditBalance ? __('receivable.bill_total_credit_balance') : __('receivable.bill_total_receivable') }}</td>
                                    <td class="num">Rp {{ number_format(abs($billRunningBalanceTotal), 0, ',', '.') }}</td>
                                </tr>
                                </tbody>
                            </table>
                            </div>
                            @php
                                $billTotalPurchase = (int) round((float) (($billStatementTotals['credit_sales'] ?? 0)));
                                $billTotalAccountPayment = (int) round((float) (($billStatementTotals['installment_payment'] ?? 0)));
                                $billTotalDeductionAndReturn = (int) round((float) (($billStatementTotals['deduction_discount'] ?? 0))) + (int) round((float) (($billStatementTotals['sales_return'] ?? 0)));
                                $billRemainingReceivable = max(0, $billRunningBalanceTotal);
                            @endphp
                            <div class="receivable-subcard" style="margin-top: 10px;">
                                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                    <strong>{{ __('receivable.bill_final_summary_title') }}</strong>
                                    @if($selectedSemester !== null && $billRunningBalanceTotal <= 0)
                                        <span style="display:inline-block; padding:2px 12px; border:2px solid #c2185b; color:#c2185b; border-radius:4px; font-size:13px; font-weight:800; letter-spacing:1px;">LUNAS</span>
                                        @if((auth()->user()?->canAccess('receivables.lock') ?? false) && !($selectedCustomerSemesterClosed ?? false))
                                            <form method="post" action="{{ route('receivables.customer-semester.close', ['customer' => $selectedCustomerId]) }}" style="display:inline;">
                                                @csrf
                                                <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                                                <input type="hidden" name="search" value="{{ $search }}">
                                                <input type="hidden" name="customer_id" value="{{ $selectedCustomerId }}">
                                                <button type="submit" class="btn" style="font-size:12px; padding:2px 10px; border-color:#c2185b; color:#c2185b;" title="Semester lunas — klik untuk menutup semester ini">
                                                    Tutup Semester
                                                </button>
                                            </form>
                                        @elseif($selectedCustomerSemesterClosed ?? false)
                                            <span style="font-size:11px; color:#6b7280; font-style:italic;">Semester telah ditutup</span>
                                        @endif
                                    @endif
                                </div>
                                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 8px 14px; margin-top: 8px;">
                                    <div>{{ __('receivable.bill_total_purchase') }}: <strong>Rp {{ number_format($billTotalPurchase, 0, ',', '.') }}</strong></div>
                                    <div>{{ __('receivable.bill_total_account_payment') }}: <strong>Rp {{ number_format($billTotalAccountPayment, 0, ',', '.') }}</strong></div>
                                    <div>{{ __('receivable.bill_total_deduction_and_return') }}: <strong>Rp {{ number_format($billTotalDeductionAndReturn, 0, ',', '.') }}</strong></div>
                                    <div>{{ $billHasCreditBalance ? __('receivable.bill_total_credit_balance') : __('receivable.bill_remaining_receivable') }}: <strong>Rp {{ number_format($billHasCreditBalance ? abs($billRunningBalanceTotal) : $billRemainingReceivable, 0, ',', '.') }}</strong></div>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div style="margin-top: 10px; text-align: right; font-weight: 700;">
                        {{ __('receivable.final_uncollected_balance') }}:
                        Rp {{ number_format((int) round(max(0, (float) ($customerOutstandingTotal ?? 0))), 0, ',', '.') }}
                    </div>
                    @if((float) ($customerOutstandingTotal ?? 0) > 0 && !($selectedCustomerSemesterClosed ?? false))
                        <div style="margin-top: 8px; text-align: right;">
                            <a
                                class="btn payment-btn"
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
                    </div>
                    <div class="receivable-subcard" style="margin-top: 10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                        <h4 style="margin: 0;">{{ __('receivable.outstanding_invoice_details') }}</h4>
                        @if((auth()->user()?->canAccess('receivables.adjust') ?? false) && (float) ($customerOutstandingTotal ?? 0) > 0)
                            <div class="flex" style="gap:8px;">
                                <button
                                    type="button"
                                    class="btn warning-btn"
                                    id="open-receivable-writeoff-modal"
                                >
                                    {{ __('receivable.method_writeoff') }}
                                </button>
                                <button
                                    type="button"
                                    class="btn warning-btn"
                                    id="open-receivable-discount-modal"
                                >
                                    {{ __('receivable.method_discount') }}
                                </button>
                            </div>
                        @endif
                    </div>
                    <div style="margin-top: 12px;">
                        <div class="receivable-scroll-wrap outstanding">
                        <table class="receivable-outstanding-table">
                            <colgroup>
                                <col style="width: 19%;">
                                <col style="width: 10%;">
                                <col style="width: 11%;">
                                <col style="width: 18%;">
                                <col style="width: 18%;">
                                <col style="width: 16%;">
                                <col style="width: 8%;">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>{{ __('txn.invoice') }}</th>
                                <th>{{ __('txn.date') }}</th>
                                <th>{{ __('txn.semester_period') }}</th>
                                <th class="num">{{ __('txn.total') }}</th>
                                <th class="num">{{ __('txn.paid') }}</th>
                                <th class="num">{{ __('txn.balance') }}</th>
                                <th class="action">{{ __('receivable.action') }}</th>
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
                                    <td class="num">Rp {{ number_format((int) round($invoice->total), 0, ',', '.') }}</td>
                                    <td class="num">Rp {{ number_format((int) round($invoice->total_paid), 0, ',', '.') }}</td>
                                    <td class="num">Rp {{ number_format((int) round($invoice->balance), 0, ',', '.') }}</td>
                                    <td class="action">
                                        @if(!($selectedCustomerSemesterClosed ?? false))
                                            <a
                                                class="btn payment-btn receivable-pay-btn"
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
                    </div>
                    </div>
                    @if(auth()->user()?->canAccess('receivables.adjust') && (float) ($customerOutstandingTotal ?? 0) > 0)
                        <div id="receivable-adjustment-modal-overlay" class="receivable-adjustment-modal-overlay"></div>
                        <div
                            id="receivable-adjustment-modal"
                            class="receivable-adjustment-modal"
                            data-customer-name="{{ (string) ($selectedCustomerName ?? '-') }}"
                            data-total-outstanding="{{ (int) round((float) ($customerOutstandingTotal ?? 0)) }}"
                            data-writeoff-action="{{ route('receivables.customer-writeoff', $selectedCustomerId) }}"
                            data-discount-action="{{ route('receivables.customer-discount', $selectedCustomerId) }}"
                            data-search="{{ $search }}"
                            data-semester="{{ (string) ($selectedSemester ?? '') }}"
                            data-customer-id="{{ (int) $selectedCustomerId }}"
                            data-payment-date="{{ now()->format('Y-m-d') }}"
                            data-csrf="{{ csrf_token() }}"
                        >
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                                <h4 id="receivable-adjustment-title" style="margin:0;">{{ __('receivable.method_writeoff') }}</h4>
                                <button type="button" id="receivable-adjustment-close" class="btn info-btn" style="min-height:30px; padding:4px 10px;">&times;</button>
                            </div>

                            <div class="row">
                                <div>
                                    <label>{{ __('receivable.customer') }}</label>
                                    <input id="receivable-adjustment-customer" type="text" value="{{ (string) ($selectedCustomerName ?? '-') }}" disabled>
                                </div>
                                <div>
                                    <label>{{ __('receivable.payment_date') }}</label>
                                    <input id="receivable-adjustment-date" type="date" value="{{ now()->format('Y-m-d') }}" disabled>
                                </div>
                            </div>

                            <div class="row">
                                <div>
                                    <label>{{ __('receivable.outstanding') }}</label>
                                    <input id="receivable-adjustment-total" type="text" disabled>
                                </div>
                                <div>
                                    <label id="receivable-adjustment-amount-label">{{ __('receivable.method_writeoff') }}</label>
                                    <input id="receivable-adjustment-amount" type="text" inputmode="numeric" class="js-thousand-input">
                                </div>
                            </div>

                            <div class="row" id="receivable-adjustment-discount-extra" style="display:none;">
                                <div>
                                    <label>{{ __('receivable.discount_percent') }}</label>
                                    <input id="receivable-adjustment-discount-percent" type="number" min="0" max="100" step="0.01" value="0">
                                </div>
                                <div>
                                    <label>{{ __('receivable.discount_pay_amount') }}</label>
                                    <input id="receivable-adjustment-pay-amount" type="text" inputmode="numeric" class="js-thousand-input">
                                </div>
                            </div>

                            <div class="footer">
                                <small id="receivable-adjustment-status" class="muted">{{ __('receivable.adjustment_auto_save_hint') }}</small>
                                <button type="button" id="receivable-adjustment-save-now" class="btn payment-btn">{{ __('receivable.save_payment') }}</button>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
