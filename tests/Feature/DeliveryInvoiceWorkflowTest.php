<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\ReceivableLedger;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryInvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_create_invoice_from_delivery_note_without_changing_stock_again(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $customer = Customer::query()->create([
            'code' => 'CUST-ERP-001',
            'name' => 'Customer ERP',
            'city' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-ERP-001',
            'name' => 'Buku',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'BK-ERP-001',
            'name' => 'Buku ERP',
            'unit' => 'exp',
            'stock' => 90,
            'price_general' => 12000,
            'is_active' => true,
        ]);
        $deliveryNote = DeliveryNote::query()->create([
            'note_number' => 'SJ-28042026-0001',
            'note_date' => '2026-04-28',
            'customer_id' => $customer->id,
            'recipient_name' => $customer->name,
            'city' => $customer->city,
            'transaction_type' => 'product',
            'created_by_name' => 'Gudang',
        ]);
        $deliveryItem = DeliveryNoteItem::query()->create([
            'delivery_note_id' => $deliveryNote->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'unit' => $product->unit,
            'quantity' => 10,
        ]);

        $this->actingAs($user)
            ->get(route('sales-invoices.pending-delivery-notes'))
            ->assertOk()
            ->assertSee('SJ-28042026-0001');

        $this->actingAs($user)
            ->post(route('sales-invoices.store-from-delivery-notes'), [
                'customer_id' => $customer->id,
                'invoice_date' => '2026-04-28',
                'semester_period' => 'S2-2526',
                'payment_method' => 'kredit',
                'items' => [
                    [
                        'delivery_note_item_id' => $deliveryItem->id,
                        'quantity' => 10,
                        'unit_price' => 12000,
                        'discount' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $invoice = SalesInvoice::query()->firstOrFail();

        $this->assertSame(90, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('sales_invoice_items', [
            'sales_invoice_id' => $invoice->id,
            'delivery_note_item_id' => $deliveryItem->id,
            'quantity' => 10,
            'line_total' => 120000,
        ]);
        $this->assertDatabaseHas('receivable_ledgers', [
            'customer_id' => $customer->id,
            'sales_invoice_id' => $invoice->id,
            'debit' => 120000,
        ]);
        $this->assertSame(120000.0, (float) ReceivableLedger::query()->where('sales_invoice_id', $invoice->id)->sum('debit'));
    }
}
