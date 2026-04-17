<?php

use App\Models\ReceivablePayment;
use App\Models\ReceivableLedger;
use App\Models\Customer;
use App\Models\SupplierLedger;
use App\Models\SupplierPayment;
use App\Models\Supplier;
use App\Models\DeliveryNote;
use App\Models\DeliveryTrip;
use App\Models\OutgoingTransaction;
use App\Models\OrderNote;
use App\Models\SchoolBulkTransaction;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\AppSetting;
use App\Models\ReportExportTask;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\IntegrityCheckLog;
use App\Models\PerformanceProbeLog;
use App\Models\User;
use App\Support\AppCache;
use App\Support\DataArchiveService;
use App\Support\SemesterBookService;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:normalize-doc-prefixes {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');

    $returnRows = SalesReturn::query()
        ->where('return_number', 'like', 'RTN-%')
        ->orWhere('return_number', 'like', 'RET-%')
        ->get(['id', 'return_number']);
    $paymentRows = ReceivablePayment::query()
        ->where('payment_number', 'like', 'PYT-%')
        ->get(['id', 'payment_number']);

    $returnChanges = [];
    foreach ($returnRows as $row) {
        $old = (string) $row->return_number;
        $new = preg_replace('/^(RTN|RET)-/i', 'RTR-', $old) ?: $old;
        if ($new !== $old) {
            $returnChanges[(int) $row->id] = ['old' => $old, 'new' => $new];
        }
    }

    $paymentChanges = [];
    foreach ($paymentRows as $row) {
        $old = (string) $row->payment_number;
        $new = preg_replace('/^PYT-/i', 'KWT-', $old) ?: $old;
        if ($new !== $old) {
            $paymentChanges[(int) $row->id] = ['old' => $old, 'new' => $new];
        }
    }

    $this->line('Planned changes:');
    $this->line('- Sales returns: '.count($returnChanges));
    $this->line('- Receivable payments: '.count($paymentChanges));

    $replaceMap = [];
    foreach ($returnChanges as $change) {
        $replaceMap[$change['old']] = $change['new'];
    }
    foreach ($paymentChanges as $change) {
        $replaceMap[$change['old']] = $change['new'];
    }

    if ($dryRun) {
        $this->warn('Dry run mode, no data changed.');
        return;
    }

    DB::transaction(function () use ($returnChanges, $paymentChanges, $replaceMap): void {
        foreach ($returnChanges as $id => $change) {
            SalesReturn::query()->whereKey($id)->update(['return_number' => $change['new']]);
        }
        foreach ($paymentChanges as $id => $change) {
            ReceivablePayment::query()->whereKey($id)->update(['payment_number' => $change['new']]);
        }

        if ($replaceMap !== []) {
            $targets = [
                ['table' => 'receivable_ledgers', 'column' => 'description'],
                ['table' => 'invoice_payments', 'column' => 'notes'],
                ['table' => 'audit_logs', 'column' => 'description'],
                ['table' => 'stock_mutations', 'column' => 'notes'],
                ['table' => 'receivable_payments', 'column' => 'notes'],
            ];

            foreach ($targets as $target) {
                DB::table($target['table'])
                    ->select(['id', $target['column']])
                    ->orderBy('id')
                    ->chunkById(200, function ($rows) use ($target, $replaceMap): void {
                        foreach ($rows as $row) {
                            $oldText = (string) ($row->{$target['column']} ?? '');
                            if ($oldText === '') {
                                continue;
                            }
                            $newText = str_replace(array_keys($replaceMap), array_values($replaceMap), $oldText);
                            if ($newText === $oldText) {
                                continue;
                            }
                            DB::table($target['table'])
                                ->where('id', (int) $row->id)
                                ->update([$target['column'] => $newText]);
                        }
                    });
            }
        }
    });

    $this->info('Normalization completed.');
})->purpose('Normalize legacy document prefixes to RTR/KWT and update related text references');

Artisan::command('app:renumber-doc-date-format {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');

    $buildNumber = function (string $prefix, ?string $date, string $currentNumber): string {
        $trimmedCurrent = trim($currentNumber);
        if ($trimmedCurrent === '' || $date === null || trim($date) === '') {
            return $trimmedCurrent;
        }

        if (preg_match('/-(\d{4})$/', $trimmedCurrent, $matches) !== 1) {
            return $trimmedCurrent;
        }

        return sprintf('%s-%s-%s', $prefix, Carbon::parse($date)->format('dmY'), $matches[1]);
    };

    $plans = [
        ['table' => 'sales_invoices', 'model' => SalesInvoice::class, 'number_column' => 'invoice_number', 'date_column' => 'invoice_date', 'prefix' => 'INV'],
        ['table' => 'sales_returns', 'model' => SalesReturn::class, 'number_column' => 'return_number', 'date_column' => 'return_date', 'prefix' => 'RTR'],
        ['table' => 'delivery_notes', 'model' => DeliveryNote::class, 'number_column' => 'note_number', 'date_column' => 'note_date', 'prefix' => 'SJ'],
        ['table' => 'order_notes', 'model' => OrderNote::class, 'number_column' => 'note_number', 'date_column' => 'note_date', 'prefix' => 'PO'],
        ['table' => 'outgoing_transactions', 'model' => OutgoingTransaction::class, 'number_column' => 'transaction_number', 'date_column' => 'transaction_date', 'prefix' => 'TRXK'],
        ['table' => 'delivery_trips', 'model' => DeliveryTrip::class, 'number_column' => 'trip_number', 'date_column' => 'trip_date', 'prefix' => 'TRP'],
        ['table' => 'receivable_payments', 'model' => ReceivablePayment::class, 'number_column' => 'payment_number', 'date_column' => 'payment_date', 'prefix' => 'KWT'],
        ['table' => 'supplier_payments', 'model' => SupplierPayment::class, 'number_column' => 'payment_number', 'date_column' => 'payment_date', 'prefix' => 'KWTS'],
        ['table' => 'school_bulk_transactions', 'model' => SchoolBulkTransaction::class, 'number_column' => 'transaction_number', 'date_column' => 'transaction_date', 'prefix' => 'BLK'],
        ['table' => 'journal_entries', 'model' => JournalEntry::class, 'number_column' => 'entry_number', 'date_column' => 'entry_date', 'prefix' => 'JR'],
    ];

    $replaceMap = [];
    $counts = [];

    foreach ($plans as $plan) {
        $query = $plan['query'] ?? ($plan['model'])::query();
        $rows = $query
            ->select(['id', $plan['number_column'], $plan['date_column']])
            ->whereNotNull($plan['number_column'])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $old = (string) ($row->{$plan['number_column']} ?? '');
            $new = $buildNumber($plan['prefix'], (string) ($row->{$plan['date_column']} ?? ''), $old);
            if ($new === '' || $new === $old) {
                continue;
            }
            $replaceMap[$old] = $new;
            $counts[$plan['table']] = (int) ($counts[$plan['table']] ?? 0) + 1;
        }
    }

    if ($replaceMap === []) {
        $this->info('Tidak ada nomor dokumen lama yang perlu diubah.');
        return;
    }

    $this->line('Rencana perubahan nomor dokumen:');
    foreach ($counts as $table => $count) {
        $this->line("- {$table}: {$count}");
    }
    $this->line('Total mapping: '.count($replaceMap));

    if ($dryRun) {
        $this->warn('Dry run mode, tidak ada data yang diubah.');
        return;
    }

    DB::transaction(function () use ($plans, $replaceMap, $buildNumber): void {
        foreach ($plans as $plan) {
            $table = (string) $plan['table'];
            DB::table($table)
                ->select(['id', $plan['number_column'], $plan['date_column']])
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $plan, $buildNumber): void {
                    foreach ($rows as $row) {
                        $old = (string) ($row->{$plan['number_column']} ?? '');
                        $new = $buildNumber($plan['prefix'], (string) ($row->{$plan['date_column']} ?? ''), $old);
                        if ($new === '' || $new === $old) {
                            continue;
                        }
                        DB::table($table)
                            ->where('id', (int) $row->id)
                            ->update([$plan['number_column'] => $new]);
                    }
                });
        }

        $textTargets = [
            ['table' => 'receivable_ledgers', 'column' => 'description'],
            ['table' => 'supplier_ledgers', 'column' => 'description'],
            ['table' => 'stock_mutations', 'column' => 'notes'],
            ['table' => 'invoice_payments', 'column' => 'notes'],
            ['table' => 'audit_logs', 'column' => 'description'],
            ['table' => 'audit_logs', 'column' => 'before_data'],
            ['table' => 'audit_logs', 'column' => 'after_data'],
            ['table' => 'audit_logs', 'column' => 'meta_data'],
            ['table' => 'sales_invoices', 'column' => 'notes'],
            ['table' => 'sales_returns', 'column' => 'reason'],
            ['table' => 'sales_returns', 'column' => 'cancel_reason'],
            ['table' => 'delivery_notes', 'column' => 'notes'],
            ['table' => 'delivery_notes', 'column' => 'cancel_reason'],
            ['table' => 'order_notes', 'column' => 'notes'],
            ['table' => 'order_notes', 'column' => 'cancel_reason'],
            ['table' => 'outgoing_transactions', 'column' => 'notes'],
            ['table' => 'delivery_trips', 'column' => 'notes'],
            ['table' => 'receivable_payments', 'column' => 'notes'],
            ['table' => 'receivable_payments', 'column' => 'cancel_reason'],
            ['table' => 'supplier_payments', 'column' => 'notes'],
            ['table' => 'supplier_payments', 'column' => 'cancel_reason'],
            ['table' => 'school_bulk_transactions', 'column' => 'notes'],
            ['table' => 'journal_entries', 'column' => 'description'],
        ];

        foreach ($textTargets as $target) {
            DB::table($target['table'])
                ->select(['id', $target['column']])
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($target, $replaceMap): void {
                    foreach ($rows as $row) {
                        $oldText = (string) ($row->{$target['column']} ?? '');
                        if ($oldText === '') {
                            continue;
                        }
                        $newText = str_replace(array_keys($replaceMap), array_values($replaceMap), $oldText);
                        if ($newText === $oldText) {
                            continue;
                        }
                        DB::table($target['table'])
                            ->where('id', (int) $row->id)
                            ->update([$target['column'] => $newText]);
                    }
                });
        }
    });

    AppCache::forgetAfterFinancialMutation();
    $this->info('Nomor dokumen lama berhasil diubah ke format DDMMYYYY.');
})->purpose('Rewrite stored transaction document numbers from YYYYMMDD to DDMMYYYY and update related text references');

