@extends('layouts.app')

@section('title', 'Arsip Data - PgPOS ERP')

@section('content')
    <style>
        .archive-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 12px;
        }
        .archive-col-12 { grid-column: span 12; }
        .archive-col-6 { grid-column: span 6; }
        @media (max-width: 1024px) {
            .archive-col-6 { grid-column: span 12; }
        }
        .archive-kv th { width: 240px; }
        .archive-list {
            margin: 0;
            padding-left: 18px;
        }
        .archive-list li + li {
            margin-top: 6px;
        }
        .archive-window-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 10px;
        }
        .archive-window-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            background: color-mix(in srgb, var(--card) 96%, var(--bg));
        }
        .archive-window-card h3 {
            margin: 0 0 6px;
            font-size: 15px;
        }
        .archive-window-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 4px 10px;
            background: color-mix(in srgb, var(--badge-neutral-bg) 92%, var(--card));
            color: var(--badge-neutral-text);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
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
    </style>

    <div class="archive-grid">
        <div class="card archive-col-12">
            <h1 class="page-title" style="margin:0 0 8px 0;">Arsip Data</h1>
            <p class="archive-muted" style="margin:0;">
                Halaman ini dipakai untuk menyiapkan arsip data production secara aman. Untuk `erpos` dengan managed DB AWS,
                backup tetap dijalankan dari app server Laravel, tetapi target database-nya tetap koneksi managed MySQL yang aktif di `.env`.
            </p>
        </div>

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
                </tbody>
            </table>
        </div>

        <div class="card archive-col-6">
            <h3 style="margin-top:0;">Prinsip Aman</h3>
            <ol class="archive-list">
                <li>Arsip transaksi utama default dibaca berdasarkan tahun, supaya operator lebih mudah meninjau periode yang akan dipindah.</li>
                <li>Backup penuh wajib dibuat dulu sebelum ekspor atau pembersihan data production.</li>
                <li>Restore drill wajib lulus dulu, terutama karena database berada di AWS Lightsail Managed MySQL.</li>
                <li>Hapus data production hanya setelah arsip diverifikasi dan periode yang dipilih benar.</li>
            </ol>
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

        <div class="card archive-col-6">
            <h3 style="margin-top:0;">Command yang Dipakai Sekarang</h3>
            <ul class="archive-list">
                @foreach($archiveCommands as $command)
                    <li><code>{{ $command }}</code></li>
                @endforeach
            </ul>
            <p class="archive-muted" style="margin:10px 0 0;">
                Tiga command ini dijalankan dari folder project Laravel di aaPanel, tetapi tetap bekerja ke managed DB AWS lewat koneksi `.env` yang aktif.
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
                Jadi untuk transaksi ERP, ya, titik kerja yang paling aman memang berdasarkan tahun. Operator tinggal menentukan tahun target, lalu ikuti alur backup, verifikasi, dan pembersihan.
            </p>
        </div>

        <div class="card archive-col-12">
            <h3 style="margin-top:0;">Catatan Command Arsip</h3>
            <p class="archive-muted" style="margin:0 0 8px;">
                Command arsip berbasis tahun sekarang sudah tersedia dengan pola berikut:
            </p>
            <pre class="archive-code">php artisan app:archive:scan 2021 --dataset=sales_invoices
php artisan app:archive:export 2021 --dataset=sales_invoices
php artisan app:archive:purge 2021 --dataset=audit_logs --confirm</pre>
            <p class="archive-muted" style="margin:10px 0 0;">
                Untuk tahap aman pertama, `purge` otomatis hanya dibuka untuk dataset log/ops yang memang aman dibersihkan. Dataset finansial seperti faktur, ledger piutang, dan hutang supplier tetap bisa di-`scan` dan di-`export`, tetapi purge masih dikunci sampai rebuilder histori finansialnya disiapkan.
            </p>
        </div>
    </div>
@endsection
