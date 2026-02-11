@extends('layouts.app')

@section('title', __('ui.users_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.users_title') }}</h1>
        <a class="btn" href="{{ route('users.create') }}">{{ __('ui.add_user') }}</a>
    </div>

    <div class="card">
        <form id="users-search-form" method="get" class="flex">
            <input id="users-search-input" type="text" name="search" placeholder="{{ __('ui.search_users_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <button type="submit">{{ __('ui.search') }}</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>{{ __('ui.name') }}</th>
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
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ strtoupper($user->role) }}</td>
                    <td>{{ strtoupper($user->locale) }}</td>
                    <td>{{ $user->theme }}</td>
                    <td>{{ $user->finance_locked ? __('ui.yes') : __('ui.no') }}</td>
                    <td>
                        <div class="flex">
                            <a class="btn secondary" href="{{ route('users.edit', $user) }}">{{ __('ui.edit') }}</a>
                            <form method="post" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('{{ __('ui.confirm_delete_user') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ __('ui.no_users') }}</td></tr>
            @endforelse
            </tbody>
        </table>
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
