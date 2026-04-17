<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DataArchiveRegistry;
use App\Support\DataArchiveService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ArchiveDataPageController extends Controller
{
    public function index(Request $request, DataArchiveService $archiveService): View
    {
        $backupFiles = collect(array_merge(
            File::glob(storage_path('app/backups').DIRECTORY_SEPARATOR.'*') ?: [],
            File::glob(storage_path('app/backups/db').DIRECTORY_SEPARATOR.'*') ?: []
        ))
            ->sort()
            ->values();

        $definitions = DataArchiveRegistry::definitions();
        $selectedYear = (int) ($request->old('archive_year', now()->year - 1) ?: now()->year - 1);
        $selectedDatasets = array_values(array_filter((array) ($request->old('datasets', ['audit_logs']) ?: ['audit_logs'])));
        $latestRestoreDrill = $archiveService->latestRestoreDrill();
        $latestFinancialSnapshot = $archiveService->latestFinancialSnapshot();

        return view('archive_data.index', [
            'latestBackup' => $backupFiles->last(),
            'backupFileCount' => $backupFiles->count(),
            'latestRestoreDrill' => $latestRestoreDrill,
            'latestFinancialSnapshot' => $latestFinancialSnapshot,
            'dbConnection' => (string) config('database.default'),
            'dbHost' => (string) config('database.connections.'.config('database.default').'.host'),
            'appEnv' => (string) config('app.env'),
            'datasets' => $definitions,
            'selectedYear' => $selectedYear,
            'selectedDatasets' => $selectedDatasets,
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
                'php artisan app:archive:scan 2025 --dataset=audit_logs',
                'php artisan app:archive:prepare-financial 2024 --dataset=sales_invoices',
            ],
            'plannedArchiveFlow' => [
                'Preview kandidat arsip per tahun.',
                'Backup penuh ke managed DB AWS lewat command aplikasi.',
                'Restore drill untuk memastikan backup valid.',
                'Export arsip per tahun sebelum pembersihan.',
                'Untuk dataset finansial yang didukung, buat snapshot finansial dulu.',
                'Purge data production hanya setelah verifikasi arsip selesai.',
            ],
        ]);
    }

    public function scan(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        [$year, $datasets] = $this->validatedInput($request);

        try {
            $result = $archiveService->scan($year, $datasets);
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->all())
                ->with('archive_error', $e->getMessage());
        }

        return back()
            ->withInput($request->all())
            ->with('archive_scan_result', $result)
            ->with('archive_success', 'Preview arsip berhasil dibuat.');
    }

    public function export(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        [$year, $datasets] = $this->validatedInput($request);

        try {
            $result = $archiveService->export($year, $datasets);
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->all())
                ->with('archive_error', $e->getMessage());
        }

        return back()
            ->withInput($request->all())
            ->with('archive_export_result', $result)
            ->with('archive_success', 'Export arsip berhasil dibuat.');
    }

    public function prepareFinancial(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        [$year, $datasets] = $this->validatedInput($request);

        try {
            $result = $archiveService->prepareFinancialSnapshot(
                $year,
                $datasets,
                null,
                (bool) $request->boolean('rebuild_journal')
            );
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->all())
                ->with('archive_error', $e->getMessage());
        }

        return back()
            ->withInput($request->all())
            ->with('archive_financial_result', $result)
            ->with('archive_success', 'Snapshot finansial berhasil dibuat.');
    }

    public function purge(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        [$year, $datasets] = $this->validatedInput($request);

        try {
            $result = $archiveService->purge(
                $year,
                $datasets,
                (bool) $request->boolean('confirm_purge'),
                null,
                (bool) $request->boolean('allow_skipped_restore')
            );
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->all())
                ->with('archive_error', $e->getMessage());
        }

        return back()
            ->withInput($request->all())
            ->with('archive_purge_result', $result)
            ->with('archive_success', $request->boolean('confirm_purge')
                ? 'Purge arsip selesai dijalankan.'
                : 'Dry-run purge selesai. Belum ada data yang dihapus.');
    }

    /**
     * @return array{0:int,1:list<string>}
     */
    private function validatedInput(Request $request): array
    {
        $validated = $request->validate([
            'archive_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'datasets' => ['required', 'array', 'min:1'],
            'datasets.*' => ['required', 'string'],
        ]);

        return [
            (int) $validated['archive_year'],
            array_values(array_unique(array_map(static fn (mixed $value): string => strtolower(trim((string) $value)), (array) $validated['datasets']))),
        ];
    }
}
