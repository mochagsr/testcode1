@extends('layouts.app')

@section('title', __('receivable.title').' - PgPOS ERP')

@section('content')
    <style>
        .receivable-ledger-table,
        .receivable-bill-table,
        .receivable-outstanding-table {
            width: 100%;
            border-collapse: collapse;
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
            border-bottom: 1px solid var(--border);
            border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            vertical-align: middle;
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
        }
        .receivable-ledger-table td.action,
        .receivable-ledger-table th.action,
        .receivable-outstanding-table td.action,
        .receivable-outstanding-table th.action {
            text-align: center;
            white-space: nowrap;
        }
        .receivable-ledger-table td,
        .receivable-bill-table td,
        .receivable-outstanding-table td {
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .receivable-bill-table,
        .receivable-outstanding-table {
            table-layout: auto;
        }
        .receivable-bill-table td.num,
        .receivable-outstanding-table td.num {
            overflow: visible;
            text-overflow: clip;
        }
        .receivable-customer-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }
        .receivable-customer-table th,
        .receivable-customer-table td {
            border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            vertical-align: middle;
            padding: 10px 8px;
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
        .receivable-customer-table td.city-col,
        .receivable-customer-table th.city-col {
            white-space: nowrap;
        }
        .receivable-customer-table td.outstanding-col,
        .receivable-customer-table th.outstanding-col {
            white-space: nowrap;
        }
        .receivable-customer-table td.action-cell,
        .receivable-customer-table th.action-cell {
            white-space: nowrap;
            width: 1%;
            min-width: 168px;
        }
        .receivable-customer-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 6px;
            flex-wrap: wrap;
        }
        .receivable-customer-actions .btn {
            min-height: 30px;
            padding: 5px 9px;
            font-size: 11px;
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
            scrollbar-gutter: stable both-edges;
            border: 1px solid color-mix(in srgb, var(--border) 75%, var(--text) 25%);
            border-radius: 8px;
        }
        .receivable-scroll-wrap.ledger table {
            min-width: 920px;
        }
        .receivable-scroll-wrap.customer {
            max-height: 360px;
        }
        .receivable-scroll-wrap.customer table {
            min-width: 760px;
        }
        .receivable-scroll-wrap.bill table {
            min-width: 920px;
        }
        .receivable-scroll-wrap.outstanding table {
            min-width: 980px;
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
        .receivable-scroll-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--card);
        }
        .receivable-scroll-wrap tbody tr:hover td {
            background: color-mix(in srgb, var(--card) 92%, var(--text) 8%);
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
        }
        @media (max-width: 1600px) {
            .receivable-customer-actions {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
            }
            .receivable-customer-actions .btn,
            .receivable-customer-actions .badge {
                width: 100%;
                justify-content: center;
                text-align: center;
            }
        }
    </style>
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
                            <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                                <option value="{{ route('reports.print', ['dataset' => 'receivables']) }}">{{ __('txn.print') }}</option>
                                <option value="{{ route('reports.export.pdf', ['dataset' => 'receivables']) }}">{{ __('txn.pdf') }}</option>
                                <option value="{{ route('reports.export.csv', ['dataset' => 'receivables']) }}">{{ __('txn.excel') }}</option>
                            </select>
                        </span>
                    </div>
                    <div class="flex" style="justify-content:space-between; align-items:center; gap:8px;">
                        <span>{{ __('receivable.report_by_semester') }} ({{ $activeSemesterForReport }})</span>
                        <span class="flex" style="gap:6px;">
                            <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
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
                                <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                                    <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
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

    <div class="row receivable-main-grid">
        <div class="col-4">
            <div class="card">
                <h3>{{ __('receivable.customer') }}</h3>
                <div class="receivable-scroll-wrap customer">
                <table class="receivable-customer-table">
                    <colgroup>
                        <col style="width: 28%;">
                        <col style="width: 12%;">
                        <col style="width: 22%;">
                        <col style="width: 38%;">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>{{ __('receivable.customer') }}</th>
                        <th class="city-col">{{ __('receivable.city') }}</th>
                        <th class="num outstanding-col">{{ __('receivable.outstanding') }}</th>
                        <th class="action-cell">{{ __('receivable.action') }}</th>
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
                            <td class="city-col">{{ $customer->city ?: '-' }}</td>
                            <td class="num outstanding-col">Rp {{ number_format((int) round($customer->outstanding_receivable), 0, ',', '.') }}</td>
                            <td class="action-cell">
                                <div class="receivable-customer-actions">
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
        <div class="col-8">
            <div class="card">
                <h3>{{ __('receivable.ledger_entries') }} @if($selectedCustomerId > 0) ({{ __('receivable.customer_id') }}: {{ $selectedCustomerId }}) @endif</h3>
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
                        <col style="width: 37%;">
                        <col style="width: 14%;">
                        <col style="width: 14%;">
                        <col style="width: 14%;">
                        <col style="width: 10%;">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>{{ __('receivable.date') }}</th>
                        <th>{{ __('receivable.description') }}</th>
                        <th class="num">{{ __('receivable.debit') }}</th>
                        <th class="num">{{ __('receivable.credit') }}</th>
                        <th class="num">{{ __('receivable.balance') }}</th>
                        <th class="action">{{ __('receivable.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @if($ledgerRows->isEmpty())
                        <tr><td colspan="6" class="muted">{{ __('receivable.select_customer') }}</td></tr>
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
                                @if($isAdminCancelInvoice)
                                    <span style="display: inline-block; background: #ffcdd2; color: #c62828; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500;">{{ __('txn.admin_badge_cancel') }}</span>
                                @elseif($canPay)
                                    <a
                                        class="btn secondary"
                                        href="{{ route('receivable-payments.create', ['customer_id' => $selectedCustomerId ?: $row->invoice->customer_id, 'amount' => (int) round((float) $row->invoice->balance), 'payment_date' => now()->format('Y-m-d'), 'preferred_invoice_id' => $row->invoice->id, 'return_to' => request()->getRequestUri()]) }}"
                                        style=""
                                    >
                                        {{ __('receivable.pay') }}
                                    </a>
                                @elseif(($selectedCustomerSemesterClosed ?? false) === true)
                                    <span style="display: inline-block; background: #ffcdd2; color: #c62828; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500;">{{ __('receivable.customer_semester_closed') }}</span>
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
                    <div class="receivable-subcard" style="margin-top: 12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom: 10px;">
                        <h4 style="margin: 0;">{{ __('receivable.customer_bill_title') }}</h4>
                        <div style="text-align: right;">
                        <select class="action-menu action-menu-lg" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                            <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                            <option value="{{ route('receivables.print-customer-bill', ['customer' => $selectedCustomerId, 'semester' => $selectedSemester]) }}">{{ __('receivable.print_customer_bill') }}</option>
                            <option value="{{ route('receivables.export-customer-bill-pdf', ['customer' => $selectedCustomerId, 'semester' => $selectedSemester]) }}">{{ __('txn.pdf') }}</option>
                            <option value="{{ route('receivables.export-customer-bill-excel', ['customer' => $selectedCustomerId, 'semester' => $selectedSemester]) }}">{{ __('txn.excel') }}</option>
                        </select>
                    </div>
                    </div>
                    @if(($billStatementRows ?? collect())->isNotEmpty())
                        <div style="margin-top: 8px;">
                            <div class="receivable-scroll-wrap bill">
                            <table class="receivable-bill-table">
                                <colgroup>
                                    <col style="width: 14%;">
                                    <col style="width: 24%;">
                                    <col style="width: 15%;">
                                    <col style="width: 15%;">
                                    <col style="width: 14%;">
                                    <col style="width: 18%;">
                                </colgroup>
                                <thead>
                                <tr>
                                    <th>{{ __('receivable.bill_date') }}</th>
                                    <th>{{ __('receivable.bill_proof_number') }}</th>
                                    <th class="num">{{ __('receivable.bill_credit_sales') }}</th>
                                    <th class="num">{{ __('receivable.bill_installment_payment') }}</th>
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
                                            <td colspan="4"></td>
                                            <td class="num">{{ number_format((int) round((float) ($billRow['running_balance'] ?? 0)), 0, ',', '.') }}</td>
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
                                            <td class="num">{{ number_format((int) round((float) ($billRow['credit_sales'] ?? 0)), 0, ',', '.') }}</td>
                                            <td class="num">{{ number_format((int) round((float) ($billRow['installment_payment'] ?? 0)), 0, ',', '.') }}</td>
                                            <td class="num">{{ number_format((int) round((float) ($billRow['sales_return'] ?? 0)), 0, ',', '.') }}</td>
                                            <td class="num">{{ number_format((int) round((float) ($billRow['running_balance'] ?? 0)), 0, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                <tr style="font-weight:700;">
                                    <td colspan="2" style="text-align:center;">{{ __('receivable.bill_total') }}</td>
                                    <td class="num">{{ number_format((int) round((float) (($billStatementTotals['credit_sales'] ?? 0))), 0, ',', '.') }}</td>
                                    <td class="num">{{ number_format((int) round((float) (($billStatementTotals['installment_payment'] ?? 0))), 0, ',', '.') }}</td>
                                    <td class="num">{{ number_format((int) round((float) (($billStatementTotals['sales_return'] ?? 0))), 0, ',', '.') }}</td>
                                    <td class="num">{{ number_format((int) round((float) (($billStatementTotals['running_balance'] ?? 0))), 0, ',', '.') }}</td>
                                </tr>
                                <tr style="font-weight:700;">
                                    <td colspan="3"></td>
                                    <td colspan="2" style="text-align:right;">{{ __('receivable.bill_total_receivable') }}</td>
                                    <td class="num">{{ number_format((int) round((float) (($billStatementTotals['running_balance'] ?? 0))), 0, ',', '.') }}</td>
                                </tr>
                                </tbody>
                            </table>
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
                    </div>
                    <div class="receivable-subcard" style="margin-top: 12px;">
                    <h4 style="margin: 0 0 8px 0;">{{ __('receivable.outstanding_invoice_details') }}</h4>
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
                                        style="max-width:220px;"
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
                        <div class="receivable-scroll-wrap outstanding">
                        <table class="receivable-outstanding-table">
                            <colgroup>
                                <col style="width: 24%;">
                                <col style="width: 12%;">
                                <col style="width: 13%;">
                                <col style="width: 14%;">
                                <col style="width: 14%;">
                                <col style="width: 14%;">
                                <col style="width: 9%;">
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
                    </div>
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

            const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                ? window.PgposAutoSearch.debounce
                : (fn, wait = 100) => {
                    let timeoutId = null;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => fn(...args), wait);
                    };
                };
            const onSearchInput = debounce(() => {
                if (window.PgposAutoSearch && !window.PgposAutoSearch.canSearchInput(searchInput)) {
                    return;
                }
                form.requestSubmit();
            }, 100);
            searchInput.addEventListener('input', onSearchInput);

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
