@php
    $sortUrl = function (string $field) use ($search, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('product-units.index', ['search' => $search, 'sort' => $field, 'direction' => $nextDir]);
    };
    $sortMark = function (string $field) use ($sort, $direction): string {
        if ($sort !== $field) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
@endphp
<div style="margin-top: 12px;" class="product-units-table-wrap">
    <table class="product-units-table">
        <thead>
        <tr>
            <th style="width: 18%;"><a class="sort-link" href="{{ $sortUrl('code') }}">{{ __('ui.code') }} <span class="sort-mark">{{ $sortMark('code') }}</span></a></th>
            <th style="width: 28%;"><a class="sort-link" href="{{ $sortUrl('name') }}">{{ __('ui.name') }} <span class="sort-mark">{{ $sortMark('name') }}</span></a></th>
            <th>{{ __('ui.description') }}</th>
            <th class="action-col">{{ __('ui.actions') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($units as $unit)
            <tr>
                <td>{{ $unit->code }}</td>
                <td>{{ $unit->name }}</td>
                <td>{{ $unit->description ?: '-' }}</td>
                <td class="action-col">
                    <div class="unit-actions">
                        <a class="btn edit-btn" href="{{ route('product-units.edit', $unit) }}">{{ __('ui.edit') }}</a>
                        <form method="post" action="{{ route('product-units.destroy', $unit) }}" data-confirm-modal data-confirm-message="{{ __('ui.confirm_delete_product_unit') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">{{ __('ui.no_product_units') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top: 12px;">
    {{ $units->links() }}
</div>
