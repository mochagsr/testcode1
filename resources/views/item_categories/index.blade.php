@extends('layouts.app')

@section('title', __('ui.item_categories_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.item_categories_title') }}</h1>
        <a class="btn" href="{{ route('item-categories.create') }}">{{ __('ui.add_category') }}</a>
    </div>

    <div class="card">
        <form method="get" class="flex">
            <input type="text" name="search" placeholder="{{ __('ui.search_item_categories_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
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
            @forelse($categories as $category)
                <tr>
                    <td>{{ $category->code }}</td>
                    <td>{{ $category->description ?: '-' }}</td>
                    <td>
                        <div class="flex">
                            <a class="btn secondary" href="{{ route('item-categories.edit', $category) }}">{{ __('ui.edit') }}</a>
                            <form method="post" action="{{ route('item-categories.destroy', $category) }}" onsubmit="return confirm('{{ __('ui.confirm_delete_category') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">{{ __('ui.no_item_categories') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $categories->links() }}
        </div>
    </div>
@endsection
