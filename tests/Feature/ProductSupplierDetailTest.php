<?php

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\OutgoingTransaction;
use App\Models\OutgoingTransactionItem;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSupplierDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_index_hides_detail_button_for_general_goods(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => config('rbac.roles.user', []),
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'umum',
            'name' => 'Umum',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'krwb68cd',
            'name' => 'Kertas Web 68gr CD',
            'unit' => 'roll',
            'stock' => 15,
            'price_agent' => 0,
            'price_sales' => 0,
            'price_general' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('products.index'));

        $response->assertOk();
        $response->assertDontSee('<a class="btn info-btn product-action-btn" href="'.route('products.show', $product).'">'.__('ui.view').'</a>', false);
        $response->assertSee('products-toolbar', false);
        $response->assertSee('@media (max-width: 1280px)', false);
        $response->assertSee('@media (max-width: 1100px)', false);
        $response->assertSee('<option value="" selected disabled>Export</option>', false);
        $response->assertSee('products/pdf?search=&amp;product_type=general', false);
        $response->assertSee('products/export.csv?search=&amp;product_type=general', false);
        $response->assertDontSee('">Export PDF</a>', false);
        $response->assertDontSee('">Export Excel</a>', false);
    }

    public function test_product_index_shows_detail_button_for_raw_materials(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => config('rbac.roles.user', []),
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'umum',
            'name' => 'Umum',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'krwb68cd',
            'name' => 'Kertas Web 68gr CD',
            'unit' => 'roll',
            'stock' => 15,
            'price_agent' => 0,
            'price_sales' => 0,
            'price_general' => 0,
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier A',
            'company_name' => 'PT A',
        ]);
        $transaction = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-A-0001',
            'transaction_date' => '2026-05-01',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S1-2627',
            'total' => 130000,
            'created_by_user_id' => $user->id,
        ]);
        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'roll',
            'quantity' => 10,
            'unit_cost' => 13000,
            'line_total' => 130000,
        ]);

        $response = $this->actingAs($user)->get(route('products.index', ['product_type' => 'raw_material']));

        $response->assertOk();
        $response->assertSee('<a class="btn info-btn product-action-btn" href="'.route('products.show', $product).'">'.__('ui.view').'</a>', false);
    }

    public function test_product_detail_hides_supplier_section_for_general_goods(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => config('rbac.roles.user', []),
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'cerdas',
            'name' => 'Cerdas',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'cbnd3e856',
            'name' => 'Bhs Indonesia 3 Ed 8 2526',
            'unit' => 'exp',
            'stock' => 710,
            'price_agent' => 3500,
            'price_sales' => 4500,
            'price_general' => 12000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('products.show', $product));

        $response->assertOk();
        $response->assertSee('Stok Total');
        $response->assertSee('710 exp');
        $response->assertDontSee('Supplier Barang Ini');
        $response->assertDontSee('Belum ada supplier yang memasok barang ini.');
    }

    public function test_product_detail_shows_suppliers_that_supplied_the_product(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => config('rbac.roles.user', []),
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'umum',
            'name' => 'Umum',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'krwb68cd',
            'name' => 'Kertas Web 68gr CD',
            'unit' => 'roll',
            'stock' => 115,
            'price_agent' => 0,
            'price_sales' => 0,
            'price_general' => 0,
            'is_active' => true,
        ]);
        $supplierA = Supplier::query()->create([
            'name' => 'Supplier A',
            'company_name' => 'PT A',
        ]);
        $supplierB = Supplier::query()->create([
            'name' => 'Supplier B',
            'company_name' => 'PT B',
        ]);
        $transactionA = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-A-0001',
            'transaction_date' => '2026-05-01',
            'supplier_id' => $supplierA->id,
            'semester_period' => 'S1-2627',
            'total' => 130000,
            'created_by_user_id' => $user->id,
        ]);
        $transactionB = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-B-0001',
            'transaction_date' => '2026-05-02',
            'supplier_id' => $supplierB->id,
            'semester_period' => 'S1-2627',
            'total' => 62500,
            'created_by_user_id' => $user->id,
        ]);

        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $transactionA->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'roll',
            'quantity' => 10,
            'unit_cost' => 13000,
            'line_total' => 130000,
        ]);
        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $transactionB->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'roll',
            'quantity' => 5,
            'unit_cost' => 12500,
            'line_total' => 62500,
        ]);
        StockMutation::query()->create([
            'product_id' => $product->id,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => 100,
            'notes' => __('ui.stock_mutation_initial_stock'),
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('products.show', $product));

        $response->assertOk();
        $response->assertSee('Supplier Barang Ini');
        $response->assertSee('Stok Total');
        $response->assertSee('115 roll');
        $response->assertSee('Stok Awal');
        $response->assertSee('100 roll');
        $response->assertSee('Total Masuk Supplier');
        $response->assertSee('15 roll');
        $response->assertSee('Supplier A');
        $response->assertSee('Supplier B');
        $response->assertSee('Rp 13.000');
        $response->assertSee('Rp 12.500');
        $response->assertSee('TRXK-A-0001');
        $response->assertSee('TRXK-B-0001');
        $response->assertSee('supplier-stock-cards?supplier_id='.$supplierA->id.'&amp;product_id='.$product->id, false);
    }

    public function test_product_stock_mutation_page_shows_total_and_initial_stock_cards(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => config('rbac.roles.user', []),
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'cerdas',
            'name' => 'Cerdas',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'cbnd3e856',
            'name' => 'Bhs Indonesia 3 Ed 8 2526',
            'unit' => 'exp',
            'stock' => 710,
            'price_agent' => 3500,
            'price_sales' => 4500,
            'price_general' => 12000,
            'is_active' => true,
        ]);
        StockMutation::query()->create([
            'product_id' => $product->id,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => 1000,
            'notes' => __('ui.stock_mutation_initial_stock'),
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('products.mutations', $product));

        $response->assertOk();
        $response->assertSee('Stok Total');
        $response->assertSee('710 exp');
        $response->assertSee('Stok Awal');
        $response->assertSee('1.000 exp');
    }

    public function test_product_index_can_filter_general_goods_and_raw_materials(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => config('rbac.roles.user', []),
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'umum',
            'name' => 'Umum',
        ]);
        $generalProduct = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'bhs3e856',
            'name' => 'Bahasa Indonesia 3',
            'unit' => 'exp',
            'stock' => 10,
            'price_agent' => 0,
            'price_sales' => 0,
            'price_general' => 12000,
            'is_active' => true,
        ]);
        $rawProduct = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'krwb68cd',
            'name' => 'Kertas Web 68gr CD',
            'unit' => 'roll',
            'stock' => 15,
            'price_agent' => 0,
            'price_sales' => 0,
            'price_general' => 0,
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Bahan Baku',
            'company_name' => 'PT Bahan',
        ]);
        $transaction = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-RW-0001',
            'transaction_date' => '2026-05-01',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S1-2627',
            'total' => 130000,
            'created_by_user_id' => $user->id,
        ]);
        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $transaction->id,
            'product_id' => $rawProduct->id,
            'product_code' => $rawProduct->code,
            'product_name' => $rawProduct->name,
            'unit' => 'roll',
            'quantity' => 10,
            'unit_cost' => 13000,
            'line_total' => 130000,
        ]);

        $generalResponse = $this->actingAs($user)->get(route('products.index'));

        $generalResponse->assertOk();
        $generalResponse->assertSee(__('ui.product_type_general'));
        $generalResponse->assertSee(__('ui.product_type_raw_material'));
        $generalResponse->assertSee($generalProduct->name);
        $generalResponse->assertDontSee($rawProduct->name);

        $rawResponse = $this->actingAs($user)->get(route('products.index', ['product_type' => 'raw_material']));

        $rawResponse->assertOk();
        $rawResponse->assertSee($rawProduct->name);
        $rawResponse->assertDontSee($generalProduct->name);
    }
}
