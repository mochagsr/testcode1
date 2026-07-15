<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ItemCategoryImportTypeTest extends TestCase
{
    use RefreshDatabase;

    private ?User $adminUser = null;

    private function admin(): User
    {
        return $this->adminUser ??= User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
    }

    private function csv(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cat').'.csv';
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'kategori.csv', 'text/csv', null, true);
    }

    public function test_import_maps_jenis_column_to_category_type(): void
    {
        $file = $this->csv(<<<'CSV'
        kode,nama,jenis,deskripsi
        buku,Buku Tulis,umum,
        rolweb,Roll Web,bahan baku,
        CSV);

        $this->actingAs($this->admin())
            ->post(route('item-categories.import'), ['import_file' => $file])
            ->assertRedirect();

        $this->assertSame(ItemCategory::TYPE_GENERAL, ItemCategory::query()->where('name', 'Buku Tulis')->value('type'));
        $this->assertSame(ItemCategory::TYPE_RAW_MATERIAL, ItemCategory::query()->where('name', 'Roll Web')->value('type'));
    }

    public function test_import_without_jenis_column_defaults_to_general(): void
    {
        $file = $this->csv(<<<'CSV'
        kode,nama,deskripsi
        buku,Buku Tulis,
        CSV);

        $this->actingAs($this->admin())
            ->post(route('item-categories.import'), ['import_file' => $file])
            ->assertRedirect();

        $this->assertSame(ItemCategory::TYPE_GENERAL, ItemCategory::query()->where('name', 'Buku Tulis')->value('type'));
    }

    public function test_import_template_contains_jenis_column(): void
    {
        $this->actingAs($this->admin())
            ->get(route('item-categories.import.template'))
            ->assertOk()
            ->assertDownload();
    }
}
