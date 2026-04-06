<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Models\OutgoingTransaction;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\ReceivableLedger;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageLoadSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menu_and_create_pages_load_without_server_error(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        $this->seedSmokeData();

        foreach ($this->adminRoutes() as $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertOk("Failed asserting GET {$url} returns 200 for admin.");
        }
    }

    public function test_user_menu_and_create_pages_load_without_server_error(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => config('rbac.roles.user', []),
        ]);

        $this->seedSmokeData();

        foreach ($this->userRoutes() as $url) {
            $response = $this->actingAs($user)->get($url);
            $response->assertOk("Failed asserting GET {$url} returns 200 for user.");
        }
    }

    /**
     * @return array<int, string>
     */
    private function adminRoutes(): array
    {
        return [
            route('dashboard'),
            route('item-categories.index'),
            route('item-categories.create'),
            route('products.index'),
            route('products.create'),
            route('customer-levels-web.index'),
            route('customer-levels-web.create'),
            route('customers-web.index'),
            route('customers-web.create'),
            route('suppliers.index'),
            route('suppliers.create'),
            route('outgoing-transactions.index'),
            route('outgoing-transactions.create'),
            route('supplier-payables.index'),
            route('supplier-payables.create'),
            route('supplier-stock-cards.index'),
            route('customer-ship-locations.index'),
            route('customer-ship-locations.create'),
            route('school-bulk-transactions.index'),
            route('school-bulk-transactions.create'),
            route('sales-invoices.index'),
            route('sales-invoices.create'),
            route('sales-returns.index'),
            route('sales-returns.create'),
            route('delivery-notes.index'),
            route('delivery-notes.create'),
            route('delivery-trips.index'),
            route('delivery-trips.create'),
            route('order-notes.index'),
            route('order-notes.create'),
            route('receivables.index'),
            route('receivables.global.index'),
            route('receivables.semester.index'),
            route('receivable-payments.index'),
            route('receivable-payments.create'),
            route('reports.index'),
            route('users.index'),
            route('users.create'),
            route('audit-logs.index'),
            route('approvals.index'),
            route('semester-transactions.index'),
            route('ops-health.index'),
            route('settings.edit'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function userRoutes(): array
    {
        return [
            route('dashboard'),
            route('customers-web.index'),
            route('customers-web.create'),
            route('suppliers.index'),
            route('outgoing-transactions.index'),
            route('outgoing-transactions.create'),
            route('supplier-payables.index'),
            route('supplier-payables.create'),
            route('supplier-stock-cards.index'),
            route('customer-ship-locations.index'),
            route('customer-ship-locations.create'),
            route('school-bulk-transactions.index'),
            route('school-bulk-transactions.create'),
            route('sales-invoices.index'),
            route('sales-invoices.create'),
            route('sales-returns.index'),
            route('sales-returns.create'),
            route('delivery-notes.index'),
            route('delivery-notes.create'),
            route('delivery-trips.index'),
            route('delivery-trips.create'),
            route('order-notes.index'),
            route('order-notes.create'),
            route('receivables.index'),
            route('receivables.global.index'),
            route('receivables.semester.index'),
            route('receivable-payments.index'),
            route('receivable-payments.create'),
            route('reports.index'),
            route('settings.edit'),
        ];
    }

    private function seedSmokeData(): void
    {
        $level = CustomerLevel::query()->create([
            'code' => 'LV-SMOKE',
            'name' => 'Level Smoke',
        ]);

        $customer = Customer::query()->create([
            'code' => 'CUST-SMOKE-001',
            'customer_level_id' => $level->id,
            'name' => 'Customer Smoke',
            'phone' => '081234567890',
            'city' => 'Malang',
            'address' => 'Jl. Smoke Test',
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Supplier Smoke',
            'company_name' => 'PT Smoke',
            'phone' => '081234567890',
            'address' => 'Surabaya',
        ]);

        $category = ItemCategory::query()->create([
            'code' => 'CAT-SMOKE',
            'name' => 'Kategori Smoke',
        ]);

        Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'BRG-SMOKE-001',
            'name' => 'Barang Smoke',
            'unit' => 'pcs',
            'stock' => 10,
            'price_general' => 15000,
            'is_active' => true,
        ]);

        ReceivableLedger::query()->create([
            'customer_id' => $customer->id,
            'entry_date' => '2026-04-05',
            'description' => 'Invoice INV-SMOKE-001',
            'debit' => 15000,
            'credit' => 0,
            'balance_after' => 15000,
            'period_code' => 'S2-2526',
        ]);

        OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-SMOKE-001',
            'transaction_date' => '2026-04-05',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'note_number' => 'NOTA-SMOKE-001',
            'total' => 25000,
            'notes' => 'Smoke transaction',
            'created_by_user_id' => User::query()->value('id'),
        ]);
    }
}
