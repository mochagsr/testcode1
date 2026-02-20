<?php

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStockMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_update_creates_manual_stock_mutation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-01',
            'name' => 'Buku',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'bk001',
            'name' => 'Buku 1',
            'unit' => 'exp',
            'stock' => 10,
            'price_agent' => 10000,
            'price_sales' => 12000,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('products.update', $product), [
            'item_category_id' => $category->id,
            'code' => 'bk001',
            'name' => 'Buku 1',
            'unit' => 'exp',
            'stock' => 25,
            'price_agent' => 10000,
            'price_sales' => 12000,
            'price_general' => 15000,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => 15,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_product_edit_shows_stock_mutation_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-01',
            'name' => 'Buku',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'bk001',
            'name' => 'Buku 1',
            'unit' => 'exp',
            'stock' => 10,
            'price_agent' => 10000,
            'price_sales' => 12000,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        foreach (range(1, 25) as $index) {
            StockMutation::query()->create([
                'product_id' => $product->id,
                'reference_type' => Product::class,
                'reference_id' => $product->id,
                'mutation_type' => $index % 2 === 0 ? 'out' : 'in',
                'quantity' => $index,
                'notes' => 'Mutasi ke-'.$index,
                'created_by_user_id' => $admin->id,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('products.edit', $product));

        $response->assertOk();
        $response->assertSee(__('ui.stock_mutations_title'));
        $response->assertSee('mutation_page=2', false);
    }
}

