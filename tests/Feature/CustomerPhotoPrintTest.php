<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPhotoPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_id_card_print_includes_contact_and_address(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::query()->create([
            'code' => 'CUS-PRINT',
            'name' => 'Anton',
            'phone' => '0811111111',
            'phone_secondary' => '0822222222',
            'city' => 'Malang',
            'address' => 'Jl. Mawar No. 7',
            'id_card_photo_path' => 'ktp/anton.jpg',
        ]);

        $this->actingAs($admin)
            ->get(route('customers-web.id-card-photo.print', $customer))
            ->assertOk()
            ->assertSee('KTP Customer')
            ->assertSee('No HP')
            ->assertSee('0811111111 / 0822222222')
            ->assertSee('Alamat')
            ->assertSee('Jl. Mawar No. 7');
    }
}
