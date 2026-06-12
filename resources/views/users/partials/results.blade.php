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
