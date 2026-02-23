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
            ->assertSee('SDN 2 Bulk')
            ->assertSee('school-page', false)
            ->assertSee(__('txn.signature_created'));

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

    public function test_school_bulk_generate_invoices_creates_one_invoice_per_school_and_skips_duplicates(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $customer = Customer::query()->create([
            'code' => 'CUST-BULK-GEN',
            'name' => 'Customer Bulk Generate',
            'city' => 'Malang',
        ]);
        $shipLocationA = CustomerShipLocation::query()->create([
            'customer_id' => $customer->id,
            'school_name' => 'SDN A',
            'recipient_name' => 'TU A',
            'recipient_phone' => '081230000001',
            'city' => 'Malang',
            'address' => 'Jl. A',
            'is_active' => true,
        ]);
        $shipLocationB = CustomerShipLocation::query()->create([
            'customer_id' => $customer->id,
            'school_name' => 'SDN B',
            'recipient_name' => 'TU B',
            'recipient_phone' => '081230000002',
            'city' => 'Malang',
            'address' => 'Jl. B',
            'is_active' => true,
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-BULK-GEN',
            'name' => 'Kategori Bulk Gen',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-BULK-GEN',
            'name' => 'Buku Bulk Gen',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $transaction = SchoolBulkTransaction::query()->create([
            'transaction_number' => 'BLK-20260220-0001',
            'transaction_date' => '2026-02-20',
            'customer_id' => $customer->id,
            'semester_period' => 'S2-2526',
            'total_locations' => 2,
            'total_items' => 1,
            'notes' => 'Generate invoice test',
            'created_by_user_id' => $user->id,
        ]);
        $transaction->locations()->createMany([
            [
                'customer_ship_location_id' => $shipLocationA->id,
                'school_name' => 'SDN A',
                'recipient_name' => 'TU A',
                'recipient_phone' => '081230000001',
                'city' => 'Malang',
                'address' => 'Jl. A',
                'sort_order' => 0,
            ],
            [
                'customer_ship_location_id' => $shipLocationB->id,
                'school_name' => 'SDN B',
                'recipient_name' => 'TU B',
                'recipient_phone' => '081230000002',
                'city' => 'Malang',
                'address' => 'Jl. B',
                'sort_order' => 1,
            ],
        ]);
        $transaction->items()->create([
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'exp',
            'quantity' => 5,
            'unit_price' => 15000,
            'sort_order' => 0,
        ]);

        $firstResponse = $this->actingAs($user)->post(
            route('school-bulk-transactions.generate-invoices', $transaction),
            [
                '_idempotency_key' => 'bulk-generate-first',
                'invoice_date' => '2026-02-20',
                'due_date' => '2026-03-01',
            ]
        );
        $firstResponse->assertRedirect(route('school-bulk-transactions.show', $transaction));

        $this->assertDatabaseCount('sales_invoices', 2);
        $generatedInvoices = SalesInvoice::query()
            ->where('school_bulk_transaction_id', $transaction->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $generatedInvoices);
        $this->assertSame(2, $generatedInvoices->pluck('school_bulk_location_id')->filter()->unique()->count());
        $this->assertTrue($generatedInvoices->every(fn(SalesInvoice $invoice): bool => (int) ($invoice->customer_ship_location_id ?? 0) > 0));
        $this->assertTrue($generatedInvoices->every(fn(SalesInvoice $invoice): bool => trim((string) ($invoice->ship_to_name ?? '')) !== ''));
        $this->assertTrue($generatedInvoices->every(fn(SalesInvoice $invoice): bool => trim((string) ($invoice->ship_to_city ?? '')) !== ''));
        $this->assertEqualsCanonicalizing(
            ['SDN A', 'SDN B'],
            $generatedInvoices->pluck('ship_to_name')->map(fn($name): string => trim((string) $name))->all()
        );
        $this->assertTrue($generatedInvoices->every(fn(SalesInvoice $invoice): bool => (int) round((float) $invoice->total) === 75000));
        $this->assertTrue($generatedInvoices->every(fn(SalesInvoice $invoice): bool => (string) $invoice->payment_status === 'unpaid'));
        $this->assertTrue($generatedInvoices->every(fn(SalesInvoice $invoice): bool => (int) round((float) $invoice->balance) === 75000));

        $product->refresh();
        $this->assertSame(90, (int) $product->stock);
        $this->assertDatabaseCount('sales_invoice_items', 2);
        $this->assertDatabaseCount('stock_mutations', 2);
        $this->assertDatabaseCount('receivable_ledgers', 2);
        $this->assertDatabaseHas('receivable_ledgers', [
            'customer_id' => $customer->id,
            'debit' => 75000,
            'credit' => 0,
        ]);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseHas('journal_entries', [
            'entry_type' => 'sales_invoice_create',
            'reference_type' => SalesInvoice::class,
        ]);

        $secondResponse = $this->actingAs($user)->post(
            route('school-bulk-transactions.generate-invoices', $transaction),
            [
                '_idempotency_key' => 'bulk-generate-second',
                'invoice_date' => '2026-02-20',
            ]
        );
        $secondResponse->assertRedirect(route('school-bulk-transactions.show', $transaction));
        $this->assertDatabaseCount('sales_invoices', 2);
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
