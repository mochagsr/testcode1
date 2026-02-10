<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivableFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_receivable_index_page_loads_successfully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('receivables.index'));

        $response->assertOk();
        $response->assertSee(__('receivable.title'));
        $response->assertSee(__('receivable.print_options_title'));
    }

    public function test_receivable_report_print_respects_semester_and_customer_filters(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'code' => 'CUST-001',
            'name' => 'Customer Alpha',
            'city' => 'Malang',
        ]);

        SalesInvoice::query()->create([
            'invoice_number' => 'INV-TEST-001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-10',
            'semester_period' => 'S1-2026',
            'subtotal' => 100000,
            'total' => 100000,
            'total_paid' => 30000,
            'balance' => 70000,
            'payment_status' => 'partial',
        ]);

        $response = $this->actingAs($user)->get(route('reports.print', [
            'dataset' => 'receivables',
            'semester' => 'S1-2026',
            'customer_id' => $customer->id,
        ]));

        $response->assertOk();
        $response->assertSee('INV-TEST-001');
        $response->assertSee('Customer Alpha');
        $response->assertSee(__('report.receivable_summary.total_unpaid_invoices'));
        $response->assertSee(__('report.receivable_summary.total_outstanding'));
    }

    public function test_receivable_report_csv_contains_summary_and_headers(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'code' => 'CUST-002',
            'name' => 'Customer Beta',
            'city' => 'Surabaya',
        ]);

        SalesInvoice::query()->create([
            'invoice_number' => 'INV-TEST-002',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-08-10',
            'semester_period' => 'S2-2026',
            'subtotal' => 50000,
            'total' => 50000,
            'total_paid' => 0,
            'balance' => 50000,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->get(route('reports.export.csv', [
            'dataset' => 'receivables',
            'semester' => 'S2-2026',
            'customer_id' => $customer->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString(__('report.receivable_summary.total_unpaid_invoices'), $content);
        $this->assertStringContainsString('INV-TEST-002', $content);
    }
}
