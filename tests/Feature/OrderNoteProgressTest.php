<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\ItemCategory;
use App\Models\OrderNote;
use App\Models\OrderNoteItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $deliveryNote = DeliveryNote::query()->create([
            'note_number' => 'SJ-ON-001',
            'note_date' => '2026-02-28',
            'customer_id' => $customer->id,
            'order_note_id' => $note->id,
            'recipient_name' => $customer->name,
            'city' => $customer->city,
            'created_by_name' => 'Tester',
            'is_canceled' => false,
        ]);

        DeliveryNoteItem::query()->create([
            'delivery_note_id' => $deliveryNote->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => 'Produk A',
            'quantity' => 4,
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

        $deliveryNote = DeliveryNote::query()->create([
            'note_number' => 'SJ-ON-002',
            'note_date' => '2026-02-28',
            'customer_id' => $customer->id,
            'order_note_id' => $note->id,
            'recipient_name' => $customer->name,
            'city' => $customer->city,
            'created_by_name' => 'Tester',
            'is_canceled' => false,
        ]);

        DeliveryNoteItem::query()->create([
            'delivery_note_id' => $deliveryNote->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => 'Produk B',
            'quantity' => 5,
        ]);

        $response = $this->actingAs($user)->get(route('order-notes.show', $note));
        $response->assertOk();
        $response->assertSee('100%');
        $response->assertSee(__('txn.order_note_status_finished'));
    }

    public function test_order_note_show_displays_delivery_breakdown_per_item_and_invoice(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $customer = Customer::query()->create([
            'code' => 'CUST-ON-004',
            'name' => 'Customer Order Note 4',
            'city' => 'Sidoarjo',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-ON-004',
            'name' => 'Kategori ON 4',
        ]);
        $productA = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-ON-004-A',
            'name' => 'Produk E',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 10000,
            'is_active' => true,
        ]);
        $productB = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-ON-004-B',
            'name' => 'Produk F',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 11000,
            'is_active' => true,
        ]);

        $note = OrderNote::query()->create([
            'note_number' => 'PO-20260228-0004',
            'note_date' => '2026-02-28',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'city' => $customer->city,
            'created_by_name' => 'Tester',
        ]);

        $itemA = OrderNoteItem::query()->create([
            'order_note_id' => $note->id,
            'product_id' => $productA->id,
            'product_code' => $productA->code,
            'product_name' => $productA->name,
            'quantity' => 12,
        ]);
        OrderNoteItem::query()->create([
            'order_note_id' => $note->id,
            'product_id' => $productB->id,
            'product_code' => $productB->code,
            'product_name' => $productB->name,
            'quantity' => 8,
        ]);

        $deliveryNote = DeliveryNote::query()->create([
            'note_number' => 'SJ-ON-004',
            'note_date' => '2026-02-28',
            'customer_id' => $customer->id,
            'order_note_id' => $note->id,
            'recipient_name' => $customer->name,
            'city' => $customer->city,
            'created_by_name' => 'Tester',
            'is_canceled' => false,
        ]);

        DeliveryNoteItem::query()->create([
            'delivery_note_id' => $deliveryNote->id,
            'order_note_item_id' => $itemA->id,
            'product_id' => $productA->id,
            'product_code' => $productA->code,
            'product_name' => $productA->name,
            'quantity' => 5,
        ]);

        $response = $this->actingAs($user)->get(route('order-notes.show', $note));
        $response->assertOk();
        $response->assertSee(__('txn.order_note_delivery_history_title'));
        $response->assertSee('SJ-ON-004');
        $response->assertSee('Produk E');
        $response->assertSee('Produk F');
        $response->assertSee(__('txn.order_note_status_partial'));
        $response->assertSee(__('txn.order_note_status_not_delivered'));
        $response->assertSee(__('txn.order_note_no_delivery_history'));
    }

    public function test_order_note_print_displays_delivery_breakdown_per_item_and_invoice(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $customer = Customer::query()->create([
            'code' => 'CUST-ON-005',
            'name' => 'Customer Order Note 5',
            'city' => 'Sidoarjo',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-ON-005',
            'name' => 'Kategori ON 5',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-ON-005',
            'name' => 'Produk G',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 10000,
            'is_active' => true,
        ]);

        $note = OrderNote::query()->create([
            'note_number' => 'PO-20260228-0005',
            'note_date' => '2026-02-28',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'city' => $customer->city,
            'created_by_name' => 'Tester',
        ]);

        $item = OrderNoteItem::query()->create([
            'order_note_id' => $note->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 10,
        ]);

        $deliveryNote = DeliveryNote::query()->create([
            'note_number' => 'SJ-ON-005',
            'note_date' => '2026-02-28',
            'customer_id' => $customer->id,
            'order_note_id' => $note->id,
            'recipient_name' => $customer->name,
            'city' => $customer->city,
            'created_by_name' => 'Tester',
            'is_canceled' => false,
        ]);

        DeliveryNoteItem::query()->create([
            'delivery_note_id' => $deliveryNote->id,
            'order_note_item_id' => $item->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 6,
        ]);

        $response = $this->actingAs($user)->get(route('order-notes.print', $note));
        $response->assertOk();
        $response->assertSee(__('txn.order_note_delivery_history_title'));
        $response->assertSee('SJ-ON-005');
        $response->assertSee('6');
        $response->assertSee('4');
    }

    public function test_order_note_lookup_returns_open_note_with_remaining_items(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $customer = Customer::query()->create([
            'code' => 'CUST-ON-003',
            'name' => 'Angga',
            'city' => 'Sidoarjo',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-ON-003',
            'name' => 'Kategori ON 3',
        ]);
        $productA = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-ON-003-A',
            'name' => 'Produk C',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 10000,
            'is_active' => true,
        ]);
        $productB = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-ON-003-B',
            'name' => 'Produk D',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 12000,
            'is_active' => true,
        ]);

        $note = OrderNote::query()->create([
            'note_number' => 'PO-20260228-0003',
            'note_date' => '2026-02-28',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'city' => $customer->city,
            'created_by_name' => 'Tester',
        ]);

        $itemA = OrderNoteItem::query()->create([
            'order_note_id' => $note->id,
            'product_id' => $productA->id,
            'product_code' => $productA->code,
            'product_name' => $productA->name,
            'quantity' => 15,
        ]);
        $itemB = OrderNoteItem::query()->create([
            'order_note_id' => $note->id,
            'product_id' => $productB->id,
            'product_code' => $productB->code,
            'product_name' => $productB->name,
            'quantity' => 10,
        ]);

        $deliveryNote = DeliveryNote::query()->create([
            'note_number' => 'SJ-ON-003',
            'note_date' => '2026-02-28',
            'customer_id' => $customer->id,
            'order_note_id' => $note->id,
            'recipient_name' => $customer->name,
            'city' => $customer->city,
            'created_by_name' => 'Tester',
            'is_canceled' => false,
        ]);

        DeliveryNoteItem::query()->create([
            'delivery_note_id' => $deliveryNote->id,
            'order_note_item_id' => $itemA->id,
            'product_id' => $productA->id,
            'product_code' => $productA->code,
            'product_name' => $productA->name,
            'quantity' => 10,
        ]);
        DeliveryNoteItem::query()->create([
            'delivery_note_id' => $deliveryNote->id,
            'order_note_item_id' => $itemB->id,
            'product_id' => $productB->id,
            'product_code' => $productB->code,
            'product_name' => $productB->name,
            'quantity' => 5,
        ]);

        $response = $this->actingAs($user)->getJson(route('api.order-notes.lookup', [
            'customer_id' => $customer->id,
            'per_page' => 200,
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.note_number', 'PO-20260228-0003');
        $response->assertJsonPath('data.0.remaining_total', 10);
        $response->assertJsonPath('data.0.fulfilled_total', 15);
        $response->assertJsonPath('data.0.ordered_total', 25);
    }

    public function test_order_note_store_requires_registered_customer_and_products(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-ON-REQ',
            'name' => 'Kategori ON Req',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-ON-REQ',
            'name' => 'Produk Req',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 10000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('order-notes.store'), [
            'note_date' => '2026-04-05',
            'customer_id' => '',
            'customer_name' => 'Customer Manual',
            'items' => [
                [
                    'product_id' => '',
                    'product_name' => 'Barang Manual',
                    'quantity' => 3,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['customer_id', 'items.0.product_id']);
        $this->assertSame(0, OrderNote::query()->count());
        $this->assertSame(0, OrderNoteItem::query()->count());

        $customer = Customer::query()->create([
            'customer_level_id' => DB::table('customer_levels')->insertGetId([
                'code' => 'LVL-ON-REQ',
                'name' => 'Level ON Req',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'code' => 'CUST-ON-REQ',
            'name' => 'Customer Terdaftar',
            'phone' => '08123',
            'city' => 'Malang',
            'address' => 'Alamat',
        ]);

        $ok = $this->actingAs($user)->post(route('order-notes.store'), [
            'note_date' => '2026-04-05',
            'customer_id' => $customer->id,
            'customer_name' => 'Label yang diketik user',
            'customer_phone' => '',
            'city' => '',
            'address' => '',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => 'Label produk bebas',
                    'quantity' => 3,
                ],
            ],
        ]);

        $ok->assertRedirect();
        $note = OrderNote::query()->latest('id')->first();
        $this->assertNotNull($note);
        $this->assertSame($customer->id, (int) $note->customer_id);
        $this->assertSame($customer->name, (string) $note->customer_name);
        $this->assertSame($customer->city, (string) $note->city);
        $this->assertSame($customer->address, (string) $note->address);
        $this->assertSame($customer->phone, (string) $note->customer_phone);

        $item = $note->items()->first();
        $this->assertNotNull($item);
        $this->assertSame($product->id, (int) $item->product_id);
        $this->assertSame($product->name, (string) $item->product_name);
        $this->assertSame($product->code, (string) $item->product_code);
    }
}
