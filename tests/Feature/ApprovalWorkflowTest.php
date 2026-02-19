<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_approval_executes_sales_invoice_correction_patch(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $customer = Customer::query()->create([
            'code' => 'CUST-APV-01',
            'name' => 'Customer Approval',
            'city' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-APV',
            'name' => 'Kategori Approval',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-APV',
            'name' => 'Produk Approval',
            'unit' => 'pcs',
            'stock' => 10,
            'price_general' => 10000,
            'is_active' => true,
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-APV-001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-11',
            'semester_period' => 'S2-2526',
            'subtotal' => 20000,
            'total' => 20000,
            'total_paid' => 0,
            'balance' => 20000,
            'payment_status' => 'unpaid',
        ]);
        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 10000,
            'discount' => 0,
            'line_total' => 20000,
        ]);

        $approval = ApprovalRequest::query()->create([
            'module' => 'transaction',
            'action' => 'correction',
            'status' => 'pending',
            'subject_id' => $invoice->id,
            'subject_type' => SalesInvoice::class,
            'payload' => [
                'type' => 'sales_invoice',
                'requested_changes' => 'Ubah qty jadi 1',
                'patch' => [
                    'invoice_date' => '2026-02-11',
                    'semester_period' => 'S2-2526',
                    'notes' => 'Auto execute patch',
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'quantity' => 1,
                            'unit_price' => 10000,
                            'discount' => 0,
                        ],
                    ],
                ],
            ],
            'reason' => 'Perlu koreksi',
            'requested_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('approvals.approve', $approval), [
            'approval_note' => 'OK',
        ]);

        $response->assertRedirect();

        $approval->refresh();
        $invoice->refresh();
        $product->refresh();

        $this->assertSame('approved', (string) $approval->status);
        $this->assertSame('success', data_get($approval->payload, 'execution.status'));
        $this->assertSame('Auto execute patch', (string) $invoice->notes);
        $this->assertSame(10000.0, (float) $invoice->total);
        $this->assertSame(11.0, (float) $product->stock);
        $this->assertDatabaseHas('sales_invoice_items', [
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'line_total' => 10000,
        ]);
    }

    public function test_user_cannot_open_approvals_page(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('approvals.index'))
            ->assertForbidden();
    }

    public function test_user_can_open_transaction_correction_wizard_page(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('transaction-corrections.create'))
            ->assertOk();
    }

    public function test_admin_approval_executes_supplier_payment_correction_patch(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $supplier = Supplier::query()->create([
            'name' => 'Supplier Approval',
            'company_name' => 'PT Supplier Approval',
            'phone' => '0812345678',
            'address' => 'Malang',
            'outstanding_payable' => 80000,
        ]);
        $payment = SupplierPayment::query()->create([
            'payment_number' => 'KWTS-20260220-0001',
            'supplier_id' => $supplier->id,
            'payment_date' => '2026-02-20',
            'proof_number' => 'NOTA-001',
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
            'supplier_payment_id' => null,
            'entry_date' => '2026-02-10',
            'period_code' => 'S2-2526',
            'description' => 'Initial payable',
            'debit' => 100000,
            'credit' => 0,
            'balance_after' => 100000,
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

        $approval = ApprovalRequest::query()->create([
            'module' => 'transaction',
            'action' => 'correction',
            'status' => 'pending',
            'subject_id' => $payment->id,
            'subject_type' => SupplierPayment::class,
            'payload' => [
                'type' => 'supplier_payment',
                'requested_changes' => 'Ubah nominal pembayaran',
                'patch' => [
                    'payment_date' => '2026-02-20',
                    'proof_number' => 'NOTA-001-REV',
                    'amount' => 30000,
                    'supplier_signature' => 'Supplier',
                    'user_signature' => 'Admin',
                    'notes' => 'Revisi approval',
                ],
            ],
            'reason' => 'Nominal salah input',
            'requested_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('approvals.approve', $approval), [
            'approval_note' => 'OK',
        ])->assertRedirect();

        $approval->refresh();
        $payment->refresh();
        $supplier->refresh();

        $this->assertSame('approved', (string) $approval->status);
        $this->assertSame('success', data_get($approval->payload, 'execution.status'));
        $this->assertSame(30000.0, (float) $payment->amount);
        $this->assertSame('NOTA-001-REV', (string) $payment->proof_number);
        $this->assertSame('Revisi approval', (string) $payment->notes);
        $this->assertSame(70000.0, (float) $supplier->outstanding_payable);
    }
}
