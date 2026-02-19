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
            <a class="btn secondary" href="{{ route('suppliers.import.template') }}">Template Import</a>
        </form>
        <form method="post" action="{{ route('suppliers.import') }}" enctype="multipart/form-data" class="flex" style="margin-top:8px;">
            @csrf
            <input type="file" name="import_file" accept=".xlsx,.xls,.csv,.txt" required style="max-width:320px;">
            <button type="submit" class="btn secondary">Import</button>
        </form>
        @if(session('import_errors'))
            <div class="card" style="margin-top:8px; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.4);">
                <strong>Error Import:</strong>
                <ul style="margin:8px 0 0 18px;">
                    @foreach(array_slice((array) session('import_errors'), 0, 20) as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
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
            const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                ? window.PgposAutoSearch.debounce
                : (fn, wait = 100) => {
                    let timeoutId = null;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => fn(...args), wait);
                    };
                };
            const onInput = debounce(() => {
                if (window.PgposAutoSearch && !window.PgposAutoSearch.canSearchInput(searchInput)) return;
                form.requestSubmit();
            }, 100);
            searchInput.addEventListener('input', () => {
                onInput();
            });
        })();
    </script>
@endsection
