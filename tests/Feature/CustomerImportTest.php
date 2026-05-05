<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_import_accepts_indonesian_headers_and_secondary_phone(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [
                'masters.customers.view',
                'masters.customers.manage',
            ],
        ]);

        CustomerLevel::query()->create([
            'code' => 'agen',
            'name' => 'Agen',
        ]);

        $csv = implode("\n", [
            'nama,level_customer,no_hp_1,no_hp_2,kota,alamat,catatan',
            'Toko Sumber Ilmu,Agen,08123456789,08234567890,Malang,Jl. Soekarno Hatta 10,Customer lama',
        ]);

        $file = UploadedFile::fake()->createWithContent('customer.csv', $csv);

        $response = $this->actingAs($user)->post(route('customers-web.import'), [
            'import_file' => $file,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'name' => 'Toko Sumber Ilmu',
            'phone' => '08123456789',
            'phone_secondary' => '08234567890',
            'city' => 'Malang',
        ]);

        $customer = Customer::query()->where('name', 'Toko Sumber Ilmu')->first();
        $this->assertNotNull($customer?->code);
    }

    public function test_customer_index_groups_import_and_export_actions(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [
                'masters.customers.view',
                'customers.import',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('customers-web.index'));

        $response->assertOk()
            ->assertSee('Import Data')
            ->assertSee('id="customer-import-modal"', false)
            ->assertSee('Export PDF')
            ->assertSee('Export Excel')
            ->assertSee(route('customers-web.export.pdf'), false)
            ->assertSee(route('customers-web.export.csv'), false);
    }

    public function test_customer_pdf_export_downloads_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Customer::query()->create([
            'code' => 'CUST-PDF-01',
            'name' => 'Customer PDF',
            'phone' => '081234',
            'city' => 'Malang',
            'address' => 'Jl. PDF',
        ]);

        $response = $this->actingAs($admin)->get(route('customers-web.export.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }
}
