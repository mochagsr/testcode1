<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductImportReconcileTest extends TestCase
{
    use RefreshDatabase;

    private ?User $adminUser = null;

    private function admin(): User
    {
        return $this->adminUser ??= User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $dataRows
     * @param  array<int, array{row:int,col:int,value:string}>  $textCells
     */
    private function makeXlsx(array $dataRows, array $textCells = []): UploadedFile
    {
        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->fromArray([['kode', 'nama', 'kategori', 'satuan', 'stok', 'harga_agen', 'harga_sales', 'harga_umum']], null, 'A1');
        $r = 2;
        foreach ($dataRows as $row) {
            $sh->fromArray([$row], null, 'A'.$r);
            $r++;
        }
        foreach ($textCells as $cell) {
            $sh->setCellValueExplicit([$cell['col'], $cell['row']], $cell['value'], DataType::TYPE_STRING);
        }
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($ss))->save($path);
        $ss->disconnectWorksheets();

        return new UploadedFile($path, 'import.xlsx', null, null, true);
    }

    public function test_analyze_classifies_new_matched_and_problem_rows(): void
    {
        Storage::fake('local');
        ItemCategory::query()->create(['code' => 'CRD', 'name' => 'CERDAS']);
        Product::query()->create(['item_category_id' => 1, 'code' => 'CRD-MTK', 'name' => 'MATEMATIKA 1', 'unit' => 'exp', 'stock' => 30, 'price_agent' => 3000, 'price_sales' => 3300, 'price_general' => 12000, 'is_active' => true]);
        // two products with the same name -> duplicate problem
        Product::query()->create(['item_category_id' => 1, 'code' => 'CRD-PP-A', 'name' => 'PP 1', 'unit' => 'exp', 'stock' => 5, 'price_agent' => 3000, 'price_sales' => 3300, 'price_general' => 12000, 'is_active' => true]);
        Product::query()->create(['item_category_id' => 1, 'code' => 'CRD-PP-B', 'name' => 'PP 1', 'unit' => 'exp', 'stock' => 7, 'price_agent' => 3000, 'price_sales' => 3300, 'price_general' => 12000, 'is_active' => true]);

        $file = $this->makeXlsx([
            ['', 'MATEMATIKA 1', 'CERDAS', 'exp', 1000, 3500, 3800, 13000],   // matched
            ['', 'BUKU BARU', 'CERDAS', 'exp', 50, 3500, 3800, 13000],        // new
            ['', 'PP 1', 'CERDAS', 'exp', 20, 3500, 3800, 13000],            // duplicate problem
            ['', 'BHS', 'TIDAKADA', 'exp', 10, 3500, 3800, 13000],          // missing category problem
        ]);

        $response = $this->actingAs($this->admin())->post(route('products.import.analyze'), ['import_file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('summary.new', 1);
        $response->assertJsonPath('summary.matched', 1);
        $response->assertJsonPath('summary.problems', 2);
        $response->assertJsonPath('matched.0.name_db', 'MATEMATIKA 1');
        $response->assertJsonPath('matched.0.stock_db', 30);
        $response->assertJsonPath('matched.0.stock_file', 1000);
        $response->assertJsonPath('matched.0.price_file.general', 13000);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_apply_update_replaces_stock_and_follows_file_price(): void
    {
        Storage::fake('local');
        ItemCategory::query()->create(['code' => 'CRD', 'name' => 'CERDAS']);
        $product = Product::query()->create(['item_category_id' => 1, 'code' => 'CRD-MTK', 'name' => 'MATEMATIKA 1', 'unit' => 'exp', 'stock' => 30, 'price_agent' => 3000, 'price_sales' => 3300, 'price_general' => 12000, 'is_active' => true]);

        $file = $this->makeXlsx([['', 'MATEMATIKA 1', 'CERDAS', 'exp', 1000, 3500, 3800, 13000]]);
        $token = $this->actingAs($this->admin())->post(route('products.import.analyze'), ['import_file' => $file])->json('token');

        $response = $this->actingAs($this->admin())->postJson(route('products.import.apply'), [
            'token' => $token,
            'update_prices' => true,
            'decisions' => [['row' => 2, 'action' => 'update', 'target_product_id' => $product->id]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('updated', 1);
        $product->refresh();
        $this->assertSame(1000, (int) $product->stock);
        $this->assertSame(13000, (int) $product->price_general);
        $this->assertSame(3500, (int) $product->price_agent);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'mutation_type' => 'in',
            'quantity' => 970,
        ]);
    }

    public function test_apply_add_sums_stock(): void
    {
        Storage::fake('local');
        ItemCategory::query()->create(['code' => 'CRD', 'name' => 'CERDAS']);
        $product = Product::query()->create(['item_category_id' => 1, 'code' => 'CRD-MTK', 'name' => 'MATEMATIKA 1', 'unit' => 'exp', 'stock' => 100, 'price_agent' => 3000, 'price_sales' => 3300, 'price_general' => 12000, 'is_active' => true]);

        $file = $this->makeXlsx([['', 'MATEMATIKA 1', 'CERDAS', 'exp', 75, 3500, 3800, 13000]]);
        $token = $this->actingAs($this->admin())->post(route('products.import.analyze'), ['import_file' => $file])->json('token');

        $this->actingAs($this->admin())->postJson(route('products.import.apply'), [
            'token' => $token,
            'update_prices' => true,
            'decisions' => [['row' => 2, 'action' => 'add', 'target_product_id' => $product->id]],
        ])->assertOk();

        $product->refresh();
        $this->assertSame(175, (int) $product->stock);
        $this->assertDatabaseHas('stock_mutations', ['product_id' => $product->id, 'mutation_type' => 'in', 'quantity' => 75]);
    }

    public function test_apply_update_prices_false_keeps_existing_prices(): void
    {
        Storage::fake('local');
        ItemCategory::query()->create(['code' => 'CRD', 'name' => 'CERDAS']);
        $product = Product::query()->create(['item_category_id' => 1, 'code' => 'CRD-MTK', 'name' => 'MATEMATIKA 1', 'unit' => 'exp', 'stock' => 30, 'price_agent' => 3000, 'price_sales' => 3300, 'price_general' => 12000, 'is_active' => true]);

        $file = $this->makeXlsx([['', 'MATEMATIKA 1', 'CERDAS', 'exp', 1000, 3500, 3800, 13000]]);
        $token = $this->actingAs($this->admin())->post(route('products.import.analyze'), ['import_file' => $file])->json('token');

        $this->actingAs($this->admin())->postJson(route('products.import.apply'), [
            'token' => $token,
            'update_prices' => false,
            'decisions' => [['row' => 2, 'action' => 'update', 'target_product_id' => $product->id]],
        ])->assertOk();

        $product->refresh();
        $this->assertSame(1000, (int) $product->stock);
        $this->assertSame(12000, (int) $product->price_general);
    }

    public function test_apply_new_creates_product(): void
    {
        Storage::fake('local');
        ItemCategory::query()->create(['code' => 'CRD', 'name' => 'CERDAS']);

        $file = $this->makeXlsx([['', 'BUKU BARU', 'CERDAS', 'exp', 50, 3500, 3800, 13000]]);
        $token = $this->actingAs($this->admin())->post(route('products.import.analyze'), ['import_file' => $file])->json('token');

        $this->actingAs($this->admin())->postJson(route('products.import.apply'), [
            'token' => $token,
            'update_prices' => true,
            'decisions' => [['row' => 2, 'action' => 'new']],
        ])->assertOk();

        $product = Product::query()->where('name', 'BUKU BARU')->first();
        $this->assertNotNull($product);
        $this->assertSame(50, (int) $product->stock);
        $this->assertDatabaseHas('stock_mutations', ['product_id' => $product->id, 'mutation_type' => 'in', 'quantity' => 50]);
    }

    public function test_indonesian_number_format_is_normalized(): void
    {
        Storage::fake('local');
        ItemCategory::query()->create(['code' => 'CRD', 'name' => 'CERDAS']);
        $product = Product::query()->create(['item_category_id' => 1, 'code' => 'CRD-MTK', 'name' => 'MATEMATIKA 1', 'unit' => 'exp', 'stock' => 0, 'price_agent' => 0, 'price_sales' => 0, 'price_general' => 0, 'is_active' => true]);

        // prices entered as text with thousand separators
        $file = $this->makeXlsx(
            [['', 'MATEMATIKA 1', 'CERDAS', 'exp', '1500', '3,500', '3,800', '13,000']],
            [
                ['row' => 2, 'col' => 5, 'value' => '1500'],
                ['row' => 2, 'col' => 6, 'value' => '3,500'],
                ['row' => 2, 'col' => 7, 'value' => '3,800'],
                ['row' => 2, 'col' => 8, 'value' => '13,000'],
            ]
        );
        $analyze = $this->actingAs($this->admin())->post(route('products.import.analyze'), ['import_file' => $file]);
        $analyze->assertOk();
        $analyze->assertJsonPath('summary.matched', 1);
        $analyze->assertJsonPath('matched.0.price_file.general', 13000);
        $token = $analyze->json('token');

        $this->actingAs($this->admin())->postJson(route('products.import.apply'), [
            'token' => $token,
            'update_prices' => true,
            'decisions' => [['row' => 2, 'action' => 'update', 'target_product_id' => $product->id]],
        ])->assertOk();

        $product->refresh();
        $this->assertSame(1500, (int) $product->stock);
        $this->assertSame(13000, (int) $product->price_general);
    }

    public function test_problem_file_download_returns_xlsx(): void
    {
        Storage::fake('local');
        ItemCategory::query()->create(['code' => 'CRD', 'name' => 'CERDAS']);

        $file = $this->makeXlsx([['', 'BHS', 'TIDAKADA', 'exp', 10, 3500, 3800, 13000]]);
        $token = $this->actingAs($this->admin())->post(route('products.import.analyze'), ['import_file' => $file])->json('token');

        $response = $this->actingAs($this->admin())->get(route('products.import.problems', ['token' => $token]));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', strtolower((string) $response->headers->get('content-type')));
    }

    public function test_same_name_different_category_is_not_flagged_as_duplicate(): void
    {
        Storage::fake('local');
        ItemCategory::query()->create(['code' => 'CRD', 'name' => 'CERDAS']);
        ItemCategory::query()->create(['code' => 'CRD64', 'name' => 'CERDAS64']);

        $file = $this->makeXlsx([
            ['', 'PAI 7 EDISI 1 HAL64 SMT 1 25/26', 'CERDAS', 'exp', 100, 3500, 3800, 13000],
            ['', 'PAI 7 EDISI 1 HAL64 SMT 1 25/26', 'CERDAS64', 'exp', 200, 3500, 3800, 13000],
        ]);

        $response = $this->actingAs($this->admin())->post(route('products.import.analyze'), ['import_file' => $file]);
        $response->assertOk();
        $response->assertJsonPath('summary.problems', 0);
        $response->assertJsonPath('summary.new', 2);
    }

    public function test_same_name_and_same_category_twice_is_flagged(): void
    {
        Storage::fake('local');
        ItemCategory::query()->create(['code' => 'CRD', 'name' => 'CERDAS']);

        $file = $this->makeXlsx([
            ['', 'PAI 7 EDISI 1 HAL64 SMT 1 25/26', 'CERDAS', 'exp', 100, 3500, 3800, 13000],
            ['', 'PAI 7 EDISI 1 HAL64 SMT 1 25/26', 'CERDAS', 'exp', 200, 3500, 3800, 13000],
        ]);

        $response = $this->actingAs($this->admin())->post(route('products.import.analyze'), ['import_file' => $file]);
        $response->assertOk();
        $response->assertJsonPath('summary.problems', 2);
    }

    public function test_analyze_requires_authentication(): void
    {
        $this->post(route('products.import.analyze'))->assertRedirect(route('login'));
    }
}
