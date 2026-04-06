<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterPermissionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_user_cannot_manage_customers_or_products(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => config('rbac.roles.user', []),
        ]);

        $this->actingAs($user)->get(route('customers-web.index'))->assertOk();
        $this->actingAs($user)->get(route('customers-web.create'))->assertForbidden();
        $this->actingAs($user)->get(route('products.index'))->assertForbidden();
        $this->actingAs($user)->get(route('products.create'))->assertForbidden();
    }

    public function test_user_with_detailed_master_permissions_can_manage_customers_and_products(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [
                'dashboard.view',
                'settings.profile',
                'masters.customers.view',
                'masters.customers.manage',
                'masters.products.view',
                'masters.products.manage',
            ],
        ]);

        $this->actingAs($user)->get(route('customers-web.index'))->assertOk();
        $this->actingAs($user)->get(route('customers-web.create'))->assertOk();
        $this->actingAs($user)->get(route('products.index'))->assertOk();
        $this->actingAs($user)->get(route('products.create'))->assertOk();
    }
}
