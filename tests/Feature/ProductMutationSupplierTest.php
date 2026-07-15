<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMutationSupplierTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
    }

    private function makeProduct(string $type, string $code, string $name): Product
    {
        $category = ItemCategory::query()->firstOrCreate(['code' => 'RW'], ['name' => 'rolweb']);

        return Product::query()->create([
            'item_category_id' => $category->id,
            'code' => $code,
            'name' => $name,
            'unit' => 'exp',
            'stock' => 100,
            'price_agent' => 1000,
            'price_sales' => 1100,
            'price_general' => 1200,
            'is_active' => true,
            'product_type' => $type,
        ]);
    }

    public function test_raw_material_mutations_show_supplier_origin(): void
    {
        $admin = $this->admin();
        $supplierA = Supplier::query()->create(['name' => 'PT Kober Tenan']);
        $supplierB = Supplier::query()->create(['name' => 'PT Rumah Cetak Kita']);
        $product = $this->makeProduct('raw_material', 'RW-1', 'kertas web 68gr cd');

        StockMutation::query()->create([
            'product_id' => $product->id,
            'reference_type' => Supplier::class,
            'reference_id' => $supplierA->id,
            'mutation_type' => 'in',
            'quantity' => 10,
            'notes' => 'Penyesuaian dari A',
            'created_by_user_id' => $admin->id,
        ]);
        StockMutation::query()->create([
            'product_id' => $product->id,
            'reference_type' => Supplier::class,
            'reference_id' => $supplierB->id,
            'mutation_type' => 'in',
            'quantity' => 20,
            'notes' => 'Penyesuaian dari B',
            'created_by_user_id' => $admin->id,
        ]);
        StockMutation::query()->create([
            'product_id' => $product->id,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => 5,
            'notes' => 'Stok awal dari import barang',
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('products.mutations', $product));
        $response->assertOk();
        // supplier origin is visible per row, and the manual/import row is still listed
        $response->assertSee('PT Kober Tenan');
        $response->assertSee('PT Rumah Cetak Kita');
        $response->assertSee('Stok awal dari import barang');
    }

    public function test_supplier_filter_narrows_raw_material_mutations(): void
    {
        $admin = $this->admin();
        $supplierA = Supplier::query()->create(['name' => 'PT Kober Tenan']);
        $supplierB = Supplier::query()->create(['name' => 'PT Rumah Cetak Kita']);
        $product = $this->makeProduct('raw_material', 'RW-2', 'kertas web 70gr');

        StockMutation::query()->create([
            'product_id' => $product->id,
            'reference_type' => Supplier::class,
            'reference_id' => $supplierA->id,
            'mutation_type' => 'in',
            'quantity' => 10,
            'notes' => 'Mutasi milik A',
            'created_by_user_id' => $admin->id,
        ]);
        StockMutation::query()->create([
            'product_id' => $product->id,
            'reference_type' => Supplier::class,
            'reference_id' => $supplierB->id,
            'mutation_type' => 'in',
            'quantity' => 20,
            'notes' => 'Mutasi milik B',
            'created_by_user_id' => $admin->id,
        ]);
        StockMutation::query()->create([
            'product_id' => $product->id,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => 5,
            'notes' => 'Mutasi manual tanpa supplier',
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('products.mutations', [
            'product' => $product,
            'supplier_id' => $supplierA->id,
        ]));

        $response->assertOk();
        $response->assertSee('Mutasi milik A');
        $response->assertDontSee('Mutasi milik B');
        $response->assertDontSee('Mutasi manual tanpa supplier');
    }

    public function test_general_product_mutations_are_untouched(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::query()->create(['name' => 'PT Kober Tenan']);
        $product = $this->makeProduct('general', 'GN-1', 'Buku Umum');

        StockMutation::query()->create([
            'product_id' => $product->id,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => 7,
            'notes' => 'Stok awal barang umum',
            'created_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('products.mutations', $product));
        $response->assertOk();
        $response->assertSee('Stok awal barang umum');
        // no supplier tracing for general goods
        $response->assertDontSee($supplier->name);
    }
}
