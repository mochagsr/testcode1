@extends('layouts.app')

@section('title', __('ui.suppliers_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .suppliers-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .suppliers-toolbar .toolbar-left {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .suppliers-toolbar .toolbar-left {
            flex: 1 1 320px;
        }
        .suppliers-toolbar .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0;
        }
        .suppliers-toolbar .search-form input[type="text"],
        .suppliers-toolbar .search-form {
            width: 100%;
            max-width: 420px;
        }
        .suppliers-toolbar .search-form input[type="text"] {
            flex: 1 1 260px;
            min-width: 0;
        }
        .suppliers-table-wrap {
            overflow-x: auto;
        }
        .suppliers-table {
            min-width: 980px;
        }
        @media (max-width: 1400px) {
            .suppliers-toolbar .toolbar-left {
                flex: 1 1 100%;
            }
        }
        @media (max-width: 1280px) {
            .suppliers-toolbar .search-form {
                width: 100%;
            }
            .suppliers-toolbar .search-form input[type="text"] {
                width: min(100%, 280px);
                max-width: min(100%, 280px);
            }
        }
        .sort-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .sort-link:hover { color: var(--primary, #2563eb); }
        .sort-mark { font-size: 11px; opacity: 0.65; }
    </style>
    @php
        $currentUser = auth()->user();
        $canCreateSuppliers = $currentUser?->canAccess('suppliers.create') ?? false;
    @endphp
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.suppliers_title') }}</h1>
        @if($canCreateSuppliers)
            <a class="btn" href="{{ route('suppliers.create') }}">{{ __('ui.add_supplier') }}</a>
        @endif
    </div>

    <div class="card">
        <div class="suppliers-toolbar">
            <div class="toolbar-left">
                <form id="suppliers-search-form" method="get" class="search-form">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input id="suppliers-search-input" type="text" name="search" value="{{ $search }}" placeholder="{{ __('ui.search_suppliers_placeholder') }}">
                    <button type="submit">{{ __('ui.search') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div id="suppliers-results">
            @include('suppliers.partials.results')
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'suppliers-search-form',
                container: 'suppliers-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('suppliers-search-input'), () => ajax.submit(), 100);
        });
    </script>
@endsection

