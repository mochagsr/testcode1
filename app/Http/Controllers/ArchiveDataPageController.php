<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\DataArchiveRegistry;
use App\Support\DataArchiveService;
use App\Support\SemesterBookService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ArchiveDataPageController extends Controller
{
    public function index(Request $request, DataArchiveService $archiveService, SemesterBookService $semesterBookService): View
    {
        $backupFiles = collect(array_merge(
            File::glob(storage_path('app/backups').DIRECTORY_SEPARATOR.'*') ?: [],
            File::glob(storage_path('app/backups/db').DIRECTORY_SEPARATOR.'*') ?: []
        ))
            ->sort()
            ->values();

        $definitions = DataArchiveRegistry::definitions();
        $selectedScopeType = (string) ($request->old('archive_scope_type', 'year') ?: 'year');
        $selectedYear = (int) ($request->old('archive_year', now()->year - 1) ?: now()->year - 1);
        $semesterOptions = $semesterBookService
            ->buildSemesterOptionCollection([], false, true)
            ->values()
            ->all();
        $selectedSemester = (string) ($request->old('archive_semester', $semesterOptions[0] ?? $semesterBookService->currentSemester()) ?: ($semesterOptions[0] ?? $semesterBookService->currentSemester()));
        $selectedDatasets = array_values(array_filter((array) ($request->old('datasets', ['audit_logs']) ?: ['audit_logs'])));
        $selectedDatasetKey = (string) ($request->old('dataset_key', $selectedDatasets[0] ?? 'audit_logs') ?: 'audit_logs');
        if (! isset($definitions[$selectedDatasetKey])) {
            $selectedDatasetKey = 'audit_logs';
        }
        $selectedDataset = $definitions[$selectedDatasetKey] ?? null;
        $latestRestoreDrill = $archiveService->latestRestoreDrill();
        $latestFinancialSnapshot = $archiveService->latestFinancialSnapshot();
        $latestArchiveReview = $archiveService->latestArchiveReview();
        $archiveHistory = $archiveService->recentExecutionHistory();

        return view('archive_data.index', [
            'latestBackup' => $backupFiles->last(),
            'backupFileCount' => $backupFiles->count(),
            'latestRestoreDrill' => $latestRestoreDrill,
            'latestFinancialSnapshot' => $latestFinancialSnapshot,
            'latestArchiveReview' => $latestArchiveReview,
            'archiveHistory' => $archiveHistory,
            'dbConnection' => (string) config('database.default'),
            'dbHost' => (string) config('database.connections.'.config('database.default').'.host'),
            'appEnv' => (string) config('app.env'),
            'datasets' => $definitions,
            'selectedScopeType' => $selectedScopeType,
            'selectedYear' => $selectedYear,
            'selectedSemester' => $selectedSemester,
            'semesterOptions' => $semesterOptions,
            'selectedDatasets' => $selectedDatasets,
            'selectedDatasetKey' => $selectedDatasetKey,
            'selectedDataset' => $selectedDataset,
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
                    'note' => 'Untuk tabel finansial utama, operator bisa mulai dari tahun atau semester, tergantung periode mana yang memang sudah selesai dan aman diarsipkan.',
                ],
            ],
            'archiveCommands' => [
                'php artisan app:db-backup --gzip',
                'php artisan app:db-restore-test',
                'php artisan app:integrity-check',
                'php artisan app:archive:scan 2025 --dataset=audit_logs',
                'php artisan app:archive:scan --semester=S1-2526 --dataset=sales_invoices',
                'php artisan app:archive:prepare-financial 2024 --dataset=sales_invoices',
            ],
            'plannedArchiveFlow' => [
                'Preview kandidat arsip per tahun atau semester.',
                'Backup penuh ke managed DB AWS lewat command aplikasi.',
                'Download file backup dari server dan simpan juga di komputer lokal / storage pribadi.',
                'Restore drill untuk memastikan backup valid.',
                'Export arsip per tahun atau semester sebelum pembersihan.',
                'Untuk dataset finansial yang didukung, buat snapshot finansial dulu.',
                'Bersihkan data production hanya setelah verifikasi arsip selesai.',
            ],
            'archiveUatChecklist' => [
                'Jalankan backup terbaru dari app server aaPanel ke managed DB aktif.',
                'Download hasil backup ke komputer lokal sebagai copy arsip operator.',
                'Jalankan restore drill dan pastikan status terakhir PASS.',
                'Preview scan untuk satu dataset kecil dulu, misalnya audit log.',
                'Buat export SQL dan cocokkan jumlah baris pada manifest.',
                'Untuk dataset finansial, siapkan snapshot lalu lakukan simulasi hapus dulu.',
                'Setelah hapus data final, jalankan deploy check dan integrity check.',
            ],
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $encoded = (string) $request->query('file', '');
        abort_if($encoded === '', 404);

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        abort_if(! is_string($decoded) || $decoded === '', 404);

        $realPath = realpath($decoded);
        $allowedBase = realpath(storage_path('app/archives'));

        abort_if($realPath === false || $allowedBase === false, 404);
        abort_if(! str_starts_with($realPath, $allowedBase.DIRECTORY_SEPARATOR) && $realPath !== $allowedBase, 403);
        abort_if(! File::exists($realPath), 404);

        return response()->download($realPath, basename($realPath));
    }

    public function scan(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        [$scope, $datasets] = $this->validatedInput($request, $archiveService);

        try {
            $result = $archiveService->scanByScope($scope, $datasets, true);
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
        [$scope, $datasets] = $this->validatedInput($request, $archiveService);

        try {
            $result = $archiveService->exportByScope($scope, $datasets);
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
        [$scope, $datasets] = $this->validatedInput($request, $archiveService);

        try {
            $result = $archiveService->prepareFinancialSnapshotByScope(
                $scope,
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
        [$scope, $datasets] = $this->validatedInput($request, $archiveService);

        try {
            $result = $archiveService->purgeByScope(
                $scope,
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
                ? 'Bersihkan data arsip selesai dijalankan.'
                : 'Simulasi hapus selesai. Belum ada data yang dihapus.');
    }

    /**
     * @return array{0:array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string},1:list<string>}
     */
    private function validatedInput(Request $request, DataArchiveService $archiveService): array
    {
        $validated = $request->validate([
            'archive_scope_type' => ['required', 'string', 'in:year,semester'],
            'archive_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'archive_semester' => ['nullable', 'string', 'max:30'],
            'dataset_key' => ['nullable', 'string'],
            'datasets' => ['nullable', 'array', 'min:1'],
            'datasets.*' => ['required', 'string'],
        ]);

        $scopeType = (string) ($validated['archive_scope_type'] ?? 'year');
        if ($scopeType === 'semester') {
            $semester = trim((string) ($validated['archive_semester'] ?? ''));
            if ($semester === '') {
                abort(422, 'Semester wajib dipilih.');
            }
            $scope = $archiveService->resolveScope('semester', null, $semester);
        } else {
            $year = (int) ($validated['archive_year'] ?? 0);
            if ($year <= 0) {
                abort(422, 'Tahun wajib diisi.');
            }
            $scope = $archiveService->resolveScope('year', $year, null);
        }

        $datasets = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            array_merge(
                (array) ($validated['datasets'] ?? []),
                [trim((string) ($validated['dataset_key'] ?? ''))]
            )
        ))));

        if ($datasets === []) {
            abort(422, 'Jenis data wajib dipilih.');
        }

        return [
            $scope,
            $datasets,
        ];
    }
}
