<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SystemAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_raise_dedupes_by_key(): void
    {
        SystemAlert::raise('scheduled_failure', 'Backup gagal', 'm', 'critical', [], 'k1');
        SystemAlert::raise('scheduled_failure', 'Backup gagal lagi', 'm2', 'critical', [], 'k1');

        $this->assertSame(1, SystemAlert::query()->unresolved()->count());
        $this->assertSame('Backup gagal lagi', (string) SystemAlert::query()->first()->title);
    }

    public function test_admin_sees_alert_on_ops_health_and_can_resolve(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $alert = SystemAlert::raise('scheduled_failure', 'Backup gagal', 'detail', 'critical', [], 'scheduled_failure:app:db-backup');

        $this->actingAs($admin)->get(route('ops-health.index'))
            ->assertOk()
            ->assertSee('Backup gagal');

        $this->actingAs($admin)->post(route('ops-health.alerts.resolve', $alert))->assertRedirect();
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_dashboard_banner_shows_for_admin_when_unresolved(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        SystemAlert::raise('scheduled_failure', 'X', 'y', 'critical', [], 'k');

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('peringatan sistem');
    }

    public function test_non_admin_cannot_resolve(): void
    {
        $user = User::factory()->create(['role' => 'user', 'permissions' => []]);
        $alert = SystemAlert::raise('scheduled_failure', 'X', 'y');

        $this->actingAs($user)->post(route('ops-health.alerts.resolve', $alert))->assertForbidden();
    }
}
