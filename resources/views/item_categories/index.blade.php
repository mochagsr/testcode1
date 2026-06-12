@extends('layouts.app')

@section('title', __('ui.item_categories_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .sort-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
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
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input id="item-categories-search-input" type="text" name="search" placeholder="{{ __('ui.search_item_categories_placeholder') }}" value="{{ $search }}">
                    <button type="submit">{{ __('ui.search') }}</button>
                    @if($search !== '')
                        <a class="btn secondary" href="{{ route('item-categories.index') }}">{{ __('ui.reset') }}</a>
                    @endif
                </form>
            </div>
        </div>

        <div id="item-categories-results">
            @include('item_categories.partials.results')
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'item-categories-search-form',
                container: 'item-categories-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('item-categories-search-input'), () => ajax.submit(), 100);
        });
    </script>
@endsection

