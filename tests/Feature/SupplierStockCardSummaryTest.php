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

    public function test_summary_shows_total_product_stock_column(): void
    {
        $this->seedCard();

        $response = $this->actingAs($this->admin)->get(route('supplier-stock-cards.index'));

        $response->assertOk();
        $response->assertSee(__('supplier_stock.master_stock'));
        $response->assertSee('120');
    }

    public function test_summary_hides_repeated_category_and_product_by_default(): void
    {
        $this->seedCard();

        $response = $this->actingAs($this->admin)->get(route('supplier-stock-cards.index'));

        $response->assertOk();
        // Both suppliers carry the same product: the label is printed on the first row only.
        $this->assertSame(1, substr_count($response->getContent(), '#stock-mutations">kertas web 68gr cd</a>'));
    }

    public function test_summary_repeats_labels_when_sorted_by_supplier(): void
    {
        $this->seedCard();

        $response = $this->actingAs($this->admin)
            ->get(route('supplier-stock-cards.index', ['sort' => 'supplier', 'direction' => 'asc']));

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), '#stock-mutations">kertas web 68gr cd</a>'));
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
