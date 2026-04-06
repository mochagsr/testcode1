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
}
