<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
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
}

