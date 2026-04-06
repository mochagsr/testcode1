<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerLevelListFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_list_can_search_by_level_code_or_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $levelA = CustomerLevel::query()->create(['code' => 'AGN', 'name' => 'Agen', 'description' => null]);
        $levelB = CustomerLevel::query()->create(['code' => 'RTL', 'name' => 'Retail', 'description' => null]);

        Customer::query()->create([
            'name' => 'Customer Agen',
            'code' => 'CUS-1',
            'customer_level_id' => $levelA->id,
        ]);
        Customer::query()->create([
            'name' => 'Customer Retail',
            'code' => 'CUS-2',
            'customer_level_id' => $levelB->id,
        ]);

        $responseByCode = $this->actingAs($admin)->get(route('customers-web.index', ['search' => 'AGN']));
        $responseByCode->assertOk();
        $responseByCode->assertSee('Customer Agen');
        $responseByCode->assertDontSee('Customer Retail');

        $responseByName = $this->actingAs($admin)->get(route('customers-web.index', ['search' => 'Retail']));
        $responseByName->assertOk();
        $responseByName->assertSee('Customer Retail');
        $responseByName->assertDontSee('Customer Agen');
    }

    public function test_customer_list_can_filter_by_selected_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $levelA = CustomerLevel::query()->create(['code' => 'L1', 'name' => 'Level 1', 'description' => null]);
        $levelB = CustomerLevel::query()->create(['code' => 'L2', 'name' => 'Level 2', 'description' => null]);

        Customer::query()->create([
            'name' => 'Customer Level 1',
            'code' => 'CUS-11',
            'customer_level_id' => $levelA->id,
        ]);
        Customer::query()->create([
            'name' => 'Customer Level 2',
            'code' => 'CUS-22',
            'customer_level_id' => $levelB->id,
        ]);

        $response = $this->actingAs($admin)->get(route('customers-web.index', ['level_id' => $levelA->id]));

        $response->assertOk();
        $response->assertSee('Customer Level 1');
        $response->assertDontSee('Customer Level 2');
    }

    public function test_level_customers_endpoint_returns_only_selected_level_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $levelA = CustomerLevel::query()->create(['code' => 'SCH', 'name' => 'Sekolah', 'description' => null]);
        $levelB = CustomerLevel::query()->create(['code' => 'TOK', 'name' => 'Toko', 'description' => null]);

        $customerA = Customer::query()->create([
            'name' => 'SMP 1',
            'code' => 'CUS-SMP1',
            'customer_level_id' => $levelA->id,
            'city' => 'Malang',
        ]);
        Customer::query()->create([
            'name' => 'Toko ABC',
            'code' => 'CUS-TOK1',
            'customer_level_id' => $levelB->id,
            'city' => 'Batu',
        ]);

        $response = $this->actingAs($admin)->getJson(route('customers-web.level-customers', $levelA));

        $response->assertOk();
        $response->assertJsonPath('level.id', $levelA->id);
        $response->assertJsonPath('level.code', 'SCH');
        $response->assertJsonCount(1, 'customers');
        $response->assertJsonPath('customers.0.id', $customerA->id);
    }

    public function test_customer_list_defaults_to_alphabetical_order_without_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);

        Customer::query()->create([
            'name' => 'Alpha Customer',
            'code' => 'CUS-1001',
        ]);

        Customer::query()->create([
            'name' => 'Zulu Customer',
            'code' => 'CUS-1002',
        ]);

        $response = $this->actingAs($admin)->get(route('customers-web.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Alpha Customer',
            'Zulu Customer',
        ]);
    }

    public function test_customer_store_redirects_to_filtered_list_for_created_customer(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $level = CustomerLevel::query()->create(['code' => 'AGN', 'name' => 'Agen', 'description' => null]);

        $response = $this->actingAs($admin)->post(route('customers-web.store'), [
            'customer_level_id' => $level->id,
            'name' => 'Customer Baru',
            'phone' => '08123456789',
            'phone_secondary' => '08999999999',
            'city' => 'Sidoarjo',
            'address' => 'Jl. Mawar No. 1',
            'outstanding_receivable' => 0,
        ]);

        $response->assertRedirect(route('customers-web.index', ['search' => 'Customer Baru']));
        $this->assertDatabaseHas('customers', [
            'name' => 'Customer Baru',
            'customer_level_id' => $level->id,
            'phone' => '08123456789',
            'phone_secondary' => '08999999999',
            'city' => 'Sidoarjo',
        ]);
    }

    public function test_default_user_can_access_customer_pages(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => config('rbac.roles.user', []),
        ]);

        $this->actingAs($user)
            ->get(route('customers-web.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('customers-web.create'))
            ->assertForbidden();
    }
}