Artisan::command('app:normalize-semester-codes {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');
    /** @var SemesterBookService $semesterBookService */
    $semesterBookService = app(SemesterBookService::class);

    $normalizeCode = function (?string $raw) use ($semesterBookService): ?string {
        $value = strtoupper(trim((string) $raw));
        if ($value === '') {
            return null;
        }

        return $semesterBookService->normalizeSemester($value);
    };

    $deriveFromDate = function (?string $date) use ($semesterBookService): ?string {
        if ($date === null || trim($date) === '') {
            return null;
        }
        try {
            return $semesterBookService->semesterFromDate(Carbon::parse($date)->format('Y-m-d'));
        } catch (\Throwable) {
            return null;
        }
    };

    $tablePlans = [
        ['table' => 'sales_invoices', 'key' => 'id', 'date' => 'invoice_date', 'period' => 'semester_period'],
        ['table' => 'sales_returns', 'key' => 'id', 'date' => 'return_date', 'period' => 'semester_period'],
        ['table' => 'outgoing_transactions', 'key' => 'id', 'date' => 'transaction_date', 'period' => 'semester_period'],
        ['table' => 'receivable_ledgers', 'key' => 'id', 'date' => 'entry_date', 'period' => 'period_code'],
    ];

    $tableChangeCounts = [];
    $tableUpdates = [];
    foreach ($tablePlans as $plan) {
        $changes = [];
        DB::table($plan['table'])
            ->select([$plan['key'], $plan['date'], $plan['period']])
            ->whereNotNull($plan['date'])
            ->orderBy($plan['key'])
            ->chunkById(500, function ($rows) use (&$changes, $plan, $deriveFromDate, $normalizeCode): void {
                foreach ($rows as $row) {
                    $derived = $deriveFromDate((string) $row->{$plan['date']});
                    if ($derived === null) {
                        continue;
                    }
                    $current = $normalizeCode((string) ($row->{$plan['period']} ?? ''));
                    if ($current === $derived) {
                        continue;
                    }
                    $changes[(int) $row->{$plan['key']}] = $derived;
                }
            }, $plan['key']);
        $tableUpdates[$plan['table']] = $changes;
        $tableChangeCounts[$plan['table']] = count($changes);
    }

    $settingsKeys = [
        'semester_period_options',
        'semester_active_periods',
        'closed_semester_periods',
    ];
    $settingsUpdates = [];
    foreach ($settingsKeys as $key) {
        $raw = (string) AppSetting::getValue($key, '');
        $normalized = collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn (string $item): ?string => $normalizeCode($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->unique()
            ->values()
            ->implode(',');
        if ($normalized !== trim($raw)) {
            $settingsUpdates[$key] = $normalized;
        }
    }

    $normalizeEntitySemesterList = function (string $raw) use ($normalizeCode): string {
        return collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(function (string $item) use ($normalizeCode): ?string {
                if (preg_match('/^(\d+)\s*[:|]\s*(.+)$/', $item, $matches) !== 1) {
                    return null;
                }
                $entityId = (int) $matches[1];
                if ($entityId <= 0) {
                    return null;
                }
                $semester = $normalizeCode((string) $matches[2]);
                if ($semester === null) {
                    return null;
                }
                return $entityId.':'.$semester;
            })
            ->filter(fn (?string $item): bool => $item !== null)
            ->unique()
            ->values()
            ->implode(',');
    };

    $customerSemesterRaw = (string) AppSetting::getValue('closed_customer_semester_periods', '');
    $customerSemesterNormalized = $normalizeEntitySemesterList($customerSemesterRaw);
    if ($customerSemesterNormalized !== trim($customerSemesterRaw)) {
        $settingsUpdates['closed_customer_semester_periods'] = $customerSemesterNormalized;
    }

    $supplierSemesterRaw = (string) AppSetting::getValue('closed_supplier_semester_periods', '');
    $supplierSemesterNormalized = $normalizeEntitySemesterList($supplierSemesterRaw);
    if ($supplierSemesterNormalized !== trim($supplierSemesterRaw)) {
        $settingsUpdates['closed_supplier_semester_periods'] = $supplierSemesterNormalized;
    }

    $this->line('Planned semester normalization:');
    foreach ($tablePlans as $plan) {
        $this->line('- '.$plan['table'].': '.($tableChangeCounts[$plan['table']] ?? 0).' row(s)');
    }
    $this->line('- settings keys: '.count($settingsUpdates));

    if ($dryRun) {
        $this->warn('Dry run mode, no data changed.');
        return;
    }

    DB::transaction(function () use ($tablePlans, $tableUpdates, $settingsUpdates): void {
        foreach ($tablePlans as $plan) {
            $updates = $tableUpdates[$plan['table']] ?? [];
            foreach ($updates as $id => $semesterCode) {
                DB::table($plan['table'])
                    ->where($plan['key'], (int) $id)
                    ->update([$plan['period'] => $semesterCode]);
            }
        }

        foreach ($settingsUpdates as $key => $value) {
            AppSetting::setValue((string) $key, (string) $value);
        }
    });

    $this->info('Semester normalization completed.');
})->purpose('Normalize semester codes to academic format S1-2526/S2-2526 based on transaction dates');

Artisan::command('app:db-backup {--path=} {--gzip}', function () {
    $connection = config('database.default');
    $config = config("database.connections.{$connection}");
    $driver = (string) ($config['driver'] ?? '');

    $backupDir = (string) ($this->option('path') ?: storage_path('app/backups/db'));
    File::ensureDirectoryExists($backupDir);
    $filename = 'backup-' . now('Asia/Jakarta')->format('Ymd-His') . '.sql';
    $target = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if ($driver === 'sqlite') {
        $dbPath = (string) ($config['database'] ?? '');
        if ($dbPath === '' || !File::exists($dbPath)) {
            $this->error('SQLite database file not found.');
            return 1;
        }
        File::copy($dbPath, $target . '.sqlite');
        $this->info('SQLite backup created: ' . $target . '.sqlite');
        return 0;
    }

    if ($driver !== 'mysql') {
        $this->error('Only mysql/sqlite backup command is supported.');
        return 1;
    }

    $host = (string) ($config['host'] ?? '127.0.0.1');
    $port = (string) ($config['port'] ?? '3306');
    $database = (string) ($config['database'] ?? '');
    $username = (string) ($config['username'] ?? '');
    $password = (string) ($config['password'] ?? '');

    $command = sprintf(
        'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --quick --lock-tables=false %s > "%s"',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($database),
        $target
    );
    exec($command, $output, $exitCode);
    if ($exitCode !== 0 || !File::exists($target)) {
        $this->error('Backup failed. Ensure mysqldump is available in PATH.');
        return 1;
    }

    if ((bool) $this->option('gzip')) {
        $gzTarget = $target . '.gz';
        $content = File::get($target);
        File::put($gzTarget, gzencode($content, 9));
        File::delete($target);
        $target = $gzTarget;
    }

    $this->info('Backup created: ' . $target);
    return 0;
})->purpose('Create database backup file');

Artisan::command('app:archive:scan {period?} {--semester=} {--dataset=*} {--json}', function (?string $period = null) {
    /** @var DataArchiveService $service */
    $service = app(DataArchiveService::class);
    $requestedDatasets = (array) $this->option('dataset');
    $semester = trim((string) ($this->option('semester') ?: ''));
    $scope = $semester !== ''
        ? $service->resolveScope('semester', null, $semester)
        : $service->resolveScope('year', (int) $period, null);
    $summary = $service->scanByScope($scope, $requestedDatasets, true);

    if ((bool) $this->option('json')) {
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return 0;
    }

    $this->info('Preview Arsip Data');
    $this->line(($summary['period_type'] === 'semester' ? 'Semester: ' : 'Tahun: ').$summary['period_value']);
    $this->line('Total kandidat baris: '.number_format((int) $summary['grand_total'], 0, ',', '.'));

    foreach ($summary['datasets'] as $datasetKey => $dataset) {
        $basis = $dataset['basis'] === 'year' ? 'berdasarkan tahun' : 'berdasarkan bulan';
        $purgeStatus = match ((string) ($dataset['purge_mode'] ?? 'locked')) {
            'standard' => 'siap',
            'financial_guarded' => 'butuh snapshot+rebuild',
            default => 'dikunci',
        };
        $this->newLine();
        $this->line(sprintf(
            '[%s] %s | total %s row(s) | %s | purge %s',
            $datasetKey,
            $dataset['label'],
            number_format((int) $dataset['total_rows'], 0, ',', '.'),
            $basis,
            $purgeStatus
        ));
        foreach ($dataset['tables'] as $table) {
            $this->line('  - '.$table['table'].': '.number_format((int) $table['rows'], 0, ',', '.'));
        }
    }

    if ($summary['missing'] !== []) {
        $this->warn('Dataset tidak dikenal: '.implode(', ', $summary['missing']));
    }

    $this->newLine();
    $periodArgument = $scope['type'] === 'semester' ? '--semester='.$scope['value'] : $scope['value'];
    $this->line('Lanjut export: php artisan app:archive:export '.$periodArgument.' --dataset=...');
    $this->line('Siapkan finansial: php artisan app:archive:prepare-financial '.$periodArgument.' --dataset=...');
    $this->line('Lanjut purge: php artisan app:archive:purge '.$periodArgument.' --dataset=... --confirm');

    return 0;
})->purpose('Preview kandidat arsip data berdasarkan tahun atau semester');

