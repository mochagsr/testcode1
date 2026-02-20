<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerShipLocation;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SchoolBulkTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolDistributionFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_ship_location_lookup_filters_by_customer_and_active_status(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $customerA = Customer::query()->create([
            'code' => 'CUST-SHIP-A',
            'name' => 'Customer A',
            'city' => 'Malang',
        ]);
        $customerB = Customer::query()->create([
            'code' => 'CUST-SHIP-B',
            'name' => 'Customer B',
            'city' => 'Surabaya',
        ]);

        CustomerShipLocation::query()->create([
            'customer_id' => $customerA->id,
            'school_name' => 'Sekolah Mawar',
            'recipient_name' => 'Admin A',
            'city' => 'Malang',
            'is_active' => true,
        ]);
        CustomerShipLocation::query()->create([
            'customer_id' => $customerA->id,
            'school_name' => 'Sekolah Nonaktif',
            'recipient_name' => 'Admin B',
            'city' => 'Malang',
            'is_active' => false,
        ]);
        CustomerShipLocation::query()->create([
            'customer_id' => $customerB->id,
            'school_name' => 'Sekolah Melati',
            'recipient_name' => 'Admin C',
            'city' => 'Surabaya',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson(route('customer-ship-locations.lookup', [
            'customer_id' => $customerA->id,
            'search' => 'sek',
            'per_page' => 20,
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.school_name', 'Sekolah Mawar');
    }

    public function test_school_bulk_transaction_store_persists_locations_items_and_supports_exports(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $customer = Customer::query()->create([
            'code' => 'CUST-BULK-1',
            'name' => 'Customer Bulk',
            'city' => 'Malang',
        ]);
        $shipLocation = CustomerShipLocation::query()->create([
            'customer_id' => $customer->id,
            'school_name' => 'SDN 1 Bulk',
            'recipient_name' => 'TU SDN 1',
            'recipient_phone' => '08120000001',
            'city' => 'Malang',
            'address' => 'Jl. Sekolah 1',
            'is_active' => true,
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-BULK',
            'name' => 'Kategori Bulk',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-BULK-1',
            'name' => 'Buku Bulk',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('school-bulk-transactions.store'), [
            '_idempotency_key' => 'bulk-test-001',
            'customer_id' => $customer->id,
            'transaction_date' => '2026-02-20',
            'semester_period' => 'S2-2526',
            'notes' => 'Transaksi sebar test',
            'locations' => [
                [
                    'customer_ship_location_id' => $shipLocation->id,
                    'school_name' => 'SDN 1 Bulk',
                    'recipient_name' => 'TU SDN 1',
                    'recipient_phone' => '08120000001',
                    'city' => 'Malang',
                    'address' => 'Jl. Sekolah 1',
                ],
                [
                    'school_name' => 'SDN 2 Bulk',
                    'recipient_name' => 'TU SDN 2',
                    'recipient_phone' => '08120000002',
                    'city' => 'Malang',
                    'address' => 'Jl. Sekolah 2',
                ],
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => 'Buku Bulk',
                    'unit' => 'exp',
                    'quantity' => 10,
                    'unit_price' => 15000,
                    'notes' => 'Item test',
                ],
            ],
        ]);

        $transaction = SchoolBulkTransaction::query()->first();
        $this->assertNotNull($transaction);
        $response->assertRedirect(route('school-bulk-transactions.show', $transaction));

        $this->assertDatabaseHas('school_bulk_transactions', [
            'id' => $transaction->id,
            'customer_id' => $customer->id,
            'total_locations' => 2,
            'total_items' => 1,
        ]);
        $this->assertDatabaseHas('school_bulk_transaction_locations', [
            'school_bulk_transaction_id' => $transaction->id,
            'customer_ship_location_id' => $shipLocation->id,
            'school_name' => 'SDN 1 Bulk',
        ]);
        $this->assertDatabaseHas('school_bulk_transaction_items', [
            'school_bulk_transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'product_name' => 'Buku Bulk',
            'quantity' => 10,
        ]);

        $this->actingAs($user)
            ->get(route('school-bulk-transactions.print', $transaction))
            ->assertOk()
            ->assertSee('SDN 1 Bulk')
            ->assertSee('SDN 2 Bulk');

        $this->actingAs($user)
            ->get(route('school-bulk-transactions.export.pdf', $transaction))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $excelResponse = $this->actingAs($user)
            ->get(route('school-bulk-transactions.export.excel', $transaction));
        $excelResponse->assertOk();
        $excelResponse->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $excelResponse->streamedContent());
    }

    public function test_customer_bill_print_includes_school_breakdown_section(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $customer = Customer::query()->create([
            'code' => 'CUST-BILL-1',
            'name' => 'Customer Bill',
            'city' => 'Malang',
        ]);

        SalesInvoice::query()->create([
            'invoice_number' => 'INV-BILL-001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-11',
            'semester_period' => 'S2-2526',
            'total' => 100000,
            'total_paid' => 25000,
            'balance' => 75000,
            'payment_status' => 'unpaid',
            'ship_to_name' => 'SDN A',
            'ship_to_city' => 'Kota A',
        ]);
        SalesInvoice::query()->create([
            'invoice_number' => 'INV-BILL-002',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-12',
            'semester_period' => 'S2-2526',
            'total' => 200000,
            'total_paid' => 50000,
            'balance' => 150000,
            'payment_status' => 'unpaid',
            'ship_to_name' => 'SDN B',
            'ship_to_city' => 'Kota B',
        ]);

        $response = $this->actingAs($user)->get(route('receivables.print-customer-bill', [
            'customer' => $customer->id,
            'semester' => 'S2-2526',
        ]));

        $response->assertOk();
        $response->assertSee(__('receivable.school_breakdown_title'));
        $response->assertSee('SDN A');
        $response->assertSee('SDN B');
        $response->assertSee('INV-BILL-001');
        $response->assertSee('INV-BILL-002');
    }
}

