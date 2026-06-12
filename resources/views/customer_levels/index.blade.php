@extends('layouts.app')

@section('title', __('ui.customer_levels_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
        .customer-levels-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .customer-levels-toolbar .toolbar-left {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1 1 320px;
        }
        .customer-levels-toolbar .toolbar-left input[type="text"] {
            width: 320px;
            max-width: min(320px, 100%);
            flex: 1 1 260px;
            min-width: 0;
        }
        .customer-levels-table-wrap {
            overflow-x: auto;
        }
        .customer-levels-table {
            min-width: 640px;
        }
        @media (max-width: 1280px) {
            .customer-levels-toolbar .toolbar-left {
                width: 100%;
            }
            .customer-levels-toolbar .toolbar-left input[type="text"] {
                width: min(100%, 280px);
                max-width: min(100%, 280px);
            }
        }
    </style>
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.customer_levels_title') }}</h1>
        <a class="btn" href="{{ route('customer-levels-web.create') }}">{{ __('ui.add_customer_level') }}</a>
    </div>

    <div class="card">
        <div class="customer-levels-toolbar">
            <form id="customer-levels-search-form" method="get" class="toolbar-left">
                <input id="customer-levels-search-input" type="text" name="search" placeholder="{{ __('ui.search_customer_levels_placeholder') }}" value="{{ $search }}">
                <button type="submit">{{ __('ui.search') }}</button>
            </form>
        </div>
    </div>

    <div id="customer-levels-results">
        @include('customer_levels.partials.results')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'customer-levels-search-form',
                container: 'customer-levels-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('customer-levels-search-input'), () => ajax.submit(), 100);
        });
    </script>
@endsection

