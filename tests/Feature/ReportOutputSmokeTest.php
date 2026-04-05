<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Models\DeliveryNote;
use App\Models\DeliveryTrip;
use App\Models\ItemCategory;
use App\Models\OrderNote;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use App\Models\ReceivableLedger;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportOutputSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_print_pdf_and_excel_routes_load_without_server_error(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        $customer = $this->seedReportData($admin);

        $datasets = [
            'products',
            'customers',
            'users',
            'sales_invoices',
            'receivables',
            'sales_returns',
            'delivery_notes',
            'delivery_trips',
            'order_notes',
            'outgoing_transactions',
            'income_statement',
            'balance_sheet',
            'semester_transactions',
        ];

        foreach ($datasets as $dataset) {
            $params = ['dataset' => $dataset];

            if (in_array($dataset, ['sales_invoices', 'sales_returns', 'delivery_notes', 'delivery_trips', 'order_notes', 'receivables', 'outgoing_transactions', 'balance_sheet', 'income_statement', 'semester_transactions'], true)) {
                $params['semester'] = 'S2-2526';
            }

            if ($dataset === 'receivables') {
                $params['customer_id'] = $customer->id;
            }

            $this->actingAs($admin)->get(route('reports.print', $params))
                ->assertOk("Failed asserting print report for {$dataset} returns 200.");
            $this->actingAs($admin)->get(route('reports.export.pdf', $params))
                ->assertOk("Failed asserting pdf report for {$dataset} returns 200.");
            $this->actingAs($admin)->get(route('reports.export.csv', $params))
                ->assertOk("Failed asserting excel report for {$dataset} returns 200.");
        }
    }

    private function seedReportData(User $admin): Customer
    {
        $level = CustomerLevel::query()->create([
            'code' => 'LV-RPT',
            'name' => 'Level Report',
        ]);

        $customer = Customer::query()->create([
            'code' => 'CUST-RPT-001',
            'customer_level_id' => $level->id,
            'name' => 'Customer Report',
            'phone' => '081233344455',
            'city' => 'Malang',
            'address' => 'Jl. Report 1',
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Supplier Report',
            'company_name' => 'PT Report',
            'phone' => '081233344466',
            'address' => 'Surabaya',
        ]);

        $category = ItemCategory::query()->create([
            'code' => 'CAT-RPT',
            'name' => 'Kategori Report',
        ]);

        $product = Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'PRD-RPT-001',
            'name' => 'Produk Report',
            'unit' => 'pcs',
            'stock' => 100,
            'price_general' => 20000,
            'is_active' => true,
        ]);

        SalesInvoice::query()->create([
            'invoice_number' => 'INV-RPT-0001',
            'customer_id' => $customer->id,
            'invoice_date' => '2026-04-05',
            'semester_period' => 'S2-2526',
            'subtotal' => 100000,
            'total' => 100000,
            'total_paid' => 20000,
            'balance' => 80000,
            'payment_status' => 'unpaid',
            'created_by_user_id' => $admin->id,
        ]);

        SalesReturn::query()->create([
            'return_number' => 'RTR-RPT-0001',
            'customer_id' => $customer->id,
            'return_date' => '2026-04-05',
            'semester_period' => 'S2-2526',
            'total' => 10000,
            'created_by_user_id' => $admin->id,
        ]);

        DeliveryNote::query()->create([
            'note_number' => 'SJ-RPT-0001',
            'note_date' => '2026-04-05',
            'customer_id' => $customer->id,
            'recipient_name' => 'Penerima Report',
            'recipient_phone' => '081200000001',
            'city' => 'Malang',
            'address' => 'Jl. Penerima Report',
            'created_by_name' => $admin->name,
        ]);

        OrderNote::query()->create([
            'note_number' => 'PO-RPT-0001',
            'note_date' => '2026-04-05',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'city' => $customer->city,
            'address' => $customer->address,
            'created_by_name' => $admin->name,
        ]);

        DeliveryTrip::query()->create([
            'trip_number' => 'TRP-RPT-0001',
            'trip_date' => '2026-04-05',
            'driver_name' => 'Supir Report',
            'assistant_name' => 'Asisten Report',
            'vehicle_plate' => 'N 1234 RPT',
            'member_count' => 2,
            'fuel_cost' => 100000,
            'toll_cost' => 20000,
            'meal_cost' => 15000,
            'other_cost' => 5000,
            'total_cost' => 140000,
            'created_by_user_id' => $admin->id,
        ]);

        OutgoingTransaction::query()->create([
            'transaction_number' => 'TRXK-RPT-0001',
            'transaction_date' => '2026-04-05',
            'supplier_id' => $supplier->id,
            'semester_period' => 'S2-2526',
            'note_number' => 'NOTA-RPT-0001',
            'total' => 40000,
            'notes' => 'Outgoing report',
            'created_by_user_id' => $admin->id,
        ]);

        ReceivableLedger::query()->create([
            'customer_id' => $customer->id,
            'entry_date' => '2026-04-05',
            'description' => 'Invoice INV-RPT-0001',
            'debit' => 100000,
            'credit' => 0,
            'balance_after' => 100000,
            'period_code' => 'S2-2526',
        ]);

        return $customer;
    }
}
