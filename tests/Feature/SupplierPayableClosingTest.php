<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Supplier;
use App\Models\User;
use App\Support\SemesterBookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPayableClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_close_supplier_book_by_year_without_month(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $supplier = Supplier::query()->create(['name' => 'Supplier Tahunan']);

        $response = $this->actingAs($admin)->post(route('supplier-payables.year-close'), [
            'supplier_id' => $supplier->id,
            'year' => '2026',
        ]);

        $response->assertRedirect(route('supplier-payables.index', [
            'supplier_id' => $supplier->id,
            'year' => '2026',
            'search' => '',
        ]));
        $this->assertSame($supplier->id.':2026', AppSetting::getValue('closed_supplier_year_periods'));
        $this->assertTrue(app(SemesterBookService::class)->isSupplierMonthClosed($supplier->id, '2026', 5));
    }

    public function test_admin_can_open_supplier_book_by_year_without_month(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $supplier = Supplier::query()->create(['name' => 'Supplier Buka Tahunan']);
        AppSetting::setValue('closed_supplier_year_periods', $supplier->id.':2026');

        $response = $this->actingAs($admin)->post(route('supplier-payables.year-open'), [
            'supplier_id' => $supplier->id,
            'year' => '2026',
        ]);

        $response->assertRedirect(route('supplier-payables.index', [
            'supplier_id' => $supplier->id,
            'year' => '2026',
            'search' => '',
        ]));
        $this->assertSame('', AppSetting::getValue('closed_supplier_year_periods'));
        $this->assertFalse(app(SemesterBookService::class)->isSupplierYearClosed($supplier->id, '2026'));
    }

    public function test_supplier_payable_closing_page_switches_between_year_and_month_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $supplier = Supplier::query()->create(['name' => 'Supplier Mode Tutup']);

        $yearResponse = $this->actingAs($admin)->get(route('supplier-payables.index', [
            'supplier_id' => $supplier->id,
            'year' => '2026',
        ]));

        $yearResponse->assertOk();
        $yearResponse->assertSee(__('supplier_payable.close_year_action'));
        $yearResponse->assertDontSee(__('supplier_payable.close_month_action'));

        $monthResponse = $this->actingAs($admin)->get(route('supplier-payables.index', [
            'supplier_id' => $supplier->id,
            'year' => '2026',
            'month' => 5,
        ]));

        $monthResponse->assertOk();
        $monthResponse->assertSee(__('supplier_payable.close_month_action'));
    }

    public function test_supplier_payable_index_uses_export_dropdown(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);

        $response = $this->actingAs($admin)->get(route('supplier-payables.index'));

        $response->assertOk();
        $response->assertSee('<option value="" selected disabled>Export</option>', false);
        $response->assertSee(route('supplier-payables.export.pdf'), false);
        $response->assertSee(route('supplier-payables.export.excel'), false);
        $response->assertDontSee('">'.__('txn.pdf').'</a>', false);
        $response->assertDontSee('">Export Excel</a>', false);
    }
}
