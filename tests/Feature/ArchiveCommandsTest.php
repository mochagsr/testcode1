<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArchiveCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/archives-test'));
        File::deleteDirectory(storage_path('app/backups'));

        parent::tearDown();
    }

    public function test_archive_scan_and_export_cover_year_filtered_audit_logs(): void
    {
        $user = User::factory()->create();

        DB::table('audit_logs')->insert([
            [
                'user_id' => $user->id,
                'action' => 'created',
                'subject_type' => 'sales_invoice',
                'subject_id' => 1,
                'description' => 'Audit 2024',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'created_at' => '2024-05-01 10:00:00',
                'updated_at' => '2024-05-01 10:00:00',
            ],
            [
                'user_id' => $user->id,
                'action' => 'updated',
                'subject_type' => 'sales_invoice',
                'subject_id' => 2,
                'description' => 'Audit 2025',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'created_at' => '2025-05-01 10:00:00',
                'updated_at' => '2025-05-01 10:00:00',
            ],
        ]);

        Artisan::call('app:archive:scan', [
            'period' => 2024,
            '--dataset' => ['audit_logs'],
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['grand_total']);
        $this->assertSame(1, $payload['datasets']['audit_logs']['total_rows']);

        $exportPath = storage_path('app/archives-test');
        $exportExit = Artisan::call('app:archive:export', [
            'period' => 2024,
            '--dataset' => ['audit_logs'],
            '--path' => $exportPath,
        ]);

        $this->assertSame(0, $exportExit);
        $this->assertNotEmpty(File::glob($exportPath.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'*.sql'));
        $this->assertNotEmpty(File::glob($exportPath.DIRECTORY_SEPARATOR.'manifests'.DIRECTORY_SEPARATOR.'*.json'));

        $sqlFile = File::glob($exportPath.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'*.sql')[0];
        $this->assertStringContainsString('INSERT INTO `audit_logs`', File::get($sqlFile));
        $this->assertStringContainsString('Audit 2024', File::get($sqlFile));
        $this->assertStringNotContainsString('Audit 2025', File::get($sqlFile));
    }

    public function test_archive_purge_deletes_audit_logs_after_backup_and_restore_guards(): void
    {
        $user = User::factory()->create();

        DB::table('audit_logs')->insert([
            [
                'user_id' => $user->id,
                'action' => 'created',
                'subject_type' => 'sales_invoice',
                'subject_id' => 1,
                'description' => 'Audit 2024',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'created_at' => '2024-05-01 10:00:00',
                'updated_at' => '2024-05-01 10:00:00',
            ],
            [
                'user_id' => $user->id,
                'action' => 'updated',
                'subject_type' => 'sales_invoice',
                'subject_id' => 2,
                'description' => 'Audit 2025',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'created_at' => '2025-05-01 10:00:00',
                'updated_at' => '2025-05-01 10:00:00',
            ],
        ]);

        File::ensureDirectoryExists(storage_path('app/backups/db'));
        File::put(storage_path('app/backups/db/backup-test.sql'), '-- backup');

        DB::table('restore_drill_logs')->insert([
            'backup_file' => storage_path('app/backups/db/backup-test.sql'),
            'status' => 'passed',
            'duration_ms' => 100,
            'message' => 'ok',
            'tested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exportPath = storage_path('app/archives-test');
        Artisan::call('app:archive:export', [
            'period' => 2024,
            '--dataset' => ['audit_logs'],
            '--path' => $exportPath,
        ]);

        $manifest = File::glob($exportPath.DIRECTORY_SEPARATOR.'manifests'.DIRECTORY_SEPARATOR.'*.json')[0];

        $dryRunExit = Artisan::call('app:archive:purge', [
            'period' => 2024,
            '--dataset' => ['audit_logs'],
            '--manifest' => $manifest,
        ]);

        $this->assertSame(0, $dryRunExit);
        $this->assertDatabaseCount('audit_logs', 2);

        $purgeExit = Artisan::call('app:archive:purge', [
            'period' => 2024,
            '--dataset' => ['audit_logs'],
            '--manifest' => $manifest,
            '--confirm' => true,
        ]);

        $this->assertSame(0, $purgeExit, Artisan::output());
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'description' => 'Audit 2025',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'description' => 'Audit 2024',
        ]);
    }

    public function test_archive_purge_rejects_financial_dataset(): void
    {
        $exit = Artisan::call('app:archive:purge', [
            'period' => 2024,
            '--dataset' => ['receivable_ledgers'],
            '--confirm' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('masih dikunci', strtolower(Artisan::output()));
    }

    public function test_archive_scan_covers_failed_jobs_and_job_batches(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'failed-2024',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'boom',
            'failed_at' => '2024-05-01 10:00:00',
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'failed-2025',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'boom',
            'failed_at' => '2025-05-01 10:00:00',
        ]);

        DB::table('job_batches')->insert([
            'id' => 'batch-2024',
            'name' => 'Batch 2024',
            'total_jobs' => 1,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => null,
            'cancelled_at' => null,
            'created_at' => strtotime('2024-06-02 11:00:00'),
            'finished_at' => strtotime('2024-06-02 11:10:00'),
        ]);
        DB::table('job_batches')->insert([
            'id' => 'batch-2025',
            'name' => 'Batch 2025',
            'total_jobs' => 1,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => null,
            'cancelled_at' => null,
            'created_at' => strtotime('2025-06-02 11:00:00'),
            'finished_at' => strtotime('2025-06-02 11:10:00'),
        ]);

        Artisan::call('app:archive:scan', [
            'period' => 2024,
            '--dataset' => ['failed_jobs', 'job_batches'],
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(2, $payload['grand_total']);
        $this->assertSame(1, $payload['datasets']['failed_jobs']['total_rows']);
        $this->assertSame(1, $payload['datasets']['job_batches']['total_rows']);
    }

    public function test_financial_snapshot_and_purge_can_run_for_sales_invoices(): void
    {
        $user = User::factory()->create();

        $levelId = DB::table('customer_levels')->insertGetId([
            'code' => 'LVL-AR',
            'name' => 'Level Arsip',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $customerId = DB::table('customers')->insertGetId([
            'customer_level_id' => $levelId,
            'code' => 'CUS-AR-001',
            'name' => 'Customer Arsip',
            'phone' => '08123',
            'city' => 'Malang',
            'address' => 'Jl. Arsip',
            'outstanding_receivable' => 12000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('item_categories')->insertGetId([
            'code' => 'CAT-AR',
            'name' => 'Kategori Arsip',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'item_category_id' => $categoryId,
            'code' => 'BRG-AR',
            'name' => 'Barang Arsip',
            'unit' => 'pcs',
            'stock' => 5,
            'price_general' => 12000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceId = DB::table('sales_invoices')->insertGetId([
            'invoice_number' => 'INV-01012024-0001',
            'customer_id' => $customerId,
            'invoice_date' => '2024-01-01',
            'due_date' => '2024-01-31',
            'semester_period' => 'S2-2425',
            'subtotal' => 12000,
            'total' => 12000,
            'total_paid' => 0,
            'balance' => 12000,
            'payment_status' => 'unpaid',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales_invoice_items')->insert([
            'sales_invoice_id' => $invoiceId,
            'product_id' => $productId,
            'product_code' => 'BRG-AR',
            'product_name' => 'Barang Arsip',
            'quantity' => 1,
            'unit_price' => 12000,
            'discount' => 0,
            'line_total' => 12000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('receivable_ledgers')->insert([
            'customer_id' => $customerId,
            'sales_invoice_id' => $invoiceId,
            'entry_date' => '2024-01-01',
            'period_code' => 'S2-2425',
            'transaction_type' => 'product',
            'description' => 'Invoice INV-01012024-0001',
            'debit' => 12000,
            'credit' => 0,
            'balance_after' => 12000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        File::ensureDirectoryExists(storage_path('app/backups/db'));
        File::put(storage_path('app/backups/db/backup-test.sql'), '-- backup');

        DB::table('restore_drill_logs')->insert([
            'backup_file' => storage_path('app/backups/db/backup-test.sql'),
            'status' => 'passed',
            'duration_ms' => 100,
            'message' => 'ok',
            'tested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exportPath = storage_path('app/archives-test');
        Artisan::call('app:archive:export', [
            'period' => 2024,
            '--dataset' => ['sales_invoices'],
            '--path' => $exportPath,
        ]);

        $manifest = File::glob($exportPath.DIRECTORY_SEPARATOR.'manifests'.DIRECTORY_SEPARATOR.'*.json')[0];

        $prepareExit = Artisan::call('app:archive:prepare-financial', [
            'period' => 2024,
            '--dataset' => ['sales_invoices'],
            '--manifest' => $manifest,
        ]);

        $this->assertSame(0, $prepareExit);
        $this->assertNotEmpty(File::glob(storage_path('app/archives/financial-snapshots').DIRECTORY_SEPARATOR.'*.json'));

        $purgeExit = Artisan::call('app:archive:purge', [
            'period' => 2024,
            '--dataset' => ['sales_invoices'],
            '--manifest' => $manifest,
            '--confirm' => true,
        ]);

        $this->assertSame(0, $purgeExit, Artisan::output());
        $this->assertDatabaseMissing('sales_invoices', ['id' => $invoiceId]);
        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'outstanding_receivable' => 0,
        ]);
    }

    public function test_archive_page_scan_action_stores_result_in_session(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        $response = $this->actingAs($admin)->post(route('archive-data.scan'), [
            'archive_scope_type' => 'year',
            'archive_year' => 2024,
            'datasets' => ['audit_logs'],
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('archive_scan_result')
            ->assertSessionHas('archive_success');
    }

    public function test_archive_scan_and_export_cover_semester_filtered_sales_invoices(): void
    {
        $customerId = $this->createArchiveCustomer('CUS-SEM', 'Customer Semester', 25000);
        $productId = $this->createArchiveProduct('BRG-SEM', 'Barang Semester', 10, 12000);
        $invoiceS1 = $this->createArchiveInvoice($customerId, 'INV-S1-2526', 12000, 0, 12000, 'unpaid', '2025-05-10', 'S1-2526');
        $invoiceS2 = $this->createArchiveInvoice($customerId, 'INV-S2-2526', 13000, 0, 13000, 'unpaid', '2025-11-10', 'S2-2526');

        DB::table('sales_invoice_items')->insert([
            [
                'sales_invoice_id' => $invoiceS1,
                'product_id' => $productId,
                'product_code' => 'SEM-1',
                'product_name' => 'Barang Semester 1',
                'quantity' => 1,
                'unit_price' => 12000,
                'discount' => 0,
                'line_total' => 12000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sales_invoice_id' => $invoiceS2,
                'product_id' => $productId,
                'product_code' => 'SEM-2',
                'product_name' => 'Barang Semester 2',
                'quantity' => 1,
                'unit_price' => 13000,
                'discount' => 0,
                'line_total' => 13000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Artisan::call('app:archive:scan', [
            '--semester' => 'S1-2526',
            '--dataset' => ['sales_invoices'],
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertSame('semester', $payload['period_type']);
        $this->assertSame('S1-2526', $payload['period_value']);
        $this->assertSame(2, $payload['grand_total']);
        $this->assertSame(2, $payload['datasets']['sales_invoices']['total_rows']);

        $exportPath = storage_path('app/archives-test');
        $exportExit = Artisan::call('app:archive:export', [
            '--semester' => 'S1-2526',
            '--dataset' => ['sales_invoices'],
            '--path' => $exportPath,
        ]);

        $this->assertSame(0, $exportExit);
        $manifestFile = File::glob($exportPath.DIRECTORY_SEPARATOR.'manifests'.DIRECTORY_SEPARATOR.'*.json')[0];
        $manifest = json_decode((string) File::get($manifestFile), true);

        $this->assertSame('semester', $manifest['period_type']);
        $this->assertSame('S1-2526', $manifest['period_value']);

        $sqlFile = File::glob($exportPath.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.'*.sql')[0];
        $sql = File::get($sqlFile);
        $this->assertStringContainsString('INV-S1-2526', $sql);
        $this->assertStringNotContainsString('INV-S2-2526', $sql);
    }

    public function test_archive_page_scan_action_accepts_semester_scope(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        $response = $this->actingAs($admin)->post(route('archive-data.scan'), [
            'archive_scope_type' => 'semester',
            'archive_semester' => 'S1-2526',
            'datasets' => ['audit_logs'],
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('archive_scan_result', function (array $result): bool {
                return ($result['period_type'] ?? null) === 'semester'
                    && ($result['period_value'] ?? null) === 'S1-2526';
            })
            ->assertSessionHas('archive_success');
    }

    public function test_archive_review_writes_report_and_lists_candidates(): void
    {
        $user = User::factory()->create();

        DB::table('audit_logs')->insert([
            'user_id' => $user->id,
            'action' => 'created',
            'subject_type' => 'sales_invoice',
            'subject_id' => 1,
            'description' => 'Audit lama',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => '2025-01-10 10:00:00',
            'updated_at' => '2025-01-10 10:00:00',
        ]);

        $exit = Artisan::call('app:archive:review', [
            '--dataset' => ['audit_logs'],
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exit);
        $this->assertIsArray($payload);
        $this->assertSame('audit_logs', $payload['datasets'][0]['key']);
        $this->assertSame(1, $payload['datasets'][0]['candidate_rows']);
        $this->assertNotEmpty(File::glob(storage_path('app/archives/reviews').DIRECTORY_SEPARATOR.'*.json'));
    }

    public function test_financial_snapshot_and_purge_can_run_for_sales_returns(): void
    {
        $customerId = $this->createArchiveCustomer('CUS-AR-RET', 'Customer Retur', 7000);
        $productId = $this->createArchiveProduct('BRG-RET', 'Barang Retur', 10, 3000);
        $invoiceId = $this->createArchiveInvoice($customerId, 'INV-01012024-RET', 10000, 0, 10000, 'unpaid');

        DB::table('receivable_ledgers')->insert([
            'customer_id' => $customerId,
            'sales_invoice_id' => $invoiceId,
            'entry_date' => '2024-01-01',
            'period_code' => 'S2-2425',
            'transaction_type' => 'product',
            'description' => 'Invoice INV-01012024-RET',
            'debit' => 10000,
            'credit' => 0,
            'balance_after' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $returnId = DB::table('sales_returns')->insertGetId([
            'return_number' => 'RTR-02012024-0001',
            'customer_id' => $customerId,
            'return_date' => '2024-01-02',
            'semester_period' => 'S2-2425',
            'transaction_type' => 'product',
            'total' => 3000,
            'reason' => 'Arsip retur',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales_return_items')->insert([
            'sales_return_id' => $returnId,
            'product_id' => $productId,
            'product_code' => 'BRG-RET',
            'product_name' => 'Barang Retur',
            'quantity' => 1,
            'unit_price' => 3000,
            'line_total' => 3000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_mutations')->insert([
            'product_id' => $productId,
            'reference_type' => \App\Models\SalesReturn::class,
            'reference_id' => $returnId,
            'mutation_type' => 'in',
            'quantity' => 1,
            'notes' => 'Retur RTR-02012024-0001',
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('receivable_ledgers')->insert([
            'customer_id' => $customerId,
            'sales_invoice_id' => null,
            'entry_date' => '2024-01-02',
            'period_code' => 'S2-2425',
            'transaction_type' => 'product',
            'description' => 'Retur RTR-02012024-0001',
            'debit' => 0,
            'credit' => 3000,
            'balance_after' => 7000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $journalEntryId = DB::table('journal_entries')->insertGetId([
            'entry_number' => 'JR-02012024-0001',
            'entry_date' => '2024-01-02',
            'entry_type' => 'sales_return_create',
            'reference_type' => \App\Models\SalesReturn::class,
            'reference_id' => $returnId,
            'description' => 'Posting retur #'.$returnId,
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $journalEntryId,
                'account_id' => 6,
                'debit' => 3000,
                'credit' => 0,
                'memo' => 'Retur',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $journalEntryId,
                'account_id' => 2,
                'debit' => 0,
                'credit' => 3000,
                'memo' => 'Piutang',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->prepareArchiveGuards();
        $manifest = $this->exportArchiveManifest(2024, ['sales_returns']);

        $prepareExit = Artisan::call('app:archive:prepare-financial', [
            'period' => 2024,
            '--dataset' => ['sales_returns'],
            '--manifest' => $manifest,
            '--rebuild-journal' => true,
        ]);

        $this->assertSame(0, $prepareExit);

        $purgeExit = Artisan::call('app:archive:purge', [
            'period' => 2024,
            '--dataset' => ['sales_returns'],
            '--manifest' => $manifest,
            '--confirm' => true,
        ]);

        $this->assertSame(0, $purgeExit, Artisan::output());
        $this->assertDatabaseMissing('sales_returns', ['id' => $returnId]);
        $this->assertDatabaseMissing('sales_return_items', ['sales_return_id' => $returnId]);
        $this->assertDatabaseMissing('stock_mutations', ['reference_type' => \App\Models\SalesReturn::class, 'reference_id' => $returnId]);
        $this->assertDatabaseMissing('receivable_ledgers', ['description' => 'Retur RTR-02012024-0001']);
        $this->assertDatabaseMissing('journal_entries', ['reference_type' => \App\Models\SalesReturn::class, 'reference_id' => $returnId]);
        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'outstanding_receivable' => 10000,
        ]);
    }

    public function test_financial_snapshot_and_purge_can_run_for_receivable_payments(): void
    {
        $customerId = $this->createArchiveCustomer('CUS-AR-PAY', 'Customer Bayar', 0);
        $invoiceId = $this->createArchiveInvoice($customerId, 'INV-05012024-0001', 5000, 5000, 0, 'paid');

        DB::table('receivable_ledgers')->insert([
            'customer_id' => $customerId,
            'sales_invoice_id' => $invoiceId,
            'entry_date' => '2024-01-05',
            'period_code' => 'S2-2425',
            'transaction_type' => 'product',
            'description' => 'Invoice INV-05012024-0001',
            'debit' => 5000,
            'credit' => 0,
            'balance_after' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = DB::table('receivable_payments')->insertGetId([
            'payment_number' => 'KWT-06012024-0001',
            'customer_id' => $customerId,
            'payment_date' => '2024-01-06',
            'customer_address' => 'Jl. Arsip',
            'amount' => 5000,
            'amount_in_words' => 'Lima ribu rupiah',
            'customer_signature' => 'Customer',
            'user_signature' => 'Admin',
            'notes' => null,
            'created_by_user_id' => null,
            'is_canceled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invoice_payments')->insert([
            'sales_invoice_id' => $invoiceId,
            'payment_date' => '2024-01-06',
            'amount' => 5000,
            'method' => 'cash',
            'notes' => 'Pembayaran KWT-06012024-0001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('receivable_ledgers')->insert([
            'customer_id' => $customerId,
            'sales_invoice_id' => $invoiceId,
            'entry_date' => '2024-01-06',
            'period_code' => 'S2-2425',
            'transaction_type' => 'product',
            'description' => 'Pembayaran KWT-06012024-0001 untuk INV-05012024-0001',
            'debit' => 0,
            'credit' => 5000,
            'balance_after' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $journalEntryId = DB::table('journal_entries')->insertGetId([
            'entry_number' => 'JR-06012024-0001',
            'entry_date' => '2024-01-06',
            'entry_type' => 'receivable_payment_create',
            'reference_type' => \App\Models\ReceivablePayment::class,
            'reference_id' => $paymentId,
            'description' => 'Posting pembayaran piutang #'.$paymentId,
            'created_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $journalEntryId,
                'account_id' => 1,
                'debit' => 5000,
                'credit' => 0,
                'memo' => 'Kas',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'journal_entry_id' => $journalEntryId,
                'account_id' => 2,
                'debit' => 0,
                'credit' => 5000,
                'memo' => 'Piutang',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->prepareArchiveGuards();
        $manifest = $this->exportArchiveManifest(2024, ['receivable_payments']);

        $prepareExit = Artisan::call('app:archive:prepare-financial', [
            'period' => 2024,
            '--dataset' => ['receivable_payments'],
            '--manifest' => $manifest,
            '--rebuild-journal' => true,
        ]);

        $this->assertSame(0, $prepareExit);

        $purgeExit = Artisan::call('app:archive:purge', [
            'period' => 2024,
            '--dataset' => ['receivable_payments'],
            '--manifest' => $manifest,
            '--confirm' => true,
        ]);

        $this->assertSame(0, $purgeExit);
        $this->assertDatabaseMissing('receivable_payments', ['id' => $paymentId]);
        $this->assertDatabaseMissing('invoice_payments', ['notes' => 'Pembayaran KWT-06012024-0001']);
        $this->assertDatabaseMissing('receivable_ledgers', ['description' => 'Pembayaran KWT-06012024-0001 untuk INV-05012024-0001']);
        $this->assertDatabaseMissing('journal_entries', ['reference_type' => \App\Models\ReceivablePayment::class, 'reference_id' => $paymentId]);
        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoiceId,
            'total_paid' => 0,
            'balance' => 5000,
            'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'outstanding_receivable' => 5000,
        ]);
    }

    private function prepareArchiveGuards(): void
    {
        File::ensureDirectoryExists(storage_path('app/backups/db'));
        File::put(storage_path('app/backups/db/backup-test.sql'), '-- backup');

        DB::table('restore_drill_logs')->insert([
            'backup_file' => storage_path('app/backups/db/backup-test.sql'),
            'status' => 'passed',
            'duration_ms' => 100,
            'message' => 'ok',
            'tested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $datasets
     */
    private function exportArchiveManifest(int $year, array $datasets): string
    {
        $exportPath = storage_path('app/archives-test');
        Artisan::call('app:archive:export', [
            'period' => $year,
            '--dataset' => $datasets,
            '--path' => $exportPath,
        ]);

        return File::glob($exportPath.DIRECTORY_SEPARATOR.'manifests'.DIRECTORY_SEPARATOR.'*.json')[0];
    }

    private function createArchiveCustomer(string $code, string $name, int $outstanding): int
    {
        $levelId = DB::table('customer_levels')->insertGetId([
            'code' => $code.'-LVL',
            'name' => 'Level '.$name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('customers')->insertGetId([
            'customer_level_id' => $levelId,
            'code' => $code,
            'name' => $name,
            'phone' => '08123',
            'city' => 'Malang',
            'address' => 'Jl. Arsip',
            'outstanding_receivable' => $outstanding,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createArchiveProduct(string $code, string $name, int $stock, int $price): int
    {
        $categoryId = DB::table('item_categories')->insertGetId([
            'code' => $code.'-CAT',
            'name' => 'Kategori '.$name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('products')->insertGetId([
            'item_category_id' => $categoryId,
            'code' => $code,
            'name' => $name,
            'unit' => 'pcs',
            'stock' => $stock,
            'price_general' => $price,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createArchiveInvoice(
        int $customerId,
        string $number,
        int $total,
        int $totalPaid,
        int $balance,
        string $status,
        string $invoiceDate = '2024-01-01',
        string $semesterPeriod = 'S2-2425'
    ): int
    {
        return (int) DB::table('sales_invoices')->insertGetId([
            'invoice_number' => $number,
            'customer_id' => $customerId,
            'invoice_date' => $invoiceDate,
            'due_date' => '2024-01-31',
            'semester_period' => $semesterPeriod,
            'subtotal' => $total,
            'total' => $total,
            'total_paid' => $totalPaid,
            'balance' => $balance,
            'payment_status' => $status,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