Artisan::command('app:archive:review {--dataset=*} {--json}', function () {
    /** @var DataArchiveService $service */
    $service = app(DataArchiveService::class);
    $requestedDatasets = (array) $this->option('dataset');
    $review = $service->review($requestedDatasets);

    if ((bool) $this->option('json')) {
        $this->line(json_encode($review, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return 0;
    }

    $this->info('Review Arsip Berkala');
    $this->line('Dibuat pada: '.Carbon::parse((string) $review['generated_at'])->format('d-m-Y H:i:s'));

    if ($review['reminders'] !== []) {
        $this->newLine();
        foreach ($review['reminders'] as $reminder) {
            $this->warn('- '.$reminder);
        }
    }

    $rows = collect($review['datasets'])
        ->map(static function (array $dataset): array {
            return [
                $dataset['label'],
                $dataset['retention'],
                $dataset['cutoff_date'],
                number_format((int) $dataset['candidate_rows'], 0, ',', '.'),
                $dataset['recommended_scope'] ?? '-',
                match ((string) ($dataset['purge_mode'] ?? 'locked')) {
                    'standard' => 'siap',
                    'financial_guarded' => 'snapshot+rebuild',
                    default => 'dikunci',
                },
            ];
        })
        ->all();

    $this->table(['Dataset', 'Retention', 'Cutoff', 'Kandidat', 'Scope', 'Mode'], $rows);

    return 0;
})->purpose('Review bulanan kandidat arsip berdasarkan retention policy');

Artisan::command('app:archive:export {period?} {--semester=} {--dataset=*} {--path=}', function (?string $period = null) {
    /** @var DataArchiveService $service */
    $service = app(DataArchiveService::class);
    $requestedDatasets = (array) $this->option('dataset');
    $path = (string) ($this->option('path') ?: '');
    $semester = trim((string) ($this->option('semester') ?: ''));
    $scope = $semester !== ''
        ? $service->resolveScope('semester', null, $semester)
        : $service->resolveScope('year', (int) $period, null);

    try {
        $result = $service->exportByScope($scope, $requestedDatasets, $path !== '' ? $path : null);
    } catch (Throwable $e) {
        $this->error('Export arsip gagal: '.$e->getMessage());
        return 1;
    }

    $this->info('Export arsip selesai.');
    $this->line('Scope        : '.(($scope['type'] === 'semester' ? 'Semester ' : 'Tahun ').$scope['value']));
    $this->line('SQL file     : '.$result['sql_file']);
    $this->line('Manifest file: '.$result['manifest_file']);
    $this->line('Total row    : '.number_format((int) $result['summary']['grand_total'], 0, ',', '.'));

    foreach ($result['summary']['datasets'] as $datasetKey => $dataset) {
        $this->line(sprintf(
            '- %s (%s): %s row(s)',
            $dataset['label'],
            $datasetKey,
            number_format((int) $dataset['total_rows'], 0, ',', '.')
        ));
    }

    if ($result['summary']['missing'] !== []) {
        $this->warn('Dataset tidak dikenal: '.implode(', ', $result['summary']['missing']));
    }

    return 0;
})->purpose('Export arsip SQL berdasarkan tahun atau semester untuk dataset terpilih');

Artisan::command('app:archive:prepare-financial {period?} {--semester=} {--dataset=*} {--manifest=} {--rebuild-journal}', function (?string $period = null) {
    /** @var DataArchiveService $service */
    $service = app(DataArchiveService::class);
    $requestedDatasets = (array) $this->option('dataset');
    $manifest = (string) ($this->option('manifest') ?: '');
    $semester = trim((string) ($this->option('semester') ?: ''));
    $scope = $semester !== ''
        ? $service->resolveScope('semester', null, $semester)
        : $service->resolveScope('year', (int) $period, null);

    try {
        $result = $service->prepareFinancialSnapshotByScope(
            $scope,
            $requestedDatasets,
            $manifest !== '' ? $manifest : null,
            (bool) $this->option('rebuild-journal')
        );
    } catch (Throwable $e) {
        $this->error('Snapshot finansial gagal: '.$e->getMessage());
        return 1;
    }

    $this->info('Snapshot finansial siap.');
    $this->line('Scope         : '.(($scope['type'] === 'semester' ? 'Semester ' : 'Tahun ').$scope['value']));
    $this->line('Snapshot file : '.$result['snapshot_file']);
    $this->line('Manifest file : '.$result['manifest_file']);
    $this->line('Backup file   : '.$result['backup_file']);
    $this->line('Restore status: '.strtoupper($result['restore_status']));
    $this->line('Customer kena : '.number_format(count($result['customer_snapshots']), 0, ',', '.'));
    $this->line('Supplier kena : '.number_format(count($result['supplier_snapshots']), 0, ',', '.'));

    return 0;
})->purpose('Prepare snapshot finansial sebelum purge dataset finansial yang sudah didukung per tahun atau semester');

Artisan::command('app:archive:purge {period?} {--semester=} {--dataset=*} {--manifest=} {--confirm} {--allow-skipped-restore}', function (?string $period = null) {
    /** @var DataArchiveService $service */
    $service = app(DataArchiveService::class);
    $requestedDatasets = (array) $this->option('dataset');
    $manifest = (string) ($this->option('manifest') ?: '');
    $confirm = (bool) $this->option('confirm');
    $allowSkippedRestore = (bool) $this->option('allow-skipped-restore');
    $semester = trim((string) ($this->option('semester') ?: ''));
    $scope = $semester !== ''
        ? $service->resolveScope('semester', null, $semester)
        : $service->resolveScope('year', (int) $period, null);

    try {
        $result = $service->purgeByScope(
            $scope,
            $requestedDatasets,
            $confirm,
            $manifest !== '' ? $manifest : null,
            $allowSkippedRestore
        );
    } catch (Throwable $e) {
        $this->error('Purge arsip ditolak: '.$e->getMessage());
        return 1;
    }

    $this->info($confirm ? 'Purge arsip selesai.' : 'Dry run purge arsip.');
    $this->line('Scope          : '.(($scope['type'] === 'semester' ? 'Semester ' : 'Tahun ').$scope['value']));
    $this->line('Backup file    : '.$result['backup_file']);
    $this->line('Manifest file  : '.$result['manifest_file']);
    $this->line('Restore status : '.strtoupper($result['restore_status']));
    if (! empty($result['snapshot_file'])) {
        $this->line('Snapshot file  : '.$result['snapshot_file']);
    }
    $this->line('Total kandidat : '.number_format((int) $result['summary']['grand_total'], 0, ',', '.'));

    foreach ($result['summary']['datasets'] as $datasetKey => $dataset) {
        $deleted = $result['deleted'][$datasetKey] ?? 0;
        $this->line(sprintf(
            '- %s (%s): kandidat %s row(s), deleted %s row(s)',
            $dataset['label'],
            $datasetKey,
            number_format((int) $dataset['total_rows'], 0, ',', '.'),
            number_format((int) $deleted, 0, ',', '.')
        ));
    }

    if (! $confirm) {
        $this->warn('Belum ada data yang dihapus. Tambahkan --confirm kalau kandidat dan guard sudah sesuai.');
    }

    if (! empty($result['post_check']) && is_array($result['post_check'])) {
        $this->newLine();
        $this->line('Post purge check:');
        $this->line('- financial rebuild exit: '.(string) ($result['post_check']['rebuild_exit'] ?? '-'));
        $this->line('- integrity exit        : '.(string) ($result['post_check']['integrity_exit'] ?? '-'));
        $this->line('- integrity latest ok   : '.var_export($result['post_check']['latest_integrity_status'] ?? null, true));
    }

    return 0;
})->purpose('Purge arsip yang sudah diexport dan lolos guard backup/restore');

Artisan::command('app:sqlite-to-mysql-snapshot {--source=} {--target=} {--temp-db=} {--mysql-host=} {--mysql-port=} {--mysql-user=} {--mysql-password=}', function () {
    $source = (string) ($this->option('source') ?: database_path('database.sqlite'));
    if (! File::exists($source)) {
        $this->error('SQLite source file not found: ' . $source);
        return 1;
    }

    $target = (string) ($this->option('target') ?: database_path('sql/tespgpos_mysql_test_snapshot.sql'));
    File::ensureDirectoryExists(dirname($target));

    $mysqlConfig = config('database.connections.mysql', []);
    $host = (string) ($this->option('mysql-host') ?: ($mysqlConfig['host'] ?? '127.0.0.1'));
    $port = (string) ($this->option('mysql-port') ?: ($mysqlConfig['port'] ?? '3306'));
    $username = (string) ($this->option('mysql-user') ?: ($mysqlConfig['username'] ?? 'root'));
    $password = (string) ($this->option('mysql-password') ?: ($mysqlConfig['password'] ?? ''));
    $tempDb = (string) ($this->option('temp-db') ?: 'tespgpos_test_snapshot');

    $this->line('Preparing temporary MySQL database: ' . $tempDb);

    try {
        $adminPdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $adminPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', str_replace('`', '``', $tempDb)));
        $adminPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', str_replace('`', '``', $tempDb)));
    } catch (Throwable $e) {
        $this->error('Failed preparing temporary MySQL database: ' . $e->getMessage());
        return 1;
    }

    config([
        'database.connections.mysql.host' => $host,
        'database.connections.mysql.port' => $port,
        'database.connections.mysql.database' => $tempDb,
        'database.connections.mysql.username' => $username,
        'database.connections.mysql.password' => $password,
        'database.connections.mysql.charset' => 'utf8mb4',
        'database.connections.mysql.collation' => 'utf8mb4_unicode_ci',
        'database.connections.mysql.strict' => true,
    ]);
    DB::purge('mysql');

    $migrateExit = Artisan::call('migrate:fresh', [
        '--database' => 'mysql',
        '--force' => true,
    ]);
    $this->output->write(Artisan::output());
    if ($migrateExit !== 0) {
        $this->error('MySQL migrate:fresh failed.');
        return 1;
    }

    try {
        $sourcePdo = new PDO(
            'sqlite:' . $source,
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $destPdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $tempDb),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ]
        );

        $tables = $sourcePdo
            ->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);

        $destPdo->exec('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $tableName) {
            $table = (string) $tableName;
            $existsStmt = $destPdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
            $existsStmt->execute([$tempDb, $table]);
            if ((int) $existsStmt->fetchColumn() === 0) {
                $this->warn('Skipping missing destination table: ' . $table);
                continue;
            }

            $sourceColumns = collect($sourcePdo->query(sprintf('PRAGMA table_info("%s")', str_replace('"', '""', $table)))->fetchAll())
                ->pluck('name')
                ->map(fn ($name) => (string) $name)
                ->values()
                ->all();

            $destColumnsStmt = $destPdo->prepare('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ORDINAL_POSITION');
            $destColumnsStmt->execute([$tempDb, $table]);
            $destColumns = collect($destColumnsStmt->fetchAll(PDO::FETCH_COLUMN))
                ->map(fn ($name) => (string) $name)
                ->values()
                ->all();

            $commonColumns = array_values(array_filter(
                $sourceColumns,
                fn (string $column): bool => in_array($column, $destColumns, true)
            ));

            if ($commonColumns === []) {
                $this->warn('Skipping table with no shared columns: ' . $table);
                continue;
            }

            $destPdo->exec(sprintf('DELETE FROM `%s`', str_replace('`', '``', $table)));

            $quotedSqliteColumns = implode(', ', array_map(
                fn (string $column): string => sprintf('"%s"', str_replace('"', '""', $column)),
                $commonColumns
            ));
            $selectStmt = $sourcePdo->query(sprintf(
                'SELECT %s FROM "%s"',
                $quotedSqliteColumns,
                str_replace('"', '""', $table)
            ));

            $insertStmt = $destPdo->prepare(sprintf(
                'INSERT INTO `%s` (`%s`) VALUES (%s)',
                str_replace('`', '``', $table),
                implode('`,`', array_map(fn (string $column): string => str_replace('`', '``', $column), $commonColumns)),
                implode(', ', array_fill(0, count($commonColumns), '?'))
            ));

            $copied = 0;
            while (($row = $selectStmt->fetch()) !== false) {
                $values = [];
                foreach ($commonColumns as $column) {
                    $value = $row[$column] ?? null;
                    if (is_resource($value)) {
                        $value = stream_get_contents($value);
                    }
                    if (is_bool($value)) {
                        $value = $value ? 1 : 0;
                    }
                    $values[] = $value;
                }
                $insertStmt->execute($values);
                $copied++;
            }

            $this->line(sprintf('Copied %d rows from %s', $copied, $table));
        }

        $destPdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $e) {
        $this->error('Snapshot copy failed: ' . $e->getMessage());
        return 1;
    }

    $dumpCommand = sprintf(
        'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --quick --lock-tables=false --default-character-set=utf8mb4 %s > "%s"',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($tempDb),
        $target
    );
    exec($dumpCommand, $dumpOutput, $dumpExitCode);
    if ($dumpExitCode !== 0 || ! File::exists($target)) {
        $this->error('mysqldump failed. Ensure mysqldump is available in PATH.');
        return 1;
    }

    $this->info('MySQL test snapshot created: ' . $target);
    return 0;
})->purpose('Build a MySQL SQL snapshot from the current SQLite database for test deployment');

