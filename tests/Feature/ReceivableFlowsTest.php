<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\InvoicePayment;
use App\Models\ItemCategory;
use App\Models\OrderNote;
use App\Models\OrderNoteItem;
use App\Models\Product;
use App\Models\ReceivableLedger;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
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
        $response->assertSee('Customer Alpha');
        $response->assertSee('PIUTANG');
        $response->assertSee('GRAND TOTAL PIUTANG');
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
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);
    }

    public function test_receivable_payment_create_prefills_customer_invoice_and_return_path(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'code' => 'CUST-003',
            'name' => 'Customer Gamma',
            'city' => 'Malang',
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-TEST-003',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-11',
            'semester_period' => 'S1-2026',
            'subtotal' => 90000,
            'total' => 90000,
            'total_paid' => 10000,
            'balance' => 80000,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->get(route('receivable-payments.create', [
            'customer_id' => $customer->id,
            'amount' => 80000,
            'payment_date' => '2026-02-11',
            'preferred_invoice_id' => $invoice->id,
            'return_to' => '/receivables?customer_id='.$customer->id,
        ]));

        $response->assertOk();
        $response->assertSee('INV-TEST-003');
        $response->assertSee('name="return_to"', false);
        $response->assertSee('/receivables?customer_id='.$customer->id, false);
        $response->assertSee('name="preferred_invoice_id"', false);
    }

    public function test_customer_writeoff_requires_admin_and_updates_receivable_records(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUST-004',
            'name' => 'Customer Delta',
            'city' => 'Surabaya',
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-TEST-004',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-11',
            'semester_period' => 'S1-2026',
            'subtotal' => 100000,
            'total' => 100000,
            'total_paid' => 0,
            'balance' => 100000,
            'payment_status' => 'unpaid',
        ]);

        $nonAdmin = User::factory()->create(['role' => 'user']);
        $this->actingAs($nonAdmin)
            ->post(route('receivables.customer-writeoff', $customer), [
                'amount' => 10000,
                'payment_date' => '2026-02-11',
                'customer_id' => $customer->id,
            ])
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->post(route('receivables.customer-writeoff', $customer), [
                'amount' => 30000,
                'payment_date' => '2026-02-11',
                'customer_id' => $customer->id,
            ])
            ->assertRedirect(route('receivables.index', [
                'search' => null,
                'semester' => null,
                'customer_id' => $customer->id,
            ]));

        $invoice->refresh();
        $this->assertSame(70000.0, (float) $invoice->balance);
        $this->assertSame(30000.0, (float) $invoice->total_paid);

        $this->assertDatabaseHas('invoice_payments', [
            'sales_invoice_id' => $invoice->id,
            'method' => 'writeoff',
            'amount' => 30000,
        ]);

        $this->assertDatabaseHas('receivable_ledgers', [
            'customer_id' => $customer->id,
            'sales_invoice_id' => $invoice->id,
            'credit' => 30000,
        ]);

        $this->assertGreaterThanOrEqual(1, InvoicePayment::query()->where('sales_invoice_id', $invoice->id)->count());
        $this->assertGreaterThanOrEqual(1, ReceivableLedger::query()->where('customer_id', $customer->id)->count());
    }

    public function test_customer_discount_appears_in_bill_statement_and_updates_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::query()->create([
            'code' => 'CUST-DISC-001',
            'name' => 'Customer Discount',
            'city' => 'Malang',
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-DISC-001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-22',
            'semester_period' => 'S2-2526',
            'subtotal' => 200000,
            'total' => 200000,
            'total_paid' => 0,
            'balance' => 200000,
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($admin)
            ->post(route('receivables.customer-discount', $customer), [
                'amount' => 50000,
                'payment_date' => '2026-02-23',
                'customer_id' => $customer->id,
            ])
            ->assertRedirect(route('receivables.index', [
                'search' => null,
                'semester' => null,
                'customer_id' => $customer->id,
            ]));

        $invoice->refresh();
        $this->assertSame(150000.0, (float) $invoice->balance);
        $this->assertSame(50000.0, (float) $invoice->total_paid);

        $this->assertDatabaseHas('invoice_payments', [
            'sales_invoice_id' => $invoice->id,
            'method' => 'discount',
            'amount' => 50000,
        ]);
        $this->assertDatabaseHas('receivable_ledgers', [
            'sales_invoice_id' => $invoice->id,
            'credit' => 50000,
        ]);

        $response = $this->actingAs($admin)->get(route('receivables.index', [
            'customer_id' => $customer->id,
        ]));
        $response->assertOk();
        $response->assertSee('INV-DISC-001 - ' . __('receivable.method_discount'));
    }

    public function test_admin_can_cancel_sales_invoice_and_stock_is_reverted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::query()->create([
            'code' => 'CUST-005',
            'name' => 'Customer Epsilon',
            'city' => 'Malang',
            'outstanding_receivable' => 120000,
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-01',
            'name' => 'Kategori Uji',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-001',
            'name' => 'Produk Uji',
            'unit' => 'pcs',
            'stock' => 5,
            'price_general' => 120000,
            'is_active' => true,
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-TEST-005',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-11',
            'semester_period' => 'S1-2026',
            'subtotal' => 120000,
            'total' => 120000,
            'total_paid' => 0,
            'balance' => 120000,
            'payment_status' => 'unpaid',
        ]);
        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 60000,
            'discount' => 0,
            'line_total' => 120000,
        ]);

        $response = $this->actingAs($admin)->post(route('sales-invoices.cancel', $invoice), [
            'cancel_reason' => 'Salah input',
        ]);

        $response->assertRedirect(route('sales-invoices.show', $invoice));

        $invoice->refresh();
        $product->refresh();

        $this->assertTrue((bool) $invoice->is_canceled);
        $this->assertSame(0.0, (float) $invoice->balance);
        $this->assertSame(7.0, (float) $product->stock);

        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'reference_type' => SalesInvoice::class,
            'reference_id' => $invoice->id,
            'mutation_type' => 'in',
            'quantity' => 2,
        ]);
    }

    public function test_admin_can_edit_invoice_items_and_stock_is_rebalanced(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::query()->create([
            'code' => 'CUST-005X',
            'name' => 'Customer Edit',
            'city' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-02',
            'name' => 'Kategori Edit',
        ]);
        $productA = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-A',
            'name' => 'Produk A',
            'unit' => 'pcs',
            'stock' => 10,
            'price_general' => 10000,
            'is_active' => true,
        ]);
        $productB = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-B',
            'name' => 'Produk B',
            'unit' => 'pcs',
            'stock' => 20,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-EDIT-001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-11',
            'semester_period' => 'S1-2026',
            'subtotal' => 20000,
            'total' => 20000,
            'total_paid' => 0,
            'balance' => 20000,
            'payment_status' => 'unpaid',
        ]);
        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $productA->id,
            'product_code' => $productA->code,
            'product_name' => $productA->name,
            'quantity' => 2,
            'unit_price' => 10000,
            'discount' => 0,
            'line_total' => 20000,
        ]);

        $response = $this->actingAs($admin)->put(route('sales-invoices.admin-update', $invoice), [
            'invoice_date' => '2026-02-11',
            'due_date' => null,
            'semester_period' => 'S1-2026',
            'notes' => 'Admin edit item',
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'discount' => 0,
                ],
                [
                    'product_id' => $productB->id,
                    'quantity' => 3,
                    'unit_price' => 15000,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales-invoices.show', $invoice));

        $invoice->refresh();
        $productA->refresh();
        $productB->refresh();

        $this->assertSame(55000.0, (float) $invoice->total);
        $this->assertSame(55000.0, (float) $invoice->balance);
        $this->assertSame(11.0, (float) $productA->stock);
        $this->assertSame(17.0, (float) $productB->stock);
        $this->assertCount(2, $invoice->items()->get());
    }

    public function test_admin_update_sales_invoice_ignores_ship_fields_from_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::query()->create([
            'code' => 'CUST-LEGACY-01',
            'name' => 'Customer Legacy',
            'city' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-LEGACY',
            'name' => 'Kategori Legacy',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-LEGACY',
            'name' => 'Produk Legacy',
            'unit' => 'pcs',
            'stock' => 15,
            'price_general' => 10000,
            'is_active' => true,
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-LEGACY-001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-11',
            'semester_period' => 'S1-2026',
            'subtotal' => 20000,
            'total' => 20000,
            'total_paid' => 0,
            'balance' => 20000,
            'payment_status' => 'unpaid',
            'ship_to_name' => 'SD Legacy',
            'ship_to_phone' => '08123456789',
            'ship_to_city' => 'Kota Legacy',
            'ship_to_address' => 'Jl Legacy No 1',
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

        $response = $this->actingAs($admin)->put(route('sales-invoices.admin-update', $invoice), [
            'invoice_date' => '2026-02-11',
            'due_date' => null,
            'semester_period' => 'S1-2026',
            'notes' => 'Admin edit legacy invoice',
            'ship_to_name' => 'Injected Name',
            'ship_to_phone' => '0800000000',
            'ship_to_city' => 'Injected City',
            'ship_to_address' => 'Injected Address',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales-invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('SD Legacy', (string) $invoice->ship_to_name);
        $this->assertSame('08123456789', (string) $invoice->ship_to_phone);
        $this->assertSame('Kota Legacy', (string) $invoice->ship_to_city);
        $this->assertSame('Jl Legacy No 1', (string) $invoice->ship_to_address);
        $this->assertSame(10000.0, (float) $invoice->total);
        $this->assertSame(10000.0, (float) $invoice->balance);
    }

    public function test_admin_update_sales_invoice_can_switch_payment_method_between_cash_and_credit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::query()->create([
            'code' => 'CUST-INV-PM-001',
            'name' => 'Customer Metode Bayar',
            'city' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-INV-PM-001',
            'name' => 'Kategori Metode Bayar',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-INV-PM-001',
            'name' => 'Produk Metode Bayar',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 100000,
            'is_active' => true,
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-PM-001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-28',
            'semester_period' => 'S2-2526',
            'subtotal' => 100000,
            'total' => 100000,
            'total_paid' => 0,
            'balance' => 100000,
            'payment_status' => 'unpaid',
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 100000,
            'discount' => 0,
            'line_total' => 100000,
        ]);

        $this->actingAs($admin)->put(route('sales-invoices.admin-update', $invoice), [
            'invoice_date' => '2026-02-28',
            'due_date' => null,
            'semester_period' => 'S2-2526',
            'payment_method' => 'tunai',
            'notes' => 'ubah jadi tunai',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100000,
                    'discount' => 0,
                ],
            ],
        ])->assertRedirect(route('sales-invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(0, (int) round((float) $invoice->balance));
        $this->assertSame('paid', (string) $invoice->payment_status);
        $this->assertSame(100000, (int) round((float) $invoice->total_paid));
        $this->assertDatabaseHas('invoice_payments', [
            'sales_invoice_id' => $invoice->id,
            'method' => 'cash',
            'amount' => 100000,
        ]);

        $this->actingAs($admin)->put(route('sales-invoices.admin-update', $invoice), [
            'invoice_date' => '2026-02-28',
            'due_date' => null,
            'semester_period' => 'S2-2526',
            'payment_method' => 'kredit',
            'notes' => 'ubah jadi kredit',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 100000,
                    'discount' => 0,
                ],
            ],
        ])->assertRedirect(route('sales-invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(100000, (int) round((float) $invoice->balance));
        $this->assertSame('unpaid', (string) $invoice->payment_status);
        $this->assertSame(0, (int) round((float) $invoice->total_paid));
    }

    public function test_admin_can_edit_sales_return_items_and_stock_is_rebalanced(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::query()->create([
            'code' => 'CUST-RET-01',
            'name' => 'Customer Return',
            'city' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-RET',
            'name' => 'Kategori Retur',
        ]);
        $productA = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-RA',
            'name' => 'Produk Retur A',
            'unit' => 'pcs',
            'stock' => 12,
            'price_general' => 10000,
            'is_active' => true,
        ]);
        $productB = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-RB',
            'name' => 'Produk Retur B',
            'unit' => 'pcs',
            'stock' => 20,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $salesReturn = SalesReturn::query()->create([
            'return_number' => 'RTR-EDIT-001',
            'customer_id' => $customer->id,
            'return_date' => '2026-02-11',
            'semester_period' => 'S1-2026',
            'total' => 20000,
        ]);
        SalesReturnItem::query()->create([
            'sales_return_id' => $salesReturn->id,
            'product_id' => $productA->id,
            'product_code' => $productA->code,
            'product_name' => $productA->name,
            'quantity' => 2,
            'unit_price' => 10000,
            'line_total' => 20000,
        ]);

        $response = $this->actingAs($admin)->put(route('sales-returns.admin-update', $salesReturn), [
            'return_date' => '2026-02-11',
            'semester_period' => 'S1-2026',
            'reason' => 'Admin edit return',
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
                [
                    'product_id' => $productB->id,
                    'quantity' => 3,
                    'unit_price' => 15000,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales-returns.show', $salesReturn));

        $salesReturn->refresh();
        $productA->refresh();
        $productB->refresh();

        $this->assertSame(55000.0, (float) $salesReturn->total);
        $this->assertSame(11.0, (float) $productA->stock);
        $this->assertSame(23.0, (float) $productB->stock);
        $this->assertCount(2, $salesReturn->items()->get());
    }

    public function test_admin_can_edit_delivery_note_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-DN',
            'name' => 'Kategori DN',
        ]);
        $productA = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-DA',
            'name' => 'Produk DN A',
            'unit' => 'pcs',
            'stock' => 10,
            'price_general' => 12000,
            'is_active' => true,
        ]);
        $productB = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-DB',
            'name' => 'Produk DN B',
            'unit' => 'exp',
            'stock' => 8,
            'price_general' => 22000,
            'is_active' => true,
        ]);

        $note = DeliveryNote::query()->create([
            'note_number' => 'SJ-EDIT-001',
            'note_date' => '2026-02-11',
            'recipient_name' => 'Penerima A',
            'city' => 'Malang',
            'created_by_name' => 'Admin',
        ]);
        DeliveryNoteItem::query()->create([
            'delivery_note_id' => $note->id,
            'product_id' => $productA->id,
            'product_code' => $productA->code,
            'product_name' => $productA->name,
            'unit' => 'pcs',
            'quantity' => 1,
            'unit_price' => 12000,
        ]);

        $response = $this->actingAs($admin)->put(route('delivery-notes.admin-update', $note), [
            'note_date' => '2026-02-11',
            'recipient_name' => 'Penerima B',
            'recipient_phone' => '08123',
            'city' => 'Surabaya',
            'address' => 'Jl. Test',
            'notes' => 'Admin edit delivery',
            'items' => [
                [
                    'product_id' => $productA->id,
                    'product_name' => $productA->name,
                    'unit' => 'pcs',
                    'quantity' => 2,
                    'unit_price' => 12000,
                    'notes' => 'Baris 1',
                ],
                [
                    'product_id' => $productB->id,
                    'product_name' => $productB->name,
                    'unit' => 'exp',
                    'quantity' => 1,
                    'unit_price' => 22000,
                    'notes' => 'Baris 2',
                ],
            ],
        ]);

        $response->assertRedirect(route('delivery-notes.show', $note));
        $note->refresh();
        $this->assertSame('Penerima B', (string) $note->recipient_name);
        $this->assertCount(2, $note->items()->get());
    }

    public function test_admin_can_edit_order_note_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-ON',
            'name' => 'Kategori ON',
        ]);
        $productA = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-OA',
            'name' => 'Produk ON A',
            'unit' => 'pcs',
            'stock' => 10,
            'price_general' => 5000,
            'is_active' => true,
        ]);
        $productB = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-OB',
            'name' => 'Produk ON B',
            'unit' => 'pcs',
            'stock' => 15,
            'price_general' => 7000,
            'is_active' => true,
        ]);

        $note = OrderNote::query()->create([
            'note_number' => 'PO-EDIT-001',
            'note_date' => '2026-02-11',
            'customer_name' => 'Customer PO',
            'city' => 'Malang',
            'created_by_name' => 'Admin',
        ]);
        OrderNoteItem::query()->create([
            'order_note_id' => $note->id,
            'product_id' => $productA->id,
            'product_code' => $productA->code,
            'product_name' => $productA->name,
            'quantity' => 1,
            'notes' => 'Awal',
        ]);

        $response = $this->actingAs($admin)->put(route('order-notes.admin-update', $note), [
            'note_date' => '2026-02-11',
            'customer_name' => 'Customer PO Updated',
            'customer_phone' => '08999',
            'city' => 'Surabaya',
            'notes' => 'Admin edit order note',
            'items' => [
                [
                    'product_id' => $productA->id,
                    'product_name' => $productA->name,
                    'quantity' => 2,
                    'notes' => 'Baris 1',
                ],
                [
                    'product_id' => $productB->id,
                    'product_name' => $productB->name,
                    'quantity' => 3,
                    'notes' => 'Baris 2',
                ],
            ],
        ]);

        $response->assertRedirect(route('order-notes.show', $note));
        $note->refresh();
        $this->assertSame('Customer PO Updated', (string) $note->customer_name);
        $this->assertCount(2, $note->items()->get());
    }

    public function test_print_customer_bill_shows_semester_breakdown_and_total(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'code' => 'CUST-006',
            'name' => 'Customer Zeta',
            'city' => 'Malang',
            'address' => 'Jl. Mawar No. 10',
        ]);

        SalesInvoice::query()->create([
            'invoice_number' => 'INV-TEST-101',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-11',
            'semester_period' => 'S1-2026',
            'subtotal' => 50000,
            'total' => 50000,
            'total_paid' => 10000,
            'balance' => 40000,
            'payment_status' => 'unpaid',
        ]);

        SalesInvoice::query()->create([
            'invoice_number' => 'INV-TEST-102',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-08-11',
            'semester_period' => 'S2-2026',
            'subtotal' => 90000,
            'total' => 90000,
            'total_paid' => 30000,
            'balance' => 60000,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->get(route('receivables.print-customer-bill', [
            'customer' => $customer->id,
        ]));

        $response->assertOk();
        $response->assertSee(__('receivable.customer_bill_title'));
        $response->assertSee('Customer Zeta');
        $response->assertSee(__('receivable.bill_opening_balance'));
        $response->assertSee('100.000');
    }

    public function test_export_customer_bill_pdf_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'code' => 'CUST-007',
            'name' => 'Customer Eta',
            'city' => 'Surabaya',
        ]);

        SalesInvoice::query()->create([
            'invoice_number' => 'INV-TEST-201',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-10',
            'semester_period' => 'S1-2026',
            'subtotal' => 75000,
            'total' => 75000,
            'total_paid' => 25000,
            'balance' => 50000,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->get(route('receivables.export-customer-bill-pdf', [
            'customer' => $customer->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_customer_bill_excel_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'code' => 'CUST-008',
            'name' => 'Customer Theta',
            'city' => 'Malang',
        ]);

        SalesInvoice::query()->create([
            'invoice_number' => 'INV-TEST-301',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-04-10',
            'semester_period' => 'S1-2026',
            'subtotal' => 80000,
            'total' => 80000,
            'total_paid' => 20000,
            'balance' => 60000,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->get(route('receivables.export-customer-bill-excel', [
            'customer' => $customer->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);
    }

    public function test_sales_invoice_create_page_hides_school_distribution_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('sales-invoices.create'));

        $response->assertOk();
        $response->assertDontSee('ship-location-search', false);
        $response->assertDontSee('ship-to-name', false);
        $response->assertDontSee('ship-to-phone', false);
        $response->assertDontSee('ship-to-city', false);
        $response->assertDontSee('ship-to-address', false);
    }

    public function test_sales_invoice_store_ignores_ship_fields_for_regular_transaction(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'code' => 'CUST-SALES-001',
            'name' => 'Customer Sales',
            'city' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-SALES',
            'name' => 'Kategori Sales',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-SALES-001',
            'name' => 'Produk Sales',
            'unit' => 'exp',
            'stock' => 25,
            'price_general' => 12000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('sales-invoices.store'), [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-02-23',
            'due_date' => '2026-03-01',
            'semester_period' => 'S2-2526',
            'payment_method' => 'kredit',
            'notes' => 'uji customer-only',
            'ship_location_id' => 99999,
            'ship_to_name' => 'Should Not Save',
            'ship_to_phone' => '0800000000',
            'ship_to_city' => 'Should Not Save',
            'ship_to_address' => 'Should Not Save',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 12000,
                    'discount' => 0,
                ],
            ],
        ]);

        $invoice = SalesInvoice::query()->first();
        $this->assertNotNull($invoice);
        $response->assertRedirect(route('sales-invoices.show', $invoice));

        $invoice->refresh();
        $this->assertNull($invoice->customer_ship_location_id);
        $this->assertNull($invoice->ship_to_name);
        $this->assertNull($invoice->ship_to_phone);
        $this->assertNull($invoice->ship_to_city);
        $this->assertNull($invoice->ship_to_address);
    }
}
