@extends('layouts.app')

@section('title', __('ui.suppliers_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('ui.suppliers_title') }}</h1>

    <div class="card">
        <form id="suppliers-search-form" method="get" class="flex">
            <input id="suppliers-search-input" type="text" name="search" value="{{ $search }}" placeholder="{{ __('ui.search_suppliers_placeholder') }}" style="max-width:320px;">
            <button type="submit">{{ __('ui.search') }}</button>
            @if(auth()->user()->role === 'admin')
                <a class="btn secondary" href="{{ route('suppliers.create') }}">{{ __('ui.add_supplier') }}</a>
            @endif
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>{{ __('ui.name') }}</th>
                <th>{{ __('ui.supplier_company_name') }}</th>
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
                        <a class="btn secondary" href="{{ route('suppliers.edit', $supplier) }}">{{ __('ui.edit') }}</a>
                        @if(auth()->user()->role === 'admin')
                            <form method="post" action="{{ route('suppliers.destroy', $supplier) }}" style="display:inline;" onsubmit="return confirm('{{ __('ui.confirm_delete_supplier') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn secondary">{{ __('ui.delete') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">{{ __('ui.no_suppliers') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:12px;">{{ $suppliers->links() }}</div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('suppliers-search-form');
            const searchInput = document.getElementById('suppliers-search-input');
            if (!form || !searchInput) return;
            let debounceTimer = null;
            searchInput.addEventListener('input', () => {
                if (debounceTimer) clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    if (window.PgposAutoSearch && !window.PgposAutoSearch.canSearchInput(searchInput)) return;
                    form.requestSubmit();
                }, 300);
            });
        })();
    </script>
@endsection
