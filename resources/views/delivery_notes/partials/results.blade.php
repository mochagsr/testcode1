@php
    $sortUrl = function (string $field) use ($search, $selectedSemester, $selectedStatus, $selectedNoteDate, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('delivery-notes.index', array_filter(['search' => $search, 'semester' => $selectedSemester, 'status' => $selectedStatus, 'note_date' => $selectedNoteDate, 'sort' => $field, 'direction' => $nextDir], fn ($v) => $v !== null && $v !== ''));
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
            {{ __('txn.summary_total_delivery_notes') }}: {{ (int) round((int) ($todaySummary->total_notes ?? 0)) }} |
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
            <th><a class="sort-link" href="{{ $sortUrl('recipient_name') }}">{{ __('txn.recipient') }} <span class="sort-mark">{{ $sortMark('recipient_name') }}</span></a></th>
            <th><a class="sort-link" href="{{ $sortUrl('city') }}">{{ __('txn.city') }} <span class="sort-mark">{{ $sortMark('city') }}</span></a></th>
            <th>{{ __('txn.created_by') }}</th>
            <th>{{ __('txn.status') }}</th>
            <th>{{ __('txn.action') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($notes as $note)
            <tr>
                <td data-label="{{ __('txn.no') }}">
                    <div class="list-doc-cell">
                        <a class="list-doc-link" href="{{ route('delivery-notes.show', $note) }}">{{ $note->note_number }}</a>
                    </div>
                </td>
                <td data-label="{{ __('txn.date') }}">{{ $note->note_date->format('d-m-Y') }}</td>
                <td data-label="{{ __('txn.recipient') }}">{{ $note->recipient_name }}</td>
                <td data-label="{{ __('txn.city') }}">{{ $note->city ?: '-' }}</td>
                <td data-label="{{ __('txn.created_by') }}">{{ $note->created_by_name ?: '-' }}</td>
                <td data-label="{{ __('txn.status') }}">{{ $note->is_canceled ? __('txn.status_canceled') : __('txn.status_active') }}</td>
                <td data-label="{{ __('txn.action') }}" class="action">
                    <div class="flex">
                        <select class="action-menu action-menu-sm" onchange="if(this.value){window.open(this.value,'_blank'); this.selectedIndex=0;}">
                            <option value="" selected disabled>{{ __('txn.action_menu') }}</option>
                            <option value="{{ route('delivery-notes.print', $note) }}">{{ __('txn.print') }}</option>
                            <option value="{{ route('delivery-notes.export.pdf', $note) }}">{{ __('txn.pdf') }}</option>
                            <option value="{{ route('delivery-notes.export.excel', $note) }}">{{ __('txn.excel') }}</option>
                        </select>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">{{ __('txn.no_delivery_notes_found') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <div style="margin-top: 12px;">
        {{ $notes->links() }}
    </div>
</div>
