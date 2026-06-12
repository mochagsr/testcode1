@php
    $sortUrl = function (string $field) use ($search, $selectedSemester, $selectedStatus, $selectedReturnDate, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('sales-returns.index', array_filter(['search' => $search, 'semester' => $selectedSemester, 'status' => $selectedStatus, 'return_date' => $selectedReturnDate, 'sort' => $field, 'direction' => $nextDir], fn ($v) => $v !== null && $v !== ''));
    };
    $sortMark = function (string $field) use ($sort, $direction): string {
        if ($sort !== $field) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
@endphp

<div class="card">
    <div class="flex" style="justify-content: space-between;">
        <strong>{{ __('txn.summary') }} {{ __('txn.date') }} {{ now()->format('d-m-Y') }}</strong>
        <div class="muted">
            {{ __('txn.summary_total_returns') }}: {{ (int) round((int) ($todaySummary->total_return ?? 0)) }} |
            {{ __('txn.summary_grand_total') }}: Rp {{ number_format((int) round((float) ($todaySummary->grand_total ?? 0), 0), 0, ',', '.') }}
        </div>
    </div>
</div>

<div class="card">
    <div class="transaction-list-scroll">
    <table>
        <thead>
        <tr>
            <th>{{ __('txn.return') }}</th>
            <th><a class="sort-link" href="{{ $sortUrl('date') }}">{{ __('txn.date') }} <span class="sort-mark">{{ $sortMark('date') }}</span></a></th>
            <th><a class="sort-link" href="{{ $sortUrl('customer') }}">{{ __('ui.customer_name') }} <span class="sort-mark">{{ $sortMark('customer') }}</span></a></th>
            <th>{{ __('txn.total_return') }}</th>
            <th>{{ __('txn.action') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($returns as $row)
            @php
                $lockKey = ((int) $row->customer_id).':'.(string) $row->semester_period;
                $lockState = $customerSemesterLockMap[$lockKey] ?? ['locked' => false, 'manual' => false, 'auto' => false];
                $adminAction = $returnAdminActionMap[(int) $row->id] ?? ['edited' => false, 'canceled' => false];
            @endphp
            <tr>
                <td>
                    <div class="list-doc-cell">
                        <a class="list-doc-link" href="{{ route('sales-returns.show', $row) }}">{{ $row->return_number }}</a>
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
                <td>{{ $row->return_date->format('d-m-Y') }}</td>
                <td>
                    {{ $row->customer->name }} <span class="muted">({{ $row->customer->city }})</span>
                </td>
                <td>Rp {{ number_format((int) round($row->total), 0, ',', '.') }}</td>
                <td>
                    <div class="flex">
                        <select class="action-menu action-menu-sm" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                            <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                            <option value="{{ route('sales-returns.print', $row) }}">{{ __('txn.print') }}</option>
                            <option value="{{ route('sales-returns.export.pdf', $row) }}">{{ __('txn.pdf') }}</option>
                            <option value="{{ route('sales-returns.export.excel', $row) }}">{{ __('txn.excel') }}</option>
                        </select>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">{{ __('txn.no_returns_found') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <div style="margin-top: 12px;">
        {{ $returns->links() }}
    </div>
</div>
