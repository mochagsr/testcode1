<div class="transaction-list-scroll">
<table>
    <thead>
    <tr>
        <th>{{ __('txn.no') }}</th>
        <th>{{ __('txn.date') }}</th>
        <th>{{ __('ui.customer_name') }}</th>
        <th>{{ __('receivable.amount_paid') }}</th>
        <th>{{ __('txn.status') }}</th>
        <th>{{ __('receivable.action') }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse($payments as $payment)
        <tr>
            <td>
                <div class="list-doc-cell">
                    <a class="list-doc-link" href="{{ route('receivable-payments.show', $payment) }}">{{ $payment->payment_number }}</a>
                </div>
            </td>
            <td>{{ $payment->payment_date?->format('d-m-Y') }}</td>
            <td>{{ $payment->customer?->name }} <span class="muted">({{ $payment->customer?->city }})</span></td>
            <td>Rp {{ number_format((int) round($payment->amount), 0, ',', '.') }}</td>
            <td>{{ $payment->is_canceled ? __('txn.status_canceled') : __('txn.status_active') }}</td>
            <td>
                <div class="flex">
                    <a class="btn info-btn" href="{{ route('receivable-payments.show', $payment) }}">{{ __('txn.detail') }}</a>
                    <select class="action-menu" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                        <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                        <option value="{{ route('receivable-payments.print', $payment) }}">{{ __('txn.print') }}</option>
                        <option value="{{ route('receivable-payments.export.pdf', $payment) }}">{{ __('txn.pdf') }}</option>
                    </select>
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">{{ __('receivable.no_receivable_payments') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>

<div style="margin-top: 12px;">
    {{ $payments->links() }}
</div>
