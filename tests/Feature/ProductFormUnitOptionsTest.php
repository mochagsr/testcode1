<?php

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFormUnitOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_product_form_shows_configured_unit_options(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        ItemCategory::query()->create([
            'code' => 'buku',
            'name' => 'Buku',
        ]);

        ProductUnit::query()->create([
            'code' => 'pack',
            'name' => 'Pack',
        ]);

        $response = $this->actingAs($admin)->get(route('products.create'));

        $response->assertOk();
        $response->assertSee('name="unit"', false);
        $response->assertSee('value="pack"', false);
        $response->assertSee('pack - Pack');
    }

    public function test_create_product_form_defaults_prices_to_zero(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        ItemCategory::query()->create([
            'code' => 'umum',
            'name' => 'Umum',
        ]);

        ProductUnit::query()->create([
            'code' => 'pcs',
            'name' => 'Pcs',
        ]);

        $response = $this->actingAs($admin)->get(route('products.create'));

        $response->assertOk();
        $response->assertSee('name="price_agent" value="0"', false);
        $response->assertSee('name="price_sales" value="0"', false);
        $response->assertSee('name="price_general" value="0"', false);
    }

    public function test_product_can_be_created_with_zero_prices(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        $category = ItemCategory::query()->create([
            'code' => 'umum',
            'name' => 'Umum',
        ]);

        ProductUnit::query()->create([
            'code' => 'pcs',
            'name' => 'Pcs',
        ]);

        $response = $this->actingAs($admin)->post(route('products.store'), [
            'item_category_id' => $category->id,
            'code' => '',
            'name' => 'Barang Supplier Umum',
            'unit' => 'pcs',
            'stock' => 3,
            'price_agent' => 0,
            'price_sales' => 0,
            'price_general' => 0,
        ]);

        $response->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Barang Supplier Umum',
            'unit' => 'pcs',
            'price_agent' => 0,
            'price_sales' => 0,
            'price_general' => 0,
        ]);

        $this->assertSame(1, Product::query()->where('name', 'Barang Supplier Umum')->count());
    }
}
