@extends('layouts.app')

@section('title', __('ui.users_title').' - '.config('app.name', 'Laravel'))

@section('content')
    <div class="page-header-actions">
        <h1 class="page-title">{{ __('ui.users_title') }}</h1>
        <div class="actions">
            <a class="btn" href="{{ route('users.create') }}">{{ __('ui.add_user') }}</a>
        </div>
    </div>

    <div class="card">
        <form id="users-search-form" method="get" class="filter-toolbar">
            <div class="filter-field">
                <label for="users-search-input">{{ __('ui.search') }}</label>
                <input id="users-search-input" type="text" name="search" placeholder="{{ __('ui.search_users_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            </div>
            <button type="submit">{{ __('ui.search') }}</button>
        </form>
    </div>

    <div class="card">
        <div class="table-mobile-scroll">
        <table class="mobile-stack-table">
            <thead>
            <tr>
                <th>{{ __('ui.name') }}</th>
                <th>{{ __('ui.username') }}</th>
                <th>{{ __('ui.email') }}</th>
                <th>{{ __('ui.role') }}</th>
                <th>{{ __('ui.language') }}</th>
                <th>{{ __('ui.theme') }}</th>
                <th>{{ __('ui.finance_lock') }}</th>
                <th>{{ __('ui.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td data-label="{{ __('ui.name') }}">{{ $user->name }}</td>
                    <td data-label="{{ __('ui.username') }}">{{ $user->username }}</td>
                    <td data-label="{{ __('ui.email') }}">{{ $user->email }}</td>
                    <td data-label="{{ __('ui.role') }}">{{ strtoupper($user->role) }}</td>
                    <td data-label="{{ __('ui.language') }}">{{ strtoupper($user->locale) }}</td>
                    <td data-label="{{ __('ui.theme') }}">{{ $user->theme }}</td>
                    <td data-label="{{ __('ui.finance_lock') }}">{{ $user->finance_locked ? __('ui.yes') : __('ui.no') }}</td>
                    <td data-label="{{ __('ui.actions') }}" class="action">
                        <div class="flex">
                            <a class="btn edit-btn" href="{{ route('users.edit', $user) }}">{{ __('ui.edit') }}</a>
                            <form method="post" action="{{ route('users.destroy', $user) }}" data-confirm-modal data-confirm-message="{{ __('ui.confirm_delete_user') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn danger-btn">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">{{ __('ui.no_users') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div style="margin-top:12px;">
            {{ $users->links() }}
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('users-search-form');
            const searchInput = document.getElementById('users-search-input');
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

