<?php

use App\Models\ReceivablePayment;
use App\Models\ReceivableLedger;
use App\Models\OutgoingTransaction;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\AppSetting;
use App\Support\SemesterBookService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

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

Artisan::command('app:db-restore-test {--file=} {--temp-db=}', function () {
    $connection = config('database.default');
    $config = config("database.connections.{$connection}");
    $driver = (string) ($config['driver'] ?? '');

    if ($driver !== 'mysql') {
        $this->warn('Restore test currently supported for MySQL only.');
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
        return 1;
    }

    $host = (string) ($config['host'] ?? '127.0.0.1');
    $port = (string) ($config['port'] ?? '3306');
    $username = (string) ($config['username'] ?? '');
    $password = (string) ($config['password'] ?? '');
    $tempDb = (string) ($this->option('temp-db') ?: ('restore_test_' . now()->format('YmdHis')));

    $dropCreate = sprintf(
        'mysql --host=%s --port=%s --user=%s --password=%s -e "DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s`;"',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($username),
        escapeshellarg($password),
        $tempDb,
        $tempDb
    );
    exec($dropCreate, $o1, $e1);
    if ($e1 !== 0) {
        $this->error('Failed preparing temporary database.');
        return 1;
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

    $cleanup = sprintf(
        'mysql --host=%s --port=%s --user=%s --password=%s -e "DROP DATABASE IF EXISTS `%s`;"',
        escapeshellarg($host),
        escapeshellarg($port),
        escapeshellarg($username),
        escapeshellarg($password),
        $tempDb
    );
    exec($cleanup);

    if ($e2 !== 0) {
        $this->error('Restore test failed.');
        return 1;
    }

    $this->info('Restore test passed for backup: ' . $file);
    return 0;
})->purpose('Run restore validation test on latest backup');

Artisan::command('app:load-test-light {--loops=50}', function () {
    $loops = max(1, (int) $this->option('loops'));
    $startedAt = microtime(true);
    for ($i = 0; $i < $loops; $i++) {
        DB::table('customers')->select(['id', 'name', 'city'])->orderBy('name')->limit(20)->get();
        DB::table('products')->select(['id', 'code', 'name'])->orderBy('name')->limit(20)->get();
        DB::table('sales_invoices')
            ->select(['id', 'invoice_number', 'customer_id', 'invoice_date'])
            ->orderByDesc('invoice_date')
            ->limit(20)
            ->get();
        DB::table('suppliers')->select(['id', 'name'])->orderBy('name')->limit(20)->get();
    }
    $durationMs = (microtime(true) - $startedAt) * 1000;

    $this->info(sprintf('Load test light complete. loops=%d, duration=%.2f ms', $loops, $durationMs));
    return 0;
})->purpose('Run lightweight DB load test for list/search endpoints');

Schedule::command('app:db-backup --gzip')->dailyAt('01:00');
