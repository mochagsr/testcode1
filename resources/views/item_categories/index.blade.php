@extends('layouts.app')

@section('title', __('ui.item_categories_title').' - PgPOS ERP')

@section('content')
    <style>
        .item-categories-table {
            table-layout: fixed;
        }
        .item-categories-table th.action-col,
        .item-categories-table td.action-col {
            width: 150px;
        }
        .item-categories-table .category-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
        }
        .item-categories-table .category-actions form {
            margin: 0;
        }
        .item-categories-table .category-actions .btn {
            min-height: 30px;
            padding: 5px 10px;
            white-space: nowrap;
        }
    </style>
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.item_categories_title') }}</h1>
        <div class="flex" style="gap:8px;">
            <a class="btn info-btn" href="{{ route('item-categories.import.template') }}">Template Import</a>
            <a class="btn" href="{{ route('item-categories.create') }}">{{ __('ui.add_category') }}</a>
        </div>
    </div>

    <div class="card">
        <form id="item-categories-search-form" method="get" class="flex">
            <input id="item-categories-search-input" type="text" name="search" placeholder="{{ __('ui.search_item_categories_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <button type="submit">{{ __('ui.search') }}</button>
            @if($search !== '')
                <a class="btn secondary" href="{{ route('item-categories.index') }}">{{ __('ui.reset') }}</a>
            @endif
        </form>
    </div>

    <div class="card">
        <form method="post" action="{{ route('item-categories.import') }}" enctype="multipart/form-data" class="flex" style="margin-bottom: 12px;">
            @csrf
            <input type="file" name="import_file" accept=".xlsx,.xls,.csv,.txt" style="max-width: 280px;" required>
            <button type="submit" class="btn process-btn">Import</button>
        </form>
        <table class="item-categories-table">
            <thead>
            <tr>
                <th>{{ __('ui.code') }}</th>
                <th>{{ __('ui.name') }}</th>
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
                            <form method="post" action="{{ route('item-categories.destroy', $category) }}" onsubmit="return confirm('{{ __('ui.confirm_delete_category') }}');">
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

        <div style="margin-top: 12px;">
            {{ $categories->links() }}
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('item-categories-search-form');
            const searchInput = document.getElementById('item-categories-search-input');
            if (!form || !searchInput) {
                return;
            }

            const debounce = (window.PgposAutoSearch && window.PgposAutoSearch.debounce)
                ? window.PgposAutoSearch.debounce
                : (fn, wait = 100) => {
                    let timeoutId = null;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => fn(...args), wait);
                    };
                };
            const onSearchInput = debounce(() => {
                if (window.PgposAutoSearch && !window.PgposAutoSearch.canSearchInput(searchInput)) {
                    return;
                }
                form.requestSubmit();
            }, 100);
            searchInput.addEventListener('input', onSearchInput);
        })();
    </script>
@endsection
