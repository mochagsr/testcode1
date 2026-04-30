@extends('layouts.app')

@section('title', __('ui.product_units_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <style>
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
                    <input id="product-units-search-input" type="text" name="search" placeholder="{{ __('ui.search_product_units_placeholder') }}" value="{{ $search }}">
                    <button type="submit">{{ __('ui.search') }}</button>
                    @if($search !== '')
                        <a class="btn secondary" href="{{ route('product-units.index') }}">{{ __('ui.reset') }}</a>
                    @endif
                </form>
            </div>
        </div>

        <div style="margin-top: 12px;" class="product-units-table-wrap">
            <table class="product-units-table">
                <thead>
                <tr>
                    <th style="width: 18%;">{{ __('ui.code') }}</th>
                    <th style="width: 28%;">{{ __('ui.name') }}</th>
                    <th>{{ __('ui.description') }}</th>
                    <th class="action-col">{{ __('ui.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($units as $unit)
                    <tr>
                        <td>{{ $unit->code }}</td>
                        <td>{{ $unit->name }}</td>
                        <td>{{ $unit->description ?: '-' }}</td>
                        <td class="action-col">
                            <div class="unit-actions">
                                <a class="btn edit-btn" href="{{ route('product-units.edit', $unit) }}">{{ __('ui.edit') }}</a>
                                <form method="post" action="{{ route('product-units.destroy', $unit) }}" data-confirm-modal data-confirm-message="{{ __('ui.confirm_delete_product_unit') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('ui.no_product_units') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 12px;">
            {{ $units->links() }}
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('product-units-search-form');
            const searchInput = document.getElementById('product-units-search-input');
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

