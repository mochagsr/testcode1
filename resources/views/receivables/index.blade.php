@extends('layouts.app')

@section('title', __('receivable.title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('receivable.title') }}</h1>

    <div class="card">
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
    </div>

    @php
        $activeSemesterForReport = $selectedSemester ?: $currentSemester;
    @endphp
    <div class="card">
        <h3 style="margin-top: 0;">{{ __('receivable.print_options_title') }}</h3>
        <table>
            <thead>
            <tr>
                <th>{{ __('receivable.report_type') }}</th>
                <th>{{ __('txn.print') }}</th>
                <th>{{ __('txn.pdf') }}</th>
                <th>{{ __('txn.excel') }}</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>{{ __('receivable.report_all_unpaid') }}</td>
                <td><a class="btn secondary" target="_blank" href="{{ route('reports.print', ['dataset' => 'receivables']) }}">{{ __('txn.print') }}</a></td>
                <td><a class="btn secondary" href="{{ route('reports.export.pdf', ['dataset' => 'receivables']) }}">{{ __('txn.pdf') }}</a></td>
                <td><a class="btn" href="{{ route('reports.export.csv', ['dataset' => 'receivables']) }}">{{ __('txn.excel') }}</a></td>
            </tr>
            <tr>
                <td>{{ __('receivable.report_by_semester') }} ({{ $activeSemesterForReport }})</td>
                <td><a class="btn secondary" target="_blank" href="{{ route('reports.print', ['dataset' => 'receivables', 'semester' => $activeSemesterForReport]) }}">{{ __('txn.print') }}</a></td>
                <td><a class="btn secondary" href="{{ route('reports.export.pdf', ['dataset' => 'receivables', 'semester' => $activeSemesterForReport]) }}">{{ __('txn.pdf') }}</a></td>
                <td><a class="btn" href="{{ route('reports.export.csv', ['dataset' => 'receivables', 'semester' => $activeSemesterForReport]) }}">{{ __('txn.excel') }}</a></td>
            </tr>
            <tr>
                <td>
                    {{ __('receivable.report_by_customer_semester') }}
                    @if($selectedCustomerId > 0)
                        ({{ ($selectedCustomerName ?? null) ?: __('receivable.customer_id').' '.$selectedCustomerId }} / {{ $activeSemesterForReport }})
                    @endif
                </td>
                <td>
                    @if($selectedCustomerId > 0)
                        <a class="btn secondary" target="_blank" href="{{ route('reports.print', ['dataset' => 'receivables', 'customer_id' => $selectedCustomerId, 'semester' => $activeSemesterForReport]) }}">{{ __('txn.print') }}</a>
                    @else
                        <span class="muted">{{ __('receivable.select_customer_first') }}</span>
                    @endif
                </td>
                <td>
                    @if($selectedCustomerId > 0)
                        <a class="btn secondary" href="{{ route('reports.export.pdf', ['dataset' => 'receivables', 'customer_id' => $selectedCustomerId, 'semester' => $activeSemesterForReport]) }}">{{ __('txn.pdf') }}</a>
                    @else
                        <span class="muted">-</span>
                    @endif
                </td>
                <td>
                    @if($selectedCustomerId > 0)
                        <a class="btn" href="{{ route('reports.export.csv', ['dataset' => 'receivables', 'customer_id' => $selectedCustomerId, 'semester' => $activeSemesterForReport]) }}">{{ __('txn.excel') }}</a>
                    @else
                        <span class="muted">-</span>
                    @endif
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="row">
        <div class="col-6">
            <div class="card">
                <h3>{{ __('receivable.customer_balances') }}</h3>
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
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->city ?: '-' }}</td>
                            <td>Rp {{ number_format($customer->outstanding_receivable, 2) }}</td>
                            <td>
                                <a class="btn secondary" href="{{ route('receivables.index', ['customer_id' => $customer->id, 'search' => $search, 'semester' => $selectedSemester]) }}">
                                    {{ __('receivable.ledger') }}
                                </a>
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
        <div class="col-6">
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
                        <?php $shownPayInvoices = []; ?>
                        @foreach($ledgerRows as $row)
                            <?php
                                $invoiceId = $row->invoice?->id;
                                $canPay = $row->invoice
                                    && (float) $row->invoice->balance > 0
                                    && (float) $row->debit > 0
                                    && !in_array($invoiceId, $shownPayInvoices, true);
                                if ($canPay && $invoiceId !== null) {
                                    $shownPayInvoices[] = $invoiceId;
                                }
                            ?>
                        <tr>
                            <td>{{ $row->entry_date->format('d-m-Y') }}</td>
                            <td>
                                {{ $row->description ?: '-' }}
                                @if($row->invoice)
                                    <div class="muted">{{ $row->invoice->invoice_number }}</div>
                                @endif
                            </td>
                            <td>Rp {{ number_format($row->debit, 2) }}</td>
                            <td>Rp {{ number_format($row->credit, 2) }}</td>
                            <td>Rp {{ number_format($row->balance_after, 2) }}</td>
                            <td>
                                @if($canPay)
                                    <button
                                        type="button"
                                        class="btn secondary receivable-pay-toggle"
                                        data-target="pay-form-{{ $row->id }}"
                                    >
                                        {{ __('receivable.pay') }}
                                    </button>
                                    <form
                                        id="pay-form-{{ $row->id }}"
                                        method="post"
                                        action="{{ route('receivables.pay', $row->invoice) }}"
                                        class="receivable-pay-form"
                                        style="display:none; margin-top:8px;"
                                    >
                                        @csrf
                                        <input type="hidden" name="search" value="{{ $search }}">
                                        <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                                        <input type="hidden" name="customer_id" value="{{ $selectedCustomerId }}">
                                        <div style="margin-bottom:8px;">
                                            <label style="display:block; margin-bottom:4px;">{{ __('receivable.payment_amount') }}</label>
                                            <input
                                                type="number"
                                                name="amount"
                                                value="{{ number_format((float) $row->invoice->balance, 2, '.', '') }}"
                                                min="0.01"
                                                max="{{ number_format((float) $row->invoice->balance, 2, '.', '') }}"
                                                step="0.01"
                                                required
                                                style="max-width:150px;"
                                            >
                                            <div class="muted" style="margin-top:4px;">
                                                {{ __('receivable.remaining') }}: Rp {{ number_format($row->invoice->balance, 2) }}
                                            </div>
                                        </div>
                                        <div style="margin-bottom:8px;">
                                            <label style="display:block; margin-bottom:4px;">{{ __('receivable.payment_method') }}</label>
                                            <select name="method" required style="max-width:170px;">
                                                <option value="cash">{{ __('receivable.method_cash') }}</option>
                                                <option value="bank_transfer">{{ __('receivable.method_transfer') }}</option>
                                            </select>
                                        </div>
                                        <div style="margin-bottom:8px;">
                                            <label style="display:block; margin-bottom:4px;">{{ __('receivable.payment_date') }}</label>
                                            <input type="date" name="payment_date" value="{{ now()->format('Y-m-d') }}" style="max-width:170px;">
                                        </div>
                                        <button type="submit" class="btn">{{ __('receivable.save_payment') }}</button>
                                    </form>
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
                }, 400);
            });

            customerSelect.addEventListener('change', () => {
                form.requestSubmit();
            });

            semesterSelect.addEventListener('change', () => {
                form.requestSubmit();
            });

            document.querySelectorAll('.receivable-pay-toggle').forEach((button) => {
                button.addEventListener('click', () => {
                    const targetId = button.getAttribute('data-target');
                    const target = targetId ? document.getElementById(targetId) : null;
                    if (!target) {
                        return;
                    }

                    const isOpen = target.style.display !== 'none';
                    target.style.display = isOpen ? 'none' : 'block';
                });
            });
        })();
    </script>
@endsection
