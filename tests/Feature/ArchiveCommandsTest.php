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
            'year' => 2024,
            '--dataset' => ['audit_logs'],
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['grand_total']);
        $this->assertSame(1, $payload['datasets']['audit_logs']['total_rows']);

        $exportPath = storage_path('app/archives-test');
        $exportExit = Artisan::call('app:archive:export', [
            'year' => 2024,
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
            'year' => 2024,
            '--dataset' => ['audit_logs'],
            '--path' => $exportPath,
        ]);

        $manifest = File::glob($exportPath.DIRECTORY_SEPARATOR.'manifests'.DIRECTORY_SEPARATOR.'*.json')[0];

        $dryRunExit = Artisan::call('app:archive:purge', [
            'year' => 2024,
            '--dataset' => ['audit_logs'],
            '--manifest' => $manifest,
        ]);

        $this->assertSame(0, $dryRunExit);
        $this->assertDatabaseCount('audit_logs', 2);

        $purgeExit = Artisan::call('app:archive:purge', [
            'year' => 2024,
            '--dataset' => ['audit_logs'],
            '--manifest' => $manifest,
            '--confirm' => true,
        ]);

        $this->assertSame(0, $purgeExit);
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
            'year' => 2024,
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
            'year' => 2024,
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
            'year' => 2024,
            '--dataset' => ['sales_invoices'],
            '--path' => $exportPath,
        ]);

        $manifest = File::glob($exportPath.DIRECTORY_SEPARATOR.'manifests'.DIRECTORY_SEPARATOR.'*.json')[0];

        $prepareExit = Artisan::call('app:archive:prepare-financial', [
            'year' => 2024,
            '--dataset' => ['sales_invoices'],
            '--manifest' => $manifest,
        ]);

        $this->assertSame(0, $prepareExit);
        $this->assertNotEmpty(File::glob(storage_path('app/archives/financial-snapshots').DIRECTORY_SEPARATOR.'*.json'));

        $purgeExit = Artisan::call('app:archive:purge', [
            'year' => 2024,
            '--dataset' => ['sales_invoices'],
            '--manifest' => $manifest,
            '--confirm' => true,
        ]);

        $this->assertSame(0, $purgeExit);
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
            'archive_year' => 2024,
            'datasets' => ['audit_logs'],
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('archive_scan_result')
            ->assertSessionHas('archive_success');
    }
}
