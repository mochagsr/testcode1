@php
    $canManageSuppliers = auth()->user()?->canAccessAny(['suppliers.edit', 'suppliers.delete']) ?? false;
    $sortUrl = function (string $field) use ($search, $sort, $direction): string {
        $nextDir = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
        return route('suppliers.index', ['search' => $search, 'sort' => $field, 'direction' => $nextDir]);
    };
    $sortMark = function (string $field) use ($sort, $direction): string {
        if ($sort !== $field) return '↕';
        return $direction === 'asc' ? '↑' : '↓';
    };
@endphp
<div class="suppliers-table-wrap">
<table class="suppliers-table">
    <thead>
    <tr>
        <th><a class="sort-link" href="{{ $sortUrl('name') }}">{{ __('ui.name') }} <span class="sort-mark">{{ $sortMark('name') }}</span></a></th>
        <th><a class="sort-link" href="{{ $sortUrl('company_name') }}">{{ __('ui.supplier_company_name') }} <span class="sort-mark">{{ $sortMark('company_name') }}</span></a></th>
        <th>{{ __('ui.phone') }}</th>
        <th>{{ __('ui.address') }}</th>
        <th>{{ __('ui.notes') }}</th>
        <th>{{ __('ui.actions') }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse($suppliers as $supplier)
        <tr>
            <td>{{ $supplier->name }}</td>
            <td>{{ $supplier->company_name ?: '-' }}</td>
            <td>{{ $supplier->phone ?: '-' }}</td>
            <td>{{ $supplier->address ?: '-' }}</td>
            <td>{{ $supplier->notes ?: '-' }}</td>
            <td>
                @if($canManageSuppliers)
                    <a class="btn edit-btn" href="{{ route('suppliers.edit', $supplier) }}">{{ __('ui.edit') }}</a>
                    <form method="post" action="{{ route('suppliers.destroy', $supplier) }}" style="display:inline;" data-confirm-modal data-confirm-message="{{ __('ui.confirm_delete_supplier') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
                    </form>
                @else
                    <span class="muted">-</span>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">{{ __('ui.no_suppliers') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div style="margin-top:12px;">{{ $suppliers->links() }}</div>
