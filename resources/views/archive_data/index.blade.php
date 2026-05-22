@extends('layouts.app')

@section('title', 'Arsip Data Bisnis - '.config('app.name', 'Laravel'))

@section('content')
    @php
        $quickScanResult       = session('quick_scan_result');
        $quickExportResult     = session('quick_export_result');
        $quickSnapshotResult   = session('quick_snapshot_result');
        $quickPurgeResult      = session('quick_purge_result');
        $quickIntegrityOk      = session('quick_integrity_ok');      // null|bool
        $eligibleScanResult    = session('eligible_scan_result');
        $eligibleExportResult  = session('eligible_export_result');
        $eligibleSoftDelResult = session('eligible_soft_delete_result');
        $eligibleHardDelResult = session('eligible_hard_delete_result');
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

        @if (session('quick_success'))
            <div class="archive-col-12 archive-flash success">{{ session('quick_success') }}</div>
        @endif
        @if (session('quick_error'))
            <div class="archive-col-12 archive-flash error">{{ session('quick_error') }}</div>
        @endif

        @if (!empty($semesterOptions))
            <div class="archive-col-12" style="background:#fff8e1;border:1px solid #f0c040;border-radius:10px;padding:12px 14px;font-size:13px;color:#5a4000;">
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

        {{-- ===== ARSIP LUNAS 5 TAHUN ===== --}}
        <div class="card archive-col-12">
            <h2 style="margin:0 0 4px;font-size:18px;">Arsipkan Data Lunas — 5 Langkah</h2>
            <p class="archive-muted" style="margin:0 0 16px;">
                Arsipkan transaksi yang sudah lunas dan berusia minimal 5 tahun. Berbeda dari Mode Mudah yang berbasis semester,
                metode ini memilih berdasarkan status pembayaran (<strong>lunas</strong>) dan tanggal lunas yang sebenarnya.
                Partial payment, data belum lunas, dan customer <em>tidak</em> dihapus.
            </p>

            @if (session('eligible_success'))
                <div class="archive-flash success" style="margin-bottom:14px;">{{ session('eligible_success') }}</div>
            @endif
            @if (session('eligible_error'))
                <div class="archive-flash error" style="margin-bottom:14px;">{{ session('eligible_error') }}</div>
            @endif

            @php
                $defaultCutoff = old('cutoff_years', 5);
            @endphp

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">

                {{-- Langkah 1: Scan --}}
                <div class="archive-step-box">
                    <h4>① Cek Data Eligible</h4>
                    <p class="archive-muted" style="margin:0 0 10px;font-size:12px;">
                        Hitung berapa baris data yang sudah lunas ≥ N tahun dan siap diarsipkan.
                    </p>
                    @if ($eligibleScanResult)
                        <div style="font-size:13px;margin-bottom:10px;">
                            <strong>Cutoff:</strong> {{ $eligibleScanResult['cutoff_date'] ?? '-' }}<br>
                            <strong>Total:</strong> {{ number_format((int)($eligibleScanResult['grand_total'] ?? 0), 0, ',', '.') }} baris
                            @foreach (($eligibleScanResult['datasets'] ?? []) as $dsKey => $ds)
                                @if ((int)($ds['count'] ?? 0) > 0)
                                    <br><span class="archive-muted" style="font-size:11px;">
                                        {{ $ds['label'] ?? $dsKey }}: {{ number_format((int)$ds['count'], 0, ',', '.') }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <form method="post" action="{{ route('archive-data.eligible-scan') }}">
                        @csrf
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                            <label style="font-size:12px;white-space:nowrap;">Minimal usia:</label>
                            <select name="cutoff_years" style="font-size:12px;width:80px;">
                                @foreach ([3,4,5,6,7,8,10] as $yr)
                                    <option value="{{ $yr }}" @selected((int)$defaultCutoff === $yr)>{{ $yr }} tahun</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn secondary">Cek Data Eligible</button>
                    </form>
                </div>

                {{-- Langkah 2: Export --}}
                <div class="archive-step-box">
                    <h4>② Buat File Arsip</h4>
                    <p class="archive-muted" style="margin:0 0 10px;font-size:12px;">
                        Ekspor semua data eligible ke file SQL sebagai cadangan sebelum dihapus.
                    </p>
                    @if ($eligibleExportResult)
                        @php
                            $elSqlEnc = !empty($eligibleExportResult['sql_file'])
                                ? rtrim(strtr(base64_encode((string)$eligibleExportResult['sql_file']), '+/', '-_'), '=')
                                : null;
                            $elManEnc = !empty($eligibleExportResult['manifest_file'])
                                ? rtrim(strtr(base64_encode((string)$eligibleExportResult['manifest_file']), '+/', '-_'), '=')
                                : null;
                        @endphp
                        <div style="margin-bottom:10px;font-size:13px;">
                            <strong>{{ number_format((int)($eligibleExportResult['grand_total'] ?? 0), 0, ',', '.') }} baris</strong>
                            tersimpan. Cutoff: {{ $eligibleExportResult['cutoff_date'] ?? '-' }}
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">
                            @if ($elSqlEnc)
                                <a href="{{ route('archive-data.download', ['file' => $elSqlEnc]) }}"
                                   class="btn secondary" style="font-size:12px;">Unduh SQL</a>
                            @endif
                            @if ($elManEnc)
                                <a href="{{ route('archive-data.download', ['file' => $elManEnc]) }}"
                                   class="btn secondary" style="font-size:12px;">Unduh Manifest</a>
                            @endif
                        </div>
                    @endif
                    <form method="post" action="{{ route('archive-data.eligible-export') }}">
                        @csrf
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                            <label style="font-size:12px;white-space:nowrap;">Minimal usia:</label>
                            <select name="cutoff_years" style="font-size:12px;width:80px;">
                                @foreach ([3,4,5,6,7,8,10] as $yr)
                                    <option value="{{ $yr }}" @selected((int)$defaultCutoff === $yr)>{{ $yr }} tahun</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn secondary">Buat File Arsip</button>
                    </form>
                </div>

                {{-- Langkah 3: Download --}}
                <div class="archive-step-box">
                    <h4>③ Unduh & Simpan</h4>
                    <p class="archive-muted" style="margin:0 0 10px;font-size:12px;">
                        Unduh file SQL ke komputer lokal. Simpan di hardisk sebelum lanjut ke langkah berikutnya.
                    </p>
                    @if (!empty($archiveSqlFiles))
                        @php $latestSql = collect($archiveSqlFiles)->first(fn($f) => str_contains($f['name'], 'eligible-archive')); @endphp
                        @if ($latestSql)
                            @php $latestSqlEnc = rtrim(strtr(base64_encode($latestSql['path']), '+/', '-_'), '='); @endphp
                            <div style="font-size:12px;margin-bottom:8px;color:var(--muted);">
                                File terbaru: <code>{{ $latestSql['name'] }}</code> ({{ $latestSql['size'] }})
                            </div>
                            <a href="{{ route('archive-data.download', ['file' => $latestSqlEnc]) }}"
                               class="btn secondary" style="font-size:12px;">Unduh File Terbaru</a>
                        @else
                            <p class="archive-muted" style="margin:0;font-size:12px;">Belum ada file eligible-archive. Jalankan Langkah ② dulu.</p>
                        @endif
                    @else
                        <p class="archive-muted" style="margin:0;font-size:12px;">Belum ada file arsip SQL.</p>
                    @endif
                </div>

                {{-- Langkah 4: Soft Delete --}}
                <div class="archive-step-box">
                    <h4>④ Soft Delete</h4>
                    <p class="archive-muted" style="margin:0 0 10px;font-size:12px;">
                        Tandai data eligible sebagai terhapus. Data masih ada di database sampai Langkah ⑤.
                    </p>
                    @if ($eligibleSoftDelResult)
                        <div style="font-size:13px;margin-bottom:10px;color:#0f6b2f;">
                            ✓ {{ number_format((int)($eligibleSoftDelResult['total'] ?? 0), 0, ',', '.') }} baris ditandai terhapus.
                        </div>
                    @endif
                    <form method="post" action="{{ route('archive-data.eligible-soft-delete') }}"
                          onsubmit="return confirm('Tandai data eligible sebagai terhapus (soft delete)? Data belum benar-benar hilang.');">
                        @csrf
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                            <label style="font-size:12px;white-space:nowrap;">Minimal usia:</label>
                            <select name="cutoff_years" style="font-size:12px;width:80px;">
                                @foreach ([3,4,5,6,7,8,10] as $yr)
                                    <option value="{{ $yr }}" @selected((int)$defaultCutoff === $yr)>{{ $yr }} tahun</option>
                                @endforeach
                            </select>
                        </div>
                        <label style="display:flex;align-items:flex-start;gap:8px;font-size:12px;margin-bottom:10px;cursor:pointer;">
                            <input type="checkbox" name="confirm_soft_delete" value="1" style="width:14px;height:14px;margin-top:2px;flex-shrink:0;accent-color:var(--primary,#3b82f6);">
                            <span>Saya sudah unduh dan simpan file arsip (Langkah ③)</span>
                        </label>
                        <button type="submit" class="btn secondary">Soft Delete</button>
                    </form>
                </div>

                {{-- Langkah 5: Hard Delete --}}
                <div class="archive-step-box" style="border-color:color-mix(in srgb,#e53e3e 40%,var(--border));">
                    <h4 style="color:#c0392b;">⑤ Hapus Permanen</h4>
                    <p class="archive-muted" style="margin:0 0 10px;font-size:12px;">
                        Hapus <strong>semua data yang sudah di-soft-delete</strong> dari database secara permanen.
                        Tidak bisa dibatalkan.
                    </p>
                    @if ($eligibleHardDelResult)
                        <div style="font-size:13px;margin-bottom:10px;color:#8e1d1d;">
                            ✓ {{ number_format((int)($eligibleHardDelResult['total'] ?? 0), 0, ',', '.') }} baris dihapus permanen.
                        </div>
                    @endif
                    <form method="post" action="{{ route('archive-data.eligible-hard-delete') }}"
                          onsubmit="return confirm('HAPUS PERMANEN semua data yang sudah di-soft-delete? Tindakan ini TIDAK BISA dibatalkan.');">
                        @csrf
                        <label style="display:flex;align-items:flex-start;gap:8px;font-size:12px;margin-bottom:10px;cursor:pointer;">
                            <input type="checkbox" name="confirm_hard_delete" value="1" style="width:14px;height:14px;margin-top:2px;flex-shrink:0;accent-color:var(--primary,#3b82f6);">
                            <span>Saya yakin ingin menghapus permanen semua data yang sudah di-soft-delete</span>
                        </label>
                        <button type="submit" class="btn danger">Hapus Permanen</button>
                    </form>
                </div>

            </div>
        </div>
        {{-- ===== AKHIR ARSIP LUNAS 5 TAHUN ===== --}}

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

