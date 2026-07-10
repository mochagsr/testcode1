<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductExportImportRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_and_reimport_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
        $category = ItemCategory::query()->create(['code' => 'CRD', 'name' => 'CERDAS']);
        Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'CRD-MTK-1',
            'name' => 'MATEMATIKA 1',
            'unit' => 'exp',
            'stock' => 175,
            'price_agent' => 3500,
            'price_sales' => 3800,
            'price_general' => 13000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('products.export.full'));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', strtolower((string) $response->headers->get('content-type')));

        $path = tempnam(sys_get_temp_dir(), 'prd').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        // Simulate a fresh server: wipe products (keep the category), then import.
        Product::query()->delete();
        $this->assertSame(0, Product::query()->count());

        $file = new UploadedFile($path, 'export.xlsx', null, null, true);
        $this->actingAs($admin)->post(route('products.import'), ['import_file' => $file])->assertRedirect();
        @unlink($path);

        $this->assertSame(1, Product::query()->count());
        $imported = Product::query()->first();
        $this->assertSame('MATEMATIKA 1', (string) $imported->name);
        $this->assertSame(175, (int) $imported->stock);
        $this->assertSame(3500, (int) $imported->price_agent);
        $this->assertSame(3800, (int) $imported->price_sales);
        $this->assertSame(13000, (int) $imported->price_general);
        $this->assertSame($category->id, (int) $imported->item_category_id);
    }

    public function test_non_admin_cannot_export_products(): void
    {
        $user = User::factory()->create(['role' => 'user', 'permissions' => []]);
        $this->actingAs($user)->get(route('products.export.full'))->assertForbidden();
    }
}
