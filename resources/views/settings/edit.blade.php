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
                    <h3 class="form-section-title">{{ __('ui.settings_company_profile') }}</h3>
                    <p class="form-section-note">{{ __('ui.settings_company_profile_note') }}</p>
                    <div class="row inline">
                        <div class="col-6">
                            <div class="form-section" style="margin-bottom: 0;">
                                <div class="row">
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_name') }}</label>
                                        <input type="text" name="company_name" value="{{ old('company_name', $companyName) }}">
                                    </div>
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_address') }}</label>
                                        <input type="text" name="company_address" value="{{ old('company_address', $companyAddress) }}">
                                    </div>
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_phone') }}</label>
                                        <input type="text" name="company_phone" value="{{ old('company_phone', $companyPhone) }}">
                                    </div>
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_email') }}</label>
                                        <input type="text" name="company_email" value="{{ old('company_email', $companyEmail) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-section" style="margin-bottom: 0;">
                                <div class="row">
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_notes') }}</label>
                                        <textarea name="company_notes" rows="3">{{ old('company_notes', $companyNotes) }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_invoice_notes') }}</label>
                                        <textarea name="company_invoice_notes" rows="6">{{ old('company_invoice_notes', $companyInvoiceNotes) }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_billing_note') }}</label>
                                        <textarea name="company_billing_note" rows="4">{{ old('company_billing_note', $companyBillingNote) }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_transfer_accounts') }}</label>
                                        <textarea name="company_transfer_accounts" rows="4">{{ old('company_transfer_accounts', $companyTransferAccounts) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
                            <textarea name="semester_period_options" rows="4" placeholder="S1-2026&#10;S2-2026">{{ old('semester_period_options', $semesterPeriodOptions) }}</textarea>
                        </div>
                        <div class="col-6">
                            <label>{{ __('ui.settings_semester_active_title') }}</label>
                            <p class="muted" style="margin: 0 0 8px 0;">{{ __('ui.settings_semester_active_note') }}</p>
                            @php
                                $activeSemesters = collect(old('semester_active_periods', $selectedActiveSemesters ?? []))
                                    ->map(fn ($item) => (string) $item)
                                    ->values();
                            @endphp
                            <div style="max-height: 180px; overflow-y: auto; border: 1px solid var(--border-color, #d0d7de); border-radius: 6px; padding: 8px 10px;">
                                @forelse(($semesterBookOptions ?? []) as $semesterOption)
                                    <label style="display: block; margin-bottom: 6px;">
                                        <input
                                            type="checkbox"
                                            name="semester_active_periods[]"
                                            value="{{ $semesterOption }}"
                                            @checked($activeSemesters->contains((string) $semesterOption))
                                        >
                                        {{ $semesterOption }}
                                    </label>
                                @empty
                                    <span class="muted">{{ __('ui.no_semester_book_options') }}</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">{{ __('ui.settings_units_title') }}</h3>
                    <p class="form-section-note">{{ __('ui.settings_units_note') }}</p>
                    <div class="row">
                        <div class="col-6">
                            <label>{{ __('ui.settings_units_list') }}</label>
                            <textarea name="product_unit_options" rows="4" placeholder="exp|Exemplar">{{ old('product_unit_options', $unitOptions) }}</textarea>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <button class="btn" type="submit">{{ __('ui.save') }}</button>
    </form>

    @if($user->role === 'admin')
        <div class="card" style="margin-top: 12px;">
            <div class="form-section">
                <h3 class="form-section-title">{{ __('ui.semester_book_title') }}</h3>
                <p class="form-section-note">{{ __('ui.semester_book_note') }}</p>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('txn.semester_period') }}</th>
                        <th>{{ __('txn.status') }}</th>
                        <th>{{ __('ui.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse(($semesterBookOptions ?? []) as $semesterOption)
                        @php
                            $isClosed = collect($closedSemesters ?? [])->contains((string) $semesterOption);
                        @endphp
                        <tr>
                            <td>{{ $semesterOption }}</td>
                            <td>{{ $isClosed ? __('ui.semester_closed') : __('ui.semester_open') }}</td>
                            <td>
                                @if($isClosed)
                                    <form method="post" action="{{ route('settings.semester.open') }}">
                                        @csrf
                                        <input type="hidden" name="semester_period" value="{{ $semesterOption }}">
                                        <button type="submit" class="btn secondary">{{ __('ui.semester_open_button') }}</button>
                                    </form>
                                @else
                                    <form method="post" action="{{ route('settings.semester.close') }}">
                                        @csrf
                                        <input type="hidden" name="semester_period" value="{{ $semesterOption }}">
                                        <button type="submit" class="btn">{{ __('ui.semester_close_button') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="muted">{{ __('ui.no_semester_book_options') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
