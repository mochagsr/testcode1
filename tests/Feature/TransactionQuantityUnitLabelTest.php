<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ItemCategory;
use App\Models\OrderNote;
use App\Models\OrderNoteItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionQuantityUnitLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_note_and_sales_invoice_forms_render_quantity_unit_labels(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);
        $this->createProductWithCustomer();

        $this->actingAs($admin)
            ->get(route('order-notes.create'))
            ->assertOk()
            ->assertSee('qty-unit-label', false)
            ->assertSee('"unit":"exp"', false);

        $this->actingAs($admin)
            ->get(route('delivery-notes.create'))
            ->assertOk()
            ->assertSee('quantity-with-unit', false)
            ->assertSee('qty-unit-label', false)
            ->assertSee('type="hidden" name="items[${index}][unit]" class="unit"', false)
            ->assertDontSee('<th style="width: 10%">'.__('txn.unit').'</th>', false);

        $this->actingAs($admin)
            ->get(route('sales-invoices.create'))
            ->assertOk()
            ->assertSee('qty-unit-label', false)
            ->assertSee('"unit":"exp"', false);

        $this->actingAs($admin)
            ->get(route('sales-returns.create'))
            ->assertOk()
            ->assertSee('qty-unit-label', false)
            ->assertSee('"unit":"exp"', false);
    }

    public function test_transaction_quantity_inputs_use_zero_placeholder_instead_of_default_one(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);
        $this->createProductWithCustomer();
        Supplier::query()->create([
            'name' => 'Supplier Unit',
            'company_name' => 'PT Supplier Unit',
        ]);

        $this->actingAs($admin)
            ->get(route('order-notes.create'))
            ->assertOk()
            ->assertSee('class="qty-input js-thousand-input"', false)
            ->assertSee('value="" placeholder="0"', false)
            ->assertDontSee('value="1" class="qty-input"', false);

        $this->actingAs($admin)
            ->get(route('delivery-notes.create'))
            ->assertOk()
            ->assertSee('class="qty-input js-thousand-input"', false)
            ->assertSee('placeholder="0"', false)
            ->assertDontSee('prefill?.quantity || 1', false);

        $this->actingAs($admin)
            ->get(route('school-bulk-transactions.create'))
            ->assertOk()
            ->assertSee('class="product-qty js-thousand-input"', false)
            ->assertSee('value="${initial.quantity || \'\'}" placeholder="0"', false)
            ->assertDontSee('initial.quantity || 1', false);

        $this->actingAs($admin)
            ->get(route('outgoing-transactions.create'))
            ->assertOk()
            ->assertSee('placeholder="0" required', false)
            ->assertDontSee('rowPrefill?.quantity ?? 1', false);

        $this->actingAs($admin)
            ->get(route('sales-invoices.create'))
            ->assertOk()
            ->assertSee('value="${initialQty}" placeholder="0" required', false)
            ->assertDontSee('prefill?.quantity || 1', false);

        $this->actingAs($admin)
            ->get(route('sales-returns.create'))
            ->assertOk()
            ->assertSee('value="" placeholder="0" required', false)
            ->assertDontSee('value="1" required', false);
    }

    public function test_order_note_lookup_includes_product_unit_for_invoice_prefill(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);
        ['customer' => $customer, 'product' => $product] = $this->createProductWithCustomer();

        $note = OrderNote::query()->create([
            'note_number' => 'PO-UNIT-001',
            'note_date' => '2026-05-04',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'city' => $customer->city,
            'created_by_name' => 'Admin',
        ]);
        OrderNoteItem::query()->create([
            'order_note_id' => $note->id,
            'product_id' => $product->id,
            'product_code' => $product->code,
            'product_name' => $product->name,
            'quantity' => 5,
        ]);

        $this->actingAs($admin)
            ->getJson(route('api.order-notes.lookup', ['customer_id' => $customer->id]))
            ->assertOk()
            ->assertJsonPath('data.0.items.0.unit', 'exp');
    }

    /**
     * @return array{customer: Customer, product: Product}
     */
    private function createProductWithCustomer(): array
    {
        $customer = Customer::query()->create([
            'code' => 'CUST-UNIT',
            'name' => 'Customer Unit',
            'city' => 'Malang',
        ]);
        $category = ItemCategory::query()->create([
            'code' => 'CAT-UNIT',
            'name' => 'Kategori Unit',
        ]);
        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-UNIT',
            'name' => 'Produk Unit',
            'unit' => 'exp',
            'stock' => 100,
            'price_general' => 10000,
            'is_active' => true,
        ]);

        return ['customer' => $customer, 'product' => $product];
    }
}
