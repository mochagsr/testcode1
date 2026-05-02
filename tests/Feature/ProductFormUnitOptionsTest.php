<?php

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFormUnitOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_product_form_shows_configured_unit_options(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        ItemCategory::query()->create([
            'code' => 'buku',
            'name' => 'Buku',
        ]);

        ProductUnit::query()->create([
            'code' => 'pack',
            'name' => 'Pack',
        ]);

        $response = $this->actingAs($admin)->get(route('products.create'));

        $response->assertOk();
        $response->assertSee('name="unit"', false);
        $response->assertSee('value="pack"', false);
        $response->assertSee('pack - Pack');
    }
}