Artisan::command('app:mysql-prod-bootstrap {--target=} {--temp-db=} {--mysql-host=} {--mysql-port=} {--mysql-user=} {--mysql-password=}', function () {
    $target = (string) ($this->option('target') ?: database_path('sql/tespgpos_mysql_prod_bootstrap.sql'));
    File::ensureDirectoryExists(dirname($target));

    $mysqlConfig = config('database.connections.mysql', []);
    $host = (string) ($this->option('mysql-host') ?: ($mysqlConfig['host'] ?? '127.0.0.1'));
    $port = (string) ($this->option('mysql-port') ?: ($mysqlConfig['port'] ?? '3306'));
    $username = (string) ($this->option('mysql-user') ?: ($mysqlConfig['username'] ?? 'root'));
    $password = (string) ($this->option('mysql-password') ?: ($mysqlConfig['password'] ?? ''));
    $tempDb = (string) ($this->option('temp-db') ?: 'tespgpos_prod_bootstrap');

    $this->line('Preparing temporary MySQL database: ' . $tempDb);

    try {
        $adminPdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $adminPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', str_replace('`', '``', $tempDb)));
        $adminPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', str_replace('`', '``', $tempDb)));
    } catch (Throwable $e) {
        $this->error('Failed preparing temporary MySQL database: ' . $e->getMessage());
        return 1;
    }

    config([
        'database.connections.mysql.host' => $host,
        'database.connections.mysql.port' => $port,
        'database.connections.mysql.database' => $tempDb,
        'database.connections.mysql.username' => $username,
        'database.connections.mysql.password' => $password,
        'database.connections.mysql.charset' => 'utf8mb4',
        'database.connections.mysql.collation' => 'utf8mb4_unicode_ci',
        'database.connections.mysql.strict' => true,
    ]);
    DB::purge('mysql');

    $migrateExit = Artisan::call('migrate:fresh', [
        '--database' => 'mysql',
        '--force' => true,
    ]);
    $this->output->write(Artisan::output());
    if ($migrateExit !== 0) {
        $this->error('MySQL migrate:fresh failed.');
        return 1;
    }

    $seedExit = Artisan::call('db:seed', [
        '--database' => 'mysql',
        '--force' => true,
    ]);
    $this->output->write(Artisan::output());
    if ($seedExit !== 0) {
        $this->error('MySQL db:seed failed.');
        return 1;
    }

    $dumpCommand = sprintf(
        'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --quick --lock-tables=false --default-character-set=utf8mb4 %s > "%s"',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($tempDb),
        $target
    );
    exec($dumpCommand, $dumpOutput, $dumpExitCode);
    if ($dumpExitCode !== 0 || ! File::exists($target)) {
        $this->error('mysqldump failed. Ensure mysqldump is available in PATH.');
        return 1;
    }

    $legacyTarget = database_path('sql/tespgpos_mysql_bootstrap.sql');
    if ($legacyTarget !== $target) {
        File::copy($target, $legacyTarget);
    }

    $this->info('MySQL production bootstrap created: ' . $target);
    return 0;
})->purpose('Build a clean MySQL production bootstrap SQL from current migrations and seeders');

