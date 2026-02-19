<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCorrectionWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_invalid_patch_json_for_supported_type(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $customer = Customer::query()->create([
            'code' => 'CUST-WIZ-01',
            'name' => 'Customer Wizard',
            'city' => 'Malang',
        ]);
        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-WIZ-001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-20',
            'semester_period' => 'S2-2526',
            'subtotal' => 10000,
            'total' => 10000,
            'total_paid' => 0,
            'balance' => 10000,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)
            ->from(route('transaction-corrections.create', ['type' => 'sales_invoice', 'id' => $invoice->id]))
            ->post(route('transaction-corrections.store'), [
                'type' => 'sales_invoice',
                'subject_id' => $invoice->id,
                'reason' => 'Test invalid json',
                'requested_changes' => 'Perbaiki item',
                'requested_patch_json' => '{"broken_json": ',
            ]);

        $response->assertRedirect(route('transaction-corrections.create', ['type' => 'sales_invoice', 'id' => $invoice->id]));
        $response->assertSessionHasErrors(['requested_patch_json']);
        $this->assertSame(0, ApprovalRequest::query()->count());
    }
}

