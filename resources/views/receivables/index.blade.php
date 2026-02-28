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
            border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, var(--text) 30%);
            vertical-align: middle;
            padding: 10px 8px;
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
            min-width: 1120px;
        }
        .receivable-scroll-wrap.bill table {
            min-width: 1040px;
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
        .receivable-scroll-wrap thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--card);
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
        <div class="col-4 receivable-col-customer">
            <div class="card">
                <h3>{{ __('receivable.customer') }}</h3>
                <div class="receivable-scroll-wrap customer">
                <table class="receivable-customer-table">
                    <colgroup>
                        <col style="width: 25%;">
                        <col style="width: 15%;">
                        <col style="width: 25%;">
                        <col style="width: 30%;">
                    </colgroup>
                    <thead>
                    <tr>
                        <th class="customer-col">{{ __('receivable.customer') }}</th>
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
                            <td class="customer-col">
                                <span class="receivable-customer-name">{{ $customer->name }}</span>
                                @if($selectedSemester)
                                    <span class="badge receivable-customer-lock {{ $rowCustomerSemesterClosed ? 'danger' : 'success' }}">
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
                                @endif
                            </td>
                            <td class="city-col">{{ $customer->city ?: '-' }}</td>
                            <td class="num outstanding-col">Rp {{ number_format((int) round($customer->outstanding_receivable), 0, ',', '.') }}</td>
                            <td class="action-cell">
                                @php
                                    $ledgerUrl = route('receivables.index', [
                                        'customer_id' => $customer->id,
                                        'search' => $search,
                                        'semester' => $selectedSemester,
                                    ]);
                                    $paymentUrl = route('receivable-payments.create', [
                                        'customer_id' => $customer->id,
                                        'amount' => (int) round((float) $customer->outstanding_receivable),
                                        'payment_date' => now()->format('Y-m-d'),
                                        'return_to' => request()->getRequestUri(),
                                    ]);
                                @endphp
                                <div class="receivable-customer-actions">
                                    <a class="btn secondary" href="{{ $ledgerUrl }}">{{ __('receivable.ledger') }}</a>
                                    @if((float) $customer->outstanding_receivable > 0 && ! $rowCustomerSemesterClosed)
                                        <a class="btn" href="{{ $paymentUrl }}">{{ __('receivable.create_payment') }}</a>
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
                            <col style="width: 33%;">
                            <col style="width: 16%;">
                            <col style="width: 16%;">
                            <col style="width: 16%;">
                            <col style="width: 8%;">
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
                                                class="btn secondary receivable-pay-btn"
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
                    <div class="receivable-subcard" style="margin-top: 8px;">
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
                                    <col style="width: 11%;">
                                    <col style="width: 27%;">
                                    <col style="width: 16%;">
                                    <col style="width: 16%;">
                                    <col style="width: 14%;">
                                    <col style="width: 16%;">
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
                                            <td class="num">Rp {{ number_format((int) round((float) ($billRow['running_balance'] ?? 0)), 0, ',', '.') }}</td>
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
                                                @if((int) ($billRow['adjustment_amount'] ?? 0) !== 0)
                                                    <span class="muted" style="display:block; font-size:11px;">
                                                        ({{ (int) ($billRow['adjustment_amount'] ?? 0) > 0 ? '+' : '-' }}Rp {{ number_format(abs((int) ($billRow['adjustment_amount'] ?? 0)), 0, ',', '.') }})
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="num">Rp {{ number_format((int) round((float) ($billRow['credit_sales'] ?? 0)), 0, ',', '.') }}</td>
                                            <td class="num">Rp {{ number_format((int) round((float) ($billRow['installment_payment'] ?? 0)), 0, ',', '.') }}</td>
                                            <td class="num">Rp {{ number_format((int) round((float) ($billRow['sales_return'] ?? 0)), 0, ',', '.') }}</td>
                                            <td class="num">Rp {{ number_format((int) round((float) ($billRow['running_balance'] ?? 0)), 0, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                <tr style="font-weight:700;">
                                    <td colspan="2" style="text-align:center;">{{ __('receivable.bill_total') }}</td>
                                    <td class="num">Rp {{ number_format((int) round((float) (($billStatementTotals['credit_sales'] ?? 0))), 0, ',', '.') }}</td>
                                    <td class="num">Rp {{ number_format((int) round((float) (($billStatementTotals['installment_payment'] ?? 0))), 0, ',', '.') }}</td>
                                    <td class="num">Rp {{ number_format((int) round((float) (($billStatementTotals['sales_return'] ?? 0))), 0, ',', '.') }}</td>
                                    <td class="num">Rp {{ number_format((int) round((float) (($billStatementTotals['running_balance'] ?? 0))), 0, ',', '.') }}</td>
                                </tr>
                                <tr style="font-weight:700;">
                                    <td colspan="3"></td>
                                    <td colspan="2" style="text-align:right;">{{ __('receivable.bill_total_receivable') }}</td>
                                    <td class="num">Rp {{ number_format((int) round((float) (($billStatementTotals['running_balance'] ?? 0))), 0, ',', '.') }}</td>
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
                    <div class="receivable-subcard" style="margin-top: 10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                        <h4 style="margin: 0;">{{ __('receivable.outstanding_invoice_details') }}</h4>
                        @if((auth()->user()?->role ?? '') === 'admin' && (float) ($customerOutstandingTotal ?? 0) > 0)
                            <div class="flex" style="gap:8px;">
                                <button
                                    type="button"
                                    class="btn secondary"
                                    id="open-receivable-writeoff-modal"
                                >
                                    {{ __('receivable.method_writeoff') }}
                                </button>
                                <button
                                    type="button"
                                    class="btn"
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
                                                class="btn secondary receivable-pay-btn"
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
                    @if((auth()->user()?->role ?? '') === 'admin' && (float) ($customerOutstandingTotal ?? 0) > 0)
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
                                <button type="button" id="receivable-adjustment-close" class="btn secondary" style="min-height:30px; padding:4px 10px;">&times;</button>
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
                                    <input id="receivable-adjustment-amount" type="number" min="1" step="1">
                                </div>
                            </div>

                            <div class="row" id="receivable-adjustment-discount-extra" style="display:none;">
                                <div>
                                    <label>{{ __('receivable.discount_percent') }}</label>
                                    <input id="receivable-adjustment-discount-percent" type="number" min="0" max="100" step="0.01" value="0">
                                </div>
                                <div>
                                    <label>{{ __('receivable.discount_pay_amount') }}</label>
                                    <input id="receivable-adjustment-pay-amount" type="number" min="0" step="1">
                                </div>
                            </div>

                            <div class="footer">
                                <small id="receivable-adjustment-status" class="muted">{{ __('receivable.adjustment_auto_save_hint') }}</small>
                                <button type="button" id="receivable-adjustment-save-now" class="btn">{{ __('receivable.save_payment') }}</button>
                            </div>
                        </div>
                    @endif
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

        })();

        (function () {
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

            let isSubmitting = false;
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
            const toInt = (value) => Math.round(Number(value || 0));

            const updateDiscountFromPercent = () => {
                const percent = clamp(Number(discountPercentInput.value || 0), 0, 100);
                const discountAmount = Math.round((totalOutstanding * percent) / 100);
                const payAmount = Math.max(0, totalOutstanding - discountAmount);
                discountPercentInput.value = Number(percent.toFixed(2)).toString();
                amountInput.value = String(discountAmount);
                payAmountInput.value = String(payAmount);
            };

            const updateDiscountFromPay = () => {
                const payAmount = clamp(toInt(payAmountInput.value), 0, totalOutstanding);
                const discountAmount = Math.max(0, totalOutstanding - payAmount);
                const percent = totalOutstanding > 0 ? (discountAmount / totalOutstanding) * 100 : 0;
                payAmountInput.value = String(payAmount);
                amountInput.value = String(discountAmount);
                discountPercentInput.value = Number(percent.toFixed(2)).toString();
            };

            const updateDiscountFromAmount = () => {
                const discountAmount = clamp(toInt(amountInput.value), 0, totalOutstanding);
                const payAmount = Math.max(0, totalOutstanding - discountAmount);
                const percent = totalOutstanding > 0 ? (discountAmount / totalOutstanding) * 100 : 0;
                amountInput.value = String(discountAmount);
                payAmountInput.value = String(payAmount);
                discountPercentInput.value = Number(percent.toFixed(2)).toString();
            };

            const openModal = (nextMode) => {
                mode = nextMode === 'discount' ? 'discount' : 'writeoff';
                totalInput.value = formatRupiah(totalOutstanding);
                setStatus(@json(__('receivable.adjustment_auto_save_hint')));

                if (mode === 'discount') {
                    titleEl.textContent = @json(__('receivable.method_discount'));
                    amountLabel.textContent = @json(__('receivable.discount_amount'));
                    discountWrap.style.display = 'grid';
                    amountInput.min = '0';
                    amountInput.max = String(totalOutstanding);
                    discountPercentInput.value = '0';
                    payAmountInput.value = String(totalOutstanding);
                    updateDiscountFromPercent();
                } else {
                    titleEl.textContent = @json(__('receivable.method_writeoff'));
                    amountLabel.textContent = @json(__('receivable.method_writeoff'));
                    discountWrap.style.display = 'none';
                    amountInput.min = '1';
                    amountInput.max = String(totalOutstanding);
                    amountInput.value = String(totalOutstanding);
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
                if (isSubmitting) {
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

                isSubmitting = true;
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
                    isSubmitting = false;
                    saveNowBtn.disabled = false;
                }
            };

            const requestCloseModal = () => {
                if (isSubmitting) {
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

            document.addEventListener('keydown', (event) => {
                if (modal.style.display !== 'block') {
                    return;
                }
                if (event.key === 'Escape') {
                    requestCloseModal();
                }
            });
        })();
    </script>
@endsection
