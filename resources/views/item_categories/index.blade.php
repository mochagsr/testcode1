@extends('layouts.app')

@section('title', __('ui.item_categories_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .item-categories-table {
            table-layout: fixed;
        }
        .item-categories-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .item-categories-toolbar .toolbar-left,
        .item-categories-toolbar .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .item-categories-toolbar .toolbar-left {
            flex: 1 1 320px;
        }
        .item-categories-toolbar .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0;
            width: 100%;
            max-width: 420px;
        }
        .item-categories-toolbar .search-form input[type="text"] {
            width: 320px;
            max-width: min(320px, 100%);
            flex: 1 1 260px;
            min-width: 0;
        }
        .item-categories-table-wrap {
            overflow-x: auto;
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
        @media (max-width: 1400px) {
            .item-categories-toolbar .toolbar-left,
            .item-categories-toolbar .toolbar-right {
                flex: 1 1 100%;
            }
        }
        @media (max-width: 1280px) {
            .item-categories-toolbar .search-form {
                width: 100%;
            }
            .item-categories-toolbar .search-form input[type="text"] {
                width: min(100%, 280px);
                max-width: min(100%, 280px);
            }
        }
    </style>
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.item_categories_title') }}</h1>
        <a class="btn" href="{{ route('item-categories.create') }}">{{ __('ui.add_category') }}</a>
    </div>

    <div class="card">
        <div class="item-categories-toolbar">
            <div class="toolbar-left">
                <form id="item-categories-search-form" method="get" class="search-form">
                    <input id="item-categories-search-input" type="text" name="search" placeholder="{{ __('ui.search_item_categories_placeholder') }}" value="{{ $search }}">
                    <button type="submit">{{ __('ui.search') }}</button>
                    @if($search !== '')
                        <a class="btn secondary" href="{{ route('item-categories.index') }}">{{ __('ui.reset') }}</a>
                    @endif
                </form>
            </div>
        </div>

        <div style="margin-top: 12px;" class="item-categories-table-wrap">
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

