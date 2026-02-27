<?php

namespace Tests\Unit;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Support\ProductCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_base_builds_expected_short_code_pattern(): void
    {
        $generator = app(ProductCodeGenerator::class);

        $code = $generator->generateBase('matematika 1 edisi 5 semester 1 tahun ajar 2025-2026');

        $this->assertSame('mt1e5s156', $code);
    }

    public function test_generate_base_supports_abbreviated_edition_semester_and_short_year(): void
    {
        $generator = app(ProductCodeGenerator::class);

        $code = $generator->generateBase('matematika 1 ed 5 smt 1 25/26');

        $this->assertSame('mt1e5s156', $code);
    }

    public function test_generate_base_supports_compact_short_year_without_separator(): void
    {
        $generator = app(ProductCodeGenerator::class);

        $code = $generator->generateBase('matematika 1 ed 5 smt 1 2526');

        $this->assertSame('mt1e5s156', $code);
    }

    public function test_generate_base_adds_c_prefix_for_cerdas_category(): void
    {
        $generator = app(ProductCodeGenerator::class);

        $code = $generator->generateBase('matematika 1 ed 5 smt 1 2526', 'cerdas');

        $this->assertSame('cmt1e5s156', $code);
    }

    public function test_generate_base_adds_p_prefix_for_pintar_category(): void
    {
        $generator = app(ProductCodeGenerator::class);

        $code = $generator->generateBase('matematika 1 ed 5 smt 1 2526', 'pintar');

        $this->assertSame('pmt1e5s156', $code);
    }

    public function test_generate_base_adds_prefix_for_paket_categories(): void
    {
        $generator = app(ProductCodeGenerator::class);

        $this->assertSame('psmt1e5s156', $generator->generateBase('matematika 1 ed 5 smt 1 2526', 'paket sd'));
        $this->assertSame('ppmt1e5s156', $generator->generateBase('matematika 1 ed 5 smt 1 2526', 'paket smp'));
        $this->assertSame('pamt1e5s156', $generator->generateBase('matematika 1 ed 5 smt 1 2526', 'paket sma'));
        $this->assertSame('pimt1e5s156', $generator->generateBase('matematika 1 ed 5 smt 1 2526', 'paket mi'));
        $this->assertSame('ptmt1e5s156', $generator->generateBase('matematika 1 ed 5 smt 1 2526', 'paket mts'));
    }

    public function test_normalize_input_cleans_manual_code(): void
    {
        $generator = app(ProductCodeGenerator::class);

        $normalized = $generator->normalizeInput('  MANUAL -- 001 !! ');

        $this->assertSame('manual-001', $normalized);
    }

    public function test_resolve_appends_sequence_for_conflicting_generated_code(): void
    {
        $category = ItemCategory::query()->create([
            'code' => 'CAT-01',
            'name' => 'Kategori',
        ]);

        Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'mt1e5s156',
            'name' => 'Existing',
            'unit' => 'pcs',
            'stock' => 1,
            'price_agent' => 1000,
            'price_sales' => 1000,
            'price_general' => 1000,
            'is_active' => true,
        ]);

        $generator = app(ProductCodeGenerator::class);

        $resolved = $generator->resolve('', 'matematika 1 edisi 5 semester 1 tahun ajar 2025-2026');

        $this->assertSame('mt1e5s15601', $resolved);
    }
}
