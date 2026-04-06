<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
