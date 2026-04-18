<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
        $this->actingAs($user)->get(route('customer-levels-web.index'))->assertForbidden();
        $this->actingAs($user)->get(route('item-categories.index'))->assertForbidden();
        $this->actingAs($user)->get(route('product-units.index'))->assertForbidden();
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
        $this->actingAs($user)->get(route('customer-levels-web.index'))->assertOk();
        $this->actingAs($user)->get(route('customer-levels-web.create'))->assertOk();
        $this->actingAs($user)->get(route('item-categories.index'))->assertOk();
        $this->actingAs($user)->get(route('item-categories.create'))->assertOk();
        $this->actingAs($user)->get(route('product-units.index'))->assertOk();
        $this->actingAs($user)->get(route('product-units.create'))->assertOk();
    }

    public function test_user_with_supplier_permission_can_manage_suppliers(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [
                'dashboard.view',
                'settings.profile',
                'masters.suppliers.view',
                'masters.suppliers.edit',
            ],
        ]);

        $this->actingAs($user)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($user)->get(route('suppliers.create'))->assertOk();
        $this->actingAs($user)->get(route('suppliers.import.template'))->assertOk();
    }

    public function test_user_with_system_detail_permissions_can_access_matching_system_pages(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [
                'dashboard.view',
                'settings.profile',
                'users.manage',
                'audit_logs.view',
                'settings.admin',
                'transactions.correction.approve',
                'imports.transactions',
                'semester.bulk',
                'transactions.create',
            ],
        ]);

        $this->actingAs($user)->get(route('users.index'))->assertOk();
        $this->actingAs($user)->get(route('audit-logs.index'))->assertOk();
        $this->actingAs($user)->get(route('approvals.index'))->assertOk();
        $this->actingAs($user)->get(route('ops-health.index'))->assertOk();
        $this->actingAs($user)->get(route('semester-transactions.index'))->assertOk();
        $this->actingAs($user)->get(route('sales-invoices.import.template'))->assertOk();
        $this->actingAs($user)->get(route('customer-ship-locations.import.template'))->assertOk();
        $this->actingAs($user)->post(route('archive-data.scan'), [
            'archive_scope_type' => 'year',
            'archive_year' => 2025,
            'datasets' => ['audit_logs'],
        ])->assertRedirect();
    }

    public function test_granular_permission_routes_do_not_require_admin_role(): void
    {
        $routes = [
            'sales-invoices.cancel',
            'sales-returns.cancel',
            'delivery-notes.cancel',
            'order-notes.cancel',
            'receivable-payments.cancel',
            'receivables.customer-writeoff',
            'receivables.customer-discount',
            'settings.semester.close',
            'settings.semester.open',
            'supplier-payables.year-close',
            'supplier-payables.year-open',
            'users.index',
            'audit-logs.index',
            'ops-health.index',
            'archive-data.index',
        ];

        foreach ($routes as $routeName) {
            $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

            $this->assertNotContains('admin', $middleware, sprintf('Route [%s] should rely on detailed permissions instead of admin-only middleware.', $routeName));
        }
    }


    public function test_outgoing_create_button_hidden_without_create_permission(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [
                'dashboard.view',
                'transactions.view',
                'settings.profile',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('outgoing-transactions.index'));

        $response->assertOk();
        $response->assertDontSee(route('outgoing-transactions.create'), false);
    }

    public function test_outgoing_create_button_visible_with_create_permission(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [
                'dashboard.view',
                'transactions.view',
                'transactions.create',
                'settings.profile',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('outgoing-transactions.index'));

        $response->assertOk();
        $response->assertSee(route('outgoing-transactions.create'), false);
        $this->actingAs($user)->get(route('outgoing-transactions.create'))->assertOk();
    }

    public function test_user_edit_form_checks_effective_default_role_permissions(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [],
        ]);

        $resolvedPermissions = $user->resolvedPermissions();

        $this->assertContains('dashboard.view', $resolvedPermissions);
        $this->assertContains('transactions.view', $resolvedPermissions);
        $this->assertContains('transactions.create', $resolvedPermissions);
        $this->assertContains('masters.suppliers.edit', $resolvedPermissions);
        $this->assertNotContains('settings.admin', $resolvedPermissions);
    }

}
