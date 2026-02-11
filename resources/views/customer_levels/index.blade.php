@extends('layouts.app')

@section('title', __('ui.customer_levels_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.customer_levels_title') }}</h1>
        <a class="btn" href="{{ route('customer-levels-web.create') }}">{{ __('ui.add_customer_level') }}</a>
    </div>

    <div class="card">
        <form id="customer-levels-search-form" method="get" class="flex">
            <input id="customer-levels-search-input" type="text" name="search" placeholder="{{ __('ui.search_customer_levels_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <button type="submit">{{ __('ui.search') }}</button>
        </form>
    </div>

    <div class="card">
        <table>
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
                            <a class="btn secondary" href="{{ route('customer-levels-web.edit', $level) }}">{{ __('ui.edit') }}</a>
                            <form method="post" action="{{ route('customer-levels-web.destroy', $level) }}" onsubmit="return confirm('{{ __('ui.confirm_delete_level') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">{{ __('ui.no_customer_levels') }}</td></tr>
            @endforelse
            </tbody>
        </table>
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

            let debounceTimer = null;
            searchInput.addEventListener('input', () => {
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }
                debounceTimer = setTimeout(() => {
                    form.requestSubmit();
                }, 100);
            });
        })();
    </script>
@endsection
