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

class SupplierStockCardSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    private function seedCard(): void
    {
        $this->admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $category = ItemCategory::query()->create(['code' => 'rolweb', 'name' => 'roll web']);
        $this->product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'RW-68',
            'name' => 'kertas web 68gr cd',
            'unit' => 'exp',
            'stock' => 120,
            'price_agent' => 1000,
            'price_sales' => 1100,
            'price_general' => 1200,
            'is_active' => true,
            'product_type' => 'raw_material',
        ]);

        foreach (['pt kober tenan', 'pt rumah cetak kita'] as $supplierName) {
            $supplier = Supplier::query()->create(['name' => $supplierName]);
            StockMutation::query()->create([
                'product_id' => $this->product->id,
                'reference_type' => Supplier::class,
                'reference_id' => $supplier->id,
                'mutation_type' => 'in',
                'quantity' => 10,
                'created_by_user_id' => $this->admin->id,
            ]);
        }
    }

    public function test_summary_shows_total_product_stock_and_unattributed_portion(): void
    {
        $this->seedCard();

        $response = $this->actingAs($this->admin)->get(route('supplier-stock-cards.index'));

        $response->assertOk();
        // 10 from each supplier = 20 traced; product stock is 120 -> 100 has no supplier origin
        $response->assertSee(__('supplier_stock.master_stock'));
        $response->assertSee(__('supplier_stock.unattributed_stock_note', ['qty' => '100']));
    }

    public function test_summary_can_be_searched_by_category(): void
    {
        $this->seedCard();

        $this->actingAs($this->admin)
            ->get(route('supplier-stock-cards.index', ['search' => 'roll web']))
            ->assertOk()
            ->assertSee('kertas web 68gr cd');

        $this->actingAs($this->admin)
            ->get(route('supplier-stock-cards.index', ['search' => 'kategori-tidak-ada']))
            ->assertOk()
            ->assertDontSee('kertas web 68gr cd');
    }

    public function test_summary_can_still_be_searched_by_product_name(): void
    {
        $this->seedCard();

        $this->actingAs($this->admin)
            ->get(route('supplier-stock-cards.index', ['search' => 'kertas web']))
            ->assertOk()
            ->assertSee('kertas web 68gr cd');
    }
}
