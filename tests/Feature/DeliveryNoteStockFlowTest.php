<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryNoteStockFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_delivery_note_decrements_stock_and_creates_stock_mutation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-DN-STK',
            'name' => 'Kategori DN Stock',
        ]);
        $customer = Customer::query()->create([
            'code' => 'CUST-DN-01',
            'name' => 'Customer DN 01',
            'city' => 'Malang',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-DN-01',
            'name' => 'Produk DN 01',
            'unit' => 'exp',
            'stock' => 10,
            'price_general' => 5000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('delivery-notes.store'), [
            'note_date' => '2026-03-01',
            'customer_id' => $customer->id,
            'recipient_name' => 'Penerima Test',
            'recipient_phone' => '08123',
            'city' => 'Malang',
            'address' => 'Jl. Test',
            'notes' => 'Catatan test',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => 'exp',
                    'quantity' => 3,
                ],
            ],
        ]);

        $note = DeliveryNote::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('delivery-notes.show', $note));

        $product->refresh();
        $this->assertSame(7, (int) $product->stock);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'reference_type' => DeliveryNote::class,
            'reference_id' => $note->id,
            'mutation_type' => 'out',
            'quantity' => 3,
        ]);
    }

    public function test_store_delivery_note_maps_manual_product_label_and_decrements_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-DN-MAN',
            'name' => 'Kategori DN Manual',
        ]);
        $customer = Customer::query()->create([
            'code' => 'CUST-DN-02',
            'name' => 'Customer DN 02',
            'city' => 'Sidoarjo',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-DN-MAN',
            'name' => 'Produk DN Manual',
            'unit' => 'pcs',
            'stock' => 10,
            'price_general' => 12000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('delivery-notes.store'), [
            'note_date' => '2026-03-01',
            'customer_id' => $customer->id,
            'recipient_name' => 'Penerima Manual',
            'city' => 'Sidoarjo',
            'items' => [
                [
                    'product_name' => $product->code . ' - ' . $product->name,
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit' => 'pcs',
                ],
            ],
        ]);

        $note = DeliveryNote::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('delivery-notes.show', $note));

        $product->refresh();
        $this->assertSame(8, (int) $product->stock);
        $this->assertDatabaseHas('delivery_note_items', [
            'delivery_note_id' => $note->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
        ]);
    }

    public function test_cancel_delivery_note_restores_stock_and_creates_in_mutation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-DN-CAN',
            'name' => 'Kategori DN Cancel',
        ]);
        $customer = Customer::query()->create([
            'code' => 'CUST-DN-03',
            'name' => 'Customer DN 03',
            'city' => 'Malang',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-DN-CAN',
            'name' => 'Produk DN Cancel',
            'unit' => 'exp',
            'stock' => 10,
            'price_general' => 7000,
            'is_active' => true,
        ]);

        $storeResponse = $this->actingAs($admin)->post(route('delivery-notes.store'), [
            'note_date' => '2026-03-01',
            'customer_id' => $customer->id,
            'recipient_name' => 'Penerima Cancel',
            'city' => 'Malang',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => 'exp',
                    'quantity' => 4,
                ],
            ],
        ]);

        $note = DeliveryNote::query()->latest('id')->firstOrFail();
        $storeResponse->assertRedirect(route('delivery-notes.show', $note));

        $product->refresh();
        $this->assertSame(6, (int) $product->stock);

        $cancelResponse = $this->actingAs($admin)->post(route('delivery-notes.cancel', $note), [
            'cancel_reason' => 'Salah input',
        ]);

        $cancelResponse->assertRedirect(route('delivery-notes.show', $note));
        $product->refresh();
        $this->assertSame(10, (int) $product->stock);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'reference_type' => DeliveryNote::class,
            'reference_id' => $note->id,
            'mutation_type' => 'in',
            'quantity' => 4,
        ]);
    }

    public function test_store_delivery_note_requires_registered_customer_and_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('delivery-notes.create'))
            ->post(route('delivery-notes.store'), [
                'note_date' => '2026-03-01',
                'customer_id' => '',
                'items' => [
                    [
                        'product_id' => '',
                        'product_name' => 'Produk Manual',
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertRedirect(route('delivery-notes.create'));
        $response->assertSessionHasErrors(['customer_id', 'items.0.product_id']);
        $this->assertSame(0, DeliveryNote::query()->count());
    }
}