Artisan::command('app:db-restore-test {--file=} {--temp-db=}', function () {
    $startedAt = microtime(true);
    $connection = config('database.default');
    $config = config("database.connections.{$connection}");
    $driver = (string) ($config['driver'] ?? '');

    if ($driver !== 'mysql') {
        $this->warn('Restore test currently supported for MySQL only.');
        DB::table('restore_drill_logs')->insert([
            'backup_file' => null,
            'status' => 'skipped',
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => 'Restore test skipped (non-mysql driver).',
            'tested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return 0;
    }

    $backupDir = storage_path('app/backups/db');
    $file = (string) ($this->option('file') ?: '');
    if ($file === '') {
        $candidates = collect(File::glob($backupDir . DIRECTORY_SEPARATOR . '*.sql'))
            ->sortDesc()
            ->values();
        $file = (string) ($candidates->first() ?? '');
    }
    if ($file === '' || !File::exists($file)) {
        $this->error('No SQL backup file found for restore test.');
        DB::table('restore_drill_logs')->insert([
            'backup_file' => $file !== '' ? $file : null,
            'status' => 'failed',
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => 'No SQL backup file found.',
            'tested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return 1;
    }

    $host = (string) ($config['host'] ?? '127.0.0.1');
    $port = (string) ($config['port'] ?? '3306');
    $username = (string) ($config['username'] ?? '');
    $password = (string) ($config['password'] ?? '');
    $tempDb = (string) ($this->option('temp-db') ?: ('restore_test_' . now()->format('YmdHis')));

    try {
        $adminPdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $adminPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', str_replace('`', '``', $tempDb)));
        $adminPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', str_replace('`', '``', $tempDb)));
    } catch (Throwable $e) {
        $message = 'Failed preparing temporary database: ' . $e->getMessage();
        $privilegeHint = str_contains(strtolower($e->getMessage()), 'denied')
            || str_contains(strtolower($e->getMessage()), 'access')
            || str_contains(strtolower($e->getMessage()), 'permission');
        if ($privilegeHint) {
            $this->warn('Restore test skipped: database user cannot create/drop temporary databases on this server.');
        } else {
            $this->error('Failed preparing temporary database.');
        }
        DB::table('restore_drill_logs')->insert([
            'backup_file' => $file,
            'status' => $privilegeHint ? 'skipped' : 'failed',
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => $message,
            'tested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $privilegeHint ? 0 : 1;
    }

    $restoreCmd = sprintf(
        'mysql --host=%s --port=%s --user=%s --password=%s %s < "%s"',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($tempDb),
        $file
    );
    exec($restoreCmd, $o2, $e2);

    try {
        $adminPdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        $adminPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', str_replace('`', '``', $tempDb)));
    } catch (Throwable) {
        // Ignore cleanup failure and continue reporting restore result.
    }

    if ($e2 !== 0) {
        $this->error('Restore test failed.');
        DB::table('restore_drill_logs')->insert([
            'backup_file' => $file,
            'status' => 'failed',
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => 'Restore command failed.',
            'tested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return 1;
    }

    DB::table('restore_drill_logs')->insert([
        'backup_file' => $file,
        'status' => 'passed',
        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'message' => 'Restore test passed.',
        'tested_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->info('Restore test passed for backup: ' . $file);
    return 0;
})->purpose('Run restore validation test on latest backup');

Artisan::command('app:financial-rebuild {--rebuild-journal}', function () {
    $invoiceUpdated = 0;
    SalesInvoice::query()
        ->select(['id', 'total'])
        ->orderBy('id')
        ->chunkById(200, function ($rows) use (&$invoiceUpdated): void {
            foreach ($rows as $row) {
                $totalPaid = (int) round((float) DB::table('invoice_payments')
                    ->where('sales_invoice_id', (int) $row->id)
                    ->sum('amount'));
                $total = (int) round((float) $row->total);
                $balance = max(0, $total - $totalPaid);
                $paymentStatus = $totalPaid <= 0
                    ? 'unpaid'
                    : ($balance <= 0 ? 'paid' : 'partial');

                SalesInvoice::query()->whereKey((int) $row->id)->update([
                    'total_paid' => $totalPaid,
                    'balance' => $balance,
                    'payment_status' => $paymentStatus,
                ]);
                $invoiceUpdated++;
            }
        });

    $receivableLedgerUpdated = 0;
    Customer::query()->select(['id'])->orderBy('id')->chunkById(200, function ($rows) use (&$receivableLedgerUpdated): void {
        foreach ($rows as $row) {
            $running = 0;
            ReceivableLedger::query()
                ->where('customer_id', (int) $row->id)
                ->orderBy('entry_date')
                ->orderBy('id')
                ->get(['id', 'debit', 'credit'])
                ->each(function (ReceivableLedger $ledger) use (&$running, &$receivableLedgerUpdated): void {
                    $running = max(0, $running + (int) round((float) $ledger->debit) - (int) round((float) $ledger->credit));
                    if ((int) round((float) $ledger->balance_after) !== $running) {
                        ReceivableLedger::query()->whereKey((int) $ledger->id)->update(['balance_after' => $running]);
                    }
                    $receivableLedgerUpdated++;
                });
        }
    });

    $customerUpdated = 0;
    Customer::query()->select(['id'])->orderBy('id')->chunkById(200, function ($rows) use (&$customerUpdated): void {
        foreach ($rows as $row) {
            $balance = max(0, (int) round((float) ReceivableLedger::query()
                ->where('customer_id', (int) $row->id)
                ->sum(DB::raw('debit - credit'))));
            Customer::query()->whereKey((int) $row->id)->update(['outstanding_receivable' => $balance]);
            $customerUpdated++;
        }
    });

    $supplierLedgerUpdated = 0;
    Supplier::query()->select(['id'])->orderBy('id')->chunkById(200, function ($rows) use (&$supplierLedgerUpdated): void {
        foreach ($rows as $row) {
            $running = 0;
            SupplierLedger::query()
                ->where('supplier_id', (int) $row->id)
                ->orderBy('entry_date')
                ->orderBy('id')
                ->get(['id', 'debit', 'credit'])
                ->each(function (SupplierLedger $ledger) use (&$running, &$supplierLedgerUpdated): void {
                    $running = max(0, $running + (int) round((float) $ledger->debit) - (int) round((float) $ledger->credit));
                    if ((int) round((float) $ledger->balance_after) !== $running) {
                        SupplierLedger::query()->whereKey((int) $ledger->id)->update(['balance_after' => $running]);
                    }
                    $supplierLedgerUpdated++;
                });
        }
    });

    $supplierUpdated = 0;
    Supplier::query()->select(['id'])->orderBy('id')->chunkById(200, function ($rows) use (&$supplierUpdated): void {
        foreach ($rows as $row) {
            $balance = max(0, (int) round((float) SupplierLedger::query()
                ->where('supplier_id', (int) $row->id)
                ->sum(DB::raw('debit - credit'))));
            Supplier::query()->whereKey((int) $row->id)->update(['outstanding_payable' => $balance]);
            $supplierUpdated++;
        }
    });

    $journalRebuilt = false;
    if ((bool) $this->option('rebuild-journal')) {
        DB::transaction(function () use (&$journalRebuilt): void {
            JournalEntryLine::query()->delete();
            JournalEntry::query()->delete();
            /** @var AccountingService $accounting */
            $accounting = app(AccountingService::class);

            SalesInvoice::query()->orderBy('invoice_date')->orderBy('id')->chunkById(200, function ($invoices) use ($accounting): void {
                foreach ($invoices as $invoice) {
                    if ((bool) $invoice->is_canceled) {
                        continue;
                    }
                    $method = ((float) $invoice->total_paid >= (float) $invoice->total && (float) $invoice->total > 0) ? 'tunai' : 'kredit';
                    $accounting->postSalesInvoice((int) $invoice->id, Carbon::parse((string) $invoice->invoice_date), (int) round((float) $invoice->total), $method);
                }
            });
            SalesReturn::query()->orderBy('return_date')->orderBy('id')->chunkById(200, function ($returns) use ($accounting): void {
                foreach ($returns as $return) {
                    if ((bool) $return->is_canceled) {
                        continue;
                    }
                    $accounting->postSalesReturn((int) $return->id, Carbon::parse((string) $return->return_date), (int) round((float) $return->total));
                }
            });
            ReceivablePayment::query()->orderBy('payment_date')->orderBy('id')->chunkById(200, function ($payments) use ($accounting): void {
                foreach ($payments as $payment) {
                    if ((bool) $payment->is_canceled) {
                        continue;
                    }

                    $appliedAmount = (int) round((float) DB::table('invoice_payments')
                        ->where('notes', 'like', '%'.(string) $payment->payment_number.'%')
                        ->sum('amount'));
                    $overPayment = max(0, (int) round((float) $payment->amount) - $appliedAmount);

                    $accounting->postReceivablePayment(
                        paymentId: (int) $payment->id,
                        date: Carbon::parse((string) $payment->payment_date),
                        appliedAmount: $appliedAmount,
                        overPayment: $overPayment
                    );
                }
            });
            OutgoingTransaction::query()->orderBy('transaction_date')->orderBy('id')->chunkById(200, function ($rows) use ($accounting): void {
                foreach ($rows as $row) {
                    $accounting->postOutgoingTransaction((int) $row->id, Carbon::parse((string) $row->transaction_date), (int) round((float) $row->total));
                }
            });
            SupplierPayment::query()->orderBy('payment_date')->orderBy('id')->chunkById(200, function ($rows) use ($accounting): void {
                foreach ($rows as $row) {
                    if ((bool) $row->is_canceled) {
                        continue;
                    }
                    $accounting->postSupplierPayment((int) $row->id, Carbon::parse((string) $row->payment_date), (int) round((float) $row->amount));
                }
            });
            $journalRebuilt = true;
        });
    }

    $this->info("Financial rebuild selesai. invoices={$invoiceUpdated}, receivable_ledgers={$receivableLedgerUpdated}, customers={$customerUpdated}, supplier_ledgers={$supplierLedgerUpdated}, suppliers={$supplierUpdated}, journals_rebuilt=".($journalRebuilt ? 'yes' : 'no'));
    return 0;
})->purpose('Rebuild invoice totals, ledger running balances, customer/supplier aggregates, and optional journals');

Artisan::command('app:load-test-light {--loops=50} {--search=abc}', function () {
    $loops = max(1, (int) $this->option('loops'));
    $searchToken = trim((string) $this->option('search'));
    if ($searchToken === '') {
        $searchToken = 'abc';
    }

    $startedAt = microtime(true);
    $metrics = [
        'customers_list_ms' => 0.0,
        'customers_search_ms' => 0.0,
        'products_list_ms' => 0.0,
        'products_search_ms' => 0.0,
        'sales_invoice_list_ms' => 0.0,
        'suppliers_list_ms' => 0.0,
        'suppliers_search_ms' => 0.0,
    ];

    $measure = static function (callable $callback): float {
        $started = microtime(true);
        $callback();
        return (microtime(true) - $started) * 1000;
    };

    for ($i = 0; $i < $loops; $i++) {
        $metrics['customers_list_ms'] += $measure(static function (): void {
            DB::table('customers')->select(['id', 'name', 'city'])->orderBy('name')->limit(20)->get();
        });
        $metrics['customers_search_ms'] += $measure(static function () use ($searchToken): void {
            DB::table('customers')
                ->select(['id', 'name', 'city', 'phone'])
                ->where(function ($query) use ($searchToken): void {
                    $query->where('name', 'like', "%{$searchToken}%")
                        ->orWhere('city', 'like', "%{$searchToken}%")
                        ->orWhere('phone', 'like', "%{$searchToken}%");
                })
                ->orderBy('name')
                ->limit(20)
                ->get();
        });
        $metrics['products_list_ms'] += $measure(static function (): void {
            DB::table('products')->select(['id', 'code', 'name'])->orderBy('name')->limit(20)->get();
        });
        $metrics['products_search_ms'] += $measure(static function () use ($searchToken): void {
            DB::table('products')
                ->select(['id', 'code', 'name'])
                ->where(function ($query) use ($searchToken): void {
                    $query->where('code', 'like', "%{$searchToken}%")
                        ->orWhere('name', 'like', "%{$searchToken}%");
                })
                ->orderBy('name')
                ->limit(20)
                ->get();
        });
        $metrics['sales_invoice_list_ms'] += $measure(static function (): void {
            DB::table('sales_invoices')
                ->select(['id', 'invoice_number', 'customer_id', 'invoice_date'])
                ->orderByDesc('invoice_date')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        });
        $metrics['suppliers_list_ms'] += $measure(static function (): void {
            DB::table('suppliers')->select(['id', 'name'])->orderBy('name')->limit(20)->get();
        });
        $metrics['suppliers_search_ms'] += $measure(static function () use ($searchToken): void {
            DB::table('suppliers')
                ->select(['id', 'name', 'company_name', 'phone'])
                ->where(function ($query) use ($searchToken): void {
                    $query->where('name', 'like', "%{$searchToken}%")
                        ->orWhere('company_name', 'like', "%{$searchToken}%")
                        ->orWhere('phone', 'like', "%{$searchToken}%");
                })
                ->orderBy('name')
                ->limit(20)
                ->get();
        });
    }
    $durationMs = (microtime(true) - $startedAt) * 1000;
    $avgLoopMs = $durationMs / max(1, $loops);
    $avgMetrics = [];
    foreach ($metrics as $metric => $value) {
        $avgMetrics[$metric] = round($value / max(1, $loops), 2);
    }

    if (Schema::hasTable('performance_probe_logs')) {
        PerformanceProbeLog::query()->create([
            'loops' => $loops,
            'duration_ms' => (int) round($durationMs),
            'avg_loop_ms' => (int) round($avgLoopMs),
            'search_token' => $searchToken,
            'metrics' => $avgMetrics,
            'probed_at' => now(),
        ]);
    }

    $this->info(sprintf(
        'Load test light complete. loops=%d, duration=%.2f ms, avg_loop=%.2f ms',
        $loops,
        $durationMs,
        $avgLoopMs
    ));
    $this->line('Avg metrics(ms): '.json_encode($avgMetrics, JSON_UNESCAPED_UNICODE));
    return 0;
})->purpose('Run lightweight DB load test for list/search endpoints');

Artisan::command('app:integrity-check', function () {
    $customerMismatches = [];
    Customer::query()
        ->select(['id', 'name', 'outstanding_receivable'])
        ->orderBy('id')
        ->chunkById(200, function ($customers) use (&$customerMismatches): void {
            foreach ($customers as $customer) {
                $openBalance = max(0, (int) round((float) ReceivableLedger::query()
                    ->where('customer_id', (int) $customer->id)
                    ->sum(DB::raw('debit - credit'))));
                $stored = (int) round((float) $customer->outstanding_receivable);
                if ($openBalance !== $stored) {
                    $customerMismatches[] = [
                        'id' => (int) $customer->id,
                        'name' => (string) $customer->name,
                        'stored' => $stored,
                        'computed' => $openBalance,
                    ];
                }
            }
        });

    $invoiceMismatches = [];
    SalesInvoice::query()
        ->select(['id', 'invoice_number', 'total', 'total_paid', 'balance', 'payment_status'])
        ->orderBy('id')
        ->chunkById(200, function ($invoices) use (&$invoiceMismatches): void {
            foreach ($invoices as $invoice) {
                $computedPaid = (int) round((float) DB::table('invoice_payments')
                    ->where('sales_invoice_id', (int) $invoice->id)
                    ->sum('amount'));
                $computedBalance = max(0, (int) round((float) $invoice->total) - $computedPaid);
                $computedStatus = $computedPaid <= 0
                    ? 'unpaid'
                    : ($computedBalance <= 0 ? 'paid' : 'partial');

                $storedPaid = (int) round((float) $invoice->total_paid);
                $storedBalance = (int) round((float) $invoice->balance);
                $storedStatus = (string) $invoice->payment_status;

                if ($computedPaid !== $storedPaid || $computedBalance !== $storedBalance || $computedStatus !== $storedStatus) {
                    $invoiceMismatches[] = [
                        'id' => (int) $invoice->id,
                        'number' => (string) $invoice->invoice_number,
                        'stored_paid' => $storedPaid,
                        'computed_paid' => $computedPaid,
                        'stored_balance' => $storedBalance,
                        'computed_balance' => $computedBalance,
                        'stored_status' => $storedStatus,
                        'computed_status' => $computedStatus,
                    ];
                }
            }
        });

    $supplierMismatches = [];
    Supplier::query()
        ->select(['id', 'name', 'outstanding_payable'])
        ->orderBy('id')
        ->chunkById(200, function ($suppliers) use (&$supplierMismatches): void {
            foreach ($suppliers as $supplier) {
                $ledgerBalance = (int) round((float) SupplierLedger::query()
                    ->where('supplier_id', (int) $supplier->id)
                    ->sum(DB::raw('debit - credit')));
                $stored = (int) round((float) $supplier->outstanding_payable);
                if ($ledgerBalance !== $stored) {
                    $supplierMismatches[] = [
                        'id' => (int) $supplier->id,
                        'name' => (string) $supplier->name,
                        'stored' => $stored,
                        'computed' => $ledgerBalance,
                    ];
                }
            }
        });

    $invalidReceivableLinks = ReceivablePayment::query()
        ->whereNotNull('customer_id')
        ->whereNotIn('customer_id', Customer::query()->select('id'))
        ->count();
    $invalidSupplierLinks = SupplierPayment::query()
        ->whereNotNull('supplier_id')
        ->whereNotIn('supplier_id', Supplier::query()->select('id'))
        ->count();

    $customerMismatchCount = count($customerMismatches);
    $invoiceMismatchCount = count($invoiceMismatches);
    $supplierMismatchCount = count($supplierMismatches);
    $isOk = $customerMismatchCount === 0
        && $invoiceMismatchCount === 0
        && $supplierMismatchCount === 0
        && $invalidReceivableLinks === 0
        && $invalidSupplierLinks === 0;

    if (Schema::hasTable('integrity_check_logs')) {
        IntegrityCheckLog::query()->create([
            'customer_mismatch_count' => $customerMismatchCount,
            'supplier_mismatch_count' => $supplierMismatchCount,
            'invalid_receivable_links' => (int) $invalidReceivableLinks,
            'invalid_supplier_links' => (int) $invalidSupplierLinks,
            'details' => [
                'customer_sample' => $customerMismatches[0] ?? null,
                'invoice_sample' => $invoiceMismatches[0] ?? null,
                'supplier_sample' => $supplierMismatches[0] ?? null,
            ],
            'is_ok' => $isOk,
            'checked_at' => now(),
        ]);
    }

    $this->info('Integrity check result');
    $this->line('Customer balance mismatches: '.count($customerMismatches));
    $this->line('Invoice payment mismatches : '.count($invoiceMismatches));
    $this->line('Supplier payable mismatches: '.count($supplierMismatches));
    $this->line('Invalid receivable customer links: '.$invalidReceivableLinks);
    $this->line('Invalid supplier payment links: '.$invalidSupplierLinks);

    if ($customerMismatches !== []) {
        $this->warn('Sample customer mismatch: '.json_encode($customerMismatches[0], JSON_UNESCAPED_UNICODE));
    }
    if ($invoiceMismatches !== []) {
        $this->warn('Sample invoice mismatch: '.json_encode($invoiceMismatches[0], JSON_UNESCAPED_UNICODE));
    }
    if ($supplierMismatches !== []) {
        $this->warn('Sample supplier mismatch: '.json_encode($supplierMismatches[0], JSON_UNESCAPED_UNICODE));
    }

    return $isOk ? 0 : 1;
})->purpose('Check cross-module financial integrity (invoice/receivable/supplier ledger)');

Artisan::command('app:query-profile', function () {
    $queries = [
        'customers_list' => "EXPLAIN SELECT id, name, city, phone FROM customers ORDER BY name LIMIT 20",
        'products_list' => "EXPLAIN SELECT id, code, name, stock FROM products ORDER BY name LIMIT 20",
        'sales_invoice_list' => "EXPLAIN SELECT id, invoice_number, customer_id, invoice_date FROM sales_invoices ORDER BY invoice_date DESC, id DESC LIMIT 20",
        'receivable_ledger_customer' => "EXPLAIN SELECT id, customer_id, sales_invoice_id, entry_date, debit, credit FROM receivable_ledgers WHERE customer_id = 1 ORDER BY entry_date DESC, id DESC LIMIT 50",
    ];

    foreach ($queries as $label => $sql) {
        $this->line("== {$label} ==");
        try {
            $rows = DB::select($sql);
            $this->line(json_encode($rows, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $throwable) {
            $this->error("Query profile failed for {$label}: ".$throwable->getMessage());
        }
    }

    return 0;
})->purpose('Run quick EXPLAIN profiling for high-frequency list/search queries');

Artisan::command('app:smoke-test', function () {
    $rows = [];
    $failures = 0;
    $warnings = 0;

    $pushRow = function (string $check, string $status, string $detail) use (&$rows, &$failures, &$warnings): void {
        $rows[] = [$check, $status, $detail];
        if ($status === 'FAIL') {
            $failures++;
        } elseif ($status === 'WARN') {
            $warnings++;
        }
    };

    $pushRow('APP_ENV', strtoupper((string) config('app.env')), 'Current environment');
    $pushRow('APP_DEBUG', (bool) config('app.debug') ? 'WARN' : 'OK', (bool) config('app.debug') ? 'Debug mode masih ON.' : 'Debug mode sudah OFF.');
    $pushRow('DB_CONNECTION', strtoupper((string) config('database.default')), 'Database connection aktif');
    $pushRow('QUEUE_CONNECTION', strtoupper((string) config('queue.default')), 'Queue driver aktif');
    $pushRow('SESSION_DRIVER', strtoupper((string) config('session.driver')), 'Session driver aktif');
    $pushRow('CACHE_STORE', strtoupper((string) config('cache.default')), 'Cache store aktif');

    $requiredTables = [
        'users',
        'products',
        'customers',
        'suppliers',
        'sales_invoices',
        'receivable_ledgers',
        'supplier_ledgers',
        'report_export_tasks',
        'jobs',
    ];

    foreach ($requiredTables as $table) {
        $pushRow(
            'TABLE: '.$table,
            Schema::hasTable($table) ? 'OK' : 'FAIL',
            Schema::hasTable($table) ? 'Tabel tersedia.' : 'Tabel belum ada.'
        );
    }

    $storageLinkPath = public_path('storage');
    $pushRow(
        'STORAGE_LINK',
        File::exists($storageLinkPath) ? 'OK' : 'WARN',
        File::exists($storageLinkPath) ? 'storage link tersedia.' : 'storage link belum dibuat / tidak terbaca.'
    );

    $backupFiles = collect(array_merge(
        File::glob(storage_path('app/backups/*')) ?: [],
        File::glob(storage_path('app/backups/db/*')) ?: []
    ))->filter(static fn (string $path): bool => File::isFile($path));
    $pushRow(
        'BACKUP_FILES',
        $backupFiles->isNotEmpty() ? 'OK' : 'WARN',
        $backupFiles->isNotEmpty() ? 'Backup ditemukan: '.$backupFiles->sort()->last() : 'Belum ada file backup.'
    );

    if (Schema::hasTable('restore_drill_logs')) {
        $latestRestoreDrill = DB::table('restore_drill_logs')->latest('tested_at')->latest('id')->first();
        $restoreStatus = strtoupper((string) ($latestRestoreDrill->status ?? ''));
        $pushRow(
            'RESTORE_DRILL',
            $latestRestoreDrill === null
                ? 'WARN'
                : ($restoreStatus === 'PASSED' ? 'OK' : ($restoreStatus === 'SKIPPED' ? 'WARN' : 'FAIL')),
            $latestRestoreDrill !== null
                ? 'Terakhir: '.Carbon::parse((string) $latestRestoreDrill->tested_at, 'Asia/Jakarta')->format('d-m-Y H:i:s').' / '.$restoreStatus
                : 'Belum ada restore drill log.'
        );
    }

    if (Schema::hasTable('integrity_check_logs')) {
        $latestIntegrityLog = IntegrityCheckLog::query()->latest('checked_at')->latest('id')->first();
        $pushRow(
            'INTEGRITY_CHECK',
            $latestIntegrityLog !== null && (bool) $latestIntegrityLog->is_ok ? 'OK' : ($latestIntegrityLog === null ? 'WARN' : 'FAIL'),
            $latestIntegrityLog !== null
                ? 'Terakhir: '.optional($latestIntegrityLog->checked_at)->format('d-m-Y H:i:s').' / '.((bool) $latestIntegrityLog->is_ok ? 'OK' : 'ANOMALI')
                : 'Belum ada integrity check log.'
        );
    }

    if (Schema::hasTable('performance_probe_logs')) {
        $latestProbe = PerformanceProbeLog::query()->latest('probed_at')->latest('id')->first();
        $pushRow(
            'PERFORMANCE_PROBE',
            $latestProbe !== null ? 'OK' : 'WARN',
            $latestProbe !== null
                ? 'Terakhir: '.optional($latestProbe->probed_at)->format('d-m-Y H:i:s').' / avg '.number_format((int) $latestProbe->avg_loop_ms, 0, ',', '.').' ms'
                : 'Belum ada performance probe log.'
        );
    }

    $this->table(['Check', 'Status', 'Detail'], $rows);
    $this->newLine();
    $this->line('Summary: OK/WARN/FAIL -> '.count($rows).' check(s)');
    $this->line('Warnings: '.$warnings);
    $this->line('Failures: '.$failures);

    return $failures === 0 ? 0 : 1;
})->purpose('Run lightweight smoke test for deploy readiness and key operational checks');

Artisan::command('app:http-smoke-test', function () {
    $admin = User::query()->where('role', 'admin')->orderBy('id')->first();
    if ($admin === null) {
        $this->error('Tidak ada user admin untuk menjalankan HTTP smoke test.');
        return 1;
    }

    $user = User::query()->where('role', 'user')->orderBy('id')->first();

    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $rows = [];
    $failures = 0;
    $warnings = 0;

    $pushRow = function (string $group, string $label, string $status, string $detail) use (&$rows, &$failures, &$warnings): void {
        $rows[] = [$group, $label, $status, $detail];
        if ($status === 'FAIL') {
            $failures++;
        } elseif ($status === 'WARN') {
            $warnings++;
        }
    };

    $dispatch = function (string $method, string $url, User $actingUser, array $payload = []) use ($kernel): array {
        $session = app('session')->driver();
        $session->start();
        Auth::guard()->setUser($actingUser);
        $originalDebug = (bool) config('app.debug');
        config(['app.debug' => true]);

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $payload['_token'] = $session->token();
        }

        $request = Request::create($url, strtoupper($method), $payload);
        $request->setLaravelSession($session);
        $request->headers->set('Accept', 'text/html,application/xhtml+xml');
        $request->headers->set('X-CSRF-TOKEN', $session->token());
        $request->setUserResolver(static fn () => $actingUser);

        try {
            $response = $kernel->handle($request);
            $status = (int) $response->getStatusCode();
            $kernel->terminate($request, $response);
            $responsePreview = trim(preg_replace('/\s+/', ' ', strip_tags((string) $response->getContent())) ?? '');
            $detail = 'HTTP '.$status;
            if ($status >= 500 && $responsePreview !== '') {
                $detail .= ' - ' . mb_substr($responsePreview, 0, 180);
            }
            return [
                'ok' => $status >= 200 && $status < 300,
                'detail' => $detail,
            ];
        } catch (\Throwable $throwable) {
            return [
                'ok' => false,
                'detail' => $throwable->getMessage(),
            ];
        } finally {
            config(['app.debug' => $originalDebug]);
            Auth::guard()->logout();
        }
    };

    $runRouteSet = function (string $group, array $routes, User $actingUser) use ($dispatch, $pushRow): void {
        foreach ($routes as $label => $definition) {
            $method = 'GET';
            $url = null;
            $payload = [];

            if (is_array($definition)) {
                $method = strtoupper((string) ($definition['method'] ?? 'GET'));
                $url = (string) ($definition['url'] ?? '');
                $payload = (array) ($definition['payload'] ?? []);
            } else {
                $url = (string) $definition;
            }

            if ($url === '') {
                $pushRow($group, $label, 'WARN', 'Route dilewati karena URL kosong.');
                continue;
            }

            $result = $dispatch($method, $url, $actingUser, $payload);
            $pushRow($group, $label, $result['ok'] ? 'OK' : 'FAIL', $result['detail']);
        }
    };

    $adminRoutes = [
        'Dashboard' => route('dashboard'),
        'Kategori Barang' => route('item-categories.index'),
        'Satuan Barang' => route('product-units.index'),
        'Barang' => route('products.index'),
        'Level Customer' => route('customer-levels-web.index'),
        'Customer' => route('customers-web.index'),
        'Supplier' => route('suppliers.index'),
        'Transaksi Keluar' => route('outgoing-transactions.index'),
        'Hutang Supplier' => route('supplier-payables.index'),
        'Kartu Stok Supplier' => route('supplier-stock-cards.index'),
        'Lokasi Kirim' => route('customer-ship-locations.index'),
        'Sebar Sekolah' => route('school-bulk-transactions.index'),
        'Faktur Penjualan' => route('sales-invoices.index'),
        'Retur Penjualan' => route('sales-returns.index'),
        'Surat Jalan' => route('delivery-notes.index'),
        'Catatan Perjalanan' => route('delivery-trips.index'),
        'Surat Pesanan' => route('order-notes.index'),
        'Piutang' => route('receivables.index'),
        'Piutang Global' => route('receivables.global.index'),
        'Piutang Semester' => route('receivables.semester.index'),
        'Bayar Piutang' => route('receivable-payments.index'),
        'Laporan' => route('reports.index'),
        'Users' => route('users.index'),
        'Audit Log' => route('audit-logs.index'),
        'Approval' => route('approvals.index'),
        'Semester Transaksi' => route('semester-transactions.index'),
        'Ops Health' => route('ops-health.index'),
        'Pengaturan' => route('settings.edit'),
    ];

    $runRouteSet('Admin Menu', $adminRoutes, $admin);

    if ($user !== null) {
        $userRoutes = [
            'Dashboard' => route('dashboard'),
            'Supplier' => route('suppliers.index'),
            'Transaksi Keluar' => route('outgoing-transactions.index'),
            'Hutang Supplier' => route('supplier-payables.index'),
            'Kartu Stok Supplier' => route('supplier-stock-cards.index'),
            'Lokasi Kirim' => route('customer-ship-locations.index'),
            'Sebar Sekolah' => route('school-bulk-transactions.index'),
            'Faktur Penjualan' => route('sales-invoices.index'),
            'Retur Penjualan' => route('sales-returns.index'),
            'Surat Jalan' => route('delivery-notes.index'),
            'Catatan Perjalanan' => route('delivery-trips.index'),
            'Surat Pesanan' => route('order-notes.index'),
            'Piutang' => route('receivables.index'),
            'Piutang Global' => route('receivables.global.index'),
            'Piutang Semester' => route('receivables.semester.index'),
            'Bayar Piutang' => route('receivable-payments.index'),
            'Laporan' => route('reports.index'),
            'Pengaturan' => route('settings.edit'),
        ];

        $runRouteSet('User Menu', $userRoutes, $user);
    } else {
        $pushRow('User Menu', 'Role user', 'WARN', 'Tidak ada user role=user, route user dilewati.');
    }

    $documentRoutes = [];
    if ($invoice = SalesInvoice::query()->orderBy('id')->first()) {
        $documentRoutes['Detail Faktur'] = route('sales-invoices.show', $invoice);
        $documentRoutes['Print Faktur'] = route('sales-invoices.print', $invoice);
    }
    if ($salesReturn = SalesReturn::query()->orderBy('id')->first()) {
        $documentRoutes['Detail Retur'] = route('sales-returns.show', $salesReturn);
        $documentRoutes['Print Retur'] = route('sales-returns.print', $salesReturn);
    }
    if ($deliveryNote = DeliveryNote::query()->orderBy('id')->first()) {
        $documentRoutes['Detail Surat Jalan'] = route('delivery-notes.show', $deliveryNote);
        $documentRoutes['Print Surat Jalan'] = route('delivery-notes.print', $deliveryNote);
    }
    if ($orderNote = OrderNote::query()->orderBy('id')->first()) {
        $documentRoutes['Detail Surat Pesanan'] = route('order-notes.show', $orderNote);
        $documentRoutes['Print Surat Pesanan'] = route('order-notes.print', $orderNote);
    }
    if ($deliveryTrip = DeliveryTrip::query()->orderBy('id')->first()) {
        $documentRoutes['Detail Catatan Perjalanan'] = route('delivery-trips.show', $deliveryTrip);
        $documentRoutes['Print Catatan Perjalanan'] = route('delivery-trips.print', $deliveryTrip);
    }
    if ($outgoing = OutgoingTransaction::query()->orderBy('id')->first()) {
        $documentRoutes['Detail Transaksi Keluar'] = route('outgoing-transactions.show', $outgoing);
        $documentRoutes['Print Transaksi Keluar'] = route('outgoing-transactions.print', $outgoing);
    }
    if ($receivablePayment = ReceivablePayment::query()->orderBy('id')->first()) {
        $documentRoutes['Detail Bayar Piutang'] = route('receivable-payments.show', $receivablePayment);
        $documentRoutes['Print Bayar Piutang'] = route('receivable-payments.print', $receivablePayment);
    }
    if ($supplierPayment = SupplierPayment::query()->orderBy('id')->first()) {
        $documentRoutes['Detail Bayar Supplier'] = route('supplier-payables.show-payment', $supplierPayment);
        $documentRoutes['Print Bayar Supplier'] = route('supplier-payables.print-payment', $supplierPayment);
    }
    if ($bulk = SchoolBulkTransaction::query()->orderBy('id')->first()) {
        $documentRoutes['Detail Sebar Sekolah'] = route('school-bulk-transactions.show', $bulk);
        $documentRoutes['Print Sebar Sekolah'] = route('school-bulk-transactions.print', $bulk);
    }
    if ($customer = Customer::query()->orderBy('id')->first()) {
        $documentRoutes['Print Tagihan Customer'] = route('receivables.print-customer-bill', $customer);
    }

    if ($documentRoutes !== []) {
        $runRouteSet('Document Routes', $documentRoutes, $admin);
    } else {
        $pushRow('Document Routes', 'Dokumen', 'WARN', 'Belum ada data dokumen untuk diuji.');
    }

    $reportRoutes = [
        'Print Report Barang' => route('reports.print', ['dataset' => 'products']),
        'Print Report Customer' => route('reports.print', ['dataset' => 'customers']),
        'Print Report Faktur' => route('reports.print', ['dataset' => 'sales_invoices', 'semester' => 'S2-2526']),
        'Print Report Piutang' => route('reports.print', ['dataset' => 'receivables', 'semester' => 'S2-2526']),
        'Print Report Surat Jalan' => route('reports.print', ['dataset' => 'delivery_notes', 'semester' => 'S2-2526']),
        'Print Report Surat Pesanan' => route('reports.print', ['dataset' => 'order_notes', 'semester' => 'S2-2526']),
        'Print Report Transaksi Keluar' => route('reports.print', ['dataset' => 'outgoing_transactions', 'semester' => 'S2-2526']),
    ];
    $runRouteSet('Report Routes', $reportRoutes, $admin);

    $lookupRoutes = [
        'Lookup Supplier' => route('suppliers.lookup', ['search' => 'a']),
        'Status Export Queue' => route('reports.queue.status'),
    ];
    if ($customer !== null) {
        $lookupRoutes['Lookup Lokasi Kirim Customer'] = route('customer-ship-locations.lookup', [
            'customer_id' => $customer->id,
            'search' => 'a',
        ]);
        $lookupRoutes['Lookup Subjenis Cetak Customer'] = route('api.customers.printing-subtypes.index', $customer);
    }
    if ($orderNote !== null && $customer !== null) {
        $lookupRoutes['Lookup Surat Pesanan Customer'] = route('api.order-notes.lookup', [
            'customer_id' => $customer->id,
            'search' => (string) $orderNote->note_number,
        ]);
    }
    $runRouteSet('Lookup Routes', $lookupRoutes, $admin);

    $actionRoutes = [];
    if ($invoice !== null) {
        $previewItems = SalesInvoice::query()
            ->with('items:id,sales_invoice_id,product_id,quantity,unit_price,discount')
            ->whereKey($invoice->id)
            ->first()
            ?->items
            ->map(fn ($item): array => [
                'product_id' => (int) ($item->product_id ?? 0),
                'quantity' => (int) ($item->quantity ?? 0),
                'unit_price' => (int) round((float) ($item->unit_price ?? 0)),
                'discount' => (int) round((float) ($item->discount ?? 0)),
            ])
            ->values()
            ->all() ?? [];

        if ($previewItems !== []) {
            $actionRoutes['Preview Koreksi Stok Faktur'] = [
                'method' => 'POST',
                'url' => route('transaction-corrections.preview-stock'),
                'payload' => [
                    'type' => 'sales_invoice',
                    'subject_id' => (int) $invoice->id,
                    'requested_patch_json' => json_encode(['items' => $previewItems], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ];
        }
    }
    if ($actionRoutes !== []) {
        $runRouteSet('Action Routes', $actionRoutes, $admin);
    } else {
        $pushRow('Action Routes', 'Preview koreksi', 'WARN', 'Belum ada data invoice untuk preview POST aman.');
    }

    $this->table(['Group', 'Route', 'Status', 'Detail'], $rows);
    $this->newLine();
    $this->line('Warnings: '.$warnings);
    $this->line('Failures: '.$failures);

    return $failures === 0 ? 0 : 1;
})->purpose('Run HTTP smoke checks for menu, document, and report routes without requiring PHPUnit on the server');

Artisan::command('app:deploy-check {--skip-ops} {--full-suite}', function () {
    $failed = false;

    if (! (bool) $this->option('skip-ops')) {
        $this->info('== app:smoke-test ==');
        $opsExitCode = Artisan::call('app:smoke-test');
        $this->output->write(Artisan::output());
        if ($opsExitCode !== 0) {
            $failed = true;
            $this->error('app:smoke-test mendeteksi FAIL.');
        }
    }

    $hasArtisanTest = array_key_exists('test', Artisan::all())
        && (file_exists(base_path('vendor/bin/phpunit')) || file_exists(base_path('vendor/bin/pest')));

    if ($hasArtisanTest) {
        $files = [
            'tests/Feature/PageLoadSmokeTest.php',
            'tests/Feature/DocumentOutputSmokeTest.php',
            'tests/Feature/ReportOutputSmokeTest.php',
            'tests/Feature/ActionSmokeTest.php',
        ];

        $args = [PHP_BINARY, base_path('artisan'), 'test', ...$files, '--stop-on-failure'];
        if ((bool) $this->option('full-suite')) {
            $args = [PHP_BINARY, base_path('artisan'), 'test', '--stop-on-failure'];
        }

        $this->info('== artisan test ==');
        $process = new Process($args, base_path(), [
            'APP_ENV' => 'testing',
        ]);
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        $processOutput = $process->getOutput().$process->getErrorOutput();
        $artisanTestMissing = str_contains($processOutput, 'Command "test" is not defined');

        if ($artisanTestMissing) {
            $this->warn('Command "artisan test" tidak tersedia di server ini. Fallback ke app:http-smoke-test.');
            $httpSmokeExitCode = Artisan::call('app:http-smoke-test');
            $this->output->write(Artisan::output());
            if ($httpSmokeExitCode !== 0) {
                $failed = true;
                $this->error('HTTP smoke test gagal.');
            }
        } elseif (! $process->isSuccessful()) {
            $failed = true;
            $this->error('Smoke test halaman/dokumen gagal.');
        }
    } else {
        $this->warn('Command "artisan test" tidak tersedia di server ini. Fallback ke app:http-smoke-test.');
        $httpSmokeExitCode = Artisan::call('app:http-smoke-test');
        $this->output->write(Artisan::output());
        if ($httpSmokeExitCode !== 0) {
            $failed = true;
            $this->error('HTTP smoke test gagal.');
        }
    }

    if ($failed) {
        $this->error('Deploy check selesai dengan masalah. Periksa output di atas.');
        return 1;
    }

    $this->info('Deploy check selesai tanpa FAIL.');
    return 0;
})->purpose('Run operational smoke test plus HTTP page/document/report smoke tests before or after deploy');

Artisan::command('app:restore-document {type} {id}', function () {
    $type = strtolower(trim((string) $this->argument('type')));
    $id = (int) $this->argument('id');
    if ($id <= 0) {
        $this->error('ID tidak valid.');
        return 1;
    }

    $modelClass = match ($type) {
        'invoice' => SalesInvoice::class,
        'return' => SalesReturn::class,
        'receivable_payment' => ReceivablePayment::class,
        'outgoing' => OutgoingTransaction::class,
        'supplier_payment' => SupplierPayment::class,
        default => null,
    };
    if ($modelClass === null) {
        $this->error('Type tidak valid. Gunakan: invoice|return|receivable_payment|outgoing|supplier_payment');
        return 1;
    }

    $record = $modelClass::withTrashed()->whereKey($id)->first();
    if ($record === null) {
        $this->error('Dokumen tidak ditemukan.');
        return 1;
    }
    if ($record->deleted_at === null) {
        $this->warn('Dokumen tidak dalam status terhapus.');
        return 0;
    }

    $record->restore();
    $this->info('Dokumen berhasil direstore.');
    return 0;
})->purpose('Restore soft-deleted financial document by type and id');

Artisan::command('app:report-exports-prune {--days=14}', function () {
    $days = max(1, (int) $this->option('days'));
    $cutoff = now()->subDays($days);
    $query = ReportExportTask::query()->where('created_at', '<', $cutoff);
    $count = 0;

    $query->orderBy('id')->chunkById(200, function ($tasks) use (&$count): void {
        foreach ($tasks as $task) {
            $path = (string) ($task->file_path ?? '');
            if ($path !== '' && File::exists(storage_path('app/'.$path))) {
                File::delete(storage_path('app/'.$path));
            }
            $task->delete();
            $count++;
        }
    });

    $this->info("Prune report export selesai. deleted={$count}");
    return 0;
})->purpose('Prune old queued export files/tasks from storage and database');

Artisan::command('app:report-exports-fix-stuck {--minutes=30}', function () {
    $minutes = max(1, (int) $this->option('minutes'));
    $threshold = now()->subMinutes($minutes);
    $affected = ReportExportTask::query()
        ->where('status', 'processing')
        ->where('updated_at', '<', $threshold)
        ->update([
            'status' => 'failed',
            'error_message' => "Auto fail: processing timeout > {$minutes} minutes",
        ]);

    $this->info("Fixed stuck export tasks: {$affected}");
    return 0;
})->purpose('Mark processing export tasks as failed when stuck for too long');

Schedule::command('app:db-backup --gzip')->dailyAt('01:00');
Schedule::command('app:db-restore-test')->weeklyOn(0, '02:00');
Schedule::command('app:archive:review')->monthlyOn(1, '02:30');
Schedule::command('app:integrity-check')->dailyAt('03:00');
Schedule::command('app:financial-rebuild')->dailyAt('03:30');
Schedule::command('app:load-test-light --loops=80 --search=ang')->dailyAt('04:30');
Schedule::command('app:report-exports-prune --days=14')->dailyAt('04:00');
Schedule::command('app:report-exports-fix-stuck --minutes=30')->hourly();
