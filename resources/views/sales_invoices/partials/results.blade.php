<div class="card">
    <div class="flex" style="justify-content: space-between;">
        <strong>{{ __('txn.summary') }} {{ __('txn.date') }} {{ now()->format('d-m-Y') }}</strong>
        <div class="muted">
            {{ __('txn.summary_total_invoices') }}: {{ (int) round((int) ($todaySummary->total_invoice ?? 0)) }} |
            {{ __('txn.summary_grand_total') }}: Rp {{ number_format((int) round((float) ($todaySummary->grand_total ?? 0), 0), 0, ',', '.') }}
        </div>
    </div>
</div>

<div class="card invoice-list-card">
    <div class="table-mobile-scroll transaction-list-scroll">
    <table class="mobile-stack-table">
        <thead>
        <tr>
            <th>{{ __('txn.invoice') }}</th>
            <th>{{ __('txn.date') }}</th>
            <th>{{ __('ui.customer_name') }}</th>
            <th>{{ __('txn.total') }}</th>
            <th>{{ __('txn.action') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($invoices as $invoice)
            @php
                $lockKey = ((int) $invoice->customer_id).':'.(string) $invoice->semester_period;
                $lockState = $customerSemesterLockMap[$lockKey] ?? ['locked' => false, 'manual' => false, 'auto' => false];
                $adminAction = $invoiceAdminActionMap[(int) $invoice->id] ?? ['edited' => false, 'canceled' => false];
            @endphp
            <tr>
                <td data-label="{{ __('txn.invoice') }}">
                    <div class="list-doc-cell">
                        <a class="list-doc-link" href="{{ route('sales-invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a>
                        @if($invoice->orderNote)
                            <span class="list-doc-meta">
                                <span class="list-doc-meta-label">{{ __('txn.linked_order_note') }}:</span>
                                <a href="{{ route('order-notes.show', $invoice->orderNote) }}">{{ $invoice->orderNote->note_number }}</a>
                            </span>
                        @endif
                        <span class="list-doc-badges">
                            @if((bool) ($lockState['manual'] ?? false))
                                <span class="badge warning">{{ __('receivable.customer_semester_locked_manual') }}</span>
                            @endif
                            @if((bool) ($adminAction['edited'] ?? false))
                                <span class="badge warning">{{ __('txn.admin_badge_edit') }}</span>
                            @endif
                            @if((bool) ($adminAction['canceled'] ?? false))
                                <span class="badge danger">{{ __('txn.admin_badge_cancel') }}</span>
                            @endif
                        </span>
                    </div>
                </td>
                <td data-label="{{ __('txn.date') }}">{{ $invoice->invoice_date->format('d-m-Y') }}</td>
                <td data-label="{{ __('ui.customer_name') }}">
                    {{ $invoice->customer->name }} <span class="muted">({{ $invoice->customer->city }})</span>
                </td>
                <td data-label="{{ __('txn.total') }}">Rp {{ number_format((int) round($invoice->total), 0, ',', '.') }}</td>
                <td data-label="{{ __('txn.action') }}" class="action">
                    <div class="flex">
                        <select class="action-menu action-menu-sm" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                            <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                            <option value="{{ route('sales-invoices.print', $invoice) }}">{{ __('txn.print') }}</option>
                            <option value="{{ route('sales-invoices.export.pdf', $invoice) }}">{{ __('txn.pdf') }}</option>
                            <option value="{{ route('sales-invoices.export.excel', $invoice) }}">{{ __('txn.excel') }}</option>
                        </select>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="muted">{{ __('txn.no_data_found') }}</td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <div style="margin-top: 12px;">
        {{ $invoices->links() }}
    </div>
</div>
