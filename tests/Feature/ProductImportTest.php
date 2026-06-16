<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_import_accepts_indonesian_headers_and_auto_generates_code_when_blank(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [
                'masters.products.view',
                'masters.products.manage',
            ],
        ]);

        ItemCategory::query()->create([
            'code' => 'buku',
            'name' => 'Buku',
        ]);

        $csv = implode("\n", [
            'kode,nama,kategori,satuan,stok,harga_agen,harga_sales,harga_umum',
            ',Matematika 1 Edisi 5 Smt 1 25/26,Buku,exp,10,50000,55000,60000',
        ]);

        $file = UploadedFile::fake()->createWithContent('barang.csv', $csv);

        $response = $this->actingAs($user)->post(route('products.import'), [
            'import_file' => $file,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'name' => 'Matematika 1 Edisi 5 Smt 1 25/26',
            'unit' => 'exp',
            'stock' => 10,
        ]);

        $product = Product::query()->where('name', 'Matematika 1 Edisi 5 Smt 1 25/26')->first();

        $this->assertNotNull($product);
        $this->assertNotSame('', (string) $product?->code);
    }

    public function test_product_import_shows_human_message_when_required_headers_are_missing(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [
                'masters.products.view',
                'masters.products.manage',
            ],
        ]);

        $csv = implode("\n", [
            'kode,nama,satuan,stok,harga_agen,harga_sales',
            ',Matematika 1 Edisi 5 Smt 1 25/26,exp,10,50000,55000',
        ]);

        $file = UploadedFile::fake()->createWithContent('barang.csv', $csv);

        $response = $this->actingAs($user)->from(route('products.index'))->post(route('products.import'), [
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Kolom wajib pada file import belum lengkap', (string) session('error'));
        $this->assertStringContainsString('Kategori', (string) session('error'));
        $this->assertStringContainsString('Harga Umum', (string) session('error'));
    }

    public function test_product_import_xlsx_keeps_stock_from_template_with_uppercase_category(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [
                'masters.products.view',
                'masters.products.manage',
            ],
        ]);

        ItemCategory::query()->create([
            'code' => 'cerdas',
            'name' => 'Cerdas',
        ]);

        $file = $this->productXlsx([
            ['kode', 'nama', 'kategori', 'satuan', 'stok', 'harga_agen', 'harga_sales', 'harga_umum'],
            ['', 'BHS INDONESIA 1 EDISI 7 HAL80 SMT 1 25/26', 'CERDAS', 'exp', 75, 3500, 4500, 12000],
            ['', 'BHS INDONESIA 2 EDISI 7 HAL80 SMT 1 25/26', 'CERDAS', 'exp', 2300, 3500, 4500, 12000],
        ]);

        $response = $this->actingAs($user)->post(route('products.import'), [
            'import_file' => $file,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'name' => 'BHS INDONESIA 1 EDISI 7 HAL80 SMT 1 25/26',
            'stock' => 75,
        ]);
        $this->assertDatabaseHas('products', [
            'name' => 'BHS INDONESIA 2 EDISI 7 HAL80 SMT 1 25/26',
            'stock' => 2300,
        ]);
    }

    public function test_product_import_accepts_localized_stock_and_price_numbers(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'permissions' => [
                'masters.products.view',
                'masters.products.manage',
            ],
        ]);

        ItemCategory::query()->create([
            'code' => 'pintar',
            'name' => 'Pintar',
        ]);

        $file = $this->productXlsx([
            ['kode', 'nama', 'kategori', 'satuan', 'stok', 'harga_agen', 'harga_sales', 'harga_umum'],
            ['', 'MATEMATIKA 1 EDISI 7 HAL80 SMT 1 25/26', 'PINTAR', 'exp', '1.000', 'Rp 3.500', '4.500', '12.000'],
        ], asText: true);

        $response = $this->actingAs($user)->post(route('products.import'), [
            'import_file' => $file,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'name' => 'MATEMATIKA 1 EDISI 7 HAL80 SMT 1 25/26',
            'stock' => 1000,
            'price_agent' => 3500,
            'price_sales' => 4500,
            'price_general' => 12000,
        ]);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function productXlsx(array $rows, bool $asText = false): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowNumber => $row) {
            foreach ($row as $columnNumber => $value) {
                $coordinate = [$columnNumber + 1, $rowNumber + 1];
                if ($asText && $rowNumber > 0) {
                    $sheet->getCell($coordinate)->setValueExplicit((string) $value, DataType::TYPE_STRING);

                    continue;
                }

                $sheet->getCell($coordinate)->setValue($value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'product-import-');
        $xlsxPath = $path.'.xlsx';
        rename($path, $xlsxPath);

        (new Xlsx($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $xlsxPath,
            'barang.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
