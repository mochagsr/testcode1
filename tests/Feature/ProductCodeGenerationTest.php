<?php

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_store_generates_product_code_from_name_when_code_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-01',
            'name' => 'Buku',
        ]);

        $response = $this->actingAs($admin)->post(route('products.store'), [
            'item_category_id' => $category->id,
            'code' => '',
            'name' => 'matematika 1 edisi 5 semester 1 tahun ajar 2025-2026',
            'unit' => 'exp',
            'stock' => 10,
            'price_agent' => 10000,
            'price_sales' => 12000,
            'price_general' => 15000,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'name' => 'matematika 1 edisi 5 semester 1 tahun ajar 2025-2026',
            'code' => 'mt1e5s156',
        ]);
    }

    public function test_web_store_appends_sequence_when_generated_code_conflicts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-01',
            'name' => 'Buku',
        ]);

        Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'mt1e5s156',
            'name' => 'Produk Existing',
            'unit' => 'exp',
            'stock' => 3,
            'price_agent' => 10000,
            'price_sales' => 12000,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('products.store'), [
            'item_category_id' => $category->id,
            'code' => '',
            'name' => 'matematika 1 edisi 5 semester 1 tahun ajar 2025-2026',
            'unit' => 'exp',
            'stock' => 7,
            'price_agent' => 9000,
            'price_sales' => 10000,
            'price_general' => 11000,
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'matematika 1 edisi 5 semester 1 tahun ajar 2025-2026',
            'code' => 'mt1e5s15601',
        ]);
    }

    public function test_api_store_keeps_manual_code_when_provided(): void
    {
        $category = ItemCategory::query()->create([
            'code' => 'CAT-01',
            'name' => 'Buku',
        ]);

        $response = $this->postJson('/api/products', [
            'item_category_id' => $category->id,
            'code' => 'MANUAL-001',
            'name' => 'produk manual',
            'unit' => 'exp',
            'stock' => 1,
            'price_agent' => 1000,
            'price_sales' => 2000,
            'price_general' => 3000,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('code', 'manual-001');
        $this->assertDatabaseHas('products', [
            'name' => 'produk manual',
            'code' => 'manual-001',
        ]);
    }

    public function test_api_store_normalizes_dirty_manual_code_input(): void
    {
        $category = ItemCategory::query()->create([
            'code' => 'CAT-01',
            'name' => 'Buku',
        ]);

        $response = $this->postJson('/api/products', [
            'item_category_id' => $category->id,
            'code' => '  MANUAL -- 001 !! ',
            'name' => 'produk manual kotor',
            'unit' => 'exp',
            'stock' => 1,
            'price_agent' => 1000,
            'price_sales' => 2000,
            'price_general' => 3000,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('code', 'manual-001');
        $this->assertDatabaseHas('products', [
            'name' => 'produk manual kotor',
            'code' => 'manual-001',
        ]);
    }

    public function test_web_update_with_empty_code_keeps_same_generated_code_for_same_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-01',
            'name' => 'Buku',
        ]);

        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'mt1e5s156',
            'name' => 'matematika 1 edisi 5 semester 1 tahun ajar 2025-2026',
            'unit' => 'exp',
            'stock' => 4,
            'price_agent' => 10000,
            'price_sales' => 11000,
            'price_general' => 12000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('products.update', $product), [
            'item_category_id' => $category->id,
            'code' => '',
            'name' => 'matematika 1 edisi 5 semester 1 tahun ajar 2025-2026',
            'unit' => 'exp',
            'stock' => 5,
            'price_agent' => 10500,
            'price_sales' => 11500,
            'price_general' => 12500,
        ])->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame('mt1e5s156', $product->code);
    }

    public function test_api_store_rejects_manual_code_that_conflicts_after_normalization(): void
    {
        $category = ItemCategory::query()->create([
            'code' => 'CAT-01',
            'name' => 'Buku',
        ]);

        Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'manual-001',
            'name' => 'existing manual',
            'unit' => 'exp',
            'stock' => 1,
            'price_agent' => 1000,
            'price_sales' => 1000,
            'price_general' => 1000,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/products', [
            'item_category_id' => $category->id,
            'code' => '  MANUAL -- 001 !! ',
            'name' => 'produk bentrok',
            'unit' => 'exp',
            'stock' => 1,
            'price_agent' => 1000,
            'price_sales' => 2000,
            'price_general' => 3000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('code');
        $response->assertJsonPath('errors.code.0', __('ui.product_code_unique_error'));
    }
}
