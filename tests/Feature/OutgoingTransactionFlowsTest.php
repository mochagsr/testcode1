<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\ItemCategory;
use App\Models\OutgoingTransaction;
use App\Models\OutgoingTransactionItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\SupplierPayment;
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

    public function test_admin_can_update_outgoing_transaction_and_rebalance_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Edit',
            'company_name' => 'PT Edit',
            'outstanding_payable' => 60000,
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-EDIT-OT',
            'name' => 'Kategori Edit OT',
        ]);
        $productA = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'OT-A',
            'name' => 'Produk OT A',
            'unit' => 'exp',
            'stock' => 10,
            'price_general' => 10000,
            'is_active' => true,
        ]);
        $productB = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'OT-B',
            'name' => 'Produk OT B',
            'unit' => 'exp',
            'stock' => 7,
            'price_general' => 5000,
            'is_active' => true,
        ]);

        $transaction = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-20260220-0001',
            'transaction_date' => '2026-02-20',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'note_number' => 'NOTA-OLD',
            'total' => 60000,
            'notes' => 'Awal',
            'created_by_user_id' => $admin->id,
        ]);
        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $transaction->id,
            'product_id' => $productA->id,
            'product_code' => $productA->code,
            'product_name' => $productA->name,
            'unit' => 'exp',
            'quantity' => 6,
            'unit_cost' => 10000,
            'line_total' => 60000,
        ]);
        SupplierLedger::query()->create([
            'supplier_id' => $supplier->id,
            'outgoing_transaction_id' => $transaction->id,
            'supplier_payment_id' => null,
            'entry_date' => '2026-02-20',
            'period_code' => 'S2-2526',
            'description' => 'Initial outgoing',
            'debit' => 60000,
            'credit' => 0,
            'balance_after' => 60000,
        ]);

        $response = $this->actingAs($admin)->put(route('outgoing-transactions.admin-update', $transaction), [
            'transaction_date' => '2026-02-20',
            'semester_period' => 'S2-2526',
            'supplier_id' => $supplier->id,
            'note_number' => 'NOTA-NEW',
            'notes' => 'Admin edit',
            'items' => [
                [
                    'product_id' => $productA->id,
                    'product_name' => $productA->name,
                    'unit' => 'exp',
                    'quantity' => 4,
                    'unit_cost' => 10000,
                    'notes' => '',
                ],
                [
                    'product_id' => $productB->id,
                    'product_name' => $productB->name,
                    'unit' => 'exp',
                    'quantity' => 2,
                    'unit_cost' => 5000,
                    'notes' => '',
                ],
            ],
        ]);

        $response->assertRedirect(route('outgoing-transactions.show', $transaction));
        $transaction->refresh();
        $productA->refresh();
        $productB->refresh();
        $supplier->refresh();

        $this->assertSame(50000.0, (float) $transaction->total);
        $this->assertSame('NOTA-NEW', (string) $transaction->note_number);
        $this->assertSame(8.0, (float) $productA->stock);
        $this->assertSame(9.0, (float) $productB->stock);
        $this->assertSame(50000.0, (float) $supplier->outstanding_payable);
    }

    public function test_admin_can_update_supplier_payment_and_adjust_outstanding(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Bayar',
            'company_name' => 'PT Bayar',
            'outstanding_payable' => 80000,
        ]);
        SupplierLedger::query()->create([
            'supplier_id' => $supplier->id,
            'outgoing_transaction_id' => null,
            'supplier_payment_id' => null,
            'entry_date' => '2026-02-10',
            'period_code' => 'S2-2526',
            'description' => 'Initial payable',
            'debit' => 100000,
            'credit' => 0,
            'balance_after' => 100000,
        ]);

        $payment = SupplierPayment::query()->create([
            'payment_number' => 'KWTS-20260220-0002',
            'supplier_id' => $supplier->id,
            'payment_date' => '2026-02-20',
            'proof_number' => 'PRF-01',
            'amount' => 20000,
            'amount_in_words' => 'dua puluh ribu rupiah',
            'supplier_signature' => 'Supplier',
            'user_signature' => 'Admin',
            'notes' => 'Awal',
            'created_by_user_id' => $admin->id,
        ]);
        SupplierLedger::query()->create([
            'supplier_id' => $supplier->id,
            'outgoing_transaction_id' => null,
            'supplier_payment_id' => $payment->id,
            'entry_date' => '2026-02-20',
            'period_code' => 'S2-2526',
            'description' => 'Initial payment',
            'debit' => 0,
            'credit' => 20000,
            'balance_after' => 80000,
        ]);

        $response = $this->actingAs($admin)->put(route('supplier-payables.admin-update', $payment), [
            'payment_date' => '2026-02-20',
            'proof_number' => 'PRF-02',
            'amount' => 30000,
            'supplier_signature' => 'Supplier',
            'user_signature' => 'Admin',
            'notes' => 'Edit admin',
        ]);

        $response->assertRedirect(route('supplier-payables.show-payment', $payment));
        $payment->refresh();
        $supplier->refresh();

        $this->assertSame(30000.0, (float) $payment->amount);
        $this->assertSame('PRF-02', (string) $payment->proof_number);
        $this->assertSame('Edit admin', (string) $payment->notes);
        $this->assertSame(70000.0, (float) $supplier->outstanding_payable);
    }

    public function test_outgoing_index_shows_admin_edit_badge_for_edited_transaction(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Badge',
            'company_name' => 'PT Badge',
        ]);
        $transaction = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-BADGE-0001',
            'transaction_date' => '2026-02-20',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'total' => 10000,
            'created_by_user_id' => $user->id,
        ]);
        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'outgoing.transaction.admin_update',
            'subject_type' => OutgoingTransaction::class,
            'subject_id' => $transaction->id,
            'description' => 'Admin edited outgoing',
        ]);

        $response = $this->actingAs($user)->get(route('outgoing-transactions.index'));

        $response->assertOk();
        $response->assertSee(__('txn.admin_badge_edit'));
    }

    public function test_supplier_payable_mutation_shows_admin_edit_badges(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Ledger Badge',
            'company_name' => 'PT Ledger Badge',
        ]);
        $transaction = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-BADGE-0002',
            'transaction_date' => '2026-02-20',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'total' => 12000,
            'created_by_user_id' => $user->id,
        ]);
        $payment = SupplierPayment::query()->create([
            'payment_number' => 'KWTS-BADGE-0001',
            'supplier_id' => $supplier->id,
            'payment_date' => '2026-02-20',
            'amount' => 5000,
            'amount_in_words' => 'lima ribu rupiah',
            'created_by_user_id' => $user->id,
        ]);
        SupplierLedger::query()->create([
            'supplier_id' => $supplier->id,
            'outgoing_transaction_id' => $transaction->id,
            'supplier_payment_id' => null,
            'entry_date' => '2026-02-20',
            'period_code' => 'S2-2526',
            'description' => 'Outgoing',
            'debit' => 12000,
            'credit' => 0,
            'balance_after' => 12000,
        ]);
        SupplierLedger::query()->create([
            'supplier_id' => $supplier->id,
            'outgoing_transaction_id' => null,
            'supplier_payment_id' => $payment->id,
            'entry_date' => '2026-02-20',
            'period_code' => 'S2-2526',
            'description' => 'Payment',
            'debit' => 0,
            'credit' => 5000,
            'balance_after' => 7000,
        ]);
        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'outgoing.transaction.admin_update',
            'subject_type' => OutgoingTransaction::class,
            'subject_id' => $transaction->id,
            'description' => 'Admin edited outgoing',
        ]);
        AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'supplier.payment.admin_update',
            'subject_type' => SupplierPayment::class,
            'subject_id' => $payment->id,
            'description' => 'Admin edited supplier payment',
        ]);

        $response = $this->actingAs($user)->get(route('supplier-payables.index', [
            'supplier_id' => $supplier->id,
        ]));

        $response->assertOk();
        $response->assertSee(__('txn.admin_badge_edit'));
    }
}
