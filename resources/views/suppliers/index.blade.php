@extends('layouts.app')

@section('title', __('ui.suppliers_title').' - PgPOS ERP')

@section('content')
    <style>
        .suppliers-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .suppliers-toolbar .toolbar-left,
        .suppliers-toolbar .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .suppliers-toolbar .toolbar-left {
            flex: 1 1 320px;
        }
        .suppliers-toolbar .toolbar-right {
            justify-content: flex-end;
            flex: 1 1 460px;
        }
        .suppliers-toolbar .search-form,
        .suppliers-toolbar .import-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            margin: 0;
        }
        .suppliers-toolbar .search-form input[type="text"],
        .suppliers-toolbar .import-form input[type="file"] {
            width: 320px;
            max-width: 320px;
        }
    </style>
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.suppliers_title') }}</h1>
        @if(auth()->user()->role === 'admin')
            <a class="btn" href="{{ route('suppliers.create') }}">{{ __('ui.add_supplier') }}</a>
        @endif
    </div>

    <div class="card">
        <div class="suppliers-toolbar">
            <div class="toolbar-left">
                <form id="suppliers-search-form" method="get" class="search-form">
                    <input id="suppliers-search-input" type="text" name="search" value="{{ $search }}" placeholder="{{ __('ui.search_suppliers_placeholder') }}">
                    <button type="submit">{{ __('ui.search') }}</button>
                </form>
            </div>
            <div class="toolbar-right">
                <form method="post" action="{{ route('suppliers.import') }}" enctype="multipart/form-data" class="import-form">
                    @csrf
                    <input type="file" name="import_file" accept=".xlsx,.xls,.csv,.txt" required>
                    <button type="submit" class="btn process-btn">Import</button>
                    <a class="btn info-btn" href="{{ route('suppliers.import.template') }}">Template Import</a>
                </form>
            </div>
        </div>
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
                        <a class="btn edit-btn" href="{{ route('suppliers.edit', $supplier) }}">{{ __('ui.edit') }}</a>
                        @if(auth()->user()->role === 'admin')
                            <form method="post" action="{{ route('suppliers.destroy', $supplier) }}" style="display:inline;" onsubmit="return confirm('{{ __('ui.confirm_delete_supplier') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
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
