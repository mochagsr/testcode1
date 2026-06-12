@php
    $sortUrl = function (string $field) use ($search, $selectedSemester, $selectedStatus, $selectedNoteDate, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('order-notes.index', array_filter(['search' => $search, 'semester' => $selectedSemester, 'status' => $selectedStatus, 'note_date' => $selectedNoteDate, 'sort' => $field, 'direction' => $nextDir], fn ($v) => $v !== null && $v !== ''));
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
            {{ __('txn.summary_total_order_notes') }}: {{ (int) round((int) ($todaySummary->total_notes ?? 0)) }} |
            {{ __('txn.summary_total_qty') }}: {{ (int) round((int) ($todaySummary->total_qty ?? 0)) }}
        </div>
    </div>
</div>

<div class="card">
    <div class="table-mobile-scroll transaction-list-scroll">
    <table class="mobile-stack-table">
        <thead>
        <tr>
            <th>{{ __('txn.no') }}</th>
            <th><a class="sort-link" href="{{ $sortUrl('date') }}">{{ __('txn.date') }} <span class="sort-mark">{{ $sortMark('date') }}</span></a></th>
            <th><a class="sort-link" href="{{ $sortUrl('customer_name') }}">{{ __('ui.customer_name') }} <span class="sort-mark">{{ $sortMark('customer_name') }}</span></a></th>
            <th><a class="sort-link" href="{{ $sortUrl('city') }}">{{ __('txn.city') }} <span class="sort-mark">{{ $sortMark('city') }}</span></a></th>
            <th><a class="sort-link" href="{{ $sortUrl('progress') }}">{{ __('txn.order_note_progress') }} <span class="sort-mark">{{ $sortMark('progress') }}</span></a></th>
            <th>{{ __('txn.balance') }}</th>
            <th><a class="sort-link" href="{{ $sortUrl('status') }}">{{ __('txn.status') }} <span class="sort-mark">{{ $sortMark('status') }}</span></a></th>
            <th>{{ __('txn.created_by') }}</th>
            <th>{{ __('txn.action') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($notes as $note)
            @php
                $progress = $noteProgressMap[(int) $note->id] ?? [
                    'ordered_total' => 0,
                    'fulfilled_total' => 0,
                    'remaining_total' => 0,
                    'progress_percent' => 0,
                    'status' => 'open',
                ];
                $progressLabel = rtrim(rtrim(number_format((float) ($progress['progress_percent'] ?? 0), 2, '.', ''), '0'), '.');
                $statusLabel = match ($progress['status'] ?? 'open') {
                    'finished' => __('txn.order_note_status_finished'),
                    'partial' => __('txn.order_note_status_partial'),
                    'not_delivered' => __('txn.order_note_status_not_delivered'),
                    default => __('txn.order_note_status_open'),
                };
            @endphp
            <tr>
                <td data-label="{{ __('txn.no') }}">
                    <div class="list-doc-cell">
                        <a class="list-doc-link" href="{{ route('order-notes.show', $note) }}">{{ $note->note_number }}</a>
                        <span class="list-doc-badges">
                            @if($note->is_canceled)
                                <span class="badge danger">{{ __('txn.status_canceled') }}</span>
                            @endif
                        </span>
                    </div>
                </td>
                <td data-label="{{ __('txn.date') }}">{{ $note->note_date->format('d-m-Y') }}</td>
                <td data-label="{{ __('ui.customer_name') }}">{{ $note->customer_name }}</td>
                <td data-label="{{ __('txn.city') }}">{{ $note->city ?: '-' }}</td>
                <td data-label="{{ __('txn.order_note_progress') }}">{{ $progressLabel }}%</td>
                <td data-label="{{ __('txn.balance') }}">{{ number_format((int) ($progress['remaining_total'] ?? 0), 0, ',', '.') }}</td>
                <td data-label="{{ __('txn.status') }}">
                    @if($note->is_canceled)
                        <span class="badge danger">{{ __('txn.status_canceled') }}</span>
                    @elseif(($progress['status'] ?? 'open') === 'finished')
                        <span class="badge success order-status-badge">{{ $statusLabel }}</span>
                    @else
                        <span class="badge warning order-status-badge">{{ $statusLabel }}</span>
                    @endif
                </td>
                <td data-label="{{ __('txn.created_by') }}">{{ $note->created_by_name ?: '-' }}</td>
                <td data-label="{{ __('txn.action') }}" class="action">
                    <div class="flex">
                        <select class="action-menu action-menu-sm" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                            <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                            <option value="{{ route('order-notes.print', $note) }}">{{ __('txn.print') }}</option>
                            <option value="{{ route('order-notes.export.pdf', $note) }}">{{ __('txn.pdf') }}</option>
                            <option value="{{ route('order-notes.export.excel', $note) }}">{{ __('txn.excel') }}</option>
                        </select>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">{{ __('txn.no_order_notes_found') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <div style="margin-top: 12px;">
        {{ $notes->links() }}
    </div>
</div>
