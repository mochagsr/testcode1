<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateReportExportTaskJob;
use App\Models\ReportExportTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportQueuedExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_export_creates_task_and_dispatches_job(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('reports.queue', [
            'dataset' => 'customers',
            'format' => 'excel',
        ]));

        $response->assertRedirect(route('reports.index'));
        $this->assertDatabaseHas('report_export_tasks', [
            'user_id' => $user->id,
            'dataset' => 'customers',
            'format' => 'excel',
            'status' => 'queued',
        ]);
        Queue::assertPushed(GenerateReportExportTaskJob::class);
    }

    public function test_download_queued_export_only_owner_or_admin_can_access(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);

        Storage::disk('local')->put('private/report_exports/1/1/file.xlsx', 'dummy');

        $task = ReportExportTask::query()->create([
            'user_id' => $owner->id,
            'dataset' => 'customers',
            'format' => 'excel',
            'status' => 'ready',
            'file_name' => 'file.xlsx',
            'file_path' => 'private/report_exports/1/1/file.xlsx',
        ]);

        $this->actingAs($other)
            ->get(route('reports.queue.download', $task))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('reports.queue.download', $task))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('reports.queue.download', $task))
            ->assertOk();
    }
}
