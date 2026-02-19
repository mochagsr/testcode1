<div class="card">
    <div class="form-section">
        <h3 class="form-section-title">{{ __('ui.user_account') }}</h3>
        <p class="form-section-note">{{ __('ui.user_account_note') }}</p>
        <div class="row">
            <div class="col-6">
                <label>{{ __('ui.name') }} <span class="label-required">*</span></label>
                <input type="text" name="name" value="{{ old('name', $user?->name) }}" required>
            </div>
            <div class="col-6">
                <label>{{ __('ui.email') }} <span class="label-required">*</span></label>
                <input type="email" name="email" value="{{ old('email', $user?->email) }}" required>
            </div>
            <div class="col-6">
                <label>{{ __('ui.password') }} @if(!$user)<span class="label-required">*</span>@endif @if($user) ({{ __('ui.new_password_optional') }}) @endif</label>
                <input type="password" name="password" @if(!$user) required @endif>
            </div>
        </div>
    </div>
    <div class="form-section">
        <h3 class="form-section-title">{{ __('ui.user_access') }}</h3>
        <p class="form-section-note">{{ __('ui.user_access_note') }}</p>
        <div class="row">
            <div class="col-3">
                <label>{{ __('ui.role') }} <span class="label-required">*</span></label>
                <select id="user-role-select" name="role" required>
                    <option value="admin" @selected(old('role', $user?->role ?? 'user') === 'admin')>Admin</option>
                    <option value="user" @selected(old('role', $user?->role ?? 'user') === 'user')>User</option>
                </select>
            </div>
            <div class="col-3">
                <label>{{ __('ui.language') }} <span class="label-required">*</span></label>
                <select name="locale" required>
                    <option value="id" @selected(old('locale', $user?->locale ?? 'id') === 'id')>ID</option>
                    <option value="en" @selected(old('locale', $user?->locale ?? 'id') === 'en')>EN</option>
                </select>
            </div>
            <div class="col-3">
                <label>{{ __('ui.theme') }} <span class="label-required">*</span></label>
                <select name="theme" required>
                    <option value="light" @selected(old('theme', $user?->theme ?? 'light') === 'light')>Light</option>
                    <option value="dark" @selected(old('theme', $user?->theme ?? 'light') === 'dark')>Dark</option>
                </select>
            </div>
            <div class="col-3">
                <label>{{ __('ui.finance_lock') }}</label>
                <select name="finance_locked">
                    <option value="0" @selected((string) old('finance_locked', (int) ($user?->finance_locked ?? false)) === '0')>{{ __('ui.no') }}</option>
                    <option value="1" @selected((string) old('finance_locked', (int) ($user?->finance_locked ?? false)) === '1')>{{ __('ui.yes') }}</option>
                </select>
            </div>
        </div>
        @php
            $selectedPermissions = collect(old('permissions', (array) ($user?->permissions ?? [])))
                ->map(fn ($permission) => strtolower((string) $permission))
                ->all();
        @endphp
        <div class="row" id="permission-grid-wrapper" style="{{ old('role', $user?->role ?? 'user') === 'admin' ? 'display:none;' : '' }}">
            <div class="col-12">
                <label>Hak Akses Detail</label>
                <div class="card" style="padding:10px;">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:8px;">
                        @foreach(($availablePermissions ?? []) as $permission)
                            <label style="display:flex; align-items:center; gap:8px; margin:0;">
                                <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $selectedPermissions, true))>
                                <span style="font-size:12px;">{{ $permission }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<button class="btn" type="submit">{{ __('ui.save') }}</button>
<a class="btn secondary" href="{{ route('users.index') }}">{{ __('ui.cancel') }}</a>

<script>
    (function () {
        const roleSelect = document.getElementById('user-role-select');
        const permissionGridWrapper = document.getElementById('permission-grid-wrapper');
        if (!roleSelect || !permissionGridWrapper) {
            return;
        }
        const syncPermissionVisibility = () => {
            permissionGridWrapper.style.display = roleSelect.value === 'admin' ? 'none' : '';
        };
        roleSelect.addEventListener('change', syncPermissionVisibility);
        syncPermissionVisibility();
    })();
</script>
