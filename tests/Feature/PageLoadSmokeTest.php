<?php

namespace Tests\Feature;

use App\Models\Customer;
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

    public function test_main_pages_load_without_server_error_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        $customer = Customer::query()->create([
            'code' => 'CUST-SMOKE-001',
            'name' => 'Customer Smoke',
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

        $routes = [
            route('dashboard'),
            route('customers-web.index'),
            route('customers-web.create'),
            route('suppliers.index'),
            route('sales-invoices.index'),
            route('sales-invoices.create'),
            route('sales-returns.index'),
            route('sales-returns.create'),
            route('delivery-notes.index'),
            route('delivery-notes.create'),
            route('order-notes.index'),
            route('order-notes.create'),
            route('outgoing-transactions.index'),
            route('outgoing-transactions.create'),
            route('school-bulk-transactions.index'),
            route('school-bulk-transactions.create'),
            route('receivables.index'),
            route('receivables.global.index'),
            route('receivables.semester.index'),
            route('receivable-payments.index'),
            route('receivable-payments.create'),
            route('supplier-payables.index'),
            route('supplier-payables.create'),
            route('reports.index'),
            route('audit-logs.index'),
            route('ops-health.index'),
            route('settings.edit'),
            route('users.index'),
        ];

        foreach ($routes as $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertOk("Failed asserting GET {$url} returns 200.");
        }
    }
}
