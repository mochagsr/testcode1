<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DataArchiveService
{
    /**
     * @param  list<string>  $requestedDatasets
     * @return array{
     *   year:int,
     *   datasets:array<string, array{
     *     label:string,
     *     basis:string,
     *     purge_allowed:bool,
     *     financial:bool,
     *     total_rows:int,
     *     tables:array<int, array{table:string, rows:int}>
     *   }>,
     *   missing:list<string>,
     *   grand_total:int
     * }
     */
    public function scan(int $year, array $requestedDatasets): array
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

                $rows = $this->tableQuery($definition, $tableDefinition, $year)->count();
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
                'financial' => $definition['financial'],
                'total_rows' => $datasetTotal,
                'tables' => $tableSummaries,
            ];
        }

        return [
            'year' => $year,
            'datasets' => $datasets,
            'missing' => $missing,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * @param  list<string>  $requestedDatasets
     * @return array{
     *   sql_file:string,
     *   manifest_file:string,
     *   summary:array{
     *     year:int,
     *     datasets:array<string, array{
     *       label:string,
     *       basis:string,
     *       purge_allowed:bool,
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
        $summary = $this->scan($year, $requestedDatasets);
        $datasetKeys = array_keys($summary['datasets']);
        $slug = $datasetKeys === [] ? 'empty' : implode('-', array_slice($datasetKeys, 0, 3));
        if (count($datasetKeys) > 3) {
            $slug .= '-plus';
        }

        $timestamp = now('Asia/Jakarta')->format('Ymd-His');
        $baseDir = $directory ?: storage_path('app/archives');
        $sqlDir = $baseDir . DIRECTORY_SEPARATOR . 'sql';
        $manifestDir = $baseDir . DIRECTORY_SEPARATOR . 'manifests';
        File::ensureDirectoryExists($sqlDir);
        File::ensureDirectoryExists($manifestDir);

        $sqlFile = $sqlDir . DIRECTORY_SEPARATOR . sprintf('archive-%d-%s-%s.sql', $year, $slug, $timestamp);
        $manifestFile = $manifestDir . DIRECTORY_SEPARATOR . sprintf('archive-%d-%s-%s.json', $year, $slug, $timestamp);

        $handle = fopen($sqlFile, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Failed creating archive SQL file: '.$sqlFile);
        }

        fwrite($handle, "-- PgPOS ERP archive export\n");
        fwrite($handle, '-- Generated at: '.now('Asia/Jakarta')->toDateTimeString()."\n");
        fwrite($handle, '-- Year filter: '.$year."\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($datasetKeys as $datasetKey) {
            $definition = DataArchiveRegistry::definitions()[$datasetKey];
            fwrite($handle, sprintf("-- Dataset: %s (%s)\n", $definition['label'], $datasetKey));

            foreach ($definition['tables'] as $tableDefinition) {
                if (! Schema::hasTable($tableDefinition['table'])) {
                    continue;
                }

                $count = $this->tableQuery($definition, $tableDefinition, $year)->count();
                if ((int) $count === 0) {
                    continue;
                }

                fwrite($handle, sprintf("-- Table: %s | rows: %d\n", $tableDefinition['table'], $count));
                $this->writeTableInsertStatements($handle, $definition, $tableDefinition, $year);
                fwrite($handle, "\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        $manifest = [
            'generated_at' => now('Asia/Jakarta')->toIso8601String(),
            'year' => $year,
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
     *     year:int,
     *     datasets:array<string, array{
     *       label:string,
     *       basis:string,
     *       purge_allowed:bool,
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
     *   deleted:array<string, int>
     * }
     */
    public function purge(
        int $year,
        array $requestedDatasets,
        bool $confirm,
        ?string $manifestFile = null,
        bool $allowSkippedRestore = false
    ): array {
        $summary = $this->scan($year, $requestedDatasets);
        $datasetKeys = array_keys($summary['datasets']);
        $definitions = DataArchiveRegistry::definitions();

        $blocked = [];
        foreach ($datasetKeys as $datasetKey) {
            if (($definitions[$datasetKey]['purge_allowed'] ?? false) === false) {
                $blocked[] = $datasetKey;
            }
        }
        if ($blocked !== []) {
            throw new \RuntimeException(
                'Purge untuk dataset finansial belum dibuka. Dataset terkunci: '.implode(', ', $blocked)
                .'. Gunakan scan/export dulu. Purge otomatis hanya aman untuk audit/ops logs saat ini.'
            );
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

        $manifest = $this->resolveManifest($year, $datasetKeys, $manifestFile);
        if ($manifest === null) {
            throw new \RuntimeException('Manifest arsip tidak ditemukan. Jalankan app:archive:export dulu untuk periode yang sama.');
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
            ];
        }

        DB::transaction(function () use ($datasetKeys, $definitions, $year, &$deleted): void {
            foreach ($datasetKeys as $datasetKey) {
                $definition = $definitions[$datasetKey];
                $tables = array_reverse($definition['tables']);
                $datasetDeleted = 0;
                foreach ($tables as $tableDefinition) {
                    if (! Schema::hasTable($tableDefinition['table'])) {
                        continue;
                    }
                    $datasetDeleted += $this->tableQuery($definition, $tableDefinition, $year)->delete();
                }
                $deleted[$datasetKey] = $datasetDeleted;
            }
        });

        $purgeDir = storage_path('app/archives/purges');
        File::ensureDirectoryExists($purgeDir);
        $purgeFile = $purgeDir.DIRECTORY_SEPARATOR.'purge-'.$year.'-'.now('Asia/Jakarta')->format('Ymd-His').'.json';
        File::put($purgeFile, json_encode([
            'purged_at' => now('Asia/Jakarta')->toIso8601String(),
            'year' => $year,
            'datasets' => $datasetKeys,
            'manifest_file' => $manifest,
            'backup_file' => $backupFile,
            'restore_status' => $restoreStatus,
            'deleted' => $deleted,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'summary' => $summary,
            'manifest_file' => $manifest,
            'backup_file' => $backupFile,
            'restore_status' => $restoreStatus,
            'deleted' => $deleted,
        ];
    }

    /**
     * @param  array{label:string,basis:string,purge_allowed:bool,financial:bool,tables:array<int, array{table:string,date_column?:string,foreign_key?:string}>}  $definition
     * @param  array{table:string,date_column?:string,foreign_key?:string}  $tableDefinition
     */
    public function tableQuery(array $definition, array $tableDefinition, int $year): Builder
    {
        if (isset($tableDefinition['date_column'])) {
            return DB::table($tableDefinition['table'])->whereYear($tableDefinition['date_column'], $year);
        }

        $root = $definition['tables'][0];
        $rootTable = $root['table'];
        $rootDateColumn = $root['date_column'] ?? 'created_at';

        return DB::table($tableDefinition['table'])->whereIn($tableDefinition['foreign_key'], function ($query) use ($rootTable, $rootDateColumn, $year): void {
            $query->from($rootTable)
                ->select('id')
                ->whereYear($rootDateColumn, $year);
        });
    }

    private function writeTableInsertStatements($handle, array $definition, array $tableDefinition, int $year): void
    {
        $columns = Schema::getColumnListing($tableDefinition['table']);
        if ($columns === []) {
            return;
        }

        $query = $this->tableQuery($definition, $tableDefinition, $year)->select($columns);
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

    private function latestBackupFile(): ?string
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

    private function latestRestoreDrill(): ?object
    {
        if (! Schema::hasTable('restore_drill_logs')) {
            return null;
        }

        return DB::table('restore_drill_logs')->latest('tested_at')->latest('id')->first();
    }

    /**
     * @param  list<string>  $datasetKeys
     */
    private function resolveManifest(int $year, array $datasetKeys, ?string $manifestFile = null): ?string
    {
        if (is_string($manifestFile) && $manifestFile !== '' && File::exists($manifestFile)) {
            return $manifestFile;
        }

        $manifestDir = storage_path('app/archives/manifests');
        if (! File::isDirectory($manifestDir)) {
            return null;
        }

        $normalizedDatasets = collect($datasetKeys)->sort()->values()->all();

        return collect(File::glob($manifestDir.DIRECTORY_SEPARATOR.'*.json'))
            ->sortDesc()
            ->first(function (string $path) use ($year, $normalizedDatasets): bool {
                $payload = json_decode((string) File::get($path), true);
                if (! is_array($payload)) {
                    return false;
                }

                $payloadYear = (int) ($payload['year'] ?? 0);
                $payloadDatasets = collect((array) ($payload['datasets'] ?? []))->map(static fn ($value): string => strtolower((string) $value))->sort()->values()->all();

                return $payloadYear === $year && $payloadDatasets === $normalizedDatasets;
            });
    }
}
