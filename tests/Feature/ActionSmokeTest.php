<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportExportTaskJob;
use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Models\CustomerPrintingSubtype;
use App\Models\CustomerShipLocation;
use App\Models\ItemCategory;
use App\Models\OrderNote;
use App\Models\OrderNoteItem;
use App\Models\Product;
use App\Models\ReportExportTask;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SchoolBulkTransaction;
use App\Models\SchoolBulkTransactionItem;
use App\Models\SchoolBulkTransactionLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ActionSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_endpoints_load_without_server_error(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        [
            'customer' => $customer,
            'orderNote' => $orderNote,
        ] = $this->seedActionData($admin);

        CustomerPrintingSubtype::query()->create([
            'customer_id' => $customer->id,
            'name' => 'LKS',
            'normalized_name' => CustomerPrintingSubtype::normalizeName('LKS'),
        ]);

        $this->actingAs($admin)
            ->getJson(route('suppliers.lookup', ['search' => 'sup']))
            ->assertOk();

        $this->actingAs($admin)
            ->getJson(route('customer-ship-locations.lookup', ['customer_id' => $customer->id, 'search' => 'smoke']))
            ->assertOk();

        $this->actingAs($admin)
            ->getJson(route('api.order-notes.lookup', ['customer_id' => $customer->id, 'search' => $orderNote->note_number]))
            ->assertOk();

        $this->actingAs($admin)
            ->getJson(route('api.customers.printing-subtypes.index', $customer))
            ->assertOk();

        $this->actingAs($admin)
            ->getJson(route('reports.queue.status'))
            ->assertOk();
    }

    public function test_non_destructive_post_actions_do_not_500(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        [
            'customer' => $customer,
            'invoice' => $invoice,
        ] = $this->seedActionData($admin);

        $this->actingAs($admin)
            ->post(route('transaction-corrections.preview-stock'), [
                'type' => 'sales_invoice',
                'subject_id' => $invoice->id,
                'requested_patch_json' => json_encode([
                    'items' => [[
                        'product_id' => (int) $invoice->items->first()->product_id,
                        'quantity' => (int) $invoice->items->first()->quantity,
                        'unit_price' => (int) $invoice->items->first()->unit_price,
                        'discount' => 0,
                    ]],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('api.customers.printing-subtypes.store', $customer), [
                'name' => 'KBR',
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->get(route('reports.queue', ['dataset' => 'products', 'format' => 'pdf']))
            ->assertRedirect();

        $queuedTask = ReportExportTask::query()->latest('id')->first();
        $this->assertNotNull($queuedTask);
        $this->assertSame('queued', $queuedTask->status);
        Queue::assertPushed(GenerateReportExportTaskJob::class);

        $this->actingAs($admin)
            ->post(route('reports.queue.cancel', $queuedTask))
            ->assertRedirect();

        $queuedTask->refresh();
        $this->assertSame('canceled', $queuedTask->status);

        $failedTask = ReportExportTask::query()->create([
            'user_id' => $admin->id,
            'dataset' => 'products',
            'format' => 'pdf',
            'status' => 'failed',
            'filters' => [],
            'error_message' => 'Simulasi failed',
        ]);

        $this->actingAs($admin)
            ->post(route('reports.queue.retry', $failedTask))
            ->assertRedirect();

        $failedTask->refresh();
        $this->assertSame('queued', $failedTask->status);

        $this->actingAs($admin)
            ->post(route('archive-data.scan'), [
                'archive_scope_type' => 'year',
                'archive_year' => '2025',
                'dataset_key' => 'sales_invoices',
            ])
            ->assertRedirect()
            ->assertSessionHas('archive_scan_result')
            ->assertSessionHas('archive_success');

        $this->actingAs($admin)
            ->post(route('archive-data.scan'), [
                'archive_scope_type' => 'semester',
                'archive_semester' => 'S2-2526',
                'datasets' => ['sales_invoices'],
            ])
            ->assertRedirect()
            ->assertSessionHas('archive_scan_result')
            ->assertSessionHas('archive_success');

        $this->actingAs($admin)
            ->post(route('archive-data.check-financial'))
            ->assertRedirect()
            ->assertSessionHas('archive_integrity_result')
            ->assertSessionHas('archive_success');

        $this->actingAs($admin)
            ->post(route('ops-health.integrity-check'))
            ->assertRedirect()
            ->assertSessionHas('ops_success');

        $this->actingAs($admin)
            ->post(route('ops-health.check-financial'))
            ->assertRedirect()
            ->assertSessionHas('ops_success');
    }

    public function test_bulk_invoice_generation_action_does_not_500(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        [
            'bulk' => $bulk,
        ] = $this->seedActionData($admin);

        $this->actingAs($admin)
            ->withHeader('X-Idempotency-Key', 'bulk-generate-smoke')
            ->post(route('school-bulk-transactions.generate-invoices', $bulk), [
                'invoice_date' => '2026-04-05',
                'due_date' => '2026-04-20',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('sales_invoices', 2);
    }

    /**
     * @return array{customer: Customer, orderNote: OrderNote, invoice: SalesInvoice, bulk: SchoolBulkTransaction}
     */
    private function seedActionData(User $admin): array
    {
        $level = CustomerLevel::query()->create([
            'code' => 'LV-ACTION',
            'name' => 'Level Action',
        ]);

        $customer = Customer::query()->create([
            'code' => 'CUST-ACTION-001',
            'customer_level_id' => $level->id,
            'name' => 'Customer Action',
            'phone' => '081234567890',
            'city' => 'Malang',
            'address' => 'Jl. Action 1',
        ]);

        CustomerShipLocation::query()->create([
            'customer_id' => $customer->id,
            'school_name' => 'Sekolah Smoke',
            'recipient_phone' => '081234567891',
            'city' => 'Malang',
            'address' => 'Jl. Sekolah Smoke',
            'is_active' => true,
        ]);

        Supplier::query()->create([
            'name' => 'Supplier Smoke',
            'company_name' => 'PT Smoke',
            'phone' => '081234567892',
            'address' => 'Surabaya',
        ]);

        $category = ItemCategory::query()->create([
            'code' => 'CAT-ACTION',
            'name' => 'Kategori Action',
        ]);

        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-ACTION-001',
            'name' => 'Produk Action',
            'unit' => 'pcs',
            'stock' => 50,
            'price_general' => 20000,
            'is_active' => true,
        ]);

        $orderNote = OrderNote::query()->create([
            'note_number' => 'PO-ACTION-0001',
            'note_date' => '2026-04-05',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'city' => $customer->city,
            'address' => $customer->address,
            'created_by_name' => $admin->name,
        ]);

        OrderNoteItem::query()->create([
            'order_note_id' => $orderNote->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 3,
            'notes' => 'Item action',
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-ACTION-0001',
            'customer_id' => $customer->id,
            'order_note_id' => $orderNote->id,
            'invoice_date' => '2026-04-05',
            'due_date' => '2026-04-20',
            'semester_period' => 'S2-2526',
            'subtotal' => 60000,
            'total' => 60000,
            'total_paid' => 0,
            'balance' => 60000,
            'payment_status' => 'unpaid',
            'created_by_user_id' => $admin->id,
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 3,
            'unit_price' => 20000,
            'discount' => 0,
            'line_total' => 60000,
        ]);

        $bulk = SchoolBulkTransaction::query()->create([
            'transaction_number' => 'BLK-ACTION-0001',
            'transaction_date' => '2026-04-05',
            'customer_id' => $customer->id,
            'semester_period' => 'S2-2526',
            'total_locations' => 1,
            'total_items' => 1,
            'notes' => 'Bulk action',
            'created_by_user_id' => $admin->id,
        ]);

        $bulkLocation = SchoolBulkTransactionLocation::query()->create([
            'school_bulk_transaction_id' => $bulk->id,
            'customer_ship_location_id' => CustomerShipLocation::query()->value('id'),
            'school_name' => 'Sekolah Smoke',
            'recipient_name' => 'Penerima Smoke',
            'recipient_phone' => '081234567891',
            'city' => 'Malang',
            'address' => 'Jl. Sekolah Smoke',
            'sort_order' => 0,
        ]);

        SchoolBulkTransactionItem::query()->create([
            'school_bulk_transaction_id' => $bulk->id,
            'school_bulk_transaction_location_id' => $bulkLocation->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => $product->unit,
            'quantity' => 2,
            'unit_price' => 20000,
            'notes' => 'Bulk item',
            'sort_order' => 0,
        ]);

        $invoice->load('items');

        return [
            'customer' => $customer,
            'orderNote' => $orderNote,
            'invoice' => $invoice,
            'bulk' => $bulk,
        ];
    }
}
