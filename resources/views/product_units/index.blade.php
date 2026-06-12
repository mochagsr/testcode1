@extends('layouts.app')

@section('title', __('ui.product_units_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .sort-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
        .product-units-table {
            table-layout: fixed;
        }
        .product-units-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .product-units-toolbar .toolbar-left {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1 1 320px;
        }
        .product-units-toolbar .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0;
            width: 100%;
            max-width: 420px;
        }
        .product-units-toolbar .search-form input[type="text"] {
            width: 320px;
            max-width: min(320px, 100%);
            flex: 1 1 260px;
            min-width: 0;
        }
        .product-units-table-wrap {
            overflow-x: auto;
        }
        .product-units-table th.action-col,
        .product-units-table td.action-col {
            width: 150px;
        }
        .product-units-table .unit-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
        }
        .product-units-table .unit-actions form {
            margin: 0;
        }
        .product-units-table .unit-actions .btn {
            min-height: 30px;
            padding: 5px 10px;
            white-space: nowrap;
        }
        @media (max-width: 1280px) {
            .product-units-toolbar .toolbar-left {
                flex-basis: 100%;
            }
            .product-units-toolbar .search-form {
                width: 100%;
            }
            .product-units-toolbar .search-form input[type="text"] {
                width: min(100%, 280px);
                max-width: min(100%, 280px);
            }
        }
    </style>
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.product_units_title') }}</h1>
        <a class="btn" href="{{ route('product-units.create') }}">{{ __('ui.add_product_unit') }}</a>
    </div>

    <div class="card">
        <div class="product-units-toolbar">
            <div class="toolbar-left">
                <form id="product-units-search-form" method="get" class="search-form">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input id="product-units-search-input" type="text" name="search" placeholder="{{ __('ui.search_product_units_placeholder') }}" value="{{ $search }}">
                    <button type="submit">{{ __('ui.search') }}</button>
                    @if($search !== '')
                        <a class="btn secondary" href="{{ route('product-units.index') }}">{{ __('ui.reset') }}</a>
                    @endif
                </form>
            </div>
        </div>

        <div id="product-units-results">
            @include('product_units.partials.results')
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'product-units-search-form',
                container: 'product-units-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('product-units-search-input'), () => ajax.submit(), 100);
        });
    </script>
@endsection

