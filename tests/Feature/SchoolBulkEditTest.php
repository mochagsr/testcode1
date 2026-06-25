<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\SchoolBulkTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolBulkEditTest extends TestCase
{
    use RefreshDatabase;

    private function makeDraft(User $user, Customer $customer, Product $product): SchoolBulkTransaction
    {
        $transaction = SchoolBulkTransaction::query()->create([
            'transaction_number' => 'BLK-EDIT-0001',
            'transaction_date' => '2026-05-07',
            'customer_id' => $customer->id,
            'semester_period' => 'S2-2526',
            'total_locations' => 1,
            'total_items' => 1,
            'created_by_user_id' => $user->id,
        ]);
        $location = $transaction->locations()->create([
            'school_name' => 'SDN Lama',
            'city' => 'Malang',
            'sort_order' => 0,
        ]);
        $transaction->items()->create([
            'school_bulk_transaction_location_id' => $location->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'exp',
            'quantity' => 3,
            'unit_price' => 5000,
            'sort_order' => 0,
        ]);

        return $transaction;
    }

    private function product(): Product
    {
        $category = ItemCategory::query()->firstOrCreate(['code' => 'CAT-EDIT'], ['name' => 'Kategori Edit']);

        return Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'P-EDIT',
            'name' => 'Buku Edit',
            'unit' => 'exp',
            'stock' => 100,
            'price_agent' => 4000,
            'price_sales' => 4500,
            'price_general' => 5000,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_edit_and_update_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $customer = Customer::query()->create(['code' => 'CUS-EDIT', 'name' => 'Customer Edit', 'city' => 'Malang']);
        $product = $this->product();
        $transaction = $this->makeDraft($admin, $customer, $product);

        $this->actingAs($admin)->get(route('school-bulk-transactions.edit', $transaction))
            ->assertOk()
            ->assertSee('SDN Lama');

        $this->actingAs($admin)->put(route('school-bulk-transactions.update', $transaction), [
            'customer_id' => $customer->id,
            'transaction_date' => '2026-05-08',
            'semester_period' => 'S2-2526',
            'locations' => [
                ['uid' => 'u1', 'school_name' => 'SDN Baru', 'customer_ship_location_id' => null],
            ],
            'location_items' => [
                'u1' => [
                    ['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 7, 'unit_price' => 5000],
                ],
            ],
        ])->assertRedirect(route('school-bulk-transactions.show', $transaction));

        $transaction->refresh()->load('locations', 'items');
        $this->assertSame(1, $transaction->locations->count());
        $this->assertSame('SDN Baru', (string) $transaction->locations->first()->school_name);
        $this->assertSame(1, $transaction->items->count());
        $this->assertSame(7, (int) $transaction->items->first()->quantity);
        $this->assertSame('2026-05-08', $transaction->transaction_date->format('Y-m-d'));
        $this->assertDatabaseMissing('school_bulk_transaction_locations', ['school_name' => 'SDN Lama']);
    }

    public function test_edit_blocked_after_delivery_note_generated(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $customer = Customer::query()->create(['code' => 'CUS-EB', 'name' => 'Customer EB', 'city' => 'Malang']);
        $product = $this->product();
        $transaction = $this->makeDraft($admin, $customer, $product);

        DeliveryNote::query()->create([
            'note_number' => 'SJ-EB-0001',
            'note_date' => '2026-05-08',
            'customer_id' => $customer->id,
            'school_bulk_transaction_id' => $transaction->id,
            'school_bulk_location_id' => $transaction->locations()->first()->id,
            'recipient_name' => 'SDN Lama',
        ]);

        $this->actingAs($admin)->get(route('school-bulk-transactions.edit', $transaction))
            ->assertRedirect(route('school-bulk-transactions.show', $transaction));

        $this->actingAs($admin)->put(route('school-bulk-transactions.update', $transaction), [
            'customer_id' => $customer->id,
            'transaction_date' => '2026-05-08',
            'locations' => [['uid' => 'u1', 'school_name' => 'SDN Ubah', 'customer_ship_location_id' => null]],
            'location_items' => ['u1' => [['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 9]]],
        ])->assertSessionHas('error');

        $this->assertDatabaseHas('school_bulk_transaction_locations', ['school_name' => 'SDN Lama']);
        $this->assertDatabaseMissing('school_bulk_transaction_locations', ['school_name' => 'SDN Ubah']);
    }
}
