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
        <div id="users-results">
            @include('users.partials.results')
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ajax = window.PgposAutoSearch.initAjaxFilter({
                form: 'users-search-form',
                container: 'users-results',
            });
            if (!ajax) {
                return;
            }
            window.PgposAutoSearch.bindDebouncedSearch(document.getElementById('users-search-input'), () => ajax.submit(), 100);
        });
    </script>
@endsection

