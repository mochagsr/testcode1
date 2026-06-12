@php
    $canManageCustomers = auth()->user()?->canAccessAny(['customers.create', 'customers.edit', 'customers.delete', 'customers.import']) ?? false;
    $sortUrl = function (string $field) use ($search, $selectedLevelId, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('customers-web.index', ['search' => $search, 'level_id' => $selectedLevelId ?: null, 'sort' => $field, 'direction' => $nextDir]);
    };
    $sortMark = function (string $field) use ($sort, $direction): string {
        if ($sort !== $field) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
@endphp

<div class="card">
    <div class="customers-table-wrap">
    <table class="customers-table">
        <thead>
        <tr>
            <th>
                <a class="sort-link" href="{{ $sortUrl('name') }}">
                    {{ __('ui.name') }} <span class="sort-mark">{{ $sortMark('name') }}</span>
                </a>
            </th>
            <th>
                <a class="sort-link" href="{{ $sortUrl('level') }}">
                    {{ __('ui.level') }} <span class="sort-mark">{{ $sortMark('level') }}</span>
                </a>
            </th>
            <th>{{ __('ui.phone') }}</th>
            <th>
                <a class="sort-link" href="{{ $sortUrl('city') }}">
                    {{ __('ui.city') }} <span class="sort-mark">{{ $sortMark('city') }}</span>
                </a>
            </th>
            <th>{{ __('ui.address') }}</th>
            <th>{{ __('ui.receivable') }}</th>
            <th class="ktp-col">{{ __('ui.id_card') }}</th>
            <th class="action-col">{{ __('ui.actions') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($customers as $customer)
            <tr>
                <td>{{ $customer->name }}</td>
                <td>
                    @if($customer->level)
                        <a href="#"
                           class="customer-level-link"
                           data-level-id="{{ (int) $customer->level->id }}"
                           data-level-label="{{ $customer->level->name }}">
                            {{ $customer->level->name }}
                        </a>
                    @else
                        -
                    @endif
                </td>
                <td>
                    @php
                        $phoneDisplay = collect([
                            (string) ($customer->phone ?? ''),
                            (string) ($customer->phone_secondary ?? ''),
                        ])->map(fn (string $value) => trim($value))->filter()->values();
                    @endphp
                    {{ $phoneDisplay->isNotEmpty() ? $phoneDisplay->implode(' / ') : '-' }}
                </td>
                <td>{{ $customer->city ?: '-' }}</td>
                <td>{{ $customer->address ?: '-' }}</td>
                <td>Rp {{ number_format((int) round($customer->outstanding_receivable), 0, ',', '.') }}</td>
                <td class="ktp-col">
                    @if($customer->id_card_photo_path)
                        <div class="compact-actions">
                            <a class="btn info-btn id-card-preview-trigger" href="#" data-image="{{ asset('storage/'.$customer->id_card_photo_path) }}">{{ __('ui.view') }}</a>
                            <a class="btn info-btn" href="{{ route('customers-web.id-card-photo.print', $customer) }}" target="_blank">{{ __('txn.print') }}</a>
                        </div>
                    @else
                        -
                    @endif
                </td>
                <td class="action-col">
                    @if($canManageCustomers)
                        <div class="compact-actions">
                            <a class="btn edit-btn" href="{{ route('customers-web.edit', $customer) }}">{{ __('ui.edit') }}</a>
                            <form method="post" action="{{ route('customers-web.destroy', $customer) }}" data-confirm-modal data-confirm-message="{{ __('ui.confirm_delete_customer') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                    @else
                        -
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">{{ __('ui.no_customers') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>

    <div style="margin-top: 12px;">
        {{ $customers->links() }}
    </div>
</div>
