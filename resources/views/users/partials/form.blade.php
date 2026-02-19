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
            $permissionGroups = collect($availablePermissions ?? [])
                ->groupBy(function (string $permission): string {
                    $prefix = explode('.', $permission)[0] ?? 'other';
                    return strtoupper(str_replace('_', ' ', $prefix));
                })
                ->sortKeys();
        @endphp
        <div class="row" id="permission-grid-wrapper" style="{{ old('role', $user?->role ?? 'user') === 'admin' ? 'display:none;' : '' }}">
            <div class="col-12">
                <label>Hak Akses Detail</label>
                <div class="card" style="padding:10px;">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:10px;">
                        @foreach($permissionGroups as $groupLabel => $permissions)
                            @php
                                $groupKey = strtolower((string) preg_replace('/[^a-z0-9]+/', '-', (string) $groupLabel));
                            @endphp
                            <div class="form-section" style="margin:0;">
                                <label style="display:flex; align-items:center; gap:8px; margin:0 0 6px 0;">
                                    <input type="checkbox" class="permission-group-toggle" data-group="{{ $groupKey }}">
                                    <strong style="font-size:12px;">{{ $groupLabel }}</strong>
                                </label>
                                @foreach($permissions as $permission)
                                    <label style="display:flex; align-items:center; gap:8px; margin:0 0 4px 0;">
                                        <input type="checkbox" class="permission-checkbox permission-group-{{ $groupKey }}" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $selectedPermissions, true))>
                                        <span style="font-size:12px;">{{ $permission }}</span>
                                    </label>
                                @endforeach
                            </div>
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

        const groupToggles = document.querySelectorAll('.permission-group-toggle');
        groupToggles.forEach((toggle) => {
            toggle.addEventListener('change', () => {
                const groupName = toggle.getAttribute('data-group');
                if (!groupName) {
                    return;
                }
                const checkboxes = document.querySelectorAll('.permission-group-' + CSS.escape(groupName));
                checkboxes.forEach((cb) => {
                    cb.checked = toggle.checked;
                });
            });
        });
    })();
</script>
