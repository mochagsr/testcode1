<?php

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchLookupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_lookup_returns_empty_when_search_token_below_three_chars(): void
    {
        $response = $this->getJson('/api/customers?search=ma');

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }

    public function test_customer_lookup_returns_matches_for_valid_search_token(): void
    {
        \App\Models\Customer::query()->create([
            'code' => 'CUST-LOOK-001',
            'name' => 'Mawar Jaya',
            'city' => 'Malang',
        ]);

        $response = $this->getJson('/api/customers?search=maw');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Mawar Jaya');
    }

    public function test_product_lookup_respects_minimum_token_and_active_only_filter(): void
    {
        $category = ItemCategory::query()->create([
            'code' => 'CAT-LOOK-001',
            'name' => 'Kategori Lookup',
        ]);
        Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-LOOK-A',
            'name' => 'Matematika Dasar',
            'unit' => 'exp',
            'stock' => 5,
            'price_general' => 10000,
            'is_active' => true,
        ]);
        Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-LOOK-B',
            'name' => 'Matematika Lanjut',
            'unit' => 'exp',
            'stock' => 5,
            'price_general' => 12000,
            'is_active' => false,
        ]);

        $shortTokenResponse = $this->getJson('/api/products?search=ma&active_only=1');
        $shortTokenResponse->assertOk();
        $shortTokenResponse->assertJsonPath('data', []);

        $validTokenResponse = $this->getJson('/api/products?search=mat&active_only=1');
        $validTokenResponse->assertOk();
        $validTokenResponse->assertJsonCount(1, 'data');
        $validTokenResponse->assertJsonPath('data.0.code', 'PRD-LOOK-A');
    }

    public function test_supplier_lookup_requires_auth_and_applies_minimum_token_rule(): void
    {
        Supplier::query()->create([
            'name' => 'Sumber Makmur',
            'company_name' => 'PT Sumber Makmur',
        ]);

        $this->get(route('suppliers.lookup', ['search' => 'sum']))
            ->assertRedirect(route('login'));

        $user = User::factory()->create();

        $shortTokenResponse = $this->actingAs($user)->getJson(route('suppliers.lookup', ['search' => 'su']));
        $shortTokenResponse->assertOk();
        $shortTokenResponse->assertJsonPath('data', []);

        $validTokenResponse = $this->actingAs($user)->getJson(route('suppliers.lookup', ['search' => 'sum']));
        $validTokenResponse->assertOk();
        $validTokenResponse->assertJsonCount(1, 'data');
        $validTokenResponse->assertJsonPath('data.0.name', 'Sumber Makmur');
    }
}
