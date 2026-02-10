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
                <select name="role" required>
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
    </div>
</div>

<button class="btn" type="submit">{{ __('ui.save') }}</button>
<a class="btn secondary" href="{{ route('users.index') }}">{{ __('ui.cancel') }}</a>
