@php
    $sortUrl = function (string $field) use ($search, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('item-categories.index', ['search' => $search, 'sort' => $field, 'direction' => $nextDir]);
    };
    $sortMark = function (string $field) use ($sort, $direction): string {
        if ($sort !== $field) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
@endphp
<div style="margin-top: 12px;" class="item-categories-table-wrap">
<table class="item-categories-table">
    <thead>
    <tr>
        <th><a class="sort-link" href="{{ $sortUrl('code') }}">{{ __('ui.code') }} <span class="sort-mark">{{ $sortMark('code') }}</span></a></th>
        <th><a class="sort-link" href="{{ $sortUrl('name') }}">{{ __('ui.name') }} <span class="sort-mark">{{ $sortMark('name') }}</span></a></th>
        <th>{{ __('ui.description') }}</th>
        <th class="action-col">{{ __('ui.actions') }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse($categories as $category)
        <tr>
            <td>{{ $category->code }}</td>
            <td>{{ $category->name }}</td>
            <td>{{ $category->description ?: '-' }}</td>
            <td class="action-col">
                <div class="category-actions">
                    <a class="btn edit-btn" href="{{ route('item-categories.edit', $category) }}">{{ __('ui.edit') }}</a>
                    <form method="post" action="{{ route('item-categories.destroy', $category) }}" data-confirm-modal data-confirm-message="{{ __('ui.confirm_delete_category') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
                    </form>
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="4" class="muted">{{ __('ui.no_item_categories') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>

<div style="margin-top: 12px;">
    {{ $categories->links() }}
</div>
