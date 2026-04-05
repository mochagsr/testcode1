<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Models\CustomerShipLocation;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\DeliveryTrip;
use App\Models\ItemCategory;
use App\Models\OrderNote;
use App\Models\OrderNoteItem;
use App\Models\OutgoingTransaction;
use App\Models\OutgoingTransactionItem;
use App\Models\Product;
use App\Models\ReceivableLedger;
use App\Models\ReceivablePayment;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SchoolBulkTransaction;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentOutputSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_detail_print_pdf_and_excel_pages_load_without_server_error(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        [
            'customer' => $customer,
            'salesInvoice' => $salesInvoice,
            'salesReturn' => $salesReturn,
            'deliveryNote' => $deliveryNote,
            'orderNote' => $orderNote,
            'deliveryTrip' => $deliveryTrip,
            'outgoingTransaction' => $outgoingTransaction,
            'receivablePayment' => $receivablePayment,
            'supplierPayment' => $supplierPayment,
            'schoolBulkTransaction' => $schoolBulkTransaction,
        ] = $this->seedDocuments($admin);

        $routes = [
            route('sales-invoices.show', $salesInvoice),
            route('sales-invoices.print', $salesInvoice),
            route('sales-invoices.export.pdf', $salesInvoice),
            route('sales-invoices.export.excel', $salesInvoice),
            route('sales-returns.show', $salesReturn),
            route('sales-returns.print', $salesReturn),
            route('sales-returns.export.pdf', $salesReturn),
            route('sales-returns.export.excel', $salesReturn),
            route('delivery-notes.show', $deliveryNote),
            route('delivery-notes.print', $deliveryNote),
            route('delivery-notes.export.pdf', $deliveryNote),
            route('delivery-notes.export.excel', $deliveryNote),
            route('order-notes.show', $orderNote),
            route('order-notes.print', $orderNote),
            route('order-notes.export.pdf', $orderNote),
            route('order-notes.export.excel', $orderNote),
            route('delivery-trips.show', $deliveryTrip),
            route('delivery-trips.print', $deliveryTrip),
            route('delivery-trips.export.pdf', $deliveryTrip),
            route('delivery-trips.export.excel', $deliveryTrip),
            route('outgoing-transactions.show', $outgoingTransaction),
            route('outgoing-transactions.print', $outgoingTransaction),
            route('outgoing-transactions.export.pdf', $outgoingTransaction),
            route('outgoing-transactions.export.excel', $outgoingTransaction),
            route('receivable-payments.show', $receivablePayment),
            route('receivable-payments.print', $receivablePayment),
            route('receivable-payments.export.pdf', $receivablePayment),
            route('receivable-payments.export.excel', $receivablePayment),
            route('supplier-payables.show-payment', $supplierPayment),
            route('supplier-payables.print-payment', $supplierPayment),
            route('supplier-payables.export-payment-pdf', $supplierPayment),
            route('supplier-payables.export-payment-excel', $supplierPayment),
            route('school-bulk-transactions.show', $schoolBulkTransaction),
            route('school-bulk-transactions.print', $schoolBulkTransaction),
            route('school-bulk-transactions.export.pdf', $schoolBulkTransaction),
            route('school-bulk-transactions.export.excel', $schoolBulkTransaction),
            route('receivables.print-customer-bill', $customer),
            route('receivables.export-customer-bill-pdf', $customer),
            route('receivables.export-customer-bill-excel', $customer),
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertOk("Failed asserting GET {$url} returns 200 for document route.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function seedDocuments(User $admin): array
    {
        $level = CustomerLevel::query()->create([
            'code' => 'LV-DOC',
            'name' => 'Level Dokumen',
        ]);

        $customer = Customer::query()->create([
            'code' => 'CUST-DOC-001',
            'customer_level_id' => $level->id,
            'name' => 'Customer Dokumen',
            'phone' => '081234567890',
            'city' => 'Malang',
            'address' => 'Jl. Dokumen 1',
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Supplier Dokumen',
            'company_name' => 'PT Dokumen',
            'phone' => '081234567891',
            'address' => 'Surabaya',
            'outstanding_payable' => 50000,
        ]);

        $category = ItemCategory::query()->create([
            'code' => 'CAT-DOC',
            'name' => 'Kategori Dokumen',
        ]);

        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-DOC-001',
            'name' => 'Produk Dokumen',
            'unit' => 'pcs',
            'stock' => 100,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        $orderNote = OrderNote::query()->create([
            'note_number' => 'PO-DOC-0001',
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
            'quantity' => 5,
            'notes' => 'Item PO',
        ]);

        $salesInvoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-DOC-0001',
            'customer_id' => $customer->id,
            'order_note_id' => $orderNote->id,
            'invoice_date' => '2026-04-05',
            'due_date' => '2026-04-20',
            'semester_period' => 'S2-2526',
            'subtotal' => 75000,
            'total' => 75000,
            'total_paid' => 25000,
            'balance' => 50000,
            'payment_status' => 'unpaid',
            'created_by_user_id' => $admin->id,
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $salesInvoice->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 5,
            'unit_price' => 15000,
            'discount' => 0,
            'line_total' => 75000,
        ]);

        ReceivableLedger::query()->create([
            'customer_id' => $customer->id,
            'sales_invoice_id' => $salesInvoice->id,
            'entry_date' => '2026-04-05',
            'description' => 'Invoice INV-DOC-0001',
            'debit' => 75000,
            'credit' => 0,
            'balance_after' => 75000,
            'period_code' => 'S2-2526',
        ]);

        $salesReturn = SalesReturn::query()->create([
            'return_number' => 'RTR-DOC-0001',
            'customer_id' => $customer->id,
            'return_date' => '2026-04-05',
            'semester_period' => 'S2-2526',
            'total' => 15000,
            'created_by_user_id' => $admin->id,
        ]);

        SalesReturnItem::query()->create([
            'sales_return_id' => $salesReturn->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 15000,
            'line_total' => 15000,
        ]);

        $deliveryNote = DeliveryNote::query()->create([
            'note_number' => 'SJ-DOC-0001',
            'note_date' => '2026-04-05',
            'customer_id' => $customer->id,
            'recipient_name' => 'Penerima Dokumen',
            'recipient_phone' => '081200000000',
            'city' => 'Malang',
            'address' => 'Jl. Penerima 1',
            'created_by_name' => $admin->name,
        ]);

        DeliveryNoteItem::query()->create([
            'delivery_note_id' => $deliveryNote->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'pcs',
            'quantity' => 2,
            'unit_price' => 15000,
            'notes' => 'Item SJ',
        ]);

        $deliveryTrip = DeliveryTrip::query()->create([
            'trip_number' => 'TRP-DOC-0001',
            'trip_date' => '2026-04-05',
            'driver_name' => 'Supir Dokumen',
            'assistant_name' => 'Asisten Dokumen',
            'vehicle_plate' => 'N 1234 DOC',
            'member_count' => 2,
            'fuel_cost' => 100000,
            'toll_cost' => 20000,
            'meal_cost' => 15000,
            'other_cost' => 5000,
            'total_cost' => 140000,
            'created_by_user_id' => $admin->id,
        ]);

        $outgoingTransaction = OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-DOC-0001',
            'transaction_date' => '2026-04-05',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'note_number' => 'NOTA-DOC-0001',
            'total' => 50000,
            'notes' => 'Transaksi keluar dokumen',
            'created_by_user_id' => $admin->id,
        ]);

        OutgoingTransactionItem::query()->create([
            'outgoing_transaction_id' => $outgoingTransaction->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'pcs',
            'quantity' => 4,
            'unit_cost' => 12500,
            'line_total' => 50000,
        ]);

        SupplierLedger::query()->create([
            'supplier_id' => $supplier->id,
            'outgoing_transaction_id' => $outgoingTransaction->id,
            'supplier_payment_id' => null,
            'entry_date' => '2026-04-05',
            'period_code' => 'S2-2526',
            'description' => 'Outgoing TRXK-DOC-0001',
            'debit' => 50000,
            'credit' => 0,
            'balance_after' => 50000,
        ]);

        $receivablePayment = ReceivablePayment::query()->create([
            'payment_number' => 'KWT-DOC-0001',
            'customer_id' => $customer->id,
            'payment_date' => '2026-04-05',
            'customer_address' => $customer->address,
            'amount' => 25000,
            'amount_in_words' => 'dua puluh lima ribu rupiah',
            'customer_signature' => 'Customer',
            'user_signature' => 'Admin',
            'notes' => 'Bayar piutang dokumen',
            'created_by_user_id' => $admin->id,
        ]);

        $supplierPayment = SupplierPayment::query()->create([
            'payment_number' => 'KWTS-DOC-0001',
            'supplier_id' => $supplier->id,
            'payment_date' => '2026-04-05',
            'proof_number' => 'BKT-DOC-001',
            'amount' => 20000,
            'amount_in_words' => 'dua puluh ribu rupiah',
            'supplier_signature' => 'Supplier',
            'user_signature' => 'Admin',
            'notes' => 'Bayar supplier dokumen',
            'created_by_user_id' => $admin->id,
        ]);

        SupplierLedger::query()->create([
            'supplier_id' => $supplier->id,
            'outgoing_transaction_id' => null,
            'supplier_payment_id' => $supplierPayment->id,
            'entry_date' => '2026-04-05',
            'period_code' => 'S2-2526',
            'description' => 'Payment KWTS-DOC-0001',
            'debit' => 0,
            'credit' => 20000,
            'balance_after' => 30000,
        ]);

        $shipLocation = CustomerShipLocation::query()->create([
            'customer_id' => $customer->id,
            'school_name' => 'SD Smoke',
            'recipient_name' => 'TU Smoke',
            'recipient_phone' => '081288888888',
            'city' => 'Malang',
            'address' => 'Jl. Sekolah 1',
            'is_active' => true,
        ]);

        $schoolBulkTransaction = SchoolBulkTransaction::query()->create([
            'transaction_number' => 'BLK-DOC-0001',
            'transaction_date' => '2026-04-05',
            'customer_id' => $customer->id,
            'semester_period' => 'S2-2526',
            'total_locations' => 1,
            'total_items' => 1,
            'notes' => 'Bulk dokumen',
            'created_by_user_id' => $admin->id,
        ]);

        $location = $schoolBulkTransaction->locations()->create([
            'customer_ship_location_id' => $shipLocation->id,
            'school_name' => 'SD Smoke',
            'recipient_name' => 'TU Smoke',
            'recipient_phone' => '081288888888',
            'city' => 'Malang',
            'address' => 'Jl. Sekolah 1',
            'sort_order' => 0,
        ]);

        $schoolBulkTransaction->items()->create([
            'product_id' => $product->id,
            'school_bulk_transaction_location_id' => $location->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => 'pcs',
            'quantity' => 3,
            'unit_price' => 15000,
            'sort_order' => 0,
        ]);

        return compact(
            'customer',
            'salesInvoice',
            'salesReturn',
            'deliveryNote',
            'orderNote',
            'deliveryTrip',
            'outgoingTransaction',
            'receivablePayment',
            'supplierPayment',
            'schoolBulkTransaction'
        );
    }
}
