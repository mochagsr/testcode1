<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ItemCategory;
use App\Models\OutgoingTransaction;
use App\Models\OutgoingTransactionItem;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class SupplierStockCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_stock_card_index_shows_default_summary_without_filter(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Default',
            'company_name' => 'PT Default',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-DFT',
            'name' => 'Kategori Default',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'BRG-DFT-01',
            'name' => 'Buku Default',
            'unit' => 'exp',
            'stock' => 0,
            'price_agent' => 1000,
            'price_sales' => 1200,
            'price_general' => 1500,
            'is_active' => true,
        ]);

        $trx = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-20260222-0099',
            'transaction_date' => '2026-02-20',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'total' => 9000,
            'created_by_user_id' => $user->id,
        ]);
        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $trx->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'exp',
            'quantity' => 9,
            'unit_cost' => 1000,
            'line_total' => 9000,
        ]);

        $response = $this->actingAs($user)->get(route('supplier-stock-cards.index'));

        $response->assertOk();
        $response->assertSee('Supplier Default');
        $response->assertSee('Buku Default');
        $response->assertSee('9');
    }

    public function test_supplier_stock_card_shows_in_out_and_balance_per_supplier(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $supplierA = Supplier::query()->create([
            'name' => 'Supplier A',
            'company_name' => 'PT A',
        ]);
        $supplierB = Supplier::query()->create([
            'name' => 'Supplier B',
            'company_name' => 'PT B',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-STK',
            'name' => 'Kategori Stok',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'BRG-STK-01',
            'name' => 'Buku Stok',
            'unit' => 'exp',
            'stock' => 0,
            'price_agent' => 1000,
            'price_sales' => 1200,
            'price_general' => 1500,
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'code' => 'CUS-STK-01',
            'name' => 'Customer Stok',
            'city' => 'Malang',
        ]);

        $trxA = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-20260222-0001',
            'transaction_date' => '2026-02-10',
            'supplier_id' => $supplierA->id,
            'semester_period' => 'S2-2526',
            'total' => 10000,
            'created_by_user_id' => $user->id,
        ]);
        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $trxA->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'exp',
            'quantity' => 10,
            'unit_cost' => 1000,
            'line_total' => 10000,
        ]);

        $trxB = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-20260222-0002',
            'transaction_date' => '2026-02-11',
            'supplier_id' => $supplierB->id,
            'semester_period' => 'S2-2526',
            'total' => 5000,
            'created_by_user_id' => $user->id,
        ]);
        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $trxB->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'exp',
            'quantity' => 5,
            'unit_cost' => 1000,
            'line_total' => 5000,
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-20260222-0001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-12',
            'semester_period' => 'S2-2526',
            'subtotal' => 12000,
            'total' => 12000,
            'total_paid' => 12000,
            'balance' => 0,
            'payment_status' => 'paid',
            'is_canceled' => false,
        ]);
        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 12,
            'unit_price' => 1000,
            'discount' => 0,
            'line_total' => 12000,
        ]);

        $salesReturn = SalesReturn::query()->create([
            'return_number' => 'RTR-20260222-0001',
            'customer_id' => $customer->id,
            'sales_invoice_id' => $invoice->id,
            'return_date' => '2026-02-13',
            'semester_period' => 'S2-2526',
            'total' => 2000,
            'reason' => 'Retur sample',
            'is_canceled' => false,
        ]);
        SalesReturnItem::query()->create([
            'sales_return_id' => $salesReturn->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 1000,
            'line_total' => 2000,
        ]);

        $response = $this->actingAs($user)->get(route('supplier-stock-cards.index', [
            'supplier_id' => $supplierB->id,
        ]));

        $response->assertOk();
        $response->assertSee(__('supplier_stock.title'));
        $response->assertSee('INV-20260222-0001');
        $response->assertSee('RTR-20260222-0001');

        /** @var LengthAwarePaginator $summaryPaginator */
        $summaryPaginator = $response->viewData('summaryPaginator');
        $summaryRow = collect($summaryPaginator->items())
            ->firstWhere('product_id', $product->id);

        $this->assertNotNull($summaryRow);
        $this->assertSame(7, (int) ($summaryRow['qty_in'] ?? 0));
        $this->assertSame(2, (int) ($summaryRow['qty_out'] ?? 0));
        $this->assertSame(5, (int) ($summaryRow['balance'] ?? 0));
    }

    public function test_supplier_stock_card_shows_manual_outgoing_items_without_product_id(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Manual',
            'company_name' => 'PT Manual',
        ]);

        $trx = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-20260222-0010',
            'transaction_date' => '2026-02-15',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'total' => 9000,
            'created_by_user_id' => $user->id,
        ]);
        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $trx->id,
            'product_id' => null,
            'product_code' => null,
            'product_name' => 'Kertas HVS Manual',
            'unit' => 'rim',
            'quantity' => 3,
            'unit_cost' => 3000,
            'line_total' => 9000,
        ]);

        $response = $this->actingAs($user)->get(route('supplier-stock-cards.index', [
            'supplier_id' => $supplier->id,
        ]));

        $response->assertOk();
        $response->assertSee('Kertas HVS Manual');

        /** @var LengthAwarePaginator $summaryPaginator */
        $summaryPaginator = $response->viewData('summaryPaginator');
        $summaryRow = collect($summaryPaginator->items())
            ->firstWhere('product_name', 'Kertas HVS Manual');

        $this->assertNotNull($summaryRow);
        $this->assertSame(3, (int) ($summaryRow['qty_in'] ?? 0));
        $this->assertSame(0, (int) ($summaryRow['qty_out'] ?? 0));
        $this->assertSame(3, (int) ($summaryRow['balance'] ?? 0));
    }

    public function test_user_can_edit_stock_from_supplier_stock_card(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Edit Stok',
            'company_name' => 'PT Edit Stok',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-ESK',
            'name' => 'Kategori Edit Stok',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'BRG-ESK-01',
            'name' => 'Barang Edit Stok',
            'unit' => 'exp',
            'stock' => 10,
            'price_agent' => 1000,
            'price_sales' => 1200,
            'price_general' => 1500,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('supplier-stock-cards.update-stock'), [
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'stock' => 25,
            'supplier_id' => $supplier->id,
        ]);

        $response->assertRedirect(route('supplier-stock-cards.index', ['supplier_id' => $supplier->id]));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 35,
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => 25,
        ]);
    }

    public function test_manual_summary_row_maps_to_master_product_by_name_for_edit_button(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Map Name',
            'company_name' => 'PT Map Name',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-MAP',
            'name' => 'Kategori Map',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'BRG-MAP-01',
            'name' => 'tinta bw merk kuda',
            'unit' => 'exp',
            'stock' => 10,
            'price_agent' => 1000,
            'price_sales' => 1200,
            'price_general' => 1500,
            'is_active' => true,
        ]);
        $trx = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-20260222-0066',
            'transaction_date' => '2026-02-15',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'total' => 12000,
            'created_by_user_id' => $user->id,
        ]);
        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $trx->id,
            'product_id' => null,
            'product_code' => null,
            'product_name' => 'tinta bw merk kuda',
            'unit' => 'exp',
            'quantity' => 12,
            'unit_cost' => 1000,
            'line_total' => 12000,
        ]);

        $response = $this->actingAs($user)->get(route('supplier-stock-cards.index'));
        $response->assertOk();

        /** @var LengthAwarePaginator $summaryPaginator */
        $summaryPaginator = $response->viewData('summaryPaginator');
        $summaryRow = collect($summaryPaginator->items())
            ->firstWhere('product_name', 'tinta bw merk kuda');

        $this->assertNotNull($summaryRow);
        $this->assertSame((int) $product->id, (int) ($summaryRow['editable_product_id'] ?? 0));
    }

    public function test_user_can_update_stock_without_product_id_using_exact_name_mapping(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-UPD',
            'name' => 'Kategori Update',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'BRG-UPD-01',
            'name' => 'tinta bw web',
            'unit' => 'exp',
            'stock' => 10,
            'price_agent' => 1000,
            'price_sales' => 1200,
            'price_general' => 1500,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('supplier-stock-cards.update-stock'), [
            'product_id' => '',
            'product_code' => '',
            'product_name' => 'tinta bw web',
            'stock' => 14,
        ]);

        $response->assertRedirect(route('supplier-stock-cards.index'));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 14,
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => 4,
        ]);
    }

    public function test_user_can_update_stock_without_product_id_by_auto_creating_master_product(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        ItemCategory::query()->create([
            'code' => 'CAT-AUTO',
            'name' => 'Kategori Auto',
        ]);

        $response = $this->actingAs($user)->post(route('supplier-stock-cards.update-stock'), [
            'product_id' => '',
            'product_code' => '',
            'product_name' => 'tinta bw non master',
            'stock' => 35,
        ]);

        $response->assertRedirect(route('supplier-stock-cards.index'));

        $created = Product::query()
            ->where('name', 'tinta bw non master')
            ->first();

        $this->assertNotNull($created);
        $this->assertSame(35, (int) $created->stock);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => (int) $created->id,
            'mutation_type' => 'in',
            'quantity' => 35,
        ]);
    }

    public function test_supplier_stock_summary_is_updated_after_manual_stock_edit(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Sinkron',
            'company_name' => 'PT Sinkron',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-SYNC',
            'name' => 'Kategori Sync',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'BRG-SYNC-01',
            'name' => 'Barang Sinkron',
            'unit' => 'exp',
            'stock' => 3,
            'price_agent' => 1000,
            'price_sales' => 1200,
            'price_general' => 1500,
            'is_active' => true,
        ]);

        $trx = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-20260222-0077',
            'transaction_date' => '2026-02-20',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'total' => 3000,
            'created_by_user_id' => $user->id,
        ]);
        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $trx->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'exp',
            'quantity' => 3,
            'unit_cost' => 1000,
            'line_total' => 3000,
        ]);

        $updateResponse = $this->actingAs($user)->post(route('supplier-stock-cards.update-stock'), [
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'stock' => 10,
            'supplier_id' => $supplier->id,
        ]);
        $updateResponse->assertRedirect(route('supplier-stock-cards.index', ['supplier_id' => $supplier->id]));

        $response = $this->actingAs($user)->get(route('supplier-stock-cards.index', [
            'supplier_id' => $supplier->id,
        ]));
        $response->assertOk();

        /** @var LengthAwarePaginator $summaryPaginator */
        $summaryPaginator = $response->viewData('summaryPaginator');
        $summaryRow = collect($summaryPaginator->items())
            ->firstWhere('product_id', $product->id);

        $this->assertNotNull($summaryRow);
        $this->assertSame(10, (int) ($summaryRow['qty_in'] ?? 0));
        $this->assertSame(0, (int) ($summaryRow['qty_out'] ?? 0));
        $this->assertSame(10, (int) ($summaryRow['balance'] ?? 0));
    }

    public function test_supplier_stock_summary_with_manual_item_without_product_id_updates_to_target_stock(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Manual Target',
            'company_name' => 'PT Manual Target',
        ]);
        ItemCategory::query()->create([
            'code' => 'CAT-MANUAL-TARGET',
            'name' => 'Kategori Manual Target',
        ]);

        $trx = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-20260222-0088',
            'transaction_date' => '2026-02-20',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'total' => 3000,
            'created_by_user_id' => $user->id,
        ]);
        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $trx->id,
            'product_id' => null,
            'product_code' => null,
            'product_name' => 'tinta bw web',
            'unit' => 'exp',
            'quantity' => 3,
            'unit_cost' => 1000,
            'line_total' => 3000,
        ]);

        $updateResponse = $this->actingAs($user)->post(route('supplier-stock-cards.update-stock'), [
            'product_id' => '',
            'product_code' => '',
            'product_name' => 'tinta bw web',
            'stock' => 35,
            'supplier_id' => $supplier->id,
        ]);
        $updateResponse->assertRedirect(route('supplier-stock-cards.index', ['supplier_id' => $supplier->id]));

        $response = $this->actingAs($user)->get(route('supplier-stock-cards.index', [
            'supplier_id' => $supplier->id,
        ]));
        $response->assertOk();

        /** @var LengthAwarePaginator $summaryPaginator */
        $summaryPaginator = $response->viewData('summaryPaginator');
        $summaryRow = collect($summaryPaginator->items())
            ->firstWhere('product_name', 'tinta bw web');

        $this->assertNotNull($summaryRow);
        $this->assertSame(35, (int) ($summaryRow['balance'] ?? 0));
    }
}
