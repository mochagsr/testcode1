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
                        <form method="post" action="{{ route('customer-levels-web.destroy', $level) }}" data-confirm-modal data-confirm-message="{{ __('ui.confirm_delete_level') }}">
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
