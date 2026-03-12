@extends('layouts.app')

@section('title', __('ui.customer_levels_title').' - PgPOS ERP')

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

    <div class="card">
        <div class="customer-levels-table-wrap">
        <table class="customer-levels-table">
            <thead>
            <tr>
                <th>{{ __('ui.code') }}</th>
                <th>{{ __('ui.description') }}</th>
                <th>{{ __('ui.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($levels as $level)
                <tr>
                    <td>{{ $level->code }}</td>
                    <td>{{ $level->description ?: '-' }}</td>
                    <td>
                        <div class="flex">
                            <a class="btn edit-btn" href="{{ route('customer-levels-web.edit', $level) }}">{{ __('ui.edit') }}</a>
                            <form method="post" action="{{ route('customer-levels-web.destroy', $level) }}" onsubmit="return confirm('{{ __('ui.confirm_delete_level') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">{{ __('ui.no_customer_levels') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div style="margin-top: 12px;">
            {{ $levels->links() }}
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('customer-levels-search-form');
            const searchInput = document.getElementById('customer-levels-search-input');
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
