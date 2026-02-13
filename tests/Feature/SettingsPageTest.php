<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_edit_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get(route('settings.edit'));

        $response->assertOk();
        $response->assertSee(__('menu.settings'));
        $response->assertSee('name="company_name"', false);
        $response->assertSee('name="semester_period_codes[]"', false);
        $response->assertSee('name="semester_active_period_codes[]"', false);
        $response->assertSee('name="product_unit_codes[]"', false);
        $response->assertSee('name="outgoing_unit_codes[]"', false);
    }

    public function test_settings_update_accepts_legacy_semester_and_company_fields(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->put(route('settings.update'), [
            'name' => 'Admin Test',
            'locale' => 'id',
            'theme' => 'light',
            'company_name' => 'CV Test',
            'company_address' => 'Alamat Test',
            'company_phone' => '0812',
            'company_email' => 'a@b.c',
            'company_notes' => 'Catatan perusahaan',
            'company_invoice_notes' => 'Catatan faktur',
            'company_billing_note' => 'Catatan tagihan',
            'company_transfer_accounts' => 'BCA 123',
            'semester_period_options' => "S1-2026\nS2-2026",
            'semester_active_periods' => ['S1-2026'],
            'product_unit_options' => 'exp|Exemplar,pcs|Pieces',
            'outgoing_unit_options' => 'exp|Exemplar,box|Box',
        ]);

        $response->assertRedirect(route('settings.edit'));
        $response->assertSessionHas('success');

        $this->assertSame('Catatan perusahaan', AppSetting::getValue('company_notes'));
        $this->assertSame('Catatan tagihan', AppSetting::getValue('company_billing_note'));
        $this->assertSame('BCA 123', AppSetting::getValue('company_transfer_accounts'));
    }
}
