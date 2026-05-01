<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppChromeAndSupplierFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_uses_company_logo_as_favicon(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company/favicon.png', 'fake-image');
        AppSetting::setValue('company_logo_path', 'company/favicon.png');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('rel="icon"', false)
            ->assertSee('storage/company/favicon.png', false);
    }

    public function test_supplier_address_field_is_textarea(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('suppliers.create'))
            ->assertOk()
            ->assertSee('<textarea name="address"', false)
            ->assertDontSee('<input type="text" name="address"', false);
    }
}
