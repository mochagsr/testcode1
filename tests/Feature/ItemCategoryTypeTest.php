<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCategoryTypeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
    }

    public function test_category_defaults_to_general_type(): void
    {
        $category = ItemCategory::query()->create(['code' => 'buku', 'name' => 'Buku']);

        $this->assertSame(ItemCategory::TYPE_GENERAL, $category->fresh()->type);
    }

    public function test_category_can_be_created_as_raw_material(): void
    {
        $this->actingAs($this->admin())
            ->post(route('item-categories.store'), [
                'name' => 'Kertas Roll',
                'type' => ItemCategory::TYPE_RAW_MATERIAL,
            ])
            ->assertRedirect(route('item-categories.index'));

        $this->assertDatabaseHas('item_categories', [
            'name' => 'Kertas Roll',
            'type' => ItemCategory::TYPE_RAW_MATERIAL,
        ]);
    }

    public function test_category_type_is_required_and_validated(): void
    {
        $this->actingAs($this->admin())
            ->post(route('item-categories.store'), ['name' => 'Tanpa Jenis', 'type' => 'ngawur'])
            ->assertSessionHasErrors('type');
    }

    public function test_of_type_scope_separates_general_and_raw_material(): void
    {
        ItemCategory::query()->create(['code' => 'buku', 'name' => 'Buku', 'type' => ItemCategory::TYPE_GENERAL]);
        ItemCategory::query()->create(['code' => 'rolweb', 'name' => 'Roll Web', 'type' => ItemCategory::TYPE_RAW_MATERIAL]);

        $this->assertSame(['Buku'], ItemCategory::query()->ofType(ItemCategory::TYPE_GENERAL)->pluck('name')->all());
        $this->assertSame(['Roll Web'], ItemCategory::query()->ofType(ItemCategory::TYPE_RAW_MATERIAL)->pluck('name')->all());
    }

    public function test_category_index_shows_type_column(): void
    {
        ItemCategory::query()->create(['code' => 'rolweb', 'name' => 'Roll Web', 'type' => ItemCategory::TYPE_RAW_MATERIAL]);

        $this->actingAs($this->admin())
            ->get(route('item-categories.index'))
            ->assertOk()
            ->assertSee(__('ui.product_type_label'))
            ->assertSee(__('ui.product_type_raw_material'));
    }

    public function test_product_form_puts_product_type_before_category(): void
    {
        ItemCategory::query()->create(['code' => 'rolweb', 'name' => 'Roll Web', 'type' => ItemCategory::TYPE_RAW_MATERIAL]);

        $content = (string) $this->actingAs($this->admin())
            ->get(route('products.create'))
            ->assertOk()
            ->getContent();

        $typePosition = strpos($content, 'id="product-type"');
        $categoryPosition = strpos($content, 'id="product-category-search"');

        $this->assertIsInt($typePosition);
        $this->assertIsInt($categoryPosition);
        $this->assertLessThan($categoryPosition, $typePosition);
    }

    public function test_product_form_exposes_category_type_for_filtering(): void
    {
        ItemCategory::query()->create(['code' => 'rolweb', 'name' => 'Roll Web', 'type' => ItemCategory::TYPE_RAW_MATERIAL]);

        $this->actingAs($this->admin())
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('"type":"raw_material"', false);
    }
}
