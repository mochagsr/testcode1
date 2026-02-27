<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ItemCategory;
use App\Models\OrderNote;
use App\Models\OrderNoteItem;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNoteProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_note_progress_is_visible_in_index_and_show_for_open_note(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $customer = Customer::query()->create([
            'code' => 'CUST-ON-001',
            'name' => 'Customer Order Note',
            'city' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-ON-001',
            'name' => 'Kategori ON 1',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-ON-001',
            'name' => 'Produk A',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 10000,
            'is_active' => true,
        ]);

        $note = OrderNote::query()->create([
            'note_number' => 'PO-20260228-0001',
            'note_date' => '2026-02-28',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'city' => $customer->city,
            'created_by_name' => 'Tester',
        ]);

        OrderNoteItem::query()->create([
            'order_note_id' => $note->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => 'Produk A',
            'quantity' => 10,
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-ON-001',
            'customer_id' => $customer->id,
            'order_note_id' => $note->id,
            'invoice_date' => '2026-02-28',
            'semester_period' => 'S2-2526',
            'subtotal' => 40000,
            'total' => 40000,
            'total_paid' => 0,
            'balance' => 40000,
            'payment_status' => 'unpaid',
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => 'Produk A',
            'quantity' => 4,
            'unit_price' => 10000,
            'line_total' => 40000,
            'discount' => 0,
        ]);

        $indexResponse = $this->actingAs($user)->get(route('order-notes.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('PO-20260228-0001');
        $indexResponse->assertSee('40%');
        $indexResponse->assertSee('6');
        $indexResponse->assertSee(__('txn.order_note_status_open'));

        $showResponse = $this->actingAs($user)->get(route('order-notes.show', $note));
        $showResponse->assertOk();
        $showResponse->assertSee('40%');
        $showResponse->assertSee('10');
        $showResponse->assertSee('4');
        $showResponse->assertSee('6');
        $showResponse->assertSee(__('txn.order_note_status_open'));
    }

    public function test_order_note_progress_marks_finished_when_fulfilled(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $customer = Customer::query()->create([
            'code' => 'CUST-ON-002',
            'name' => 'Customer Order Note 2',
            'city' => 'Surabaya',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-ON-002',
            'name' => 'Kategori ON 2',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-ON-002',
            'name' => 'Produk B',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 12000,
            'is_active' => true,
        ]);

        $note = OrderNote::query()->create([
            'note_number' => 'PO-20260228-0002',
            'note_date' => '2026-02-28',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'city' => $customer->city,
            'created_by_name' => 'Tester',
        ]);

        OrderNoteItem::query()->create([
            'order_note_id' => $note->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => 'Produk B',
            'quantity' => 5,
        ]);

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-ON-002',
            'customer_id' => $customer->id,
            'order_note_id' => $note->id,
            'invoice_date' => '2026-02-28',
            'semester_period' => 'S2-2526',
            'subtotal' => 60000,
            'total' => 60000,
            'total_paid' => 0,
            'balance' => 60000,
            'payment_status' => 'unpaid',
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => 'Produk B',
            'quantity' => 5,
            'unit_price' => 12000,
            'line_total' => 60000,
            'discount' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('order-notes.show', $note));
        $response->assertOk();
        $response->assertSee('100%');
        $response->assertSee(__('txn.order_note_status_finished'));
    }
}
