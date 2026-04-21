@extends('layouts.app')

@section('title', 'Arsip Data - PgPOS ERP')

@section('content')
    @php
        $scanResult = session('archive_scan_result');
        $exportResult = session('archive_export_result');
        $financialResult = session('archive_financial_result');
        $purgeResult = session('archive_purge_result');
        $selected = collect(old('datasets', $selectedDatasets ?? []))->map(fn ($value) => strtolower((string) $value))->all();
        $scopeType = old('archive_scope_type', $selectedScopeType ?? 'year');
        $selectedDatasetKey = old('dataset_key', $selectedDatasetKey ?? ($selected[0] ?? 'audit_logs'));
        $selectedDataset = $datasets[$selectedDatasetKey] ?? null;
        $selectedDatasetMode = (string) ($selectedDataset['purge_mode'] ?? 'locked');
        $selectedDatasetLabel = (string) ($selectedDataset['label'] ?? $selectedDatasetKey);
        $selectedDatasetIsFinancial = (bool) ($selectedDataset['financial'] ?? false);
        $encodedExportSqlFile = !empty($exportResult['sql_file']) ? rtrim(strtr(base64_encode((string) $exportResult['sql_file']), '+/', '-_'), '=') : null;
        $encodedExportManifestFile = !empty($exportResult['manifest_file']) ? rtrim(strtr(base64_encode((string) $exportResult['manifest_file']), '+/', '-_'), '=') : null;
        $encodedFinancialSnapshotFile = !empty($financialResult['snapshot_file']) ? rtrim(strtr(base64_encode((string) $financialResult['snapshot_file']), '+/', '-_'), '=') : null;
        $encodedFinancialManifestFile = !empty($financialResult['manifest_file']) ? rtrim(strtr(base64_encode((string) $financialResult['manifest_file']), '+/', '-_'), '=') : null;
    @endphp

    <style>
        .archive-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 12px;
        }
        .archive-col-12 { grid-column: span 12; }
        .archive-col-8 { grid-column: span 8; }
        .archive-col-6 { grid-column: span 6; }
        .archive-col-4 { grid-column: span 4; }
        @media (max-width: 1100px) {
            .archive-col-8,
            .archive-col-6,
            .archive-col-4 { grid-column: span 12; }
        }
        .archive-kv th { width: 240px; }
        .archive-list {
            margin: 0;
            padding-left: 18px;
        }
        .archive-list li + li { margin-top: 6px; }
        .archive-window-grid,
        .archive-dataset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 10px;
        }
        .archive-window-card,
        .archive-dataset-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            background: color-mix(in srgb, var(--card) 96%, var(--bg));
        }
        .archive-window-card h3,
        .archive-dataset-card h3 {
            margin: 0 0 6px;
            font-size: 15px;
        }
        .archive-window-pill,
        .archive-mode-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .archive-window-pill {
            background: color-mix(in srgb, var(--badge-neutral-bg) 92%, var(--card));
            color: var(--badge-neutral-text);
        }
        .archive-mode-pill.standard {
            background: color-mix(in srgb, #d4f8df 82%, var(--card));
            color: #0d7a31;
        }
        .archive-mode-pill.financial_guarded {
            background: color-mix(in srgb, #fff1c9 85%, var(--card));
            color: #8a5a00;
        }
        .archive-mode-pill.locked {
            background: color-mix(in srgb, #ffd6d6 85%, var(--card));
            color: #a12727;
        }
        .archive-code {
            margin: 0;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: color-mix(in srgb, var(--surface) 92%, var(--card));
            font-size: 13px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .archive-muted {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }
        .archive-form-grid {
            display: grid;
            grid-template-columns: minmax(160px, 220px) 1fr;
            gap: 12px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .archive-form-grid {
                grid-template-columns: 1fr;
            }
        }
        .archive-action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }
        .archive-checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 10px;
        }
        .archive-simple-grid {
            display: grid;
            grid-template-columns: minmax(220px, 300px) 1fr;
            gap: 14px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .archive-simple-grid {
                grid-template-columns: 1fr;
            }
        }
        .archive-checkbox {
            display: block;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            background: color-mix(in srgb, var(--card) 96%, var(--bg));
        }
        .archive-checkbox strong {
            display: block;
            margin-bottom: 4px;
        }
        .archive-flash {
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
        }
        .archive-flash.success {
            background: color-mix(in srgb, #d8f4df 86%, var(--card));
            color: #0f6b2f;
            border: 1px solid color-mix(in srgb, #9ad0aa 86%, var(--card));
        }
        .archive-flash.error {
            background: color-mix(in srgb, #ffdcdc 86%, var(--card));
            color: #8e1d1d;
            border: 1px solid color-mix(in srgb, #f0a2a2 86%, var(--card));
        }
        .archive-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .archive-table th,
        .archive-table td {
            border-bottom: 1px solid var(--border);
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        .archive-table th {
            font-size: 12px;
            color: var(--muted);
        }
        .archive-step-box {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            background: color-mix(in srgb, var(--surface) 95%, var(--card));
        }
        .archive-step-box h4 {
            margin: 0 0 8px;
            font-size: 15px;
        }
        .archive-action-row .btn[disabled] {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .archive-download-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
    </style>

    <div class="archive-grid">
        <div class="card archive-col-12">
            <h1 class="page-title" style="margin:0 0 8px 0;">Arsip Data</h1>
            <p class="archive-muted" style="margin:0;">
                Halaman ini dipakai untuk menyiapkan arsip data production secara aman. Untuk `erpos` dengan managed DB AWS,
                backup tetap dijalankan dari app server Laravel, tetapi target database-nya tetap koneksi managed MySQL yang aktif di `.env`.
            </p>
        </div>

        @if (session('archive_success'))
            <div class="archive-col-12 archive-flash success">{{ session('archive_success') }}</div>
        @endif

        @if (session('archive_error'))
            <div class="archive-col-12 archive-flash error">{{ session('archive_error') }}</div>
        @endif

        @if ($errors->any())
            <div class="archive-col-12 archive-flash error">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="card archive-col-6">
            <h3 style="margin-top:0;">Konteks Server Saat Ini</h3>
            <table class="archive-kv">
                <tbody>
                <tr><th>Environment</th><td>{{ $appEnv }}</td></tr>
                <tr><th>DB Connection</th><td>{{ $dbConnection }}</td></tr>
                <tr><th>DB Host</th><td>{{ $dbHost !== '' ? $dbHost : '-' }}</td></tr>
                <tr><th>Backup Terakhir</th><td>{{ $latestBackup ?: '-' }}</td></tr>
                <tr><th>Total File Backup</th><td>{{ number_format((int) $backupFileCount, 0, ',', '.') }}</td></tr>
                <tr><th>Restore Drill Terakhir</th><td>{{ $latestRestoreDrill?->tested_at ? \Illuminate\Support\Carbon::parse((string) $latestRestoreDrill->tested_at)->format('d-m-Y H:i:s') : '-' }}</td></tr>
                <tr><th>Status Restore Drill</th><td>{{ $latestRestoreDrill?->status ? strtoupper((string) $latestRestoreDrill->status) : '-' }}</td></tr>
                <tr><th>Snapshot Finansial Terakhir</th><td>{{ is_array($latestFinancialSnapshot) ? basename((string) ($latestFinancialSnapshot['path'] ?? '-')) : '-' }}</td></tr>
                <tr><th>Review Arsip Terakhir</th><td>{{ is_array($latestArchiveReview) ? \Illuminate\Support\Carbon::parse((string) ($latestArchiveReview['generated_at'] ?? now()))->format('d-m-Y H:i:s') : '-' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card archive-col-6">
            <h3 style="margin-top:0;">Prinsip Aman</h3>
            <ol class="archive-list">
                <li>Arsip transaksi utama sekarang bisa dibaca berdasarkan tahun atau semester, sesuai kebutuhan periode bersih-bersih data.</li>
                <li>Backup penuh wajib dibuat dulu sebelum ekspor atau pembersihan data production.</li>
                <li>Restore drill wajib lulus dulu, terutama karena database berada di AWS Lightsail Managed MySQL.</li>
                <li>Dataset finansial yang sudah dibuka tetap harus melalui snapshot finansial dan rebuild setelah purge.</li>
            </ol>
        </div>

        <div class="card archive-col-12">
            <h3 style="margin-top:0;">Aksi Arsip</h3>
            <form id="archive-action-form" method="post">
                @csrf
                <div class="archive-form-grid">
                    <div>
                        <label for="archive_scope_type" class="archive-muted" style="display:block;margin-bottom:6px;">Basis arsip</label>
                        <select id="archive_scope_type" name="archive_scope_type" onchange="window.toggleArchiveScope && window.toggleArchiveScope(this.value)">
                            <option value="year" {{ $scopeType === 'year' ? 'selected' : '' }}>Tahun</option>
                            <option value="semester" {{ $scopeType === 'semester' ? 'selected' : '' }}>Semester</option>
                        </select>

                        <div id="archive-year-field" style="{{ $scopeType === 'semester' ? 'display:none;' : '' }} margin-top:12px;">
                            <label for="archive_year" class="archive-muted" style="display:block;margin-bottom:6px;">Tahun target</label>
                            <input type="number" id="archive_year" name="archive_year" value="{{ old('archive_year', $selectedYear) }}" min="2000" max="2100">
                        </div>

                        <div id="archive-semester-field" style="{{ $scopeType === 'semester' ? '' : 'display:none;' }} margin-top:12px;">
                            <label for="archive_semester" class="archive-muted" style="display:block;margin-bottom:6px;">Semester target</label>
                            <select id="archive_semester" name="archive_semester">
                                @foreach(($semesterOptions ?? []) as $semesterOption)
                                    <option value="{{ $semesterOption }}" {{ old('archive_semester', $selectedSemester) === $semesterOption ? 'selected' : '' }}>{{ $semesterOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="archive-muted" style="margin-top:8px;">Untuk managed DB AWS, file arsip dibuat di server dulu lalu sebaiknya diunduh dan disimpan juga di komputer lokal.</div>

                        <label style="display:flex; gap:8px; align-items:flex-start; margin-top:12px;">
                            <input type="checkbox" name="rebuild_journal" value="1" {{ old('rebuild_journal') ? 'checked' : '' }}>
                            <span class="archive-muted">Saat snapshot finansial, siapkan rebuild journal juga.</span>
                        </label>

                        <label style="display:flex; gap:8px; align-items:flex-start; margin-top:8px;">
                            <input type="checkbox" name="allow_skipped_restore" value="1" {{ old('allow_skipped_restore') ? 'checked' : '' }}>
                            <span class="archive-muted">Izinkan restore drill terakhir berstatus `SKIPPED` bila alasannya memang dipahami.</span>
                        </label>

                        <label style="display:flex; gap:8px; align-items:flex-start; margin-top:8px;">
                            <input type="checkbox" name="confirm_purge" value="1" {{ old('confirm_purge') ? 'checked' : '' }}>
                            <span class="archive-muted">Centang hanya saat benar-benar siap menghapus data production.</span>
                        </label>
                    </div>

                    <div>
                        <div class="archive-simple-grid">
                            <div>
                                <label for="dataset_key" class="archive-muted" style="display:block;margin-bottom:6px;">Jenis data</label>
                                <select id="dataset_key" name="dataset_key" onchange="window.updateArchiveDataset && window.updateArchiveDataset(this.value)">
                                    @foreach($datasets as $key => $dataset)
                                        <option value="{{ $key }}" {{ $selectedDatasetKey === $key ? 'selected' : '' }}>{{ $dataset['label'] }}</option>
                                    @endforeach
                                </select>
                                <div class="archive-muted" style="margin-top:8px;">
                                    Pilih satu jenis data dulu agar langkahnya lebih mudah dibaca.
                                </div>
                            </div>

                            <div class="archive-step-box">
                                <h4 id="archive-selected-label">{{ $selectedDatasetLabel }}</h4>
                                <div id="archive-selected-mode" class="archive-mode-pill {{ $selectedDatasetMode }}">
                                    @if($selectedDatasetMode === 'standard')
                                        Langsung bisa export lalu purge
                                    @elseif($selectedDatasetMode === 'financial_guarded')
                                        Butuh snapshot finansial dulu
                                    @else
                                        Baru bisa scan dan export
                                    @endif
                                </div>
                                <div id="archive-selected-note" class="archive-muted">
                                    @if($selectedDatasetMode === 'standard')
                                        Untuk data log/ops seperti ini, langkahnya cukup: `Preview Scan` -> `Buat Export SQL` -> `Dry Run Purge` -> `Purge Final`.
                                    @elseif($selectedDatasetMode === 'financial_guarded')
                                        Untuk data finansial seperti ini, langkahnya: `Preview Scan` -> `Buat Export SQL` -> `Siapkan Snapshot Finansial` -> `Dry Run Purge` -> `Purge Final`.
                                    @else
                                        Untuk data ini, purge masih dikunci. Saat ini pakai dulu: `Preview Scan` dan `Buat Export SQL`.
                                    @endif
                                </div>
                                <div class="archive-muted" style="margin-top:8px;">
                                    Basis data ini: <strong id="archive-selected-basis">{{ ($selectedDataset['basis'] ?? 'year') === 'year' ? 'Tahun' : 'Bulan' }}</strong>.
                                    Scope yang boleh dipakai: <strong id="archive-selected-scope">{{ implode(' / ', array_map(fn ($item) => $item === 'semester' ? 'semester' : 'tahun', $selectedDataset['scope_modes'] ?? ['year'])) }}</strong>.
                                </div>
                            </div>
                        </div>

                        <div class="archive-action-row">
                            <button type="submit" class="btn secondary" formaction="{{ route('archive-data.scan') }}">1. Cek Dulu</button>
                            <button type="submit" class="btn secondary" formaction="{{ route('archive-data.export') }}">2. Buat File Arsip</button>
                            <button type="submit" id="archive-financial-button" class="btn secondary" formaction="{{ route('archive-data.prepare-financial') }}" {{ $selectedDatasetMode !== 'financial_guarded' ? 'disabled' : '' }}>3. Snapshot Finansial</button>
                            <button type="submit" id="archive-dry-run-button" class="btn secondary" formaction="{{ route('archive-data.purge') }}" onclick="this.form.confirm_purge.checked=false;" {{ $selectedDatasetMode === 'locked' ? 'disabled' : '' }}>4. Coba Simulasi Hapus</button>
                            <button type="submit" id="archive-purge-button" class="btn danger" formaction="{{ route('archive-data.purge') }}" onclick="this.form.confirm_purge.checked=true;" {{ $selectedDatasetMode === 'locked' ? 'disabled' : '' }}>5. Hapus Data</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card archive-col-12">
            <h3 style="margin-top:0;">Window Retention yang Dipakai Sekarang</h3>
            <div class="archive-window-grid">
                @foreach($retentionWindows as $window)
                    <div class="archive-window-card">
                        <h3>{{ $window['label'] }}</h3>
                        <div class="archive-window-pill">Online {{ $window['period'] }}</div>
                        <div class="archive-muted" style="margin-bottom:6px;">Basis kerja operator: {{ $window['basis'] === 'tahun' ? 'berdasarkan tahun' : 'berdasarkan bulan' }}.</div>
                        <div class="archive-muted">{{ $window['note'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        @if (is_array($latestArchiveReview))
            <div class="card archive-col-12">
                <h3 style="margin-top:0;">Review Arsip Terakhir</h3>
                <table class="archive-kv" style="margin-bottom:12px;">
                    <tbody>
                    <tr><th>Dibuat pada</th><td>{{ \Illuminate\Support\Carbon::parse((string) ($latestArchiveReview['generated_at'] ?? now()))->format('d-m-Y H:i:s') }}</td></tr>
                    <tr><th>File review</th><td><code>{{ $latestArchiveReview['path'] ?? '-' }}</code></td></tr>
                    </tbody>
                </table>
                @if (!empty($latestArchiveReview['reminders']))
                    <ul class="archive-list" style="margin-bottom:12px;">
                        @foreach(($latestArchiveReview['reminders'] ?? []) as $reminder)
                            <li>{{ $reminder }}</li>
                        @endforeach
                    </ul>
                @endif
                <table class="archive-table">
                    <thead>
                    <tr>
                        <th>Dataset</th>
                        <th>Retention</th>
                        <th>Cutoff</th>
                        <th>Kandidat</th>
                        <th>Scope</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(($latestArchiveReview['datasets'] ?? []) as $dataset)
                        <tr>
                            <td>{{ $dataset['label'] ?? $dataset['key'] ?? '-' }}</td>
                            <td>{{ $dataset['retention'] ?? '-' }}</td>
                            <td>{{ $dataset['cutoff_date'] ?? '-' }}</td>
                            <td>{{ number_format((int) ($dataset['candidate_rows'] ?? 0), 0, ',', '.') }}</td>
                            <td>{{ $dataset['recommended_scope'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (is_array($scanResult))
            <div class="card archive-col-8">
                <h3 style="margin-top:0;">Hasil Preview Scan</h3>
                <table class="archive-table">
                    <thead>
                    <tr>
                        <th>Dataset</th>
                        <th>Mode Purge</th>
                        <th>Total Row</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(($scanResult['datasets'] ?? []) as $datasetKey => $dataset)
                        <tr>
                            <td>
                                <strong>{{ $dataset['label'] }}</strong><br>
                                <span class="archive-muted"><code>{{ $datasetKey }}</code></span>
                            </td>
                            <td>{{ match ((string) ($dataset['purge_mode'] ?? 'locked')) {
                                'standard' => 'Purge biasa',
                                'financial_guarded' => 'Snapshot + rebuild',
                                default => 'Dikunci',
                            } }}</td>
                            <td>{{ number_format((int) $dataset['total_rows'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card archive-col-4">
                <h3 style="margin-top:0;">Ringkasan Scan</h3>
                <table class="archive-kv">
                    <tbody>
                    <tr><th>Tahun</th><td>{{ $scanResult['year'] ?? '-' }}</td></tr>
                    <tr><th>Semester</th><td>{{ $scanResult['semester'] ?? '-' }}</td></tr>
                    <tr><th>Scope</th><td>{{ ($scanResult['period_type'] ?? 'year') === 'semester' ? 'Semester' : 'Tahun' }} {{ $scanResult['period_value'] ?? ($scanResult['year'] ?? '-') }}</td></tr>
                    <tr><th>Total kandidat</th><td>{{ number_format((int) ($scanResult['grand_total'] ?? 0), 0, ',', '.') }}</td></tr>
                    <tr><th>Dataset tidak dikenal</th><td>{{ empty($scanResult['missing']) ? '-' : implode(', ', $scanResult['missing']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        @endif

        @if (is_array($exportResult))
            <div class="card archive-col-12">
                <h3 style="margin-top:0;">Hasil File Arsip</h3>
                <table class="archive-kv">
                    <tbody>
                    <tr><th>SQL file</th><td><code>{{ $exportResult['sql_file'] ?? '-' }}</code></td></tr>
                    <tr><th>Manifest file</th><td><code>{{ $exportResult['manifest_file'] ?? '-' }}</code></td></tr>
                    <tr><th>Scope</th><td>{{ (($exportResult['summary']['period_type'] ?? 'year') === 'semester') ? 'Semester' : 'Tahun' }} {{ $exportResult['summary']['period_value'] ?? (($exportResult['summary']['year'] ?? '-')) }}</td></tr>
                    <tr><th>Total row</th><td>{{ number_format((int) (($exportResult['summary']['grand_total'] ?? 0)), 0, ',', '.') }}</td></tr>
                    </tbody>
                </table>
                <div class="archive-download-row">
                    @if($encodedExportSqlFile)
                        <a href="{{ route('archive-data.download', ['file' => $encodedExportSqlFile]) }}" class="btn secondary">Download SQL</a>
                    @endif
                    @if($encodedExportManifestFile)
                        <a href="{{ route('archive-data.download', ['file' => $encodedExportManifestFile]) }}" class="btn secondary">Download Manifest</a>
                    @endif
                </div>
                <p class="archive-muted" style="margin:10px 0 0;">Kalau mau lanjut hapus data, simpan dulu file ini di komputer lokal.</p>
            </div>
        @endif

        @if (is_array($financialResult))
            <div class="card archive-col-12">
                <h3 style="margin-top:0;">Snapshot Finansial</h3>
                <table class="archive-kv">
                    <tbody>
                    <tr><th>Snapshot file</th><td><code>{{ $financialResult['snapshot_file'] ?? '-' }}</code></td></tr>
                    <tr><th>Manifest file</th><td><code>{{ $financialResult['manifest_file'] ?? '-' }}</code></td></tr>
                    <tr><th>Scope</th><td>{{ (($financialResult['period_type'] ?? 'year') === 'semester') ? 'Semester' : 'Tahun' }} {{ $financialResult['period_value'] ?? (($financialResult['year'] ?? '-')) }}</td></tr>
                    <tr><th>Customer terdampak</th><td>{{ number_format(count($financialResult['customer_snapshots'] ?? []), 0, ',', '.') }}</td></tr>
                    <tr><th>Supplier terdampak</th><td>{{ number_format(count($financialResult['supplier_snapshots'] ?? []), 0, ',', '.') }}</td></tr>
                    </tbody>
                </table>
                <div class="archive-download-row">
                    @if($encodedFinancialSnapshotFile)
                        <a href="{{ route('archive-data.download', ['file' => $encodedFinancialSnapshotFile]) }}" class="btn secondary">Download Snapshot</a>
                    @endif
                    @if($encodedFinancialManifestFile)
                        <a href="{{ route('archive-data.download', ['file' => $encodedFinancialManifestFile]) }}" class="btn secondary">Download Manifest</a>
                    @endif
                </div>
            </div>
        @endif

        @if (is_array($purgeResult))
            <div class="card archive-col-12">
                <h3 style="margin-top:0;">Hasil Purge</h3>
                <table class="archive-kv">
                    <tbody>
                    <tr><th>Backup file</th><td><code>{{ $purgeResult['backup_file'] ?? '-' }}</code></td></tr>
                    <tr><th>Manifest file</th><td><code>{{ $purgeResult['manifest_file'] ?? '-' }}</code></td></tr>
                    <tr><th>Snapshot file</th><td><code>{{ $purgeResult['snapshot_file'] ?? '-' }}</code></td></tr>
                    <tr><th>Restore status</th><td>{{ strtoupper((string) ($purgeResult['restore_status'] ?? '-')) }}</td></tr>
                    <tr><th>Total kandidat</th><td>{{ number_format((int) (($purgeResult['summary']['grand_total'] ?? 0)), 0, ',', '.') }}</td></tr>
                    @if (!empty($purgeResult['post_check']) && is_array($purgeResult['post_check']))
                        <tr><th>Financial rebuild exit</th><td>{{ $purgeResult['post_check']['rebuild_exit'] ?? '-' }}</td></tr>
                        <tr><th>Integrity exit</th><td>{{ $purgeResult['post_check']['integrity_exit'] ?? '-' }}</td></tr>
                        <tr><th>Integrity latest ok</th><td>{{ var_export($purgeResult['post_check']['latest_integrity_status'] ?? null, true) }}</td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        @endif

        <div class="card archive-col-6">
            <h3 style="margin-top:0;">Command yang Dipakai Sekarang</h3>
            <ul class="archive-list">
                @foreach($archiveCommands as $command)
                    <li><code>{{ $command }}</code></li>
                @endforeach
            </ul>
            <p class="archive-muted" style="margin:10px 0 0;">
                Tiga command ini tetap berjalan dari folder project Laravel di aaPanel, tetapi target database-nya tetap managed DB AWS lewat `.env`.
            </p>
        </div>

        <div class="card archive-col-6">
            <h3 style="margin-top:0;">Alur Semi-Manual yang Disepakati</h3>
            <ol class="archive-list">
                @foreach($plannedArchiveFlow as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
            <p class="archive-muted" style="margin:10px 0 0;">
                Untuk transaksi ERP, operator sekarang bisa memilih scope yang paling masuk akal: `tahun` untuk sapuan besar atau `semester` untuk periode yang sudah benar-benar selesai.
            </p>
        </div>

        <div class="card archive-col-6">
            <h3 style="margin-top:0;">Checklist UAT Arsip Nyata</h3>
            <ol class="archive-list">
                @foreach($archiveUatChecklist as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ol>
        </div>

        <div class="card archive-col-6">
            <h3 style="margin-top:0;">Histori Eksekusi Arsip</h3>
            @if (!empty($archiveHistory))
                <table class="archive-table">
                    <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Aksi</th>
                        <th>Ringkasan</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($archiveHistory as $entry)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse((string) $entry['created_at'])->format('d-m-Y H:i:s') }}</td>
                            <td>
                                <strong>{{ $entry['title'] }}</strong><br>
                                <span class="archive-muted"><code>{{ $entry['path'] }}</code></span>
                            </td>
                            <td>{{ $entry['summary'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <p class="archive-muted" style="margin:0;">Belum ada histori eksekusi arsip yang tersimpan.</p>
            @endif
        </div>

        <div class="card archive-col-12">
            <h3 style="margin-top:0;">Catatan Command Arsip</h3>
            <pre class="archive-code">php artisan app:archive:scan 2021 --dataset=sales_invoices
php artisan app:archive:scan --semester=S1-2526 --dataset=sales_invoices
php artisan app:archive:export 2021 --dataset=sales_invoices
php artisan app:archive:export --semester=S1-2526 --dataset=sales_returns
php artisan app:archive:prepare-financial 2021 --dataset=sales_invoices --rebuild-journal
php artisan app:archive:review
php artisan app:archive:purge 2021 --dataset=audit_logs --confirm</pre>
            <p class="archive-muted" style="margin:10px 0 0;">
                Purge biasa sekarang dibuka juga untuk beberapa dataset ops tambahan seperti `failed_jobs` dan `job_batches`. Purge finansial tahap lanjut sekarang juga dibuka untuk `sales_returns` dan `receivable_payments`, tetapi tetap wajib snapshot + rebuild agar saldo dan jurnal tetap konsisten. Untuk semester lama yang mau disimpan, backup dari server tetap perlu diunduh dan disimpan juga di lokal operator.
            </p>
        </div>
    </div>

    <script>
        window.archiveDatasetMeta = @json(collect($datasets)->map(fn ($dataset) => [
            'label' => $dataset['label'],
            'mode' => $dataset['purge_mode'] ?? 'locked',
            'basis' => ($dataset['basis'] ?? 'year') === 'year' ? 'Tahun' : 'Bulan',
            'scope' => implode(' / ', array_map(fn ($item) => $item === 'semester' ? 'semester' : 'tahun', $dataset['scope_modes'] ?? ['year'])),
        ])->all());

        window.toggleArchiveScope = function (value) {
            const yearField = document.getElementById('archive-year-field');
            const semesterField = document.getElementById('archive-semester-field');
            if (!yearField || !semesterField) {
                return;
            }
            const isSemester = value === 'semester';
            yearField.style.display = isSemester ? 'none' : '';
            semesterField.style.display = isSemester ? '' : 'none';
        };

        window.updateArchiveDataset = function (value) {
            const meta = (window.archiveDatasetMeta || {})[value];
            if (!meta) {
                return;
            }

            const label = document.getElementById('archive-selected-label');
            const mode = document.getElementById('archive-selected-mode');
            const note = document.getElementById('archive-selected-note');
            const basis = document.getElementById('archive-selected-basis');
            const scope = document.getElementById('archive-selected-scope');
            const financialButton = document.getElementById('archive-financial-button');
            const dryRunButton = document.getElementById('archive-dry-run-button');
            const purgeButton = document.getElementById('archive-purge-button');

            if (label) label.textContent = meta.label;
            if (basis) basis.textContent = meta.basis;
            if (scope) scope.textContent = meta.scope;

            if (mode) {
                mode.className = 'archive-mode-pill ' + meta.mode;
                mode.textContent = meta.mode === 'standard'
                    ? 'Langsung bisa export lalu purge'
                    : (meta.mode === 'financial_guarded'
                        ? 'Butuh snapshot finansial dulu'
                        : 'Baru bisa scan dan export');
            }

            if (note) {
                note.textContent = meta.mode === 'standard'
                    ? 'Untuk data log/ops seperti ini, langkahnya cukup: Preview Scan -> Buat Export SQL -> Dry Run Purge -> Purge Final.'
                    : (meta.mode === 'financial_guarded'
                        ? 'Untuk data finansial seperti ini, langkahnya: Preview Scan -> Buat Export SQL -> Siapkan Snapshot Finansial -> Dry Run Purge -> Purge Final.'
                        : 'Untuk data ini, purge masih dikunci. Saat ini pakai dulu: Preview Scan dan Buat Export SQL.');
            }

            if (financialButton) financialButton.disabled = meta.mode !== 'financial_guarded';
            if (dryRunButton) dryRunButton.disabled = meta.mode === 'locked';
            if (purgeButton) purgeButton.disabled = meta.mode === 'locked';
        };
    </script>
@endsection
