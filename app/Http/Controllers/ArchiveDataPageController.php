<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\IntegrityCheckLog;
use App\Support\DataArchiveRegistry;
use App\Support\DataArchiveService;
use App\Support\SemesterBookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use SanderMuller\FluentValidation\FluentRule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $definitions = DataArchiveRegistry::businessDefinitions();
        $selectedScopeType = 'year';
        $semesterOptions = $semesterBookService
            ->buildSemesterOptionCollection([], false, true)
            ->values()
            ->all();
        $selectedSemester = (string) ($request->old('archive_semester', $semesterOptions[0] ?? $semesterBookService->currentSemester()) ?: ($semesterOptions[0] ?? $semesterBookService->currentSemester()));
        $defaultDatasetKey = array_key_first($definitions) ?? 'sales_invoices';
        $selectedDatasets = array_values(array_filter((array) ($request->old('datasets', [$defaultDatasetKey]) ?: [$defaultDatasetKey])));
        $selectedDatasetKey = (string) ($request->old('dataset_key', $selectedDatasets[0] ?? $defaultDatasetKey) ?: $defaultDatasetKey);
        if (! isset($definitions[$selectedDatasetKey])) {
            $selectedDatasetKey = $defaultDatasetKey;
        }
        $selectedDataset = $definitions[$selectedDatasetKey] ?? null;
        $datasetYearOptions = $this->datasetYearOptions($definitions, $semesterBookService);
        $yearOptions = $datasetYearOptions[$selectedDatasetKey] ?? [];
        $selectedYear = trim((string) ($request->old('archive_year', $yearOptions[0] ?? '') ?: ($yearOptions[0] ?? '')));
        $datasetMeta = collect($definitions)->mapWithKeys(fn (array $dataset, string $datasetKey): array => [
            $datasetKey => [
                'label' => (string) ($dataset['label'] ?? ''),
                'mode' => (string) ($dataset['purge_mode'] ?? 'locked'),
                'basis' => (($dataset['basis'] ?? 'year') === 'year') ? 'Tahun' : 'Bulan',
                'scope' => implode(' / ', array_map(
                    static fn (string $item): string => $item === 'semester' ? 'semester' : 'tahun',
                    (array) ($dataset['scope_modes'] ?? ['year'])
                )),
                'year_options' => $datasetYearOptions[$datasetKey] ?? [],
                'year_note' => $this->datasetYearNote($datasetKey),
            ],
        ])->all();
        $latestRestoreDrill = $archiveService->latestRestoreDrill();
        $latestFinancialSnapshot = $archiveService->latestFinancialSnapshot();
        $latestArchiveReview = $archiveService->latestArchiveReview();
        $archiveHistory = $archiveService->recentExecutionHistory();
        $latestIntegrityLog = IntegrityCheckLog::query()
            ->latest('checked_at')
            ->latest('id')
            ->first();
        $systemCleanupRules = DataArchiveRegistry::automaticCleanupRules();
        $latestSystemCleanup = $this->latestSystemCleanupSummary();

        return view('archive_data.index', [
            'latestBackup' => $backupFiles->last(),
            'backupFileCount' => $backupFiles->count(),
            'latestRestoreDrill' => $latestRestoreDrill,
            'latestFinancialSnapshot' => $latestFinancialSnapshot,
            'latestArchiveReview' => $latestArchiveReview,
            'archiveHistory' => $archiveHistory,
            'latestIntegrityLog' => $latestIntegrityLog,
            'dbConnection' => (string) config('database.default'),
            'dbHost' => (string) config('database.connections.'.config('database.default').'.host'),
            'appEnv' => (string) config('app.env'),
            'datasets' => $definitions,
            'selectedScopeType' => $selectedScopeType,
            'selectedYear' => $selectedYear,
            'yearOptions' => $yearOptions,
            'datasetYearOptions' => $datasetYearOptions,
            'selectedSemester' => $selectedSemester,
            'semesterOptions' => $semesterOptions,
            'selectedDatasets' => $selectedDatasets,
            'selectedDatasetKey' => $selectedDatasetKey,
            'selectedDataset' => $selectedDataset,
            'datasetMeta' => $datasetMeta,
            'systemCleanupRules' => $systemCleanupRules,
            'latestSystemCleanup' => $latestSystemCleanup,
            'retentionWindows' => [
                [
                    'label' => 'Transaksi ERP',
                    'period' => '60 bulan',
                    'basis' => 'tahun',
                    'note' => 'Untuk tabel finansial utama, operator bisa mulai dari tahun ajaran yang sudah ditutup atau langsung dari semester yang memang sudah selesai dan aman diarsipkan.',
                ],
            ],
            'archiveCommands' => [
                'php artisan app:db-backup --gzip',
                'php artisan app:db-restore-test',
                'php artisan app:integrity-check',
                'php artisan app:archive:scan 2526 --dataset=sales_invoices',
                'php artisan app:archive:scan --semester=S1-2526 --dataset=sales_returns',
                'php artisan app:archive:prepare-financial 2526 --dataset=sales_invoices',
                'php artisan app:system-logs-cleanup',
            ],
            'archiveSqlFiles' => $this->listArchiveSqlFiles(),
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
                'Preview scan untuk satu dataset bisnis dulu, misalnya faktur penjualan atau retur penjualan.',
                'Buat export SQL dan cocokkan jumlah baris pada manifest.',
                'Untuk dataset finansial, siapkan snapshot lalu lakukan simulasi hapus dulu.',
                'Setelah hapus data final, jalankan deploy check dan integrity check.',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @return list<array{name:string, path:string, size:string, modified:string}>
     */
    private function listArchiveSqlFiles(): array
    {
        $files = collect(File::glob(storage_path('app/archives/sql').DIRECTORY_SEPARATOR.'*.sql') ?: [])
            ->sortDesc()
            ->take(20)
            ->values();

        return $files->map(static function (string $path): array {
            $bytes = File::size($path);
            $size = $bytes >= 1048576
                ? number_format($bytes / 1048576, 1).' MB'
                : number_format($bytes / 1024, 0, ',', '.').' KB';

            return [
                'name' => basename($path),
                'path' => $path,
                'size' => $size,
                'modified' => \Illuminate\Support\Carbon::createFromTimestamp(
                    (int) File::lastModified($path)
                )->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d-m-Y H:i'),
            ];
        })->all();
    }

    private function latestSystemCleanupSummary(): ?array
    {
        $files = collect(File::glob(storage_path('app/system-cleanups').DIRECTORY_SEPARATOR.'*.json') ?: [])
            ->sortDesc()
            ->values();

        $latest = $files->first();
        if (! is_string($latest) || $latest === '' || ! File::exists($latest)) {
            return null;
        }

        $decoded = json_decode((string) File::get($latest), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     * @return array<string, array<int, string>>
     */
    private function datasetYearOptions(array $definitions, SemesterBookService $semesterBookService): array
    {
        $closedSemesterYears = $semesterBookService->closedArchiveYearOptions();
        $options = [];

        foreach (array_keys($definitions) as $datasetKey) {
            $options[$datasetKey] = $datasetKey === 'products'
                ? $this->productYearOptions()
                : $closedSemesterYears;
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private function productYearOptions(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('products')) {
            return [];
        }

        return DB::table('products')
            ->whereNotNull('created_at')
            ->orderByDesc('created_at')
            ->pluck('created_at')
            ->map(static function ($createdAt): string {
                return trim((string) optional(\Illuminate\Support\Carbon::parse((string) $createdAt))->format('Y'));
            })
            ->filter(static fn (string $year): bool => preg_match('/^\d{4}$/', $year) === 1)
            ->unique()
            ->values()
            ->all();
    }

    private function datasetYearNote(string $datasetKey): string
    {
        return $datasetKey === 'products'
            ? 'Daftar Barang memakai tahun dari data barang yang sudah ada, jadi tidak perlu menunggu tutup semester.'
            : 'Tahun target hanya diambil dari tahun ajaran yang semester-nya sudah ditutup di menu Pengaturan.';
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

    public function checkFinancial(Request $request): RedirectResponse
    {
        try {
            $exitCode = Artisan::call('app:integrity-check');
            $latest = IntegrityCheckLog::query()
                ->latest('checked_at')
                ->latest('id')
                ->first();
        } catch (\Throwable $e) {
            return back()
                ->withInput($request->all())
                ->with('archive_error', $e->getMessage());
        }

        if (! $latest instanceof IntegrityCheckLog) {
            return back()
                ->withInput($request->all())
                ->with('archive_error', 'Hasil cek finansial belum ditemukan setelah command dijalankan.');
        }

        return back()
            ->withInput($request->all())
            ->with('archive_integrity_result', [
                'is_ok' => (bool) $latest->is_ok,
                'checked_at' => optional($latest->checked_at)?->format('d-m-Y H:i:s'),
                'customer_mismatch_count' => (int) $latest->customer_mismatch_count,
                'supplier_mismatch_count' => (int) $latest->supplier_mismatch_count,
                'invalid_receivable_links' => (int) $latest->invalid_receivable_links,
                'invalid_supplier_links' => (int) $latest->invalid_supplier_links,
                'command_exit_code' => $exitCode,
            ])
            ->with('archive_success', 'Cek finansial selesai dijalankan.');
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

    public function quickScan(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        [$scope, $standardDatasets, $financialDatasets] = $this->quickValidatedInput($request, $archiveService);

        try {
            $result = $archiveService->scanByScope($scope, array_merge($standardDatasets, $financialDatasets), true);
        } catch (\Throwable $e) {
            return back()->withInput($request->all())->with('quick_error', $e->getMessage());
        }

        $total = number_format((int) ($result['grand_total'] ?? 0), 0, ',', '.');

        return back()
            ->withInput($request->all())
            ->with('quick_scan_result', $result)
            ->with('quick_success', "Scan selesai: {$total} baris data ditemukan untuk semester ini.");
    }

    public function quickExport(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        [$scope, $standardDatasets, $financialDatasets] = $this->quickValidatedInput($request, $archiveService);

        try {
            $result = $archiveService->exportByScope($scope, array_merge($standardDatasets, $financialDatasets));
        } catch (\Throwable $e) {
            return back()->withInput($request->all())->with('quick_error', $e->getMessage());
        }

        return back()
            ->withInput($request->all())
            ->with('quick_export_result', $result)
            ->with('quick_success', 'File arsip berhasil dibuat. Unduh dan simpan file-nya.');
    }

    public function quickSnapshot(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        [$scope, , $financialDatasets] = $this->quickValidatedInput($request, $archiveService);

        if ($financialDatasets === []) {
            return back()->withInput($request->all())->with('quick_success', 'Tidak ada dataset finansial, snapshot tidak diperlukan.');
        }

        try {
            $result = $archiveService->prepareFinancialSnapshotByScope($scope, $financialDatasets, null, false);
        } catch (\Throwable $e) {
            return back()->withInput($request->all())->with('quick_error', $e->getMessage());
        }

        // Auto-run integrity check after snapshot
        $integrityOk = null;
        try {
            Artisan::call('app:integrity-check');
            $latest = \App\Models\IntegrityCheckLog::query()
                ->latest('checked_at')->latest('id')->first();
            $integrityOk = $latest instanceof \App\Models\IntegrityCheckLog
                ? (bool) $latest->is_ok
                : null;
        } catch (\Throwable) {
            // non-fatal: snapshot still succeeded
        }

        $integrityMsg = is_null($integrityOk)
            ? 'Snapshot berhasil. Cek kondisi keuangan tidak dapat dijalankan.'
            : ($integrityOk
                ? 'Snapshot berhasil. Kondisi keuangan: Aman — siap lanjut ke Langkah ④.'
                : 'Snapshot berhasil, tetapi ditemukan ketidaksesuaian keuangan. Periksa sebelum hapus data.');

        return back()
            ->withInput($request->all())
            ->with('quick_snapshot_result', $result)
            ->with('quick_integrity_ok', $integrityOk)
            ->with('quick_success', $integrityMsg);
    }

    public function importArchive(Request $request): RedirectResponse
    {
        $allowedBase = realpath(storage_path('app/archives'));

        // From existing archive file on server
        if ($request->has('archive_file')) {
            $filePath = trim((string) $request->input('archive_file', ''));
            $realPath = realpath($filePath);
            if ($realPath === false || $allowedBase === false
                || (! str_starts_with($realPath, $allowedBase.DIRECTORY_SEPARATOR) && $realPath !== $allowedBase)
                || ! File::exists($realPath)
                || ! str_ends_with(strtolower($realPath), '.sql')
            ) {
                return back()->with('import_error', 'File arsip tidak ditemukan atau tidak valid.');
            }
        } elseif ($request->hasFile('archive_upload')) {
            $uploaded = $request->file('archive_upload');
            if (! $uploaded || $uploaded->getClientOriginalExtension() !== 'sql') {
                return back()->with('import_error', 'Hanya file .sql yang diizinkan.');
            }
            $realPath = $uploaded->getRealPath();
            if (! is_string($realPath) || $realPath === '') {
                return back()->with('import_error', 'File upload gagal dibaca.');
            }
        } else {
            return back()->with('import_error', 'Tidak ada file yang dipilih.');
        }

        try {
            $sql = (string) File::get($realPath);
            // Safety: only allow INSERT, CREATE TEMPORARY TABLE, SET, LOCK, UNLOCK statements
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                static function (string $stmt): bool {
                    if ($stmt === '') {
                        return false;
                    }
                    $upper = strtoupper(ltrim($stmt));

                    return str_starts_with($upper, 'INSERT')
                        || str_starts_with($upper, 'SET ')
                        || str_starts_with($upper, 'LOCK ')
                        || str_starts_with($upper, 'UNLOCK')
                        || str_starts_with($upper, '/*!');
                }
            );

            $count = 0;
            DB::transaction(static function () use ($statements, &$count): void {
                foreach ($statements as $stmt) {
                    DB::statement($stmt);
                    $count++;
                }
            });

            return back()->with('import_success', "Import selesai: {$count} statement berhasil dijalankan.");
        } catch (\Throwable $e) {
            return back()->with('import_error', 'Import gagal: '.$e->getMessage());
        }
    }

    public function quickPurge(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        [$scope, $standardDatasets, $financialDatasets] = $this->quickValidatedInput($request, $archiveService);
        $confirm = $request->boolean('confirm_purge');
        $allowSkipped = $request->boolean('allow_skipped_restore');

        $allDeleted = [];
        $errors = [];

        if ($standardDatasets !== []) {
            try {
                $stdResult = $archiveService->purgeByScope($scope, $standardDatasets, $confirm, null, $allowSkipped);
                $allDeleted = array_merge($allDeleted, (array) ($stdResult['deleted'] ?? []));
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($financialDatasets !== [] && $errors === []) {
            try {
                $finResult = $archiveService->purgeByScope($scope, $financialDatasets, $confirm, null, $allowSkipped);
                $allDeleted = array_merge($allDeleted, (array) ($finResult['deleted'] ?? []));
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            return back()->withInput($request->all())->with('quick_error', implode(' — ', $errors));
        }

        $totalDeleted = array_sum($allDeleted);
        $totalFormatted = number_format($totalDeleted, 0, ',', '.');

        return back()
            ->withInput($request->all())
            ->with('quick_purge_result', ['deleted' => $allDeleted, 'total' => $totalDeleted])
            ->with('quick_success', $confirm
                ? "Selesai. {$totalFormatted} baris data semester ini berhasil dihapus."
                : "Simulasi selesai: {$totalFormatted} baris akan dihapus. Belum ada yang dihapus.");
    }

    /**
     * @return array{
     *   0: array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string},
     *   1: list<string>,
     *   2: list<string>
     * }
     */
    private function quickValidatedInput(Request $request, DataArchiveService $archiveService): array
    {
        $semester = trim((string) $request->input('quick_semester', ''));
        if ($semester === '') {
            abort(422, 'Semester wajib dipilih.');
        }

        $scope = $archiveService->resolveScope('semester', null, $semester);
        $definitions = DataArchiveRegistry::businessDefinitions();

        $standardDatasets = [];
        $financialDatasets = [];

        foreach ($definitions as $key => $def) {
            if (! ($def['purge_allowed'] ?? false)) {
                continue;
            }
            $mode = (string) ($def['purge_mode'] ?? 'locked');
            if ($mode === 'standard') {
                $standardDatasets[] = $key;
            } elseif ($mode === 'financial_guarded') {
                $financialDatasets[] = $key;
            }
        }

        return [$scope, $standardDatasets, $financialDatasets];
    }

    public function eligibleScan(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        $cutoffYears = max(1, (int) $request->input('cutoff_years', 5));

        try {
            $result = $archiveService->scanEligible($cutoffYears);
        } catch (\Throwable $e) {
            return back()->with('eligible_error', $e->getMessage());
        }

        $total = number_format($result['grand_total'], 0, ',', '.');

        return back()
            ->with('eligible_scan_result', $result)
            ->with('eligible_success', "Scan selesai: ditemukan {$total} baris data yang sudah lunas dan berusia ≥ {$cutoffYears} tahun.");
    }

    public function eligibleExport(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        $cutoffYears = max(1, (int) $request->input('cutoff_years', 5));

        try {
            $result = $archiveService->exportEligibleToSql($cutoffYears);
        } catch (\Throwable $e) {
            return back()->with('eligible_error', $e->getMessage());
        }

        $total = number_format($result['grand_total'], 0, ',', '.');
        $fileName = basename($result['sql_file']);

        return back()
            ->with('eligible_export_result', $result)
            ->with('eligible_success', "Export berhasil: {$total} baris disimpan ke file {$fileName}. Unduh dan simpan sebelum hapus.");
    }

    public function eligibleSoftDelete(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        $cutoffYears = max(1, (int) $request->input('cutoff_years', 5));

        if (! $request->boolean('confirm_soft_delete')) {
            return back()->with('eligible_error', 'Centang konfirmasi sebelum melanjutkan soft delete.');
        }

        try {
            $result = $archiveService->softDeleteEligible($cutoffYears);
        } catch (\Throwable $e) {
            return back()->with('eligible_error', $e->getMessage());
        }

        $total = number_format($result['total'], 0, ',', '.');

        return back()
            ->with('eligible_soft_delete_result', $result)
            ->with('eligible_success', "Soft delete selesai: {$total} baris ditandai terhapus. Data belum benar-benar hilang.");
    }

    public function eligibleHardDelete(Request $request, DataArchiveService $archiveService): RedirectResponse
    {
        if (! $request->boolean('confirm_hard_delete')) {
            return back()->with('eligible_error', 'Centang konfirmasi sebelum menghapus permanen.');
        }

        try {
            $result = $archiveService->hardDeleteAllArchived();
        } catch (\Throwable $e) {
            return back()->with('eligible_error', $e->getMessage());
        }

        $total = number_format($result['total'], 0, ',', '.');

        return back()
            ->with('eligible_hard_delete_result', $result)
            ->with('eligible_success', "Hapus permanen selesai: {$total} baris dihapus dari database.");
    }

    /**
     * @return array{0:array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string},1:list<string>}
     */
    private function validatedInput(Request $request, DataArchiveService $archiveService): array
    {
        $validated = $request->validate([
            'archive_scope_type' => FluentRule::string()->required()->in(['year', 'semester']),
            'archive_year' => FluentRule::string()->nullable()->regex('/^\d{4}$/'),
            'archive_semester' => FluentRule::string()->nullable()->max(30),
            'dataset_key' => FluentRule::string()->nullable(),
            'datasets' => FluentRule::array()->nullable()->min(1),
            'datasets.*' => FluentRule::string()->required(),
        ]);

        $scopeType = (string) ($validated['archive_scope_type'] ?? 'year');
        if ($scopeType === 'semester') {
            $semester = trim((string) ($validated['archive_semester'] ?? ''));
            if ($semester === '') {
                abort(422, 'Semester wajib dipilih.');
            }
            $scope = $archiveService->resolveScope('semester', null, $semester);
        } else {
            $year = trim((string) ($validated['archive_year'] ?? ''));
            if ($year === '') {
                abort(422, 'Tahun target belum ada. Tutup periode semester dulu di menu Pengaturan.');
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

        $allowedDatasets = array_keys(DataArchiveRegistry::businessDefinitions());
        $invalidDatasets = array_values(array_diff($datasets, $allowedDatasets));
        if ($invalidDatasets !== []) {
            abort(422, 'Jenis data ini dibersihkan otomatis oleh sistem atau belum tersedia untuk arsip manual.');
        }

        return [
            $scope,
            $datasets,
        ];
    }
}
