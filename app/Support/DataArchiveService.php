<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Customer;
use App\Models\ReceivablePayment;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DataArchiveService
{
    public function __construct(
        private readonly SemesterBookService $semesterBookService
    ) {}

    /**
     * @param  list<string>  $requestedDatasets
     * @return array{
     *   period_type:string,
     *   period_value:string,
     *   year:int,
     *   semester:?string,
     *   datasets:array<string, array{
     *     label:string,
     *     basis:string,
     *     purge_allowed:bool,
     *     purge_mode:string,
     *     financial:bool,
     *     total_rows:int,
     *     tables:array<int, array{table:string, rows:int}>
     *   }>,
     *   missing:list<string>,
     *   grand_total:int
     * }
     */
    public function scan(int $year, array $requestedDatasets, bool $persistHistory = false): array
    {
        return $this->scanByScope($this->yearScope($year), $requestedDatasets, $persistHistory);
    }

    /**
     * @param  list<string>  $requestedDatasets
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}  $scope
     * @return array{
     *   period_type:string,
     *   period_value:string,
     *   year:int,
     *   semester:?string,
     *   datasets:array<string, array{
     *     label:string,
     *     basis:string,
     *     purge_allowed:bool,
     *     purge_mode:string,
     *     financial:bool,
     *     total_rows:int,
     *     tables:array<int, array{table:string, rows:int}>
     *   }>,
     *   missing:list<string>,
     *   grand_total:int
     * }
     */
    public function scanByScope(array $scope, array $requestedDatasets, bool $persistHistory = false): array
    {
        $definitions = DataArchiveRegistry::resolve($requestedDatasets);
        $missing = DataArchiveRegistry::missing($definitions, $requestedDatasets);
        $grandTotal = 0;
        $datasets = [];

        foreach ($definitions as $key => $definition) {
            $tableSummaries = [];
            $datasetTotal = 0;

            foreach ($definition['tables'] as $tableDefinition) {
                if (! Schema::hasTable($tableDefinition['table'])) {
                    $tableSummaries[] = [
                        'table' => $tableDefinition['table'],
                        'rows' => 0,
                    ];

                    continue;
                }

                $rows = $this->tableQuery($definition, $tableDefinition, $scope)->count();
                $datasetTotal += (int) $rows;
                $tableSummaries[] = [
                    'table' => $tableDefinition['table'],
                    'rows' => (int) $rows,
                ];
            }

            $grandTotal += $datasetTotal;
            $datasets[$key] = [
                'label' => $definition['label'],
                'basis' => $definition['basis'],
                'purge_allowed' => $definition['purge_allowed'],
                'purge_mode' => (string) ($definition['purge_mode'] ?? 'locked'),
                'financial' => $definition['financial'],
                'total_rows' => $datasetTotal,
                'tables' => $tableSummaries,
            ];
        }

        $payload = [
            'period_type' => $scope['type'],
            'period_value' => $scope['value'],
            'year' => (int) ($scope['year'] ?? 0),
            'semester' => $scope['semester'],
            'datasets' => $datasets,
            'missing' => $missing,
            'grand_total' => $grandTotal,
        ];

        if ($persistHistory) {
            $this->writeArchiveRecord('scans', 'scan', [
                'generated_at' => now('Asia/Jakarta')->toIso8601String(),
                'scope' => $scope,
                'summary' => $payload,
                'datasets' => array_keys($datasets),
                'grand_total' => $grandTotal,
            ]);
        }

        return $payload;
    }

    /**
     * @param  list<string>  $requestedDatasets
     * @return array{
     *   sql_file:string,
     *   manifest_file:string,
     *   summary:array{
     *     period_type:string,
     *     period_value:string,
     *     year:int,
     *     semester:?string,
     *     datasets:array<string, array{
     *       label:string,
     *       basis:string,
     *       purge_allowed:bool,
     *       purge_mode:string,
     *       financial:bool,
     *       total_rows:int,
     *       tables:array<int, array{table:string, rows:int}>
     *     }>,
     *     missing:list<string>,
     *     grand_total:int
     *   }
     * }
     */
    public function export(int $year, array $requestedDatasets, ?string $directory = null): array
    {
        return $this->exportByScope($this->yearScope($year), $requestedDatasets, $directory);
    }

    /**
     * @param  list<string>  $requestedDatasets
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}  $scope
     * @return array{
     *   sql_file:string,
     *   manifest_file:string,
     *   summary:array{
     *     period_type:string,
     *     period_value:string,
     *     year:int,
     *     semester:?string,
     *     datasets:array<string, array{
     *       label:string,
     *       basis:string,
     *       purge_allowed:bool,
     *       purge_mode:string,
     *       financial:bool,
     *       total_rows:int,
     *       tables:array<int, array{table:string, rows:int}>
     *     }>,
     *     missing:list<string>,
     *     grand_total:int
     *   }
     * }
     */
    public function exportByScope(array $scope, array $requestedDatasets, ?string $directory = null): array
    {
        $summary = $this->scanByScope($scope, $requestedDatasets);
        $datasetKeys = array_keys($summary['datasets']);
        $slug = $datasetKeys === [] ? 'empty' : implode('-', array_slice($datasetKeys, 0, 3));
        if (count($datasetKeys) > 3) {
            $slug .= '-plus';
        }

        $timestamp = now('Asia/Jakarta')->format('Ymd-His');
        $baseDir = $directory ?: storage_path('app/archives');
        $sqlDir = $baseDir.DIRECTORY_SEPARATOR.'sql';
        $manifestDir = $baseDir.DIRECTORY_SEPARATOR.'manifests';
        File::ensureDirectoryExists($sqlDir);
        File::ensureDirectoryExists($manifestDir);

        $scopeSlug = $this->scopeSlug($scope);
        $sqlFile = $sqlDir.DIRECTORY_SEPARATOR.sprintf('archive-%s-%s-%s.sql', $scopeSlug, $slug, $timestamp);
        $manifestFile = $manifestDir.DIRECTORY_SEPARATOR.sprintf('archive-%s-%s-%s.json', $scopeSlug, $slug, $timestamp);

        $handle = fopen($sqlFile, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Failed creating archive SQL file: '.$sqlFile);
        }

        fwrite($handle, '-- '.config('app.name', 'Laravel')." archive export\n");
        fwrite($handle, '-- Generated at: '.now('Asia/Jakarta')->toDateTimeString()."\n");
        fwrite($handle, '-- Scope filter: '.$this->scopeLabel($scope)."\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($datasetKeys as $datasetKey) {
            $definition = DataArchiveRegistry::definitions()[$datasetKey];
            fwrite($handle, sprintf("-- Dataset: %s (%s)\n", $definition['label'], $datasetKey));

            foreach ($definition['tables'] as $tableDefinition) {
                if (! Schema::hasTable($tableDefinition['table'])) {
                    continue;
                }

                $count = $this->tableQuery($definition, $tableDefinition, $scope)->count();
                if ((int) $count === 0) {
                    continue;
                }

                fwrite($handle, sprintf("-- Table: %s | rows: %d\n", $tableDefinition['table'], $count));
                $this->writeTableInsertStatements($handle, $definition, $tableDefinition, $scope);
                fwrite($handle, "\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        $manifest = [
            'generated_at' => now('Asia/Jakarta')->toIso8601String(),
            'scope' => $scope,
            'period_type' => $scope['type'],
            'period_value' => $scope['value'],
            'year' => (int) ($scope['year'] ?? 0),
            'semester' => $scope['semester'],
            'datasets' => $datasetKeys,
            'sql_file' => $sqlFile,
            'summary' => $summary,
        ];
        File::put($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'sql_file' => $sqlFile,
            'manifest_file' => $manifestFile,
            'summary' => $summary,
        ];
    }

    /**
     * @param  list<string>  $requestedDatasets
     * @return array{
     *   summary:array{
     *     period_type:string,
     *     period_value:string,
     *     year:int,
     *     semester:?string,
     *     datasets:array<string, array{
     *       label:string,
     *       basis:string,
     *       purge_allowed:bool,
     *       purge_mode:string,
     *       financial:bool,
     *       total_rows:int,
     *       tables:array<int, array{table:string, rows:int}>
     *     }>,
     *     missing:list<string>,
     *     grand_total:int
     *   },
     *   manifest_file:string,
     *   backup_file:string,
     *   restore_status:string,
     *   deleted:array<string, int>,
     *   snapshot_file:?string,
     *   post_check?:array<string, mixed>|null
     * }
     */
    public function purge(
        int $year,
        array $requestedDatasets,
        bool $confirm,
        ?string $manifestFile = null,
        bool $allowSkippedRestore = false
    ): array {
        return $this->purgeByScope(
            $this->yearScope($year),
            $requestedDatasets,
            $confirm,
            $manifestFile,
            $allowSkippedRestore
        );
    }

    /**
     * @param  list<string>  $requestedDatasets
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}  $scope
     * @return array{
     *   summary:array{
     *     period_type:string,
     *     period_value:string,
     *     year:int,
     *     semester:?string,
     *     datasets:array<string, array{
     *       label:string,
     *       basis:string,
     *       purge_allowed:bool,
     *       purge_mode:string,
     *       financial:bool,
     *       total_rows:int,
     *       tables:array<int, array{table:string, rows:int}>
     *     }>,
     *     missing:list<string>,
     *     grand_total:int
     *   },
     *   manifest_file:string,
     *   backup_file:string,
     *   restore_status:string,
     *   deleted:array<string, int>,
     *   snapshot_file:?string,
     *   post_check?:array<string, mixed>|null
     * }
     */
    public function purgeByScope(
        array $scope,
        array $requestedDatasets,
        bool $confirm,
        ?string $manifestFile = null,
        bool $allowSkippedRestore = false
    ): array {
        $summary = $this->scanByScope($scope, $requestedDatasets);
        $datasetKeys = array_keys($summary['datasets']);
        $definitions = DataArchiveRegistry::definitions();

        $locked = [];
        $standard = [];
        $financialGuarded = [];
        foreach ($datasetKeys as $datasetKey) {
            $mode = (string) ($definitions[$datasetKey]['purge_mode'] ?? 'locked');
            if ($mode === 'locked') {
                $locked[] = $datasetKey;

                continue;
            }
            if ($mode === 'financial_guarded') {
                $financialGuarded[] = $datasetKey;

                continue;
            }
            $standard[] = $datasetKey;
        }

        if ($locked !== []) {
            throw new \RuntimeException(
                'Purge untuk dataset ini masih dikunci. Dataset terkunci: '.implode(', ', $locked)
                .'. Gunakan scan/export dulu atau siapkan rebuilder yang sesuai.'
            );
        }
        if ($standard !== [] && $financialGuarded !== []) {
            throw new \RuntimeException('Jangan campur purge dataset log/ops dengan dataset finansial dalam satu eksekusi. Jalankan terpisah agar verifikasi lebih jelas.');
        }

        $backupFile = $this->latestBackupFile();
        if ($backupFile === null) {
            throw new \RuntimeException('Tidak ada file backup terbaru. Jalankan app:db-backup --gzip dulu.');
        }

        $restore = $this->latestRestoreDrill();
        if ($restore === null) {
            throw new \RuntimeException('Belum ada restore drill log. Jalankan app:db-restore-test dulu.');
        }

        $restoreStatus = strtolower((string) ($restore->status ?? ''));
        if ($restoreStatus === 'skipped' && ! $allowSkippedRestore) {
            throw new \RuntimeException('Restore drill terakhir berstatus SKIPPED. Pakai --allow-skipped-restore hanya jika alasan skip sudah dipahami.');
        }
        if (! in_array($restoreStatus, ['passed', 'skipped'], true)) {
            throw new \RuntimeException('Restore drill terakhir belum aman untuk purge. Status: '.strtoupper($restoreStatus));
        }

        $manifest = $this->resolveManifest($scope, $datasetKeys, $manifestFile);
        if ($manifest === null) {
            throw new RuntimeException('Manifest arsip tidak ditemukan. Jalankan app:archive:export dulu untuk periode yang sama.');
        }

        $snapshotFile = null;
        if ($financialGuarded !== []) {
            $unsupported = array_values(array_diff($financialGuarded, $this->supportedFinancialPurgeDatasets()));
            if ($unsupported !== []) {
                throw new RuntimeException('Dataset finansial ini belum siap dipurge otomatis: '.implode(', ', $unsupported).'. Tahap aman saat ini baru dibuka untuk sales_invoices, sales_returns, outgoing_transactions, receivable_payments, dan supplier_payments.');
            }

            $snapshotFile = $this->resolveFinancialSnapshot($scope, $financialGuarded, $manifest);
            if ($snapshotFile === null) {
                throw new RuntimeException('Snapshot finansial belum ditemukan. Jalankan app:archive:prepare-financial dulu untuk periode dan dataset yang sama.');
            }
        }

        $deleted = [];
        foreach ($datasetKeys as $datasetKey) {
            $deleted[$datasetKey] = 0;
        }

        if (! $confirm) {
            return [
                'summary' => $summary,
                'manifest_file' => $manifest,
                'backup_file' => $backupFile,
                'restore_status' => $restoreStatus,
                'deleted' => $deleted,
                'snapshot_file' => $snapshotFile,
            ];
        }

        DB::transaction(function () use ($datasetKeys, $definitions, $scope, &$deleted): void {
            foreach ($datasetKeys as $datasetKey) {
                $definition = $definitions[$datasetKey];
                $deleted[$datasetKey] = $this->purgeDataset($datasetKey, $definition, $scope);
            }
        });

        $postCheck = null;
        if ($financialGuarded !== []) {
            $postCheck = $this->runFinancialRebuildAndChecks($snapshotFile, true);
        }

        $purgeDir = storage_path('app/archives/purges');
        File::ensureDirectoryExists($purgeDir);
        $purgeFile = $purgeDir.DIRECTORY_SEPARATOR.'purge-'.$this->scopeSlug($scope).'-'.now('Asia/Jakarta')->format('Ymd-His').'.json';
        File::put($purgeFile, json_encode([
            'purged_at' => now('Asia/Jakarta')->toIso8601String(),
            'scope' => $scope,
            'period_type' => $scope['type'],
            'period_value' => $scope['value'],
            'year' => (int) ($scope['year'] ?? 0),
            'semester' => $scope['semester'],
            'datasets' => $datasetKeys,
            'manifest_file' => $manifest,
            'backup_file' => $backupFile,
            'restore_status' => $restoreStatus,
            'deleted' => $deleted,
            'snapshot_file' => $snapshotFile,
            'post_check' => $postCheck,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'summary' => $summary,
            'manifest_file' => $manifest,
            'backup_file' => $backupFile,
            'restore_status' => $restoreStatus,
            'deleted' => $deleted,
            'snapshot_file' => $snapshotFile,
            'post_check' => $postCheck,
        ];
    }

    /**
     * @param  list<string>  $requestedDatasets
     * @return array{
     *   year:int,
     *   period_type:string,
     *   period_value:string,
     *   semester:?string,
     *   manifest_file:string,
     *   backup_file:string,
     *   restore_status:string,
     *   summary:array{
     *     year:int,
     *     datasets:array<string, array{
     *       label:string,
     *       basis:string,
     *       purge_allowed:bool,
     *       purge_mode:string,
     *       financial:bool,
     *       total_rows:int,
     *       tables:array<int, array{table:string, rows:int}>
     *     }>,
     *     missing:list<string>,
     *     grand_total:int
     *   },
     *   snapshot_file:string,
     *   customer_snapshots:list<array<string, mixed>>,
     *   supplier_snapshots:list<array<string, mixed>>
     * }
     */
    public function prepareFinancialSnapshot(
        int $year,
        array $requestedDatasets,
        ?string $manifestFile = null,
        bool $rebuildJournal = false
    ): array {
        return $this->prepareFinancialSnapshotByScope(
            $this->yearScope($year),
            $requestedDatasets,
            $manifestFile,
            $rebuildJournal
        );
    }

    /**
     * @param  list<string>  $requestedDatasets
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}  $scope
     * @return array{
     *   year:int,
     *   period_type:string,
     *   period_value:string,
     *   semester:?string,
     *   manifest_file:string,
     *   backup_file:string,
     *   restore_status:string,
     *   summary:array{
     *     period_type:string,
     *     period_value:string,
     *     year:int,
     *     semester:?string,
     *     datasets:array<string, array{
     *       label:string,
     *       basis:string,
     *       purge_allowed:bool,
     *       purge_mode:string,
     *       financial:bool,
     *       total_rows:int,
     *       tables:array<int, array{table:string, rows:int}>
     *     }>,
     *     missing:list<string>,
     *     grand_total:int
     *   },
     *   snapshot_file:string,
     *   customer_snapshots:list<array<string, mixed>>,
     *   supplier_snapshots:list<array<string, mixed>>
     * }
     */
    public function prepareFinancialSnapshotByScope(
        array $scope,
        array $requestedDatasets,
        ?string $manifestFile = null,
        bool $rebuildJournal = false
    ): array {
        $summary = $this->scanByScope($scope, $requestedDatasets);
        $datasetKeys = array_keys($summary['datasets']);
        $definitions = DataArchiveRegistry::definitions();

        if ($datasetKeys === []) {
            throw new \RuntimeException('Tidak ada dataset yang dipilih untuk snapshot finansial.');
        }

        $unsupported = [];
        foreach ($datasetKeys as $datasetKey) {
            $mode = (string) ($definitions[$datasetKey]['purge_mode'] ?? 'locked');
            if ($mode !== 'financial_guarded' || ! in_array($datasetKey, $this->supportedFinancialPurgeDatasets(), true)) {
                $unsupported[] = $datasetKey;
            }
        }
        if ($unsupported !== []) {
            throw new \RuntimeException('Snapshot finansial saat ini baru dibuka untuk dataset: '.implode(', ', $this->supportedFinancialPurgeDatasets()).'. Dataset tidak didukung: '.implode(', ', $unsupported));
        }

        $backupFile = $this->latestBackupFile();
        if ($backupFile === null) {
            throw new \RuntimeException('Tidak ada file backup terbaru. Jalankan app:db-backup --gzip dulu.');
        }

        $restore = $this->latestRestoreDrill();
        if ($restore === null) {
            throw new \RuntimeException('Belum ada restore drill log. Jalankan app:db-restore-test dulu.');
        }

        $restoreStatus = strtolower((string) ($restore->status ?? ''));
        if (! in_array($restoreStatus, ['passed', 'skipped'], true)) {
            throw new \RuntimeException('Restore drill terakhir belum aman untuk snapshot finansial. Status: '.strtoupper($restoreStatus));
        }

        $manifest = $this->resolveManifest($scope, $datasetKeys, $manifestFile);
        if ($manifest === null) {
            throw new RuntimeException('Manifest arsip tidak ditemukan. Jalankan app:archive:export dulu untuk periode yang sama.');
        }

        $customerIds = collect();
        $supplierIds = collect();

        foreach ($datasetKeys as $datasetKey) {
            $customerIds = $customerIds->merge($this->datasetCustomerIds($datasetKey, $scope));
            $supplierIds = $supplierIds->merge($this->datasetSupplierIds($datasetKey, $scope));
        }

        $customerSnapshots = Customer::query()
            ->whereIn('id', $customerIds->unique()->filter()->values()->all())
            ->orderBy('id')
            ->get(['id', 'name', 'outstanding_receivable'])
            ->map(function (Customer $customer): array {
                return [
                    'id' => (int) $customer->id,
                    'name' => (string) $customer->name,
                    'outstanding_receivable' => (int) $customer->outstanding_receivable,
                    'invoice_balance_sum' => (int) round((float) SalesInvoice::query()
                        ->where('customer_id', (int) $customer->id)
                        ->where('is_canceled', false)
                        ->sum('balance')),
                    'ledger_net_sum' => (int) round((float) DB::table('receivable_ledgers')
                        ->where('customer_id', (int) $customer->id)
                        ->sum(DB::raw('debit - credit'))),
                ];
            })
            ->all();

        $supplierSnapshots = Supplier::query()
            ->whereIn('id', $supplierIds->unique()->filter()->values()->all())
            ->orderBy('id')
            ->get(['id', 'name', 'outstanding_payable'])
            ->map(function (Supplier $supplier): array {
                return [
                    'id' => (int) $supplier->id,
                    'name' => (string) $supplier->name,
                    'outstanding_payable' => (int) $supplier->outstanding_payable,
                    'ledger_net_sum' => (int) round((float) SupplierLedger::query()
                        ->where('supplier_id', (int) $supplier->id)
                        ->sum(DB::raw('debit - credit'))),
                ];
            })
            ->all();

        $snapshotDir = storage_path('app/archives/financial-snapshots');
        File::ensureDirectoryExists($snapshotDir);
        $slug = implode('-', $datasetKeys);
        $snapshotFile = $snapshotDir.DIRECTORY_SEPARATOR.'financial-snapshot-'.$this->scopeSlug($scope).'-'.$slug.'-'.now('Asia/Jakarta')->format('Ymd-His').'.json';

        File::put($snapshotFile, json_encode([
            'prepared_at' => now('Asia/Jakarta')->toIso8601String(),
            'scope' => $scope,
            'period_type' => $scope['type'],
            'period_value' => $scope['value'],
            'year' => (int) ($scope['year'] ?? 0),
            'semester' => $scope['semester'],
            'datasets' => $datasetKeys,
            'manifest_file' => $manifest,
            'backup_file' => $backupFile,
            'restore_status' => $restoreStatus,
            'rebuild_journal' => $rebuildJournal,
            'summary' => $summary,
            'customer_snapshots' => $customerSnapshots,
            'supplier_snapshots' => $supplierSnapshots,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'year' => (int) ($scope['year'] ?? 0),
            'period_type' => $scope['type'],
            'period_value' => $scope['value'],
            'semester' => $scope['semester'],
            'manifest_file' => $manifest,
            'backup_file' => $backupFile,
            'restore_status' => $restoreStatus,
            'summary' => $summary,
            'snapshot_file' => $snapshotFile,
            'customer_snapshots' => $customerSnapshots,
            'supplier_snapshots' => $supplierSnapshots,
        ];
    }

    /**
     * @param  list<string>  $requestedDatasets
     * @return array{
     *   generated_at:string,
     *   reminders:list<string>,
     *   datasets:list<array{
     *     key:string,
     *     label:string,
     *     basis:string,
     *     scope_modes:list<string>,
     *     retention:string,
     *     purge_mode:string,
     *     cutoff_date:string,
     *     candidate_rows:int,
     *     oldest_entry:?string,
     *     newest_entry:?string,
     *     recommended_scope:?string,
     *     recommended_year_scope:?string,
     *     recommended_semester_scope:?string
     *   }>
     * }
     */
    public function review(array $requestedDatasets = []): array
    {
        $definitions = $requestedDatasets === []
            ? DataArchiveRegistry::businessDefinitions()
            : DataArchiveRegistry::resolve($requestedDatasets);
        $datasets = [];

        foreach ($definitions as $key => $definition) {
            $retention = $this->retentionRule($key);
            if ($retention === null) {
                continue;
            }

            [$candidateRows, $oldestEntry, $newestEntry] = $this->reviewDatasetWindow($definition, $retention['cutoff']);
            $recommendedScopes = $this->recommendedReviewScopes($definition, $oldestEntry);
            $datasets[] = [
                'key' => $key,
                'label' => $definition['label'],
                'basis' => $definition['basis'],
                'scope_modes' => array_values((array) ($definition['scope_modes'] ?? ['year'])),
                'retention' => $retention['label'],
                'purge_mode' => (string) ($definition['purge_mode'] ?? 'locked'),
                'cutoff_date' => $retention['cutoff']->toDateString(),
                'candidate_rows' => $candidateRows,
                'oldest_entry' => $oldestEntry,
                'newest_entry' => $newestEntry,
                'recommended_scope' => $recommendedScopes['default'],
                'recommended_year_scope' => $recommendedScopes['year'],
                'recommended_semester_scope' => $recommendedScopes['semester'],
            ];
        }

        usort($datasets, static function (array $left, array $right): int {
            return [$right['candidate_rows'], $left['label']] <=> [$left['candidate_rows'], $right['label']];
        });

        $reminders = [];
        if (collect($datasets)->contains(static fn (array $dataset): bool => (int) $dataset['candidate_rows'] > 0)) {
            $reminders[] = 'Ada dataset yang melewati window retention. Jalankan scan/export pada periode tertua sebelum purge.';
        }
        if ($this->latestBackupFile() === null) {
            $reminders[] = 'Backup DB belum ditemukan. Jalankan app:db-backup --gzip sebelum review arsip berikutnya.';
        }
        if ($this->latestRestoreDrill() === null) {
            $reminders[] = 'Restore drill belum ada. Jalankan app:db-restore-test untuk memastikan backup benar-benar bisa dipakai.';
        }

        $payload = [
            'generated_at' => now('Asia/Jakarta')->toIso8601String(),
            'reminders' => $reminders,
            'datasets' => $datasets,
        ];

        $this->writeArchiveRecord('reviews', 'review', $payload);

        return $payload;
    }

    /**
     * @return array{type:string,value:string,year:int,semester:?string,start:?string,end:?string}
     */
    public function yearScope(int|string $year): array
    {
        $normalized = trim((string) $year);

        $archiveYearRange = $this->semesterBookService->archiveYearDateRange($normalized);
        if ($archiveYearRange !== null) {
            return [
                'type' => 'year',
                'value' => $archiveYearRange['label'],
                'year' => (int) $archiveYearRange['label'],
                'semester' => null,
                'start' => $archiveYearRange['start'],
                'end' => $archiveYearRange['end'],
            ];
        }

        $calendarYear = (int) $normalized;
        if ($calendarYear < 2000 || $calendarYear > 2100) {
            throw new RuntimeException('Tahun arsip tidak valid.');
        }

        return [
            'type' => 'year',
            'value' => (string) $calendarYear,
            'year' => $calendarYear,
            'semester' => null,
            'start' => null,
            'end' => null,
        ];
    }

    /**
     * @return array{type:string,value:string,year:?int,semester:string,start:string,end:string}
     */
    public function semesterScope(string $semester): array
    {
        $normalized = $this->semesterBookService->normalizeSemester($semester);
        if ($normalized === null) {
            throw new RuntimeException('Kode semester tidak valid.');
        }

        $range = $this->semesterBookService->semesterDateRange($normalized);
        if ($range === null) {
            throw new RuntimeException('Range tanggal semester tidak ditemukan.');
        }

        return [
            'type' => 'semester',
            'value' => $normalized,
            'year' => null,
            'semester' => $normalized,
            'start' => $range['start'],
            'end' => $range['end'],
        ];
    }

    /**
     * @return array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}
     */
    public function resolveScope(string $scopeType, int|string|null $year = null, ?string $semester = null): array
    {
        return $scopeType === 'semester'
            ? $this->semesterScope((string) $semester)
            : $this->yearScope((string) $year);
    }

    /**
     * @param  array{label:string,basis:string,purge_allowed:bool,purge_mode:string,financial:bool,tables:array<int, array{table:string,date_column?:string,date_kind?:string,foreign_key?:string}>}  $definition
     * @param  array{table:string,date_column?:string,date_kind?:string,foreign_key?:string}  $tableDefinition
     */
    /**
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}|int  $scope
     */
    public function tableQuery(array $definition, array $tableDefinition, array|int $scope): Builder
    {
        $scope = is_int($scope) ? $this->yearScope($scope) : $scope;
        if (isset($tableDefinition['date_column'])) {
            $query = DB::table($tableDefinition['table']);

            return $this->applyScopeToDateQuery($query, $tableDefinition, $scope);
        }

        $root = $definition['tables'][0];
        $rootTable = $root['table'];

        return DB::table($tableDefinition['table'])->whereIn(
            $tableDefinition['foreign_key'],
            $this->rootIdQuery($definition, $scope)->select($rootTable.'.id')
        );
    }

    /**
     * @param  array{label:string,basis:string,purge_allowed:bool,purge_mode:string,financial:bool,tables:array<int, array{table:string,date_column?:string,date_kind?:string,foreign_key?:string,semester_column?:string}>}  $definition
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}  $scope
     */
    private function rootIdQuery(array $definition, array $scope): Builder
    {
        $root = $definition['tables'][0];
        $query = DB::table($root['table']);

        return $this->applyScopeToDateQuery($query, $root, $scope);
    }

    /**
     * @param  array{table:string,date_column?:string,date_kind?:string,foreign_key?:string,semester_column?:string}  $tableDefinition
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}  $scope
     */
    private function applyScopeToDateQuery(Builder $query, array $tableDefinition, array $scope): Builder
    {
        if ($scope['type'] === 'semester' && isset($tableDefinition['semester_column'])) {
            return $query->where($tableDefinition['semester_column'], $scope['semester']);
        }

        $dateColumn = $tableDefinition['date_column'] ?? 'created_at';
        if (($tableDefinition['date_kind'] ?? 'calendar') === 'unix') {
            if (! empty($scope['start']) && ! empty($scope['end'])) {
                return $query->whereBetween(
                    $dateColumn,
                    [strtotime((string) $scope['start'].' 00:00:00'), strtotime((string) $scope['end'].' 23:59:59')]
                );
            }

            return $query->whereBetween(
                $dateColumn,
                [strtotime((string) $scope['year'].'-01-01 00:00:00'), strtotime((string) $scope['year'].'-12-31 23:59:59')]
            );
        }

        if (! empty($scope['start']) && ! empty($scope['end'])) {
            $start = (string) $scope['start'];
            $end = (string) $scope['end'];

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) === 1) {
                $start .= ' 00:00:00';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) === 1) {
                $end .= ' 23:59:59';
            }

            return $query->whereBetween($dateColumn, [$start, $end]);
        }

        return $query->whereYear($dateColumn, (int) $scope['year']);
    }

    private function writeTableInsertStatements($handle, array $definition, array $tableDefinition, array $scope): void
    {
        $columns = Schema::getColumnListing($tableDefinition['table']);
        if ($columns === []) {
            return;
        }

        $query = $this->tableQuery($definition, $tableDefinition, $scope)->select($columns);
        if (in_array('id', $columns, true)) {
            $query->orderBy('id');
        }

        $pdo = DB::connection()->getPdo();
        $page = 1;
        $perPage = 250;

        do {
            $rows = (clone $query)->forPage($page, $perPage)->get();
            if ($rows->isEmpty()) {
                break;
            }

            $valueLines = [];
            foreach ($rows as $row) {
                $record = (array) $row;
                $values = [];
                foreach ($columns as $column) {
                    $values[] = $this->sqlValue($pdo, $record[$column] ?? null);
                }
                $valueLines[] = '('.implode(', ', $values).')';
            }

            $columnList = implode(', ', array_map(static fn (string $column): string => '`'.$column.'`', $columns));
            fwrite(
                $handle,
                sprintf(
                    "INSERT INTO `%s` (%s) VALUES\n%s;\n",
                    $tableDefinition['table'],
                    $columnList,
                    implode(",\n", $valueLines)
                )
            );

            $page++;
        } while ($rows->count() === $perPage);
    }

    private function sqlValue(\PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($value instanceof \DateTimeInterface) {
            return $pdo->quote($value->format('Y-m-d H:i:s'));
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            return $pdo->quote(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value) ?: '';
        }

        return $pdo->quote((string) $value);
    }

    public function latestBackupFile(): ?string
    {
        $files = collect(array_merge(
            File::glob(storage_path('app/backups').DIRECTORY_SEPARATOR.'*') ?: [],
            File::glob(storage_path('app/backups/db').DIRECTORY_SEPARATOR.'*') ?: []
        ))
            ->filter(static fn (string $file): bool => str_ends_with($file, '.sql') || str_ends_with($file, '.sql.gz'))
            ->sort()
            ->values();

        $file = $files->last();
        if (! is_string($file) || $file === '') {
            return null;
        }

        return $file;
    }

    public function latestRestoreDrill(): ?object
    {
        if (! Schema::hasTable('restore_drill_logs')) {
            return null;
        }

        return DB::table('restore_drill_logs')->latest('tested_at')->latest('id')->first();
    }

    /**
     * @param  list<string>  $datasetKeys
     */
    public function latestFinancialSnapshot(?int $year = null, array $datasetKeys = [], ?array $scope = null): ?array
    {
        $snapshotDir = storage_path('app/archives/financial-snapshots');
        if (! File::isDirectory($snapshotDir)) {
            return null;
        }

        $normalizedDatasets = collect($datasetKeys)->map(static fn (mixed $key): string => strtolower((string) $key))->sort()->values()->all();

        $path = collect(File::glob($snapshotDir.DIRECTORY_SEPARATOR.'*.json'))
            ->sortDesc()
            ->first(function (string $file) use ($year, $normalizedDatasets, $scope): bool {
                $payload = json_decode((string) File::get($file), true);
                if (! is_array($payload)) {
                    return false;
                }

                if ($scope !== null && ! $this->matchesStoredScope($payload, $scope)) {
                    return false;
                }

                if ($scope === null && $year !== null && (int) ($payload['year'] ?? 0) !== $year) {
                    return false;
                }

                if ($normalizedDatasets !== []) {
                    $payloadDatasets = collect((array) ($payload['datasets'] ?? []))
                        ->map(static fn (mixed $value): string => strtolower((string) $value))
                        ->sort()
                        ->values()
                        ->all();

                    if ($payloadDatasets !== $normalizedDatasets) {
                        return false;
                    }
                }

                return true;
            });

        if (! is_string($path) || $path === '') {
            return null;
        }

        $payload = json_decode((string) File::get($path), true);
        if (! is_array($payload)) {
            return null;
        }

        $payload['path'] = $path;

        return $payload;
    }

    public function latestArchiveReview(): ?array
    {
        $reviewDir = storage_path('app/archives/reviews');
        if (! File::isDirectory($reviewDir)) {
            return null;
        }

        $path = collect(File::glob($reviewDir.DIRECTORY_SEPARATOR.'*.json'))
            ->sortDesc()
            ->first();

        if (! is_string($path) || $path === '') {
            return null;
        }

        $payload = json_decode((string) File::get($path), true);
        if (! is_array($payload)) {
            return null;
        }

        $payload['path'] = $path;

        return $payload;
    }

    /**
     * @return list<array{type:string,path:string,created_at:string,title:string,summary:string}>
     */
    public function recentExecutionHistory(int $limit = 12): array
    {
        $directories = [
            'scan' => storage_path('app/archives/scans'),
            'export' => storage_path('app/archives/manifests'),
            'snapshot' => storage_path('app/archives/financial-snapshots'),
            'purge' => storage_path('app/archives/purges'),
            'review' => storage_path('app/archives/reviews'),
        ];

        $entries = collect();
        foreach ($directories as $type => $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::glob($directory.DIRECTORY_SEPARATOR.'*.json') ?: [] as $path) {
                $payload = json_decode((string) File::get($path), true);
                if (! is_array($payload)) {
                    continue;
                }

                $entry = $this->historyEntryFromPayload($type, $path, $payload);
                if ($entry !== null) {
                    $entries->push($entry);
                }
            }
        }

        return $entries
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $datasetKeys
     */
    public function resolveManifest(int|array $scope, array $datasetKeys, ?string $manifestFile = null): ?string
    {
        if (is_string($manifestFile) && $manifestFile !== '' && File::exists($manifestFile)) {
            return $manifestFile;
        }

        $scope = is_int($scope) ? $this->yearScope($scope) : $scope;
        $manifestDir = storage_path('app/archives/manifests');
        if (! File::isDirectory($manifestDir)) {
            return null;
        }

        $normalizedDatasets = collect($datasetKeys)->sort()->values()->all();

        return collect(File::glob($manifestDir.DIRECTORY_SEPARATOR.'*.json'))
            ->sortDesc()
            ->first(function (string $path) use ($scope, $normalizedDatasets): bool {
                $payload = json_decode((string) File::get($path), true);
                if (! is_array($payload)) {
                    return false;
                }

                $payloadDatasets = collect((array) ($payload['datasets'] ?? []))->map(static fn ($value): string => strtolower((string) $value))->sort()->values()->all();

                return $payloadDatasets === $normalizedDatasets && $this->matchesStoredScope($payload, $scope);
            });
    }

    /**
     * @return list<string>
     */
    public function supportedFinancialPurgeDatasets(): array
    {
        return [
            'sales_invoices',
            'sales_returns',
            'outgoing_transactions',
            'receivable_ledgers',
            'receivable_payments',
            'supplier_payments',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function datasetCustomerIds(string $datasetKey, array $scope)
    {
        return match ($datasetKey) {
            'sales_invoices' => $this->rootIdQuery(DataArchiveRegistry::definitions()['sales_invoices'], $scope)
                ->pluck('customer_id'),
            'sales_returns' => $this->rootIdQuery(DataArchiveRegistry::definitions()['sales_returns'], $scope)
                ->pluck('customer_id'),
            'receivable_ledgers' => $this->rootIdQuery(DataArchiveRegistry::definitions()['receivable_ledgers'], $scope)
                ->pluck('customer_id'),
            'receivable_payments' => $this->rootIdQuery(DataArchiveRegistry::definitions()['receivable_payments'], $scope)
                ->pluck('customer_id'),
            default => collect(),
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function datasetSupplierIds(string $datasetKey, array $scope)
    {
        return match ($datasetKey) {
            'outgoing_transactions' => $this->rootIdQuery(DataArchiveRegistry::definitions()['outgoing_transactions'], $scope)
                ->pluck('supplier_id'),
            'supplier_payments' => $this->rootIdQuery(DataArchiveRegistry::definitions()['supplier_payments'], $scope)
                ->pluck('supplier_id'),
            default => collect(),
        };
    }

    /**
     * @param  list<string>  $datasetKeys
     */
    private function resolveFinancialSnapshot(array $scope, array $datasetKeys, string $manifestFile): ?string
    {
        $snapshot = $this->latestFinancialSnapshot(null, $datasetKeys, $scope);
        if ($snapshot === null) {
            return null;
        }

        if (($snapshot['manifest_file'] ?? null) !== $manifestFile) {
            return null;
        }

        return (string) ($snapshot['path'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function runFinancialRebuildAndChecks(?string $snapshotFile, bool $defaultRebuildJournal = true): array
    {
        $snapshotPayload = null;
        if (is_string($snapshotFile) && $snapshotFile !== '' && File::exists($snapshotFile)) {
            $snapshotPayload = json_decode((string) File::get($snapshotFile), true);
        }

        $rebuildJournal = (bool) ($snapshotPayload['rebuild_journal'] ?? $defaultRebuildJournal);
        $rebuildExit = Artisan::call('app:financial-rebuild', array_filter([
            '--rebuild-journal' => $rebuildJournal ? true : null,
        ], static fn (mixed $value): bool => $value !== null));
        $rebuildOutput = trim(Artisan::output());

        $integrityExit = Artisan::call('app:integrity-check');
        $integrityOutput = trim(Artisan::output());
        $latestIntegrity = Schema::hasTable('integrity_check_logs')
            ? DB::table('integrity_check_logs')->latest('checked_at')->latest('id')->first()
            : null;

        return [
            'rebuild_exit' => $rebuildExit,
            'rebuild_output' => $rebuildOutput,
            'integrity_exit' => $integrityExit,
            'integrity_output' => $integrityOutput,
            'latest_integrity_status' => is_object($latestIntegrity) ? (bool) ($latestIntegrity->is_ok ?? false) : null,
            'latest_integrity_checked_at' => is_object($latestIntegrity) ? (string) ($latestIntegrity->checked_at ?? '') : null,
        ];
    }

    /**
     * @param  array{label:string,basis:string,purge_allowed:bool,purge_mode:string,financial:bool,tables:array<int, array{table:string,date_column?:string,date_kind?:string,foreign_key?:string}>}  $definition
     */
    private function purgeDataset(string $datasetKey, array $definition, array $scope): int
    {
        return match ($datasetKey) {
            'sales_returns' => $this->purgeSalesReturns($definition, $scope),
            'receivable_ledgers' => $this->purgeReceivableLedgers($definition, $scope),
            'receivable_payments' => $this->purgeReceivablePayments($definition, $scope),
            default => $this->purgeGenericDataset($definition, $scope),
        };
    }

    /**
     * @param  array{label:string,basis:string,purge_allowed:bool,purge_mode:string,financial:bool,tables:array<int, array{table:string,date_column?:string,date_kind?:string,foreign_key?:string}>}  $definition
     */
    private function purgeGenericDataset(array $definition, array $scope): int
    {
        $tables = array_reverse($definition['tables']);
        $datasetDeleted = 0;

        foreach ($tables as $tableDefinition) {
            if (! Schema::hasTable($tableDefinition['table'])) {
                continue;
            }
            $datasetDeleted += $this->tableQuery($definition, $tableDefinition, $scope)->delete();
        }

        return $datasetDeleted;
    }

    /**
     * @param  array{label:string,basis:string,purge_allowed:bool,purge_mode:string,financial:bool,tables:array<int, array{table:string,date_column?:string,date_kind?:string,foreign_key?:string}>}  $definition
     */
    private function purgeSalesReturns(array $definition, array $scope): int
    {
        $returns = $this->rootIdQuery($definition, $scope)
            ->select(['id', 'return_number'])
            ->get();

        if ($returns->isEmpty()) {
            return 0;
        }

        $returnIds = $returns->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $returnNumbers = $returns->pluck('return_number')->filter()->map(static fn (mixed $value): string => (string) $value)->all();

        $deleted = $this->deleteJournalEntriesForReference(SalesReturn::class, $returnIds);

        if (Schema::hasTable('stock_mutations')) {
            $deleted += DB::table('stock_mutations')
                ->where('reference_type', SalesReturn::class)
                ->whereIn('reference_id', $returnIds)
                ->delete();
        }

        if (Schema::hasTable('receivable_ledgers')) {
            $deleted += $this->deleteRowsMatchingNumbers('receivable_ledgers', 'description', $returnNumbers);
        }

        return $deleted + $this->purgeGenericDataset($definition, $scope);
    }

    /**
     * @param  array{label:string,basis:string,purge_allowed:bool,purge_mode:string,financial:bool,tables:array<int, array{table:string,date_column?:string,date_kind?:string,foreign_key?:string}>}  $definition
     */
    private function purgeReceivablePayments(array $definition, array $scope): int
    {
        $payments = $this->rootIdQuery($definition, $scope)
            ->select(['id', 'payment_number'])
            ->get();

        if ($payments->isEmpty()) {
            return 0;
        }

        $paymentIds = $payments->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $paymentNumbers = $payments->pluck('payment_number')->filter()->map(static fn (mixed $value): string => (string) $value)->all();

        $deleted = $this->deleteJournalEntriesForReference(ReceivablePayment::class, $paymentIds);

        if (Schema::hasTable('invoice_payments')) {
            $deleted += $this->deleteRowsMatchingNumbers('invoice_payments', 'notes', $paymentNumbers);
        }

        if (Schema::hasTable('receivable_ledgers')) {
            $deleted += $this->deleteRowsMatchingNumbers('receivable_ledgers', 'description', $paymentNumbers);
        }

        return $deleted + $this->purgeGenericDataset($definition, $scope);
    }

    /**
     * @param  array{label:string,basis:string,purge_allowed:bool,purge_mode:string,financial:bool,tables:array<int, array{table:string,date_column?:string,date_kind?:string,foreign_key?:string,semester_column?:string}>}  $definition
     */
    private function purgeReceivableLedgers(array $definition, array $scope): int
    {
        $this->assertReceivableLedgerScopeCanBePurged($definition, $scope);

        return $this->purgeGenericDataset($definition, $scope);
    }

    /**
     * @param  array{label:string,basis:string,purge_allowed:bool,purge_mode:string,financial:bool,tables:array<int, array{table:string,date_column?:string,date_kind?:string,foreign_key?:string,semester_column?:string}>}  $definition
     */
    private function assertReceivableLedgerScopeCanBePurged(array $definition, array $scope): void
    {
        $this->assertReceivableLedgerScopeIsClosed($scope);
        $this->assertReceivableLedgerScopeMeetsRetention($scope);

        $rows = $this->rootIdQuery($definition, $scope)
            ->select(['id', 'customer_id', 'sales_invoice_id', 'balance_after'])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $openBalances = $rows
            ->groupBy('customer_id')
            ->map(static fn ($items): int => (int) round((float) optional($items->last())->balance_after))
            ->filter(static fn (int $balance): bool => $balance !== 0);

        if ($openBalances->isNotEmpty()) {
            $customerNames = Customer::query()
                ->whereIn('id', $openBalances->keys()->all())
                ->orderBy('name')
                ->pluck('name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->all();

            throw new RuntimeException(
                'Ledger Piutang untuk periode ini belum aman dibersihkan karena saldo akhirnya belum nol. '
                .'Selesaikan dulu piutang customer berikut: '.implode(', ', $customerNames)
            );
        }

        $invoiceIds = $rows
            ->pluck('sales_invoice_id')
            ->filter(static fn (mixed $id): bool => $id !== null)
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($invoiceIds === []) {
            return;
        }

        $unsettledInvoices = SalesInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->where(function ($query): void {
                $query->where('is_canceled', false)
                    ->where('balance', '>', 0);
            })
            ->orderBy('invoice_number')
            ->pluck('invoice_number')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        if ($unsettledInvoices !== []) {
            throw new RuntimeException(
                'Ledger Piutang untuk periode ini belum aman dibersihkan karena masih ada faktur yang belum lunas: '
                .implode(', ', $unsettledInvoices)
            );
        }

        $invoicesThatWouldReopen = SalesInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->where('is_canceled', false)
            ->orderBy('invoice_number')
            ->get(['id', 'invoice_number', 'total'])
            ->filter(function (SalesInvoice $invoice): bool {
                $appliedAmount = (int) round((float) DB::table('invoice_payments')
                    ->where('sales_invoice_id', (int) $invoice->id)
                    ->sum('amount'));
                $expectedBalance = max(0, (int) round((float) $invoice->total) - $appliedAmount);

                return $expectedBalance > 0;
            })
            ->pluck('invoice_number')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();

        if ($invoicesThatWouldReopen !== []) {
            throw new RuntimeException(
                'Ledger Piutang untuk periode ini belum aman dibersihkan karena beberapa faktur akan terbuka lagi setelah rebuild. '
                .'Pastikan pelunasannya sudah tercatat di pembayaran faktur untuk: '.implode(', ', $invoicesThatWouldReopen)
            );
        }
    }

    /**
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}  $scope
     */
    private function assertReceivableLedgerScopeIsClosed(array $scope): void
    {
        if ($scope['type'] === 'semester') {
            if (! $this->semesterBookService->isClosed((string) $scope['semester'])) {
                throw new RuntimeException('Ledger Piutang hanya bisa dibersihkan untuk semester yang sudah ditutup di Pengaturan.');
            }

            return;
        }

        $closedYears = $this->semesterBookService->closedArchiveYearOptions();
        if (! in_array((string) $scope['value'], $closedYears, true)) {
            throw new RuntimeException('Ledger Piutang hanya bisa dibersihkan untuk tahun ajaran yang semester-nya sudah ditutup di Pengaturan.');
        }
    }

    /**
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}  $scope
     */
    private function assertReceivableLedgerScopeMeetsRetention(array $scope): void
    {
        $retention = $this->retentionRule('receivable_ledgers');
        if (! is_array($retention) || ! isset($retention['cutoff']) || ! $retention['cutoff'] instanceof Carbon) {
            return;
        }

        $scopeEnd = $scope['end'] ?? null;
        if (! is_string($scopeEnd) || trim($scopeEnd) === '') {
            throw new RuntimeException('Range tanggal periode Ledger Piutang tidak ditemukan, jadi sistem belum bisa memastikan usia arsipnya.');
        }

        $scopeEndDate = Carbon::parse($scopeEnd, 'Asia/Jakarta')->endOfDay();
        /** @var Carbon $cutoff */
        $cutoff = $retention['cutoff'];
        if ($scopeEndDate->greaterThanOrEqualTo($cutoff)) {
            throw new RuntimeException('Ledger Piutang baru bisa dibersihkan jika periode tersebut sudah lewat 60 bulan.');
        }
    }

    /**
     * @param  list<int>  $referenceIds
     */
    private function deleteJournalEntriesForReference(string $referenceType, array $referenceIds): int
    {
        if ($referenceIds === [] || ! Schema::hasTable('journal_entries')) {
            return 0;
        }

        $journalIds = DB::table('journal_entries')
            ->where('reference_type', $referenceType)
            ->whereIn('reference_id', $referenceIds)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($journalIds === []) {
            return 0;
        }

        $deleted = 0;
        if (Schema::hasTable('journal_entry_lines')) {
            $deleted += DB::table('journal_entry_lines')
                ->whereIn('journal_entry_id', $journalIds)
                ->delete();
        }

        $deleted += DB::table('journal_entries')
            ->whereIn('id', $journalIds)
            ->delete();

        return $deleted;
    }

    /**
     * @param  list<string>  $numbers
     */
    private function deleteRowsMatchingNumbers(string $table, string $column, array $numbers): int
    {
        if ($numbers === [] || ! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)
            ->where(function ($query) use ($column, $numbers): void {
                foreach ($numbers as $number) {
                    $query->orWhere($column, 'like', '%'.$number.'%');
                }
            })
            ->delete();
    }

    /**
     * @return array{label:string,cutoff:\Illuminate\Support\Carbon}|null
     */
    private function retentionRule(string $datasetKey): ?array
    {
        return match ($datasetKey) {
            'audit_logs', 'report_export_tasks', 'integrity_check_logs', 'performance_probe_logs', 'restore_drill_logs', 'failed_jobs', 'job_batches' => [
                'label' => '3 bulan',
                'cutoff' => now('Asia/Jakarta')->subMonths(3)->startOfDay(),
            ],
            'sales_invoices', 'sales_returns', 'delivery_notes', 'order_notes', 'outgoing_transactions', 'receivable_ledgers', 'receivable_payments', 'supplier_payments', 'school_bulk_transactions' => [
                'label' => '60 bulan',
                'cutoff' => now('Asia/Jakarta')->subMonths(60)->startOfDay(),
            ],
            default => null,
        };
    }

    /**
     * @param  array{label:string,basis:string,purge_allowed:bool,purge_mode:string,financial:bool,tables:array<int, array{table:string,date_column?:string,date_kind?:string,foreign_key?:string}>}  $definition
     * @return array{0:int,1:?string,2:?string}
     */
    private function reviewDatasetWindow(array $definition, \Illuminate\Support\Carbon $cutoff): array
    {
        $root = $definition['tables'][0] ?? null;
        if (! is_array($root) || ! isset($root['table'], $root['date_column']) || ! Schema::hasTable($root['table'])) {
            return [0, null, null];
        }

        $query = DB::table($root['table']);
        if (($root['date_kind'] ?? 'calendar') === 'unix') {
            $query->where($root['date_column'], '<', $cutoff->timestamp);
        } else {
            $query->where($root['date_column'], '<', $cutoff->toDateString());
        }

        $candidateRows = (int) $query->count();
        if ($candidateRows === 0) {
            return [0, null, null];
        }

        if (($root['date_kind'] ?? 'calendar') === 'unix') {
            $oldest = DB::table($root['table'])->where($root['date_column'], '<', $cutoff->timestamp)->min($root['date_column']);
            $newest = DB::table($root['table'])->where($root['date_column'], '<', $cutoff->timestamp)->max($root['date_column']);

            return [
                $candidateRows,
                $oldest !== null ? date('Y-m-d H:i:s', (int) $oldest) : null,
                $newest !== null ? date('Y-m-d H:i:s', (int) $newest) : null,
            ];
        }

        return [
            $candidateRows,
            DB::table($root['table'])->where($root['date_column'], '<', $cutoff->toDateString())->min($root['date_column']),
            DB::table($root['table'])->where($root['date_column'], '<', $cutoff->toDateString())->max($root['date_column']),
        ];
    }

    /**
     * @param  array{label:string,basis:string,purge_allowed:bool,purge_mode:string,financial:bool,tables:array<int, array{table:string,date_column?:string,date_kind?:string,foreign_key?:string}>}  $definition
     */
    private function recommendedReviewScopes(array $definition, ?string $oldestEntry): array
    {
        if ($oldestEntry === null || trim($oldestEntry) === '') {
            return [
                'default' => null,
                'year' => null,
                'semester' => null,
            ];
        }

        $reference = $this->normalizeReviewDate($oldestEntry);
        if ($reference === null) {
            return [
                'default' => null,
                'year' => null,
                'semester' => null,
            ];
        }

        $yearScope = (string) $reference->year;
        $semesterScope = null;
        $scopeModes = array_values((array) ($definition['scope_modes'] ?? ['year']));

        if (in_array('semester', $scopeModes, true)) {
            $semesterScope = $this->semesterBookService->semesterFromDate($reference->toDateString());
        }

        $default = in_array('semester', $scopeModes, true) && $semesterScope !== null
            ? $semesterScope
            : $yearScope;

        return [
            'default' => $default,
            'year' => $yearScope,
            'semester' => $semesterScope,
        ];
    }

    private function normalizeReviewDate(string $value): ?\Illuminate\Support\Carbon
    {
        try {
            return \Illuminate\Support\Carbon::parse($value, 'Asia/Jakarta');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}  $scope
     */
    private function scopeSlug(array $scope): string
    {
        return strtolower(str_replace([' ', '/'], ['-', '-'], $scope['value']));
    }

    /**
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}  $scope
     */
    private function scopeLabel(array $scope): string
    {
        return $scope['type'] === 'semester'
            ? 'Semester '.$scope['value']
            : 'Tahun '.$scope['value'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{type:string,value:string,year:?int,semester:?string,start:?string,end:?string}  $scope
     */
    private function matchesStoredScope(array $payload, array $scope): bool
    {
        $payloadType = (string) ($payload['period_type'] ?? $payload['scope']['type'] ?? 'year');
        $payloadValue = (string) ($payload['period_value']
            ?? $payload['scope']['value']
            ?? (($payloadType === 'semester')
                ? ($payload['semester'] ?? '')
                : (string) ($payload['year'] ?? '')));

        return $payloadType === $scope['type'] && $payloadValue === $scope['value'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeArchiveRecord(string $directoryName, string $prefix, array $payload): string
    {
        $directory = storage_path('app/archives'.DIRECTORY_SEPARATOR.$directoryName);
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.$prefix.'-'.now('Asia/Jakarta')->format('Ymd-His').'.json';
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{type:string,path:string,created_at:string,title:string,summary:string}|null
     */
    private function historyEntryFromPayload(string $type, string $path, array $payload): ?array
    {
        $createdAt = (string) ($payload['generated_at']
            ?? $payload['prepared_at']
            ?? $payload['purged_at']
            ?? '');

        if ($createdAt === '') {
            return null;
        }

        $datasets = collect((array) ($payload['datasets'] ?? ($payload['summary']['datasets'] ?? [])))
            ->map(static function (mixed $item, mixed $key): string {
                if (is_string($item)) {
                    return $item;
                }

                return (string) $key;
            })
            ->filter()
            ->values()
            ->all();

        $title = match ($type) {
            'scan' => 'Preview scan',
            'export' => 'Export SQL',
            'snapshot' => 'Snapshot finansial',
            'purge' => 'Purge arsip',
            'review' => 'Review arsip',
            default => ucfirst($type),
        };

        $summary = match ($type) {
            'review' => sprintf(
                '%d dataset, %d kandidat aktif',
                count((array) ($payload['datasets'] ?? [])),
                collect((array) ($payload['datasets'] ?? []))->sum(static fn (array $dataset): int => (int) ($dataset['candidate_rows'] ?? 0))
            ),
            default => sprintf(
                '%s | total %s row',
                $datasets !== [] ? implode(', ', $datasets) : '-',
                number_format((int) ($payload['grand_total'] ?? $payload['summary']['grand_total'] ?? 0), 0, ',', '.')
            ),
        };

        $scopeType = (string) ($payload['period_type'] ?? $payload['scope']['type'] ?? '');
        $scopeValue = (string) ($payload['period_value']
            ?? $payload['scope']['value']
            ?? (($scopeType === 'semester')
                ? ($payload['semester'] ?? '')
                : ($payload['year'] ?? '')));

        if ($scopeValue !== '') {
            $scopeText = $scopeType === 'semester' ? 'semester '.$scopeValue : 'tahun '.$scopeValue;
            $summary = $scopeText.' | '.$summary;
        }

        return [
            'type' => $type,
            'path' => $path,
            'created_at' => $createdAt,
            'title' => $title,
            'summary' => $summary,
        ];
    }
}
