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
            '--dataset' => ['sales_invoices'],
            '--confirm' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('dataset finansial belum dibuka', strtolower(Artisan::output()));
    }
}
