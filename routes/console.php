<?php

use App\Models\ReceivablePayment;
use App\Models\ReceivableLedger;
use App\Models\Customer;
use App\Models\SupplierLedger;
use App\Models\SupplierPayment;
use App\Models\Supplier;
use App\Models\OutgoingTransaction;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\AppSetting;
use App\Models\ReportExportTask;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Support\SemesterBookService;
use App\Services\AccountingService;
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
        DB::table('restore_drill_logs')->insert([
            'backup_file' => $file,
            'status' => 'failed',
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'message' => 'Failed preparing temporary database.',
            'tested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
    $customerUpdated = 0;
    Customer::query()->select(['id'])->orderBy('id')->chunkById(200, function ($rows) use (&$customerUpdated): void {
        foreach ($rows as $row) {
            $balance = (int) round((float) SalesInvoice::query()
                ->where('customer_id', (int) $row->id)
                ->where('is_canceled', false)
                ->sum('balance'));
            Customer::query()->whereKey((int) $row->id)->update(['outstanding_receivable' => max(0, $balance)]);
            $customerUpdated++;
        }
    });

    $supplierUpdated = 0;
    Supplier::query()->select(['id'])->orderBy('id')->chunkById(200, function ($rows) use (&$supplierUpdated): void {
        foreach ($rows as $row) {
            $balance = (int) round((float) SupplierLedger::query()
                ->where('supplier_id', (int) $row->id)
                ->sum(DB::raw('debit - credit')));
            Supplier::query()->whereKey((int) $row->id)->update(['outstanding_payable' => max(0, $balance)]);
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

    $this->info("Financial rebuild selesai. customers={$customerUpdated}, suppliers={$supplierUpdated}, journals_rebuilt=".($journalRebuilt ? 'yes' : 'no'));
    return 0;
})->purpose('Rebuild financial aggregates (customer/supplier) and optional journal rebuild');

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

Artisan::command('app:integrity-check', function () {
    $customerMismatches = [];
    Customer::query()
        ->select(['id', 'name', 'outstanding_receivable'])
        ->orderBy('id')
        ->chunkById(200, function ($customers) use (&$customerMismatches): void {
            foreach ($customers as $customer) {
                $openBalance = (int) round((float) SalesInvoice::query()
                    ->where('customer_id', (int) $customer->id)
                    ->where('is_canceled', false)
                    ->sum('balance'));
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

    $this->info('Integrity check result');
    $this->line('Customer balance mismatches: '.count($customerMismatches));
    $this->line('Supplier payable mismatches: '.count($supplierMismatches));
    $this->line('Invalid receivable customer links: '.$invalidReceivableLinks);
    $this->line('Invalid supplier payment links: '.$invalidSupplierLinks);

    if ($customerMismatches !== []) {
        $this->warn('Sample customer mismatch: '.json_encode($customerMismatches[0], JSON_UNESCAPED_UNICODE));
    }
    if ($supplierMismatches !== []) {
        $this->warn('Sample supplier mismatch: '.json_encode($supplierMismatches[0], JSON_UNESCAPED_UNICODE));
    }

    return (count($customerMismatches) === 0 && count($supplierMismatches) === 0 && $invalidReceivableLinks === 0 && $invalidSupplierLinks === 0) ? 0 : 1;
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
Schedule::command('app:integrity-check')->dailyAt('03:00');
Schedule::command('app:financial-rebuild')->dailyAt('03:30');
Schedule::command('app:report-exports-prune --days=14')->dailyAt('04:00');
Schedule::command('app:report-exports-fix-stuck --minutes=30')->hourly();
