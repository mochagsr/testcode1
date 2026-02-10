@extends('layouts.app')

@section('title', __('ui.settings_title').' - PgPOS ERP')

@section('content')
    <h1 class="page-title">{{ __('menu.settings') }}</h1>

    <form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('ui.profile') }}</h3>
                <p class="form-section-note">{{ __('ui.settings_profile_note') }}</p>
                <div class="row">
                    <div class="col-6">
                        <label>{{ __('ui.name') }} <span class="label-required">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="col-6">
                        <label>{{ __('ui.email') }}</label>
                        <input type="email" value="{{ $user->email }}" disabled>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">{{ __('ui.preferences') }}</h3>
                <p class="form-section-note">{{ __('ui.settings_preferences_note') }}</p>
                <div class="row">
                    <div class="col-4">
                        <label>{{ __('ui.language') }} <span class="label-required">*</span></label>
                        <select name="locale" required>
                            <option value="id" @selected(old('locale', $user->locale) === 'id')>Indonesia</option>
                            <option value="en" @selected(old('locale', $user->locale) === 'en')>English</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.theme') }} <span class="label-required">*</span></label>
                        <select name="theme" required>
                            <option value="light" @selected(old('theme', $user->theme) === 'light')>Light</option>
                            <option value="dark" @selected(old('theme', $user->theme) === 'dark')>Dark</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label>{{ __('ui.new_password_optional') }}</label>
                        <input type="password" name="password">
                    </div>
                </div>
            </div>

            @if($user->role === 'admin')
                <div class="form-section">
                    <h3 class="form-section-title">{{ __('ui.settings_company_logo') }}</h3>
                    <p class="form-section-note">{{ __('ui.settings_company_logo_note') }}</p>
                    <div class="row">
                        <div class="col-6">
                            <label>{{ __('ui.settings_upload_logo') }}</label>
                            <input type="file" name="company_logo" accept="image/*">
                            @if($companyLogoPath)
                                <p style="margin-top: 8px;">
                                    {{ __('ui.settings_current_logo') }}
                                    <a href="{{ asset('storage/' . $companyLogoPath) }}" target="_blank">{{ __('ui.settings_view_logo') }}</a>
                                </p>
                                <label style="margin-top: 8px;">
                                    <input type="checkbox" name="remove_company_logo" value="1">
                                    {{ __('ui.settings_remove_logo') }}
                                </label>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">{{ __('ui.settings_semester_title') }}</h3>
                    <p class="form-section-note">{{ __('ui.settings_semester_note') }}</p>
                    <div class="row">
                        <div class="col-6">
                            <label>{{ __('ui.settings_semester_list') }}</label>
                            <textarea name="semester_period_options" rows="3" placeholder="S1-2026, S2-2026">{{ old('semester_period_options', $semesterPeriodOptions) }}</textarea>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <button class="btn" type="submit">{{ __('ui.save') }}</button>
    </form>
@endsection
