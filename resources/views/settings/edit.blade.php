@extends('layouts.app')

@section('title', __('ui.settings_title').' - PgPOS ERP')

@section('content')
    <style>
        .settings-top-inline {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 10px;
        }
        .settings-top-inline > .col-6 {
            grid-column: span 6;
        }
        @media (max-width: 640px) {
            .settings-top-inline > .col-6 {
                grid-column: span 12;
            }
        }
    </style>

    <h1 class="page-title">{{ __('menu.settings') }}</h1>

    <form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="settings-top-inline">
                <div class="col-6">
                    <div class="form-section" style="margin-bottom: 0;">
                        <h3 class="form-section-title">{{ __('ui.profile') }}</h3>
                        <p class="form-section-note">{{ __('ui.settings_profile_note') }}</p>
                        <div class="row">
                            <div class="col-12">
                                <label>{{ __('ui.name') }} <span class="label-required">*</span></label>
                                <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                            </div>
                            <div class="col-12">
                                <label>{{ __('ui.email') }}</label>
                                <input type="email" value="{{ $user->email }}" disabled>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-section" style="margin-bottom: 0;">
                        <h3 class="form-section-title">{{ __('ui.preferences') }}</h3>
                        <p class="form-section-note">{{ __('ui.settings_preferences_note') }}</p>
                        <div class="row">
                            <div class="col-6">
                                <label>{{ __('ui.language') }} <span class="label-required">*</span></label>
                                <select name="locale" required>
                                    <option value="id" @selected(old('locale', $user->locale) === 'id')>Indonesia</option>
                                    <option value="en" @selected(old('locale', $user->locale) === 'en')>English</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label>{{ __('ui.theme') }} <span class="label-required">*</span></label>
                                <select name="theme" required>
                                    <option value="light" @selected(old('theme', $user->theme) === 'light')>Light</option>
                                    <option value="dark" @selected(old('theme', $user->theme) === 'dark')>Dark</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label>{{ __('ui.new_password_optional') }}</label>
                                <input type="password" name="password">
                            </div>
                        </div>
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
                                        <label>{{ __('ui.settings_company_invoice_notes') }}</label>
                                        <textarea name="company_invoice_notes" rows="12">{{ old('company_invoice_notes', $companyInvoiceNotes) }}</textarea>
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
                    @php
                        $semesterRows = collect(old('semester_period_codes', $semesterBookOptions ?? []))
                            ->map(fn ($item): string => trim((string) $item))
                            ->filter(fn (string $item): bool => $item !== '')
                            ->values();
                        if ($semesterRows->isEmpty()) {
                            $semesterRows = collect($semesterBookOptions ?? []);
                        }
                        $activeSemesters = collect(old('semester_active_period_codes', $selectedActiveSemesters ?? []))
                            ->map(fn ($item) => (string) $item)
                            ->values();
                    @endphp
                    <label>{{ __('ui.settings_semester_list') }} / {{ __('ui.active') }}</label>
                    <p class="muted" style="margin: 0 0 8px 0;">{{ __('ui.settings_semester_active_note') }}</p>
                    <table id="semester-codes-table">
                        <thead>
                        <tr>
                            <th>{{ __('txn.semester_period') }}</th>
                            <th style="width: 140px;">{{ __('ui.active') }}</th>
                            <th style="width: 110px;">{{ __('txn.action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($semesterRows as $semesterRow)
                            <tr>
                                <td>
                                    <input type="text" name="semester_period_codes[]" value="{{ $semesterRow }}" placeholder="S1-2526" class="semester-code-input">
                                </td>
                                <td>
                                    <label style="display: inline-flex; align-items: center; gap: 6px;">
                                        <input
                                            type="checkbox"
                                            name="semester_active_period_codes[]"
                                            value="{{ $semesterRow }}"
                                            class="semester-active-checkbox"
                                            @checked($activeSemesters->contains((string) $semesterRow))
                                        >
                                        {{ __('ui.active') }}
                                    </label>
                                </td>
                                <td>
                                    <button type="button" class="btn secondary remove-row">{{ __('txn.remove') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td>
                                    <input type="text" name="semester_period_codes[]" value="" placeholder="S1-2526" class="semester-code-input">
                                </td>
                                <td>
                                    <label style="display: inline-flex; align-items: center; gap: 6px;">
                                        <input
                                            type="checkbox"
                                            name="semester_active_period_codes[]"
                                            value=""
                                            class="semester-active-checkbox"
                                            checked
                                        >
                                        {{ __('ui.active') }}
                                    </label>
                                </td>
                                <td><button type="button" class="btn secondary remove-row">{{ __('txn.remove') }}</button></td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                    <button type="button" id="add-semester-row" class="btn secondary" style="margin-top: 8px;">{{ __('txn.add_row') }}</button>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">{{ __('ui.settings_units_title') }}</h3>
                    <p class="form-section-note">{{ __('ui.settings_units_note') }}</p>
                    <div class="row inline">
                        <div class="col-6">
                            <label>{{ __('ui.settings_units_list') }} ({{ __('txn.sales_invoice') }})</label>
                            @php
                                $productCodes = collect(old('product_unit_codes', $unitOptionRows->pluck('code')->all()))->values();
                                $productLabels = collect(old('product_unit_labels', $unitOptionRows->pluck('label')->all()))->values();
                            @endphp
                            <table id="product-units-table">
                                <thead>
                                <tr>
                                    <th style="width: 35%;">{{ __('txn.code') }}</th>
                                    <th>{{ __('txn.name') }}</th>
                                    <th style="width: 90px;">{{ __('txn.action') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @for($i = 0; $i < max($productCodes->count(), 1); $i++)
                                    <tr>
                                        <td>
                                            <input type="text" name="product_unit_codes[]" value="{{ $productCodes[$i] ?? '' }}" list="product-unit-code-suggestions" placeholder="exp">
                                        </td>
                                        <td>
                                            <input type="text" name="product_unit_labels[]" value="{{ $productLabels[$i] ?? '' }}" placeholder="Exemplar">
                                        </td>
                                        <td>
                                            <button type="button" class="btn secondary remove-row">{{ __('txn.remove') }}</button>
                                        </td>
                                    </tr>
                                @endfor
                                </tbody>
                            </table>
                            <button type="button" id="add-product-unit-row" class="btn secondary" style="margin-top: 8px;">{{ __('txn.add_row') }}</button>
                        </div>
                        <div class="col-6">
                            <label>{{ __('ui.settings_units_list') }} ({{ __('txn.outgoing_transactions_title') }})</label>
                            @php
                                $outgoingCodes = collect(old('outgoing_unit_codes', $outgoingUnitOptionRows->pluck('code')->all()))->values();
                                $outgoingLabels = collect(old('outgoing_unit_labels', $outgoingUnitOptionRows->pluck('label')->all()))->values();
                            @endphp
                            <table id="outgoing-units-table">
                                <thead>
                                <tr>
                                    <th style="width: 35%;">{{ __('txn.code') }}</th>
                                    <th>{{ __('txn.name') }}</th>
                                    <th style="width: 90px;">{{ __('txn.action') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @for($i = 0; $i < max($outgoingCodes->count(), 1); $i++)
                                    <tr>
                                        <td>
                                            <input type="text" name="outgoing_unit_codes[]" value="{{ $outgoingCodes[$i] ?? '' }}" list="outgoing-unit-code-suggestions" placeholder="exp">
                                        </td>
                                        <td>
                                            <input type="text" name="outgoing_unit_labels[]" value="{{ $outgoingLabels[$i] ?? '' }}" placeholder="Exemplar">
                                        </td>
                                        <td>
                                            <button type="button" class="btn secondary remove-row">{{ __('txn.remove') }}</button>
                                        </td>
                                    </tr>
                                @endfor
                                </tbody>
                            </table>
                            <button type="button" id="add-outgoing-unit-row" class="btn secondary" style="margin-top: 8px;">{{ __('txn.add_row') }}</button>
                        </div>
                    </div>
                    <datalist id="product-unit-code-suggestions">
                        @foreach($unitCodeSuggestions as $codeSuggestion)
                            <option value="{{ $codeSuggestion }}"></option>
                        @endforeach
                    </datalist>
                    <datalist id="outgoing-unit-code-suggestions">
                        @foreach($outgoingUnitCodeSuggestions as $codeSuggestion)
                            <option value="{{ $codeSuggestion }}"></option>
                        @endforeach
                    </datalist>
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
                    @forelse(($semesterBookPaginator ?? collect()) as $semesterOption)
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
                                        <input type="hidden" name="semester_book_page" value="{{ ($semesterBookPaginator ?? null)?->currentPage() ?? 1 }}">
                                        <button type="submit" class="btn secondary">{{ __('ui.semester_open_button') }}</button>
                                    </form>
                                @else
                                    <form method="post" action="{{ route('settings.semester.close') }}">
                                        @csrf
                                        <input type="hidden" name="semester_period" value="{{ $semesterOption }}">
                                        <input type="hidden" name="semester_book_page" value="{{ ($semesterBookPaginator ?? null)?->currentPage() ?? 1 }}">
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
                @if(isset($semesterBookPaginator) && $semesterBookPaginator->lastPage() > 1)
                    <div style="margin-top: 10px;">
                        {{ $semesterBookPaginator->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <script>
        (function () {
            function bindRemoveButtons(tableId, minRows = 1) {
                const table = document.getElementById(tableId);
                const tbody = table?.querySelector('tbody');
                if (!table || !tbody) {
                    return;
                }
                tbody.querySelectorAll('.remove-row').forEach((button) => {
                    button.addEventListener('click', () => {
                        const rows = tbody.querySelectorAll('tr');
                        if (rows.length <= minRows) {
                            const inputs = rows[0]?.querySelectorAll('input');
                            inputs?.forEach((input) => { input.value = ''; });
                            return;
                        }
                        button.closest('tr')?.remove();
                    });
                });
            }

            function addRow(tableId, html) {
                const table = document.getElementById(tableId);
                const tbody = table?.querySelector('tbody');
                if (!tbody) {
                    return;
                }
                const row = document.createElement('tr');
                row.innerHTML = html;
                tbody.appendChild(row);
                bindRemoveButtons(tableId);
                bindSemesterRowSync();
            }

            function bindSemesterRowSync() {
                const table = document.getElementById('semester-codes-table');
                const rows = table?.querySelectorAll('tbody tr') ?? [];
                rows.forEach((row) => {
                    const codeInput = row.querySelector('.semester-code-input');
                    const activeCheckbox = row.querySelector('.semester-active-checkbox');
                    if (!codeInput || !activeCheckbox || codeInput.dataset.bound === '1') {
                        return;
                    }
                    const syncValue = () => {
                        activeCheckbox.value = codeInput.value.trim();
                    };
                    syncValue();
                    codeInput.addEventListener('input', syncValue);
                    codeInput.dataset.bound = '1';
                });
            }

            document.getElementById('add-semester-row')?.addEventListener('click', () => {
                addRow('semester-codes-table', `
                    <td><input type="text" name="semester_period_codes[]" value="" placeholder="S1-2526" class="semester-code-input"></td>
                    <td>
                        <label style="display: inline-flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="semester_active_period_codes[]" value="" class="semester-active-checkbox" checked>
                            {{ __('ui.active') }}
                        </label>
                    </td>
                    <td><button type="button" class="btn secondary remove-row">{{ __('txn.remove') }}</button></td>
                `);
            });

            document.getElementById('add-product-unit-row')?.addEventListener('click', () => {
                addRow('product-units-table', `
                    <td><input type="text" name="product_unit_codes[]" value="" list="product-unit-code-suggestions" placeholder="exp"></td>
                    <td><input type="text" name="product_unit_labels[]" value="" placeholder="Exemplar"></td>
                    <td><button type="button" class="btn secondary remove-row">{{ __('txn.remove') }}</button></td>
                `);
            });

            document.getElementById('add-outgoing-unit-row')?.addEventListener('click', () => {
                addRow('outgoing-units-table', `
                    <td><input type="text" name="outgoing_unit_codes[]" value="" list="outgoing-unit-code-suggestions" placeholder="exp"></td>
                    <td><input type="text" name="outgoing_unit_labels[]" value="" placeholder="Exemplar"></td>
                    <td><button type="button" class="btn secondary remove-row">{{ __('txn.remove') }}</button></td>
                `);
            });

            bindRemoveButtons('semester-codes-table');
            bindSemesterRowSync();
            bindRemoveButtons('product-units-table');
            bindRemoveButtons('outgoing-units-table');
        })();
    </script>
@endsection
