<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutgoingTransactionFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_index_page_loads_successfully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('suppliers.index'));

        $response->assertOk();
        $response->assertSee(__('ui.suppliers_title'));
    }

    public function test_outgoing_transaction_store_creates_transaction_and_increments_stock(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'finance_locked' => false,
        ]);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Alpha',
            'company_name' => 'PT Alpha',
            'phone' => '08123456789',
            'address' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-OT',
            'name' => 'Kategori OT',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'BRG-OT-01',
            'name' => 'Barang OT',
            'unit' => 'exp',
            'stock' => 10,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('outgoing-transactions.store'), [
            'supplier_id' => $supplier->id,
            'transaction_date' => '2026-02-12',
            'semester_period' => 'S1-2026',
            'note_number' => 'NOTA-001',
            'notes' => 'Pembelian stok',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => 'exp',
                    'quantity' => 5,
                    'unit_cost' => 15000,
                    'notes' => 'Baris 1',
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('outgoing_transactions', [
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'note_number' => 'NOTA-001',
            'total' => 75000,
        ]);

        $product->refresh();
        $this->assertSame(15.0, (float) $product->stock);

        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => 5,
        ]);
    }

    public function test_outgoing_transaction_store_fails_if_supplier_semester_closed(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'finance_locked' => false,
        ]);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Beta',
            'company_name' => 'PT Beta',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-OTS',
            'name' => 'Kategori OTS',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'BRG-OT-02',
            'name' => 'Barang OTS',
            'unit' => 'exp',
            'stock' => 10,
            'price_general' => 20000,
            'is_active' => true,
        ]);

        AppSetting::setValue('closed_supplier_semester_periods', $supplier->id.':S2-2526');

        $response = $this->actingAs($user)->from(route('outgoing-transactions.create'))->post(route('outgoing-transactions.store'), [
            'supplier_id' => $supplier->id,
            'transaction_date' => '2026-02-12',
            'semester_period' => 'S2-2526',
            'note_number' => 'NOTA-002',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => 'exp',
                    'quantity' => 1,
                    'unit_cost' => 20000,
                ],
            ],
        ]);

        $response->assertRedirect(route('outgoing-transactions.create'));
        $response->assertSessionHasErrors('semester_period');
        $this->assertDatabaseMissing('outgoing_transactions', [
            'note_number' => 'NOTA-002',
        ]);
    }
}
