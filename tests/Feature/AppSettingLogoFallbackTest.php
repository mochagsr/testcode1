<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppSettingLogoFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_logo_path_falls_back_to_existing_company_file_when_setting_is_blank(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company/test-logo.png', 'fake-image');

        $this->assertSame('company/test-logo.png', AppSetting::getValue('company_logo_path'));
    }

    public function test_company_logo_path_falls_back_when_saved_path_is_missing(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company/fallback-logo.png', 'fake-image');
        AppSetting::setValue('company_logo_path', 'company/missing-logo.png');

        $this->assertSame('company/fallback-logo.png', AppSetting::getValue('company_logo_path'));
    }
}
