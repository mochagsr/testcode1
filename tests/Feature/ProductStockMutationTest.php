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

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('id');
    }

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

    public function test_product_mutations_page_loads_and_shows_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-02',
            'name' => 'Alat',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'alt001',
            'name' => 'Alat 1',
            'unit' => 'exp',
            'stock' => 12,
            'price_agent' => 10000,
            'price_sales' => 12000,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        foreach (range(1, 24) as $index) {
            StockMutation::query()->create([
                'product_id' => $product->id,
                'reference_type' => Product::class,
                'reference_id' => $product->id,
                'mutation_type' => $index % 2 === 0 ? 'out' : 'in',
                'quantity' => $index,
                'notes' => 'Mutasi produk-'.$index,
                'created_by_user_id' => $admin->id,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('products.mutations', $product));

        $response->assertOk();
        $response->assertSee(__('ui.stock_mutations_title'));
        $response->assertSee($product->code);
        $response->assertSee('mutation_page=2', false);
    }

    public function test_quick_stock_update_from_products_index_updates_stock_and_creates_mutation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-03',
            'name' => 'Kertas',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'krt001',
            'name' => 'Kertas A4',
            'unit' => 'exp',
            'stock' => 10,
            'price_agent' => 10000,
            'price_sales' => 12000,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson(route('products.quick-stock', $product), [
            'stock' => 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('stock', 0);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 0,
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'mutation_type' => 'out',
            'quantity' => 10,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_stock_change_message_hides_price_when_price_not_changed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-04',
            'name' => 'Buku',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'bk004',
            'name' => 'Buku 4',
            'unit' => 'exp',
            'stock' => 100,
            'price_agent' => 10000,
            'price_sales' => 12000,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('products.update', $product), [
            'item_category_id' => $category->id,
            'code' => 'bk004',
            'name' => 'Buku 4',
            'unit' => 'exp',
            'stock' => 90,
            'price_agent' => 10000,
            'price_sales' => 12000,
            'price_general' => 15000,
        ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success', function (string $message): bool {
            return str_contains($message, 'Pengurangan stok')
                && ! str_contains($message, 'Perubahan harga');
        });
    }

    public function test_stock_change_message_shows_only_changed_price_field(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-05',
            'name' => 'Buku',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'bk005',
            'name' => 'Buku 5',
            'unit' => 'exp',
            'stock' => 100,
            'price_agent' => 10000,
            'price_sales' => 12000,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('products.update', $product), [
            'item_category_id' => $category->id,
            'code' => 'bk005',
            'name' => 'Buku 5',
            'unit' => 'exp',
            'stock' => 95,
            'price_agent' => 11000,
            'price_sales' => 12000,
            'price_general' => 15000,
        ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('success', function (string $message): bool {
            return str_contains($message, 'Pengurangan stok')
                && str_contains($message, 'Perubahan harga')
                && str_contains($message, 'Agen Rp 11.000')
                && ! str_contains($message, 'Sales Rp')
                && ! str_contains($message, 'Umum Rp');
        });
    }
}
