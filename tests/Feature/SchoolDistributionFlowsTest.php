<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerShipLocation;
use App\Models\DeliveryNote;
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

    public function test_customer_ship_location_index_does_not_show_import_actions(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        $this
            ->actingAs($admin)
            ->get(route('customer-ship-locations.index'))
            ->assertOk()
            ->assertDontSee('name="import_file"', false)
            ->assertDontSee('Template Import')
            ->assertDontSee('customer-ship-locations/import', false);
    }

    public function test_customer_ship_location_status_can_be_changed_from_index(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);
        $customer = Customer::query()->create([
            'code' => 'CUST-SHIP-STATUS',
            'name' => 'Customer Status',
            'city' => 'Malang',
        ]);
        $location = CustomerShipLocation::query()->create([
            'customer_id' => $customer->id,
            'school_name' => 'Sekolah Status',
            'recipient_phone' => '081230000099',
            'city' => 'Malang',
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('customer-ship-locations.index'))
            ->assertOk()
            ->assertSee(route('customer-ship-locations.update-status', $location), false)
            ->assertSee('name="is_active"', false);

        $this
            ->actingAs($admin)
            ->patch(route('customer-ship-locations.update-status', $location), [
                'is_active' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse($location->fresh()->is_active);
    }

    public function test_customer_ship_location_create_form_places_school_and_city_before_phone(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);
        Customer::query()->create([
            'code' => 'CUST-SHIP-FORM',
            'name' => 'Customer Form',
            'city' => 'Malang',
        ]);

        $content = $this
            ->actingAs($admin)
            ->get(route('customer-ship-locations.create'))
            ->assertOk()
            ->getContent();

        $schoolPosition = strpos($content, 'name="school_name"');
        $cityPosition = strpos($content, 'name="city"');
        $phonePosition = strpos($content, 'name="recipient_phone"');

        $this->assertIsInt($schoolPosition);
        $this->assertIsInt($cityPosition);
        $this->assertIsInt($phonePosition);
        $this->assertLessThan($phonePosition, $schoolPosition);
        $this->assertLessThan($phonePosition, $cityPosition);
        $this->assertMatchesRegularExpression('/<option value="1"[^>]*selected[^>]*>/', $content);
    }

    public function test_school_bulk_create_form_shows_unit_as_quantity_label_without_unit_column(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => config('rbac.roles.user', []),
        ]);

        $content = $this
            ->actingAs($user)
            ->get(route('school-bulk-transactions.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('quantity-with-unit', $content);
        $this->assertStringContainsString('qty-unit-label', $content);
        $this->assertStringContainsString('school-table-scroll', $content);
        $this->assertStringContainsString('school-items-table', $content);
        $this->assertStringContainsString('type="hidden" class="product-unit"', $content);
        $this->assertStringNotContainsString('<th>Satuan</th>', $content);
    }

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
                    'uid' => 'loc-a',
                    'customer_ship_location_id' => $shipLocation->id,
                    'school_name' => 'SDN 1 Bulk',
                    'recipient_name' => 'TU SDN 1',
                    'recipient_phone' => '08120000001',
                    'city' => 'Malang',
                    'address' => 'Jl. Sekolah 1',
                ],
                [
                    'uid' => 'loc-b',
                    'school_name' => 'SDN 2 Bulk',
                    'recipient_name' => 'TU SDN 2',
                    'recipient_phone' => '08120000002',
                    'city' => 'Malang',
                    'address' => 'Jl. Sekolah 2',
                ],
            ],
            'location_items' => [
                'loc-a' => [
                    [
                        'product_id' => $product->id,
                        'product_name' => 'Buku Bulk',
                        'unit' => 'exp',
                        'quantity' => 10,
                        'unit_price' => 15000,
                        'notes' => 'Item test A',
                    ],
                ],
                'loc-b' => [
                    [
                        'product_id' => $product->id,
                        'product_name' => 'Buku Bulk',
                        'unit' => 'exp',
                        'quantity' => 20,
                        'unit_price' => 15000,
                        'notes' => 'Item test B',
                    ],
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
            'total_items' => 2,
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
        $this->assertDatabaseHas('school_bulk_transaction_items', [
            'school_bulk_transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'product_name' => 'Buku Bulk',
            'quantity' => 20,
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

    public function test_school_bulk_generates_delivery_notes_before_invoice_and_skips_duplicates(): void
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
            'total_items' => 2,
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
        $locationA = $transaction->locations()->where('school_name', 'SDN A')->firstOrFail();
        $locationB = $transaction->locations()->where('school_name', 'SDN B')->firstOrFail();
        $transaction->items()->create([
            'product_id' => $product->id,
            'school_bulk_transaction_location_id' => $locationA->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'exp',
            'quantity' => 5,
            'unit_price' => 15000,
            'sort_order' => 0,
        ]);
        $transaction->items()->create([
            'product_id' => $product->id,
            'school_bulk_transaction_location_id' => $locationB->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'exp',
            'quantity' => 7,
            'unit_price' => 15000,
            'sort_order' => 0,
        ]);

        $firstResponse = $this->actingAs($user)->post(
            route('school-bulk-transactions.generate-invoices', $transaction),
            [
                '_idempotency_key' => 'bulk-generate-first',
                'note_date' => '2026-02-20',
            ]
        );
        $firstResponse->assertRedirect(route('school-bulk-transactions.show', $transaction));

        $this->assertDatabaseCount('delivery_notes', 2);
        $generatedNotes = DeliveryNote::query()
            ->where('school_bulk_transaction_id', $transaction->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $generatedNotes);
        $this->assertSame(2, $generatedNotes->pluck('school_bulk_location_id')->filter()->unique()->count());
        $this->assertTrue($generatedNotes->every(fn (DeliveryNote $note): bool => (int) ($note->customer_ship_location_id ?? 0) > 0));
        $this->assertTrue($generatedNotes->every(fn (DeliveryNote $note): bool => trim((string) ($note->recipient_name ?? '')) !== ''));
        $this->assertTrue($generatedNotes->every(fn (DeliveryNote $note): bool => trim((string) ($note->city ?? '')) !== ''));
        $this->assertEqualsCanonicalizing(
            ['SDN A', 'SDN B'],
            $generatedNotes->pluck('recipient_name')->map(fn ($name): string => trim((string) $name))->all()
        );

        $product->refresh();
        $this->assertSame(88, (int) $product->stock);
        $this->assertDatabaseCount('delivery_note_items', 2);
        $this->assertDatabaseCount('sales_invoices', 0);
        $this->assertDatabaseCount('sales_invoice_items', 0);
        $this->assertDatabaseCount('stock_mutations', 2);
        $this->assertDatabaseHas('stock_mutations', [
            'reference_type' => DeliveryNote::class,
        ]);
        $this->assertDatabaseCount('receivable_ledgers', 0);
        $this->assertDatabaseCount('journal_entries', 0);

        $secondResponse = $this->actingAs($user)->post(
            route('school-bulk-transactions.generate-invoices', $transaction),
            [
                '_idempotency_key' => 'bulk-generate-second',
                'note_date' => '2026-02-20',
            ]
        );
        $secondResponse->assertRedirect(route('school-bulk-transactions.show', $transaction));
        $this->assertDatabaseCount('delivery_notes', 2);
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
