<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\StockMutation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_with_stock_mutations_only_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct();

        StockMutation::query()->create([
            'product_id' => $product->id,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => 10,
            'notes' => 'Stok awal',
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success', __('ui.product_deleted_success'));

        $this->assertDatabaseMissing('stock_mutations', ['product_id' => $product->id]);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_product_used_by_business_transaction_is_deactivated_instead_of_500_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct();
        $customer = Customer::query()->create([
            'code' => 'CUS-DEL',
            'name' => 'Customer Delete Test',
        ]);
        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-DEL-001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-04-25',
            'subtotal' => 10000,
            'total' => 10000,
            'balance' => 10000,
            'payment_status' => 'unpaid',
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 10000,
            'discount' => 0,
            'line_total' => 10000,
        ]);

        $this->actingAs($admin)
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success', __('ui.product_deactivated_success'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('sales_invoice_items', [
            'product_id' => $product->id,
        ]);
    }

    private function createProduct(): Product
    {
        $category = ItemCategory::query()->create([
            'code' => 'CAT-DEL',
            'name' => 'Kategori Hapus',
        ]);

        return Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'del-product',
            'name' => 'Produk Hapus',
            'unit' => 'exp',
            'stock' => 10,
            'price_agent' => 1000,
            'price_sales' => 2000,
            'price_general' => 3000,
            'is_active' => true,
        ]);
    }
}
