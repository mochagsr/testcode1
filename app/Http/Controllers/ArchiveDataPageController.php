<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ArchiveDataPageController extends Controller
{
    public function index(): View
    {
        $backupFiles = collect(Storage::disk('local')->files('backups'))
            ->merge(Storage::disk('local')->files('backups/db'))
            ->sort()
            ->values();

        $latestRestoreDrill = Schema::hasTable('restore_drill_logs')
            ? DB::table('restore_drill_logs')->latest('tested_at')->latest('id')->first()
            : null;

        return view('archive_data.index', [
            'latestBackup' => $backupFiles->last(),
            'backupFileCount' => $backupFiles->count(),
            'latestRestoreDrill' => $latestRestoreDrill,
            'dbConnection' => (string) config('database.default'),
            'dbHost' => (string) config('database.connections.'.config('database.default').'.host'),
            'appEnv' => (string) config('app.env'),
            'retentionWindows' => [
                [
                    'label' => 'Audit Log',
                    'period' => '3 bulan',
                    'basis' => 'bulan',
                    'note' => 'Cukup untuk jejak operasional harian. Histori lebih lama tetap aman di backup / arsip SQL.',
                ],
                [
                    'label' => 'Transaksi ERP',
                    'period' => '60 bulan',
                    'basis' => 'tahun',
                    'note' => 'Untuk tabel finansial utama, titik aman paling mudah dibaca operator adalah berdasarkan tahun.',
                ],
            ],
            'archiveCommands' => [
                'php artisan app:db-backup --gzip',
                'php artisan app:db-restore-test',
                'php artisan app:integrity-check',
            ],
            'plannedArchiveFlow' => [
                'Preview kandidat arsip per tahun.',
                'Backup penuh ke managed DB AWS lewat command aplikasi.',
                'Restore drill untuk memastikan backup valid.',
                'Export arsip per tahun sebelum pembersihan.',
                'Purge data production hanya setelah verifikasi arsip selesai.',
            ],
        ]);
    }
}
