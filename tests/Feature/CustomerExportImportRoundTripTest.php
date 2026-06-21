<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CustomerExportImportRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_and_reimport_customers_preserving_phone(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $level = CustomerLevel::query()->create(['code' => 'AGN', 'name' => 'Agen']);
        Customer::query()->create([
            'code' => 'CUS-1',
            'name' => 'Toko Sumber Ilmu',
            'customer_level_id' => $level->id,
            'phone' => '08123456789',
            'phone_secondary' => '08234567890',
            'city' => 'Malang',
            'address' => 'Jl. Soekarno Hatta 10',
            'notes' => 'Customer lama',
        ]);

        $response = $this->actingAs($admin)->get(route('customers-web.export.full'));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', strtolower((string) $response->headers->get('content-type')));

        $path = tempnam(sys_get_temp_dir(), 'cust').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        // Simulate moving to a fresh server: wipe customers, then import the export.
        Customer::query()->delete();
        $this->assertSame(0, Customer::query()->count());

        $file = new UploadedFile($path, 'export.xlsx', null, null, true);
        $this->actingAs($admin)->post(route('customers-web.import'), ['import_file' => $file])->assertRedirect();
        @unlink($path);

        $this->assertSame(1, Customer::query()->count());
        $imported = Customer::query()->first();
        $this->assertSame('Toko Sumber Ilmu', (string) $imported->name);
        $this->assertSame('08123456789', (string) $imported->phone);
        $this->assertSame('08234567890', (string) $imported->phone_secondary);
        $this->assertSame('Malang', (string) $imported->city);
        $this->assertSame($level->id, (int) $imported->customer_level_id);
    }

    public function test_non_admin_cannot_export(): void
    {
        $user = User::factory()->create(['role' => 'user', 'permissions' => ['*']]);
        $this->actingAs($user)->get(route('customers-web.export.full'))->assertForbidden();
    }
}
