<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PulseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_pulse_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);

        $this->actingAs($admin)->get('/pulse')->assertOk();
    }

    public function test_non_admin_is_forbidden_from_pulse_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'user', 'permissions' => []]);

        $this->actingAs($user)->get('/pulse')->assertForbidden();
    }

    public function test_guest_cannot_open_pulse_dashboard(): void
    {
        $this->get('/pulse')->assertForbidden();
    }

    public function test_pulse_menu_visible_only_to_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('pulse'), false);

        $user = User::factory()->create(['role' => 'user', 'permissions' => ['dashboard.view']]);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('pulse'), false);
    }
}
