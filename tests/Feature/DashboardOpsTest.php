<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_operational_summary_blocks(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('ui.dashboard_pending_approvals'));
        $response->assertSee(__('ui.dashboard_pending_report_tasks'));
        $response->assertSee(__('ui.dashboard_ops_snapshot'));
        $response->assertSee(__('ui.dashboard_database_size'));
        $response->assertSee(__('ui.dashboard_archive_status_title'));
        $response->assertSee(__('ui.dashboard_archive_uat_title'));
        $response->assertSee(__('ui.dashboard_quick_actions'));
    }

    public function test_audit_log_page_shows_extended_module_filters(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);

        $response = $this->actingAs($user)->get(route('audit-logs.index'));

        $response->assertOk();
        $response->assertSee(__('ui.audit_module_receivable_payment'));
        $response->assertSee(__('ui.audit_module_supplier_payment'));
        $response->assertSee(__('ui.audit_module_outgoing_transaction'));
        $response->assertSee(__('ui.audit_module_delivery_trip'));
        $response->assertSee(__('ui.audit_module_school_bulk'));
        $response->assertSee(__('ui.audit_module_master'));
    }

    public function test_audit_log_page_stays_accessible_with_malformed_payload_values(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);

        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'master.customer.updated',
            'subject_type' => \App\Models\Customer::class,
            'subject_id' => 9999,
            'description' => 'Customer malformed payload',
            'before_data' => [
                'updated_at' => '2026-99-99 99:99:99',
                'meta' => ['broken' => ['nested' => ['date' => '2026-13-40']]],
            ],
            'after_data' => [
                'updated_at' => '2026-13-40',
                'notes' => 'Tetap tampil',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('audit-logs.index'));

        $response->assertOk();
        $response->assertSee('Customer malformed payload');
    }
}
