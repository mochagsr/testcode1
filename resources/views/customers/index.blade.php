@extends('layouts.app')

@section('title', __('ui.customers_title').' - PgPOS ERP')

@section('content')
    <div class="flex" style="justify-content: space-between; margin-bottom: 12px;">
        <h1 class="page-title" style="margin: 0;">{{ __('ui.customers_title') }}</h1>
        <a class="btn" href="{{ route('customers-web.create') }}">{{ __('ui.add_customer') }}</a>
    </div>

    <div class="card">
        <form method="get" class="flex">
            <input type="text" name="search" placeholder="{{ __('ui.search_customers_placeholder') }}" value="{{ $search }}" style="max-width: 320px;">
            <button type="submit">{{ __('ui.search') }}</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>{{ __('ui.name') }}</th>
                <th>{{ __('ui.level') }}</th>
                <th>{{ __('ui.phone') }}</th>
                <th>{{ __('ui.city') }}</th>
                <th>{{ __('ui.receivable') }}</th>
                <th>{{ __('ui.id_card') }}</th>
                <th>{{ __('ui.actions') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->level?->code ?: '-' }}</td>
                    <td>{{ $customer->phone ?: '-' }}</td>
                    <td>{{ $customer->city ?: '-' }}</td>
                    <td>Rp {{ number_format($customer->outstanding_receivable, 2) }}</td>
                    <td>
                        @if($customer->id_card_photo_path)
                            <a class="btn secondary" target="_blank" href="{{ asset('storage/'.$customer->id_card_photo_path) }}">{{ __('ui.view') }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        <div class="flex">
                            <a class="btn secondary" href="{{ route('customers-web.edit', $customer) }}">{{ __('ui.edit') }}</a>
                            <form method="post" action="{{ route('customers-web.destroy', $customer) }}" onsubmit="return confirm('{{ __('ui.confirm_delete_customer') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn">{{ __('ui.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ __('ui.no_customers') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 12px;">
            {{ $customers->links() }}
        </div>
    </div>
@endsection
