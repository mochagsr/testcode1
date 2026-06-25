<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Models\CustomerShipLocation;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\InvoicePayment;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SchoolBulkTransaction;
use App\Models\SchoolBulkTransactionLocation;
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
            ->assertSee('class="ship-location-status-input"', false)
            ->assertSee('type="hidden" name="is_active" value="0"', false)
            ->assertSee(__('txn.status_active'));

        $this
            ->actingAs($admin)
            ->patch(route('customer-ship-locations.update-status', $location), [
                'is_active' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse($location->fresh()->is_active);
    }

    public function test_school_bulk_index_shows_delivery_note_progress_without_total_items(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);
        $customer = Customer::query()->create([
            'code' => 'CUST-BULK-INDEX',
            'name' => 'Customer Bulk Index',
            'city' => 'Malang',
        ]);
        $transaction = SchoolBulkTransaction::query()->create([
            'transaction_number' => 'BLK-INDEX-0001',
            'transaction_date' => '2026-05-07',
            'customer_id' => $customer->id,
            'semester_period' => 'S1-2627',
            'total_locations' => 3,
            'total_items' => 9,
            'created_by_user_id' => $admin->id,
        ]);
        $location = $transaction->locations()->create([
            'school_name' => 'SDN Index',
            'city' => 'Malang',
            'sort_order' => 0,
        ]);
        DeliveryNote::query()->create([
            'note_number' => 'SJ-INDEX-0001',
            'note_date' => '2026-05-07',
            'customer_id' => $customer->id,
            'school_bulk_transaction_id' => $transaction->id,
            'school_bulk_location_id' => $location->id,
            'recipient_name' => 'SDN Index',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('school-bulk-transactions.index'))
            ->assertOk()
            ->assertSee(__('school_bulk.delivery_notes_created'))
            ->assertSee(__('school_bulk.delivery_notes_pending'))
            ->assertDontSee('<th>'.__('school_bulk.total_items').'</th>', false);

        $content = preg_replace('/\s+/', ' ', $response->getContent());

        $this->assertStringContainsString('BLK-INDEX-0001', (string) $content);
        $this->assertStringContainsString('<td>3</td> <td>1</td> <td>2</td>', (string) $content);
    }

    public function test_school_bulk_draft_can_be_deleted_by_transaction_creator_before_documents_are_generated(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [],
        ]);
        $customer = Customer::query()->create([
            'code' => 'CUST-BULK-DELETE',
            'name' => 'Customer Bulk Delete',
            'city' => 'Malang',
        ]);
        $transaction = SchoolBulkTransaction::query()->create([
            'transaction_number' => 'BLK-DELETE-0001',
            'transaction_date' => '2026-05-07',
            'customer_id' => $customer->id,
            'semester_period' => 'S1-2627',
            'total_locations' => 1,
            'total_items' => 1,
            'created_by_user_id' => $user->id,
        ]);
        $location = $transaction->locations()->create([
            'school_name' => 'SDN Draft Delete',
            'city' => 'Malang',
            'sort_order' => 0,
        ]);
        $item = $transaction->items()->create([
            'school_bulk_transaction_location_id' => $location->id,
            'product_name' => 'Buku Draft Delete',
            'unit' => 'exp',
            'quantity' => 10,
            'unit_price' => 15000,
            'sort_order' => 0,
        ]);

        $this
            ->actingAs($user)
            ->get(route('school-bulk-transactions.index'))
            ->assertOk()
            ->assertSee(route('school-bulk-transactions.destroy', $transaction), false)
            ->assertSee(__('ui.delete'));

        $this
            ->actingAs($user)
            ->delete(route('school-bulk-transactions.destroy', $transaction))
            ->assertRedirect(route('school-bulk-transactions.index'))
            ->assertSessionHas('success', __('school_bulk.bulk_transaction_deleted'));

        $this->assertDatabaseMissing('school_bulk_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseMissing('school_bulk_transaction_locations', ['id' => $location->id]);
        $this->assertDatabaseMissing('school_bulk_transaction_items', ['id' => $item->id]);
    }

    public function test_school_bulk_delete_is_blocked_after_delivery_note_is_generated(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);
        $customer = Customer::query()->create([
            'code' => 'CUST-BULK-BLOCK',
            'name' => 'Customer Bulk Block',
            'city' => 'Malang',
        ]);
        $transaction = SchoolBulkTransaction::query()->create([
            'transaction_number' => 'BLK-BLOCK-0001',
            'transaction_date' => '2026-05-07',
            'customer_id' => $customer->id,
            'semester_period' => 'S1-2627',
            'total_locations' => 1,
            'total_items' => 1,
            'created_by_user_id' => $admin->id,
        ]);
        $location = $transaction->locations()->create([
            'school_name' => 'SDN Block Delete',
            'city' => 'Malang',
            'sort_order' => 0,
        ]);
        DeliveryNote::query()->create([
            'note_number' => 'SJ-BLOCK-0001',
            'note_date' => '2026-05-07',
            'customer_id' => $customer->id,
            'school_bulk_transaction_id' => $transaction->id,
            'school_bulk_location_id' => $location->id,
            'recipient_name' => 'SDN Block Delete',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('school-bulk-transactions.index'))
            ->assertOk()
            ->assertSee('title="'.__('school_bulk.bulk_transaction_delete_hint').'"', false)
            ->assertSee(__('school_bulk.bulk_transaction_delete_hint'));

        $this
            ->actingAs($admin)
            ->get(route('school-bulk-transactions.show', $transaction))
            ->assertOk()
            ->assertSee('title="'.__('school_bulk.bulk_transaction_delete_hint').'"', false)
            ->assertSee(__('school_bulk.bulk_transaction_delete_hint'));

        $this
            ->actingAs($admin)
            ->delete(route('school-bulk-transactions.destroy', $transaction))
            ->assertRedirect()
            ->assertSessionHas('error', __('school_bulk.bulk_transaction_delete_blocked'));

        $this->assertDatabaseHas('school_bulk_transactions', ['id' => $transaction->id]);
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

    public function test_school_bulk_uses_customer_level_price_through_delivery_note_invoice_flow(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $salesLevel = CustomerLevel::query()->create([
            'code' => 'SLS',
            'name' => 'Sales',
        ]);
        $customer = Customer::query()->create([
            'code' => 'CUST-BULK-SALES',
            'name' => 'Anton Sales',
            'city' => 'Malang',
            'customer_level_id' => $salesLevel->id,
        ]);
        $shipLocation = CustomerShipLocation::query()->create([
            'customer_id' => $customer->id,
            'school_name' => 'SDN Sales',
            'recipient_phone' => '08120000001',
            'city' => 'Malang',
            'address' => 'Jl. Sales',
            'is_active' => true,
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-SALES',
            'name' => 'Kategori Sales',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-SALES',
            'name' => 'Buku Harga Sales',
            'unit' => 'exp',
            'stock' => 100,
            'price_agent' => 3500,
            'price_sales' => 4500,
            'price_general' => 12000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('school-bulk-transactions.store'), [
            '_idempotency_key' => 'bulk-sales-price-001',
            'customer_id' => $customer->id,
            'transaction_date' => '2026-05-10',
            'semester_period' => 'S1-2627',
            'locations' => [
                [
                    'uid' => 'loc-sales',
                    'customer_ship_location_id' => $shipLocation->id,
                    'school_name' => $shipLocation->school_name,
                    'recipient_phone' => $shipLocation->recipient_phone,
                    'city' => $shipLocation->city,
                    'address' => $shipLocation->address,
                ],
            ],
            'location_items' => [
                'loc-sales' => [
                    [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit' => $product->unit,
                        'quantity' => 10,
                        'unit_price' => '',
                    ],
                ],
            ],
        ]);

        $transaction = SchoolBulkTransaction::query()->firstOrFail();
        $response->assertRedirect(route('school-bulk-transactions.show', $transaction));
        $this->assertDatabaseHas('school_bulk_transaction_items', [
            'school_bulk_transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'unit_price' => 4500,
        ]);

        $this->actingAs($user)
            ->post(route('school-bulk-transactions.generate-delivery-notes', $transaction), [
                'note_date' => '2026-05-10',
            ])
            ->assertRedirect(route('school-bulk-transactions.show', $transaction));

        $deliveryNote = DeliveryNote::query()
            ->where('school_bulk_transaction_id', $transaction->id)
            ->firstOrFail();

        $this->assertDatabaseHas('delivery_note_items', [
            'delivery_note_id' => $deliveryNote->id,
            'product_id' => $product->id,
            'unit_price' => 4500,
        ]);

        $this->actingAs($user)
            ->get(route('sales-invoices.create-from-delivery-notes', [
                'delivery_note_ids' => [$deliveryNote->id],
            ]))
            ->assertOk()
            ->assertSee('name="items[0][unit_price]" value="4500"', false)
            ->assertDontSee('name="items[0][unit_price]" value="12000"', false);
    }

    public function test_school_bulk_single_item_template_is_applied_to_all_schools(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $customer = Customer::query()->create([
            'code' => 'CUST-BULK-TEMPLATE',
            'name' => 'Customer Bulk Template',
            'city' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-BULK-TPL',
            'name' => 'Kategori Bulk Template',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-BULK-TPL',
            'name' => 'Buku Template',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('school-bulk-transactions.store'), [
            '_idempotency_key' => 'bulk-template-test-001',
            'customer_id' => $customer->id,
            'transaction_date' => '2026-02-20',
            'semester_period' => 'S2-2526',
            'locations' => [
                ['uid' => 'loc-a', 'school_name' => 'SDN A', 'city' => 'Malang'],
                ['uid' => 'loc-b', 'school_name' => 'SDN B', 'city' => 'Malang'],
                ['uid' => 'loc-c', 'school_name' => 'SDN C', 'city' => 'Malang'],
            ],
            'location_items' => [
                'loc-a' => [
                    [
                        'product_id' => $product->id,
                        'product_name' => 'Buku Template',
                        'unit' => 'exp',
                        'quantity' => 10,
                        'unit_price' => 15000,
                    ],
                ],
            ],
        ]);

        $transaction = SchoolBulkTransaction::query()->firstOrFail();
        $response->assertRedirect(route('school-bulk-transactions.show', $transaction));

        $this->assertSame(3, $transaction->locations()->count());
        $this->assertSame(3, $transaction->items()->count());
        $this->assertSame(3, $transaction->items()->pluck('school_bulk_transaction_location_id')->unique()->count());

        $this
            ->actingAs($user)
            ->get(route('school-bulk-transactions.show', $transaction))
            ->assertOk()
            ->assertSee('SDN A')
            ->assertSee('SDN B')
            ->assertSee('SDN C');
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
            route('school-bulk-transactions.generate-delivery-notes', $transaction),
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

        $this
            ->actingAs($user)
            ->get(route('school-bulk-transactions.show', $transaction))
            ->assertOk()
            ->assertSee('2 / 2 '.__('school_bulk.total_schools'))
            ->assertSee(__('school_bulk.pending_schools').': 0');

        $secondResponse = $this->actingAs($user)->post(
            route('school-bulk-transactions.generate-delivery-notes', $transaction),
            [
                '_idempotency_key' => 'bulk-generate-second',
                'note_date' => '2026-02-20',
            ]
        );
        $secondResponse->assertRedirect(route('school-bulk-transactions.show', $transaction));
        $this->assertDatabaseCount('delivery_notes', 2);
    }

    public function test_customer_bill_print_includes_invoice_breakdown_section(): void
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
        $response->assertSee(__('receivable.invoice_breakdown_title'));
        $response->assertSee('receivable-breakdown-page');
        $response->assertDontSee('Breakdown Per Sekolah');
        $response->assertDontSee('SDN A');
        $response->assertDontSee('SDN B');
        $response->assertSee('INV-BILL-001');
        $response->assertSee('INV-BILL-002');
    }

    public function test_customer_bill_print_splits_combined_invoice_by_delivery_note_school(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $customer = Customer::query()->create([
            'code' => 'CUST-BILL-BULK',
            'name' => 'Customer Bill Bulk',
            'city' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-BILL-BULK',
            'name' => 'Kategori Bill Bulk',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-BILL-BULK',
            'name' => 'Buku Bill Bulk',
            'unit' => 'exp',
            'stock' => 1000,
            'price_general' => 5000,
            'is_active' => true,
        ]);
        $transaction = SchoolBulkTransaction::query()->create([
            'transaction_number' => 'BLK-BILL-BULK',
            'transaction_date' => '2026-05-07',
            'customer_id' => $customer->id,
            'semester_period' => 'S1-2627',
            'total_locations' => 3,
            'total_items' => 3,
            'created_by_user_id' => $user->id,
        ]);
        $locations = collect([
            ['school_name' => 'sd prambon 1', 'city' => 'malang', 'sort_order' => 0],
            ['school_name' => 'sd prambon 2', 'city' => 'malang', 'sort_order' => 1],
            ['school_name' => 'sd prambon 3', 'city' => 'malang', 'sort_order' => 2],
        ])->map(fn (array $location): SchoolBulkTransactionLocation => SchoolBulkTransactionLocation::query()->create([
            'school_bulk_transaction_id' => $transaction->id,
            'school_name' => $location['school_name'],
            'recipient_name' => $location['school_name'],
            'city' => $location['city'],
            'sort_order' => $location['sort_order'],
        ]));

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-BILL-BULK-001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-05-07',
            'semester_period' => 'S1-2627',
            'school_bulk_transaction_id' => $transaction->id,
            'total' => 1500000,
            'total_paid' => 500000,
            'balance' => 1000000,
            'payment_status' => 'partial',
            'ship_to_name' => 'sd prambon 1',
            'ship_to_city' => 'malang',
        ]);

        $locations->each(function (SchoolBulkTransactionLocation $location, int $index) use ($customer, $invoice, $product, $transaction): void {
            $deliveryNote = DeliveryNote::query()->create([
                'note_number' => 'SJ-BILL-BULK-00'.($index + 1),
                'note_date' => '2026-05-07',
                'customer_id' => $customer->id,
                'school_bulk_transaction_id' => $transaction->id,
                'school_bulk_location_id' => $location->id,
                'recipient_name' => $location->school_name,
                'city' => $location->city,
                'is_canceled' => false,
            ]);
            $deliveryNoteItem = DeliveryNoteItem::query()->create([
                'delivery_note_id' => $deliveryNote->id,
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'unit' => 'exp',
                'quantity' => 100,
                'unit_price' => 5000,
            ]);

            SalesInvoiceItem::query()->create([
                'sales_invoice_id' => $invoice->id,
                'delivery_note_item_id' => $deliveryNoteItem->id,
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'quantity' => 100,
                'unit_price' => 5000,
                'discount' => 0,
                'line_total' => 500000,
            ]);
        });
        InvoicePayment::query()->create([
            'sales_invoice_id' => $invoice->id,
            'payment_date' => '2026-05-07',
            'amount' => 500000,
            'method' => 'Tunai',
            'notes' => 'Pembayaran KWT-BILL-BULK-001',
        ]);

        $response = $this->actingAs($user)->get(route('receivables.print-customer-bill', [
            'customer' => $customer->id,
            'semester' => 'S1-2627',
        ]));

        $response->assertOk();
        $response->assertSee(__('receivable.invoice_breakdown_title'));
        $response->assertDontSee('sd prambon 1');
        $response->assertDontSee('sd prambon 2');
        $response->assertDontSee('sd prambon 3');
        $response->assertSee('INV-BILL-BULK-001');
        $response->assertSee('KWT-BILL-BULK-001');
        $response->assertDontSee('166.667');
    }
}
