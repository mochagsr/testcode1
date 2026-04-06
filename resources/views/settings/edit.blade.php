@extends('layouts.app')

@section('title', __('ui.settings_title').' - PgPOS ERP')

@section('content')
    @php
        $isEnglishSettings = app()->getLocale() === 'en';
        $settingsHints = [
            'profile_title' => $isEnglishSettings ? 'Manage the name shown on receipts and your account profile.' : 'Atur nama pengguna yang tampil di nota dan profil akun Anda.',
            'profile_name' => $isEnglishSettings ? 'This name is shown on receipts and several account displays.' : 'Nama ini tampil di nota dan beberapa tampilan akun.',
            'profile_email' => $isEnglishSettings ? 'This email is used for login and is managed from user data.' : 'Email ini dipakai untuk login dan diatur dari data pengguna.',
            'preferences_title' => $isEnglishSettings ? 'Choose the interface language, theme, and password for this account.' : 'Pilih bahasa, tema, dan password untuk akun yang sedang dipakai.',
            'preferences_language' => $isEnglishSettings ? 'Change the interface language for this account only.' : 'Ubah bahasa tampilan untuk akun yang sedang login saja.',
            'preferences_theme' => $isEnglishSettings ? 'Choose light or dark appearance for this account.' : 'Pilih tampilan terang atau gelap untuk akun ini.',
            'preferences_password' => $isEnglishSettings ? 'Leave blank if you do not want to change the current password.' : 'Kosongkan jika tidak ingin mengganti password saat ini.',
            'company_profile_title' => $isEnglishSettings ? 'Company identity used on printed documents and reports.' : 'Identitas perusahaan yang dipakai di header cetak dokumen dan laporan.',
            'company_name' => $isEnglishSettings ? 'Shown in document and report headers.' : 'Nama perusahaan yang tampil di header dokumen dan laporan.',
            'company_address' => $isEnglishSettings ? 'Shown in printed headers and invoices.' : 'Alamat perusahaan yang tampil di header cetak dan invoice.',
            'company_phone' => $isEnglishSettings ? 'Phone number shown on printed documents when needed.' : 'Nomor telepon perusahaan yang bisa tampil di dokumen cetak.',
            'company_email' => $isEnglishSettings ? 'Email shown on printed documents when needed.' : 'Email perusahaan yang bisa tampil di dokumen cetak.',
            'company_invoice_notes' => $isEnglishSettings ? 'Default notes printed on customer invoices.' : 'Catatan default yang tampil di invoice customer.',
            'report_header_text' => $isEnglishSettings ? 'Optional custom header text for operational reports.' : 'Teks header tambahan untuk report operasional jika diperlukan.',
            'company_logo_title' => $isEnglishSettings ? 'Upload the company logo used in supported documents and reports.' : 'Upload logo perusahaan yang dipakai di dokumen dan report yang mendukung logo.',
            'company_logo_upload' => $isEnglishSettings ? 'Accepted image file for the active company logo.' : 'File gambar yang akan dipakai sebagai logo perusahaan aktif.',
            'print_workflow_title' => $isEnglishSettings ? 'Configure the default printing behavior for the team.' : 'Atur perilaku cetak default yang dipakai tim saat print dokumen.',
            'print_mode' => $isEnglishSettings ? 'Choose browser printing or QZ Tray integration.' : 'Pilih print lewat browser atau integrasi QZ Tray.',
            'print_paper_preset' => $isEnglishSettings ? 'Default paper size preset used by print actions.' : 'Preset ukuran kertas default yang dipakai saat print.',
            'print_small_rows_threshold' => $isEnglishSettings ? 'Used to decide when the compact print layout should be used.' : 'Dipakai untuk menentukan kapan layout print kecil digunakan.',
            'semester_title' => $isEnglishSettings ? 'Manage semester options, active flags, and open/closed status.' : 'Kelola daftar semester, status aktif, dan status buka/tutup semester.',
            'semester_list' => $isEnglishSettings ? 'Only open semesters can appear in transaction dropdowns.' : 'Hanya semester yang masih terbuka yang bisa tampil di dropdown transaksi.',
            'semester_active' => $isEnglishSettings ? 'Use this flag to allow a semester in transaction dropdowns.' : 'Centang ini jika semester boleh muncul di dropdown transaksi.',
            'semester_action' => $isEnglishSettings ? 'Close a semester to prevent new transactions from using it.' : 'Tutup semester jika ingin menghentikan transaksi baru pada periode itu.',
            'units_title' => $isEnglishSettings ? 'Manage unit options shown in sales and supplier goods receipt forms.' : 'Kelola daftar satuan yang tampil di form penjualan dan tanda terima barang.',
            'product_units' => $isEnglishSettings ? 'Units shown in Sales Invoice item rows.' : 'Satuan yang tampil di baris item Faktur Penjualan.',
            'outgoing_units' => $isEnglishSettings ? 'Units shown in Supplier Goods Receipt item rows.' : 'Satuan yang tampil di baris item Tanda Terima Barang.',
        ];
        $settingsHintIcon = static fn (string $text): string => '<span class="settings-help" tabindex="0" title="'.e($text).'" aria-label="'.e($text).'">?</span>';
    @endphp
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
        #semester-codes-table .semester-code-input {
            width: 110px;
            max-width: 110px;
            min-width: 110px;
            padding: 7px 8px;
        }
        #semester-codes-table {
            table-layout: fixed;
        }
        #semester-codes-table th,
        #semester-codes-table td {
            padding: 6px 8px;
            vertical-align: middle;
        }
        #semester-codes-table th:nth-child(1),
        #semester-codes-table td:nth-child(1) {
            width: 150px;
        }
        #semester-codes-table th:nth-child(2),
        #semester-codes-table td:nth-child(2),
        #semester-codes-table th:nth-child(3),
        #semester-codes-table td:nth-child(3),
        #semester-codes-table th:nth-child(4),
        #semester-codes-table td:nth-child(4),
        #semester-codes-table th:nth-child(5),
        #semester-codes-table td:nth-child(5) {
            width: 110px;
            text-align: center;
        }
        #semester-codes-table th:nth-child(6),
        #semester-codes-table td:nth-child(6) {
            width: 190px;
            text-align: center;
        }
        #semester-codes-table td .flex {
            justify-content: center;
            gap: 6px;
        }
        #semester-codes-table td .btn {
            min-height: 32px;
            padding: 7px 10px;
        }
        .settings-help {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            margin-left: 6px;
            border-radius: 999px;
            border: 1px solid var(--border);
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            cursor: help;
            background: color-mix(in srgb, var(--card) 92%, var(--bg));
            vertical-align: middle;
        }
        .settings-help:focus {
            outline: 2px solid color-mix(in srgb, var(--accent) 55%, transparent);
            outline-offset: 2px;
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
                        <h3 class="form-section-title">{{ __('ui.profile') }} {!! $settingsHintIcon($settingsHints['profile_title']) !!}</h3>
                        <p class="form-section-note">{{ __('ui.settings_profile_note') }}</p>
                        <div class="row">
                            <div class="col-12">
                                <label>{{ __('ui.name') }} {!! $settingsHintIcon($settingsHints['profile_name']) !!} <span class="label-required">*</span></label>
                                <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                            </div>
                            <div class="col-12">
                                <label>{{ __('ui.email') }} {!! $settingsHintIcon($settingsHints['profile_email']) !!}</label>
                                <input type="email" value="{{ $user->email }}" disabled>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="form-section" style="margin-bottom: 0;">
                        <h3 class="form-section-title">{{ __('ui.preferences') }} {!! $settingsHintIcon($settingsHints['preferences_title']) !!}</h3>
                        <p class="form-section-note">{{ __('ui.settings_preferences_note') }}</p>
                        <div class="row">
                            <div class="col-6">
                                <label>{{ __('ui.language') }} {!! $settingsHintIcon($settingsHints['preferences_language']) !!} <span class="label-required">*</span></label>
                                <select name="locale" required>
                                    <option value="id" @selected(old('locale', $user->locale) === 'id')>Indonesia</option>
                                    <option value="en" @selected(old('locale', $user->locale) === 'en')>English</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label>{{ __('ui.theme') }} {!! $settingsHintIcon($settingsHints['preferences_theme']) !!} <span class="label-required">*</span></label>
                                <select name="theme" required>
                                    <option value="light" @selected(old('theme', $user->theme) === 'light')>Light</option>
                                    <option value="dark" @selected(old('theme', $user->theme) === 'dark')>Dark</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label>{{ __('ui.new_password_optional') }} {!! $settingsHintIcon($settingsHints['preferences_password']) !!}</label>
                                <input type="password" name="password">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($user->role === 'admin')
                <div class="form-section">
                    <h3 class="form-section-title">{{ __('ui.settings_company_profile') }} {!! $settingsHintIcon($settingsHints['company_profile_title']) !!}</h3>
                    <p class="form-section-note">{{ __('ui.settings_company_profile_note') }}</p>
                    <div class="row inline">
                        <div class="col-6">
                            <div class="form-section" style="margin-bottom: 0;">
                                <div class="row">
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_name') }} {!! $settingsHintIcon($settingsHints['company_name']) !!}</label>
                                        <input type="text" name="company_name" value="{{ old('company_name', $companyName) }}">
                                    </div>
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_address') }} {!! $settingsHintIcon($settingsHints['company_address']) !!}</label>
                                        <textarea name="company_address" rows="3" style="min-height: 86px;">{{ old('company_address', $companyAddress) }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_phone') }} {!! $settingsHintIcon($settingsHints['company_phone']) !!}</label>
                                        <input type="text" name="company_phone" value="{{ old('company_phone', $companyPhone) }}">
                                    </div>
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_email') }} {!! $settingsHintIcon($settingsHints['company_email']) !!}</label>
                                        <input type="text" name="company_email" value="{{ old('company_email', $companyEmail) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-section" style="margin-bottom: 0;">
                                <div class="row">
                                    <div class="col-12">
                                        <label>{{ __('ui.settings_company_invoice_notes') }} {!! $settingsHintIcon($settingsHints['company_invoice_notes']) !!}</label>
                                        <textarea name="company_invoice_notes" rows="12">{{ old('company_invoice_notes', $companyInvoiceNotes) }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <label>Template Header Report {!! $settingsHintIcon($settingsHints['report_header_text']) !!}</label>
                                        <textarea name="report_header_text" rows="3" placeholder="Contoh: FAKTUR RESMI - CV. PUSTAKA GRAFIKA">{{ old('report_header_text', $reportHeaderText ?? '') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">{{ __('ui.settings_company_logo') }} {!! $settingsHintIcon($settingsHints['company_logo_title']) !!}</h3>
                    <p class="form-section-note">{{ __('ui.settings_company_logo_note') }}</p>
                    <div class="row">
                        <div class="col-6">
                            <label>{{ __('ui.settings_upload_logo') }} {!! $settingsHintIcon($settingsHints['company_logo_upload']) !!}</label>
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
                    <h3 class="form-section-title">{{ __('ui.settings_print_workflow_title') }} {!! $settingsHintIcon($settingsHints['print_workflow_title']) !!}</h3>
                    <p class="form-section-note">{{ __('ui.settings_print_workflow_note') }}</p>
                    <div class="row inline">
                        <div class="col-4">
                            <label>{{ __('ui.settings_print_mode') }} {!! $settingsHintIcon($settingsHints['print_mode']) !!}</label>
                            <select name="print_workflow_mode">
                                <option value="browser" @selected(old('print_workflow_mode', $printWorkflowMode ?? 'browser') === 'browser')>Browser</option>
                                <option value="qz" @selected(old('print_workflow_mode', $printWorkflowMode ?? 'browser') === 'qz')>QZ Tray</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label>{{ __('ui.settings_print_paper_preset') }} {!! $settingsHintIcon($settingsHints['print_paper_preset']) !!}</label>
                            <select name="print_paper_preset">
                                <option value="auto" @selected(old('print_paper_preset', $printPaperPreset ?? 'auto') === 'auto')>{{ __('ui.settings_print_paper_auto') }}</option>
                                <option value="9.5x5.5" @selected(old('print_paper_preset', $printPaperPreset ?? 'auto') === '9.5x5.5')>9.5x5.5</option>
                                <option value="9.5x11" @selected(old('print_paper_preset', $printPaperPreset ?? 'auto') === '9.5x11')>9.5x11</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label>{{ __('ui.settings_print_small_rows_threshold') }} {!! $settingsHintIcon($settingsHints['print_small_rows_threshold']) !!}</label>
                            <input type="number" name="print_small_rows_threshold" min="5" max="200" step="1" value="{{ old('print_small_rows_threshold', (int) ($printSmallRowsThreshold ?? 35)) }}">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">{{ __('ui.settings_semester_title') }} {!! $settingsHintIcon($settingsHints['semester_title']) !!}</h3>
                    <p class="form-section-note">{{ __('ui.settings_semester_note') }}</p>
                    @php
                        $semesterRows = collect(old('semester_period_codes', $semesterBookOptions ?? []))
                            ->map(fn ($item): string => trim((string) $item))
                            ->filter(fn (string $item): bool => $item !== '')
                            ->values();
                        if ($semesterRows->isEmpty()) {
                            $semesterRows = collect($semesterBookOptions ?? []);
                        }
                        $semesterRows = $semesterRows
                            ->sort(function (string $left, string $right): int {
                                $semesterSortKey = function (string $semester): string {
                                    if (preg_match('/^S([12])-(\d{2})(\d{2})$/', strtoupper(trim($semester)), $matches) === 1) {
                                        return sprintf('%02d%02d%d', (int) $matches[2], (int) $matches[3], (int) $matches[1]);
                                    }

                                    return '9999999' . strtoupper(trim($semester));
                                };

                                return $semesterSortKey($left) <=> $semesterSortKey($right);
                            })
                            ->values();
                        $activeSemesters = collect(old('semester_active_period_codes', $selectedActiveSemesters ?? []))
                            ->map(fn ($item) => (string) $item)
                            ->values();
                        $semesterMetadataMap = $semesterMetadata ?? [];
                    @endphp
                    <label>{{ __('ui.settings_semester_list') }} / {{ __('ui.active') }} {!! $settingsHintIcon($settingsHints['semester_list']) !!}</label>
                    <p class="muted" style="margin: 0 0 8px 0;">{{ __('ui.settings_semester_active_note') }}</p>
                    <div class="table-mobile-scroll">
                    <table id="semester-codes-table">
                        <thead>
                        <tr>
                            <th>{{ __('txn.semester_period') }}</th>
                            <th style="width: 140px;">Tanggal Dibuat</th>
                            <th style="width: 140px;">Tanggal Tutup</th>
                            <th style="width: 140px;">{{ __('ui.active') }}</th>
                            <th style="width: 140px;">{{ __('txn.status') }}</th>
                            <th style="width: 110px;">{{ __('txn.action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($semesterRows as $semesterRow)
                            @php
                                $createdAtRaw = $semesterMetadataMap[(string) $semesterRow]['created_at'] ?? null;
                                $createdAt = $createdAtRaw ? \Carbon\Carbon::parse($createdAtRaw)->format('d-m-Y') : '-';
                                $closedAtRaw = ($closedSemesterMetadata ?? [])[(string) $semesterRow]['closed_at'] ?? null;
                                $closedAt = $closedAtRaw ? \Carbon\Carbon::parse($closedAtRaw)->format('d-m-Y') : '-';
                                $isClosed = collect($closedSemesters ?? [])->contains((string) $semesterRow);
                            @endphp
                            <tr>
                                <td>
                                    <input type="text" name="semester_period_codes[]" value="{{ $semesterRow }}" placeholder="S1-2526" class="semester-code-input">
                                </td>
                                <td>{{ $createdAt }}</td>
                                <td>{{ $closedAt }}</td>
                                <td>
                                    <label style="display: inline-flex; align-items: center; gap: 6px;">
                                        <input
                                            type="checkbox"
                                            name="semester_active_period_codes[]"
                                            value="{{ $semesterRow }}"
                                            class="semester-active-checkbox"
                                            @checked($activeSemesters->contains((string) $semesterRow))
                                        >
                                        {{ __('ui.active') }} {!! $settingsHintIcon($settingsHints['semester_active']) !!}
                                    </label>
                                </td>
                                <td>{{ $isClosed ? __('ui.semester_closed') : __('ui.semester_open') }}</td>
                                <td>
                                    <div class="flex" style="justify-content: center;">
                                        @if($isClosed)
                                            <button type="submit" class="btn warning-btn" form="semester-open-{{ md5((string) $semesterRow) }}" title="{{ $settingsHints['semester_action'] }}">{{ __('ui.semester_open_button') }}</button>
                                        @else
                                            <button type="submit" class="btn edit-btn" form="semester-close-{{ md5((string) $semesterRow) }}" title="{{ $settingsHints['semester_action'] }}">{{ __('ui.semester_close_button') }}</button>
                                        @endif
                                        <button type="button" class="btn danger-btn remove-row">{{ __('txn.remove') }}</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td>
                                    <input type="text" name="semester_period_codes[]" value="" placeholder="S1-2526" class="semester-code-input">
                                </td>
                                <td>-</td>
                                <td>-</td>
                                <td>
                                    <label style="display: inline-flex; align-items: center; gap: 6px;">
                                        <input
                                            type="checkbox"
                                            name="semester_active_period_codes[]"
                                            value=""
                                            class="semester-active-checkbox"
                                            checked
                                        >
                                        {{ __('ui.active') }} {!! $settingsHintIcon($settingsHints['semester_active']) !!}
                                    </label>
                                </td>
                                <td>{{ __('ui.semester_open') }}</td>
                                <td><button type="button" class="btn danger-btn remove-row">{{ __('txn.remove') }}</button></td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                    </div>
                    <button type="button" id="add-semester-row" class="btn process-soft-btn" style="margin-top: 8px;">{{ __('txn.add_row') }}</button>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">{{ __('ui.settings_units_title') }} {!! $settingsHintIcon($settingsHints['units_title']) !!}</h3>
                    <p class="form-section-note">{{ __('ui.settings_units_note') }}</p>
                    <div class="row inline">
                        <div class="col-6">
                            <label>{{ __('ui.settings_units_list') }} ({{ __('txn.sales_invoice') }}) {!! $settingsHintIcon($settingsHints['product_units']) !!}</label>
                            @php
                                $productCodes = collect(old('product_unit_codes', $unitOptionRows->pluck('code')->all()))->values();
                                $productLabels = collect(old('product_unit_labels', $unitOptionRows->pluck('label')->all()))->values();
                            @endphp
                            <div class="table-mobile-scroll">
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
                                            <button type="button" class="btn danger-btn remove-row">{{ __('txn.remove') }}</button>
                                        </td>
                                    </tr>
                                @endfor
                                </tbody>
                            </table>
                            </div>
                            <button type="button" id="add-product-unit-row" class="btn process-soft-btn" style="margin-top: 8px;">{{ __('txn.add_row') }}</button>
                        </div>
                        <div class="col-6">
                            <label>{{ __('ui.settings_units_list') }} ({{ __('txn.outgoing_transactions_title') }}) {!! $settingsHintIcon($settingsHints['outgoing_units']) !!}</label>
                            @php
                                $outgoingCodes = collect(old('outgoing_unit_codes', $outgoingUnitOptionRows->pluck('code')->all()))->values();
                                $outgoingLabels = collect(old('outgoing_unit_labels', $outgoingUnitOptionRows->pluck('label')->all()))->values();
                            @endphp
                            <div class="table-mobile-scroll">
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
                                            <button type="button" class="btn danger-btn remove-row">{{ __('txn.remove') }}</button>
                                        </td>
                                    </tr>
                                @endfor
                                </tbody>
                            </table>
                            </div>
                            <button type="button" id="add-outgoing-unit-row" class="btn process-soft-btn" style="margin-top: 8px;">{{ __('txn.add_row') }}</button>
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
        @foreach($semesterRows as $semesterRow)
            <form id="semester-close-{{ md5((string) $semesterRow) }}" method="post" action="{{ route('settings.semester.close') }}" style="display:none;">
                @csrf
                <input type="hidden" name="semester_period" value="{{ $semesterRow }}">
            </form>
            <form id="semester-open-{{ md5((string) $semesterRow) }}" method="post" action="{{ route('settings.semester.open') }}" style="display:none;">
                @csrf
                <input type="hidden" name="semester_period" value="{{ $semesterRow }}">
            </form>
        @endforeach
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
                    <td>-</td>
                    <td>-</td>
                    <td>
                        <label style="display: inline-flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="semester_active_period_codes[]" value="" class="semester-active-checkbox" checked>
                            {{ __('ui.active') }}
                        </label>
                    </td>
                    <td>{{ __('ui.semester_open') }}</td>
                    <td><button type="button" class="btn danger-btn remove-row">{{ __('txn.remove') }}</button></td>
                `);
            });

            document.getElementById('add-product-unit-row')?.addEventListener('click', () => {
                addRow('product-units-table', `
                    <td><input type="text" name="product_unit_codes[]" value="" list="product-unit-code-suggestions" placeholder="exp"></td>
                    <td><input type="text" name="product_unit_labels[]" value="" placeholder="Exemplar"></td>
                    <td><button type="button" class="btn danger-btn remove-row">{{ __('txn.remove') }}</button></td>
                `);
            });

            document.getElementById('add-outgoing-unit-row')?.addEventListener('click', () => {
                addRow('outgoing-units-table', `
                    <td><input type="text" name="outgoing_unit_codes[]" value="" list="outgoing-unit-code-suggestions" placeholder="exp"></td>
                    <td><input type="text" name="outgoing_unit_labels[]" value="" placeholder="Exemplar"></td>
                    <td><button type="button" class="btn danger-btn remove-row">{{ __('txn.remove') }}</button></td>
                `);
            });

            bindRemoveButtons('semester-codes-table');
            bindSemesterRowSync();
            bindRemoveButtons('product-units-table');
            bindRemoveButtons('outgoing-units-table');
        })();
    </script>
@endsection
