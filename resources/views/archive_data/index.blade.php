@extends('layouts.app')

@section('title', 'Arsip Data Bisnis - '.config('app.name', 'Laravel'))

@section('content')
    @php
        $quickScanResult     = session('quick_scan_result');
        $quickExportResult   = session('quick_export_result');
        $quickSnapshotResult = session('quick_snapshot_result');
        $quickPurgeResult    = session('quick_purge_result');
        $quickIntegrityOk    = session('quick_integrity_ok');   // null|bool
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
        .archive-scroll-box {
            max-height: 360px;
            overflow: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }
        .archive-scroll-box .archive-table th {
            position: sticky;
            top: 0;
            background: color-mix(in srgb, var(--card) 96%, var(--bg));
            z-index: 1;
        }
        .archive-help-box {
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 12px;
            background: color-mix(in srgb, var(--surface) 94%, var(--card));
        }
        .archive-help-box h4 {
            margin: 0 0 8px;
            font-size: 15px;
        }
    </style>

    <div class="archive-grid">
        <div class="card archive-col-12">
            <h1 class="page-title" style="margin:0 0 8px 0;">Arsip Data Bisnis</h1>
            <p class="archive-muted" style="margin:0;">
                Halaman ini dipakai untuk menyiapkan arsip data bisnis production secara aman. Log sistem seperti audit log, failed jobs,
                performance probe, restore drill, dan task export sekarang dibersihkan otomatis oleh scheduler. Untuk `erpos` dengan managed DB AWS,
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

        @php
            $quickScanResult    = session('quick_scan_result');
            $quickExportResult  = session('quick_export_result');
            $quickSnapshotResult = session('quick_snapshot_result');
            $quickPurgeResult   = session('quick_purge_result');
        @endphp

        @if (session('quick_success'))
            <div class="archive-col-12 archive-flash success">{{ session('quick_success') }}</div>
        @endif
        @if (session('quick_error'))
            <div class="archive-col-12 archive-flash error">{{ session('quick_error') }}</div>
        @endif

        @if (!empty($semesterOptions))
            <div class="archive-col-12" style="background:color-mix(in srgb,#fff8e1 88%,var(--card));border:1px solid #f0c040;border-radius:10px;padding:12px 14px;font-size:13px;">
                <strong>Pengingat:</strong> Ada {{ count($semesterOptions) }} semester yang sudah ditutup.
                Gunakan <strong>Mode Mudah</strong> di bawah untuk mengarsipkan data lama secara berkala.
            </div>
        @endif

        {{-- ===== MODE MUDAH ===== --}}
        <div class="card archive-col-12">
            <h2 style="margin:0 0 4px;font-size:18px;">Arsipkan Semester — Mode Mudah</h2>
            <p class="archive-muted" style="margin:0 0 16px;">
                Pilih semester lalu ikuti 4 langkah. Semua dataset bisnis diproses sekaligus — tidak perlu pilih satu-satu.
            </p>

            <form id="quick-archive-form" method="post">
                @csrf
                <input type="hidden" name="confirm_purge" id="quick-confirm-purge" value="0">

                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                    <label style="font-weight:600;white-space:nowrap;">Semester target:</label>
                    @if (empty($semesterOptions))
                        <span class="archive-muted">Belum ada semester yang ditutup. Tutup semester dulu di menu Pengaturan.</span>
                    @else
                        <select name="quick_semester" style="min-width:160px;">
                            @foreach ($semesterOptions as $opt)
                                <option value="{{ $opt }}" @selected(old('quick_semester', $semesterOptions[0] ?? '') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">

                    {{-- Langkah 1 --}}
                    <div class="archive-step-box">
                        <h4>① Cek Data</h4>
                        <p class="archive-muted" style="margin:0 0 10px;font-size:12px;">
                            Hitung berapa baris data yang ada di semester ini sebelum dihapus.
                        </p>
                        @if ($quickScanResult)
                            <div style="font-size:13px;margin-bottom:10px;">
                                <strong>{{ number_format((int) ($quickScanResult['grand_total'] ?? 0), 0, ',', '.') }} baris</strong>
                                dari {{ count($quickScanResult['datasets'] ?? []) }} dataset.
                                @foreach ($quickScanResult['datasets'] ?? [] as $dsKey => $ds)
                                    @if ((int)($ds['total_rows'] ?? 0) > 0)
                                        <br><span class="archive-muted" style="font-size:11px;">
                                            {{ $ds['label'] ?? $dsKey }}: {{ number_format((int)$ds['total_rows'], 0, ',', '.') }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                        <button type="submit" class="btn secondary"
                            formaction="{{ route('archive-data.quick-scan') }}"
                            {{ empty($semesterOptions) ? 'disabled' : '' }}>
                            Cek Semua Data
                        </button>
                    </div>

                    {{-- Langkah 2 --}}
                    <div class="archive-step-box">
                        <h4>② Buat File Arsip</h4>
                        <p class="archive-muted" style="margin:0 0 10px;font-size:12px;">
                            Simpan semua data ke file SQL sebagai cadangan sebelum dihapus.
                        </p>
                        @if ($quickExportResult)
                            @php
                                $qSqlEnc = !empty($quickExportResult['sql_file'])
                                    ? rtrim(strtr(base64_encode((string)$quickExportResult['sql_file']), '+/', '-_'), '=')
                                    : null;
                                $qManEnc = !empty($quickExportResult['manifest_file'])
                                    ? rtrim(strtr(base64_encode((string)$quickExportResult['manifest_file']), '+/', '-_'), '=')
                                    : null;
                            @endphp
                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">
                                @if ($qSqlEnc)
                                    <a href="{{ route('archive-data.download', ['file' => $qSqlEnc]) }}"
                                       class="btn secondary" style="font-size:12px;">Unduh SQL</a>
                                @endif
                                @if ($qManEnc)
                                    <a href="{{ route('archive-data.download', ['file' => $qManEnc]) }}"
                                       class="btn secondary" style="font-size:12px;">Unduh Manifest</a>
                                @endif
                            </div>
                        @endif
                        <button type="submit" class="btn secondary"
                            formaction="{{ route('archive-data.quick-export') }}"
                            {{ empty($semesterOptions) ? 'disabled' : '' }}>
                            Buat File Arsip
                        </button>
                    </div>

                    {{-- Langkah 3 --}}
                    <div class="archive-step-box">
                        <h4>③ Snapshot + Cek Kondisi</h4>
                        <p class="archive-muted" style="margin:0 0 10px;font-size:12px;">
                            Simpan saldo piutang/hutang, lalu otomatis cek kondisi keuangan.
                        </p>
                        @if ($quickSnapshotResult)
                            <div style="font-size:13px;margin-bottom:6px;color:#0f6b2f;">✓ Snapshot tersimpan</div>
                        @endif
                        @if (!is_null($quickIntegrityOk))
                            <div style="font-size:13px;margin-bottom:10px;{{ $quickIntegrityOk ? 'color:#0f6b2f' : 'color:#8e1d1d' }}">
                                {{ $quickIntegrityOk ? '✓ Kondisi keuangan: Aman' : '✗ Ditemukan ketidaksesuaian keuangan' }}
                            </div>
                        @endif
                        <button type="submit" class="btn secondary"
                            formaction="{{ route('archive-data.quick-snapshot') }}"
                            {{ empty($semesterOptions) ? 'disabled' : '' }}>
                            Snapshot + Cek Otomatis
                        </button>
                    </div>

                    {{-- Langkah 4 --}}
                    <div class="archive-step-box">
                        <h4>④ Hapus Data</h4>
                        <p class="archive-muted" style="margin:0 0 10px;font-size:12px;">
                            Simulasi dulu. Kalau sudah yakin, klik Hapus Permanen.
                        </p>
                        @if ($quickPurgeResult)
                            <div style="font-size:13px;margin-bottom:10px;">
                                <strong>{{ number_format((int)($quickPurgeResult['total'] ?? 0), 0, ',', '.') }} baris</strong>
                                {{ (int)($quickPurgeResult['total'] ?? 0) > 0 ? 'berhasil dihapus' : 'akan dihapus (simulasi)' }}.
                            </div>
                        @endif
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="submit" class="btn secondary"
                                formaction="{{ route('archive-data.quick-purge') }}"
                                onclick="document.getElementById('quick-confirm-purge').value='0';"
                                {{ empty($semesterOptions) ? 'disabled' : '' }}>
                                Simulasi
                            </button>
                            <button type="submit" class="btn danger"
                                formaction="{{ route('archive-data.quick-purge') }}"
                                onclick="if(!confirm('Yakin hapus semua data semester ini? Pastikan file arsip sudah diunduh dan disimpan.')){return false;} document.getElementById('quick-confirm-purge').value='1';"
                                {{ empty($semesterOptions) ? 'disabled' : '' }}>
                                Hapus Permanen
                            </button>
                        </div>
                    </div>

                </div>
            </form>
        </div>
        {{-- ===== AKHIR MODE MUDAH ===== --}}

        <div class="card archive-col-6">
            <h3 style="margin-top:0;">Status Backup dan Pemeriksaan</h3>
            <p class="archive-muted" style="margin:0 0 12px;">
                Bagian ini dipakai untuk memastikan backup dan pemeriksaan dasar sudah pernah jalan sebelum data dibersihkan.
            </p>
            <table class="archive-kv">
                <tbody>
                <tr><th>Backup Terakhir</th><td>{{ $latestBackup ? basename((string) $latestBackup) : '-' }}</td></tr>
                <tr><th>Total File Backup</th><td>{{ number_format((int) $backupFileCount, 0, ',', '.') }}</td></tr>
                <tr><th>Restore Drill Terakhir</th><td>{{ $latestRestoreDrill?->tested_at ? \Illuminate\Support\Carbon::parse((string) $latestRestoreDrill->tested_at)->format('d-m-Y H:i:s') : '-' }}</td></tr>
                <tr><th>Status Restore Drill</th><td>{{ $latestRestoreDrill?->status ? strtoupper((string) $latestRestoreDrill->status) : '-' }}</td></tr>
                <tr><th>Snapshot Finansial Terakhir</th><td>{{ is_array($latestFinancialSnapshot) ? basename((string) ($latestFinancialSnapshot['path'] ?? '-')) : '-' }}</td></tr>
                <tr><th>Review Arsip Terakhir</th><td>{{ is_array($latestArchiveReview) ? \Illuminate\Support\Carbon::parse((string) ($latestArchiveReview['generated_at'] ?? now()))->format('d-m-Y H:i:s') : '-' }}</td></tr>
                </tbody>
            </table>
        </div>

        {{-- REMOVED: Prinsip Aman + Aksi Arsip (complex mode) --}}
        <div class="card archive-col-12" style="display:none;">
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
                            <select id="archive_year" name="archive_year" {{ empty($yearOptions ?? []) ? 'disabled' : '' }}>
                                @if(empty($yearOptions ?? []))
                                    <option value="">Kosong / belum ada</option>
                                @else
                                    @foreach(($yearOptions ?? []) as $yearOption)
                                        <option value="{{ $yearOption }}" {{ (string) old('archive_year', $selectedYear) === (string) $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
                                    @endforeach
                                @endif
                            </select>
                            <div id="archive-year-note" class="archive-muted" style="margin-top:8px;">{{ $selectedYearNote }}</div>
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

                        <input type="hidden" id="confirm_purge" name="confirm_purge" value="{{ old('confirm_purge') ? '1' : '0' }}">

                        <details style="margin-top:12px;">
                            <summary class="archive-muted" style="cursor:pointer;">Pengaturan tambahan</summary>
                            <label style="display:flex; gap:8px; align-items:flex-start; margin-top:12px;">
                                <input type="checkbox" name="rebuild_journal" value="1" {{ old('rebuild_journal') ? 'checked' : '' }}>
                                <span class="archive-muted">Saat snapshot finansial, siapkan rebuild journal juga.</span>
                            </label>

                            <label style="display:flex; gap:8px; align-items:flex-start; margin-top:8px;">
                                <input type="checkbox" name="allow_skipped_restore" value="1" {{ old('allow_skipped_restore') ? 'checked' : '' }}>
                                <span class="archive-muted">Izinkan restore drill terakhir berstatus `SKIPPED` bila alasannya memang dipahami.</span>
                            </label>
                        </details>
                    </div>

                    <div>
                        <div class="archive-simple-grid">
                            <div>
                                <label for="dataset_key" class="archive-muted" style="display:block;margin-bottom:6px;">Jenis data bisnis</label>
                                <select id="dataset_key" name="dataset_key" onchange="window.updateArchiveDataset && window.updateArchiveDataset(this.value)">
                                    @foreach($datasets as $key => $dataset)
                                        <option value="{{ $key }}" {{ $selectedDatasetKey === $key ? 'selected' : '' }}>{{ $dataset['label'] }}</option>
                                    @endforeach
                                </select>
                                <div class="archive-muted" style="margin-top:8px;">
                                    Pilih satu jenis data bisnis dulu agar langkahnya lebih mudah dibaca.
                                </div>
                            </div>

                            <div class="archive-step-box">
                                <h4 id="archive-selected-label">{{ $selectedDatasetLabel }}</h4>
                                <div id="archive-selected-mode" class="archive-mode-pill {{ $selectedDatasetMode }}">
                                    @if($selectedDatasetMode === 'standard')
                                        Langsung bisa export lalu bersihkan data
                                    @elseif($selectedDatasetMode === 'financial_guarded')
                                        Butuh snapshot finansial dulu
                                    @else
                                        Baru bisa scan dan export
                                    @endif
                                </div>
                                <div id="archive-selected-note" class="archive-muted">
                                    @if($selectedDatasetMode === 'standard')
                                        Untuk data bisnis seperti ini, langkahnya cukup: `Cek Dulu` -> `Buat File Arsip` -> `Coba Simulasi Hapus` -> `Hapus Data`.
                                    @elseif($selectedDatasetMode === 'financial_guarded')
                                        Untuk data finansial seperti ini, langkahnya: `Cek Dulu` -> `Buat File Arsip` -> `Snapshot Finansial` -> `Coba Simulasi Hapus` -> `Hapus Data`.
                                    @else
                                        Untuk data ini, pembersihan data masih dikunci. Saat ini pakai dulu: `Cek Dulu` dan `Buat File Arsip`.
                                    @endif
                                </div>
                                <div class="archive-muted" style="margin-top:8px;">
                                    Basis data ini: <strong id="archive-selected-basis">{{ ($selectedDataset['basis'] ?? 'year') === 'year' ? 'Tahun ajaran' : 'Bulan' }}</strong>.
                                    Scope yang boleh dipakai: <strong id="archive-selected-scope">{{ implode(' / ', array_map(fn ($item) => $item === 'semester' ? 'semester' : 'tahun', $selectedDataset['scope_modes'] ?? ['year'])) }}</strong>.
                                </div>
                                <div class="archive-help-box" style="margin-top:12px;">
                                    <h4>Urutan klik yang aman</h4>
                                    <ol id="archive-selected-steps" class="archive-list">
                                        @if($selectedDatasetMode === 'standard')
                                            <li>Klik <strong>1. Cek Dulu</strong>.</li>
                                            <li>Klik <strong>2. Buat File Arsip</strong>.</li>
                                            <li>Klik <strong>4. Coba Simulasi Hapus</strong>.</li>
                                            <li>Kalau hasilnya sudah sesuai, klik <strong>5. Hapus Data</strong>.</li>
                                        @elseif($selectedDatasetMode === 'financial_guarded')
                                            <li>Klik <strong>1. Cek Dulu</strong>.</li>
                                            <li>Klik <strong>2. Buat File Arsip</strong>.</li>
                                            <li>Klik <strong>3. Snapshot Finansial</strong>.</li>
                                            <li>Klik <strong>4. Coba Simulasi Hapus</strong>.</li>
                                            <li>Kalau hasilnya sudah sesuai, klik <strong>5. Hapus Data</strong>.</li>
                                        @else
                                            <li>Klik <strong>1. Cek Dulu</strong>.</li>
                                            <li>Klik <strong>2. Buat File Arsip</strong>.</li>
                                            <li>Untuk jenis data ini, pembersihan masih dikunci.</li>
                                        @endif
                                    </ol>
                                </div>
                                <div class="archive-muted" style="margin-top:10px;">
                                    Kalau kamu pilih <strong>Faktur Penjualan</strong> lalu export per tahun atau semester, file SQL yang dibuat berisi tabel utama dan tabel terkait untuk periode itu. File itu bisa diunduh lalu diimport ke MySQL lokal di komputer kamu sebagai arsip periode tersebut.
                                </div>
                            </div>
                        </div>

                        <div class="archive-action-row">
                            <button type="submit" class="btn secondary" formaction="{{ route('archive-data.scan') }}">1. Cek Dulu</button>
                            <button type="submit" class="btn secondary" formaction="{{ route('archive-data.export') }}">2. Buat File Arsip</button>
                            <button type="submit" class="btn secondary" formaction="{{ route('archive-data.check-financial') }}">3. Cek Finansial</button>
                            <button type="submit" id="archive-financial-button" class="btn secondary" formaction="{{ route('archive-data.prepare-financial') }}" {{ $selectedDatasetMode !== 'financial_guarded' ? 'disabled' : '' }}>4. Snapshot Finansial</button>
                            <button type="submit" id="archive-dry-run-button" class="btn secondary" formaction="{{ route('archive-data.purge') }}" onclick="document.getElementById('confirm_purge').value='0';" {{ $selectedDatasetMode === 'locked' ? 'disabled' : '' }}>5. Coba Simulasi Hapus</button>
                            <button type="submit" id="archive-purge-button" class="btn danger" formaction="{{ route('archive-data.purge') }}" onclick="document.getElementById('confirm_purge').value='1';" {{ $selectedDatasetMode === 'locked' ? 'disabled' : '' }}>6. Hapus Data</button>
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

        <div class="card archive-col-12">
            <h3 style="margin-top:0;">Pembersihan Otomatis Log Sistem</h3>
            <p class="archive-muted" style="margin:0 0 12px;">
                Bagian ini tidak perlu kamu arsipkan manual lagi. Scheduler akan membersihkan log sistem secara otomatis sesuai umur data yang disepakati.
            </p>
            <div class="archive-window-grid">
                @foreach($systemCleanupRules as $cleanupKey => $cleanupRule)
                    <div class="archive-window-card">
                        <h3>{{ $cleanupRule['label'] }}</h3>
                        <div class="archive-window-pill">Auto bersih {{ $cleanupRule['days'] }} hari</div>
                        <div class="archive-muted">Key: <code>{{ $cleanupKey }}</code></div>
                    </div>
                @endforeach
            </div>
            <table class="archive-kv" style="margin-top:12px;">
                <tbody>
                <tr><th>Cleanup terakhir</th><td>{{ is_array($latestSystemCleanup) ? \Illuminate\Support\Carbon::parse((string) ($latestSystemCleanup['generated_at'] ?? now()))->format('d-m-Y H:i:s') : '-' }}</td></tr>
                <tr><th>Status</th><td>{{ is_array($latestSystemCleanup) ? strtoupper((string) ($latestSystemCleanup['status'] ?? '-')) : '-' }}</td></tr>
                <tr><th>Total baris dibersihkan</th><td>{{ is_array($latestSystemCleanup) ? number_format((int) ($latestSystemCleanup['total_deleted'] ?? 0), 0, ',', '.') : '0' }}</td></tr>
                </tbody>
            </table>
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

        @if (false) {{-- removed old scan result --}}
            <div class="card archive-col-8">
                <h3 style="margin-top:0;">Hasil Preview Scan</h3>
                <table class="archive-table">
                    <thead>
                    <tr>
                        <th>Dataset</th>
                        <th>Mode Bersihkan Data</th>
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
                                'standard' => 'Bersihkan data biasa',
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
                <h3 style="margin-top:0;">Hasil Bersihkan Data</h3>
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

        {{-- ===== IMPORT ARSIP ===== --}}
        <div class="card archive-col-12">
            <h3 style="margin-top:0;">Import Arsip ke Database Lokal</h3>
            <p class="archive-muted" style="margin:0 0 12px;">
                Pilih file arsip yang sudah dibuat, lalu import ke database yang aktif sekarang.
                Gunakan ini di <strong>environment lokal</strong> untuk melihat atau menganalisis data lama.
            </p>

            @if (!empty($archiveSqlFiles))
                <div class="archive-scroll-box" style="max-height:220px; margin-bottom:14px;">
                    <table class="archive-table">
                        <thead>
                        <tr>
                            <th>File</th>
                            <th>Ukuran</th>
                            <th>Tanggal</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($archiveSqlFiles as $sqlFile)
                            @php
                                $sqlEnc = rtrim(strtr(base64_encode($sqlFile['path']), '+/', '-_'), '=');
                            @endphp
                            <tr>
                                <td style="font-size:12px;"><code>{{ $sqlFile['name'] }}</code></td>
                                <td style="font-size:12px;">{{ $sqlFile['size'] }}</td>
                                <td style="font-size:12px;">{{ $sqlFile['modified'] }}</td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <a href="{{ route('archive-data.download', ['file' => $sqlEnc]) }}" class="btn secondary" style="font-size:11px;padding:4px 8px;">Unduh</a>
                                        <form method="post" action="{{ route('archive-data.import') }}" style="display:inline;" onsubmit="return confirm('Import file ini ke database sekarang?');">
                                            @csrf
                                            <input type="hidden" name="archive_file" value="{{ $sqlFile['path'] }}">
                                            <button type="submit" class="btn secondary" style="font-size:11px;padding:4px 8px;">Import</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="archive-muted">Belum ada file arsip SQL. Buat dulu lewat Mode Mudah → Langkah ②.</p>
            @endif

            <div style="margin-top:12px; padding-top:12px; border-top:1px solid var(--border);">
                <p class="archive-muted" style="margin:0 0 8px; font-size:12px;">Atau upload file SQL dari komputer:</p>
                <form method="post" action="{{ route('archive-data.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <input type="file" name="archive_upload" accept=".sql" style="font-size:13px;">
                        <button type="submit" class="btn secondary" onclick="return confirm('Import file SQL yang dipilih ke database sekarang?');">Upload & Import</button>
                    </div>
                </form>
            </div>

            @if (session('import_success'))
                <div class="archive-flash success" style="margin-top:12px;">{{ session('import_success') }}</div>
            @endif
            @if (session('import_error'))
                <div class="archive-flash error" style="margin-top:12px;">{{ session('import_error') }}</div>
            @endif
        </div>
        {{-- ===== AKHIR IMPORT ARSIP ===== --}}

        <div class="card archive-col-12">
            <h3 style="margin-top:0;">Histori Eksekusi Arsip Data Bisnis</h3>
            @if (!empty($archiveHistory))
                <div class="archive-scroll-box">
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
                </div>
            @else
                <p class="archive-muted" style="margin:0;">Belum ada histori eksekusi arsip yang tersimpan.</p>
            @endif
        </div>

    </div>

@endsection

