<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\Spk;
use App\Models\SpkStage;
use App\Models\StockMutation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionCalendarTest extends TestCase
{
    use RefreshDatabase;

    private ?User $adminUser = null;

    private function admin(): User
    {
        return $this->adminUser ??= User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
    }

    private function generalProduct(int $stock = 0): Product
    {
        $category = ItemCategory::query()->firstOrCreate(['code' => 'buku'], ['name' => 'Buku', 'type' => 'general']);

        return Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'BK-'.uniqid(),
            'name' => 'Buku Jadi',
            'unit' => 'pcs',
            'stock' => $stock,
            'price_agent' => 1000, 'price_sales' => 1100, 'price_general' => 1200,
            'is_active' => true, 'product_type' => 'general',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function spkPayload(array $overrides = []): array
    {
        return array_merge([
            'konsumen' => 'Pustaka Grafika',
            'alamat' => 'Malang',
            'jenis_order' => 'Cover LKS',
            'tanggal_order' => '2026-07-03',
            'deadline_kirim' => '2026-07-07',
            'pakai_web' => '1',
            'pakai_sheet' => '1',
            'jenis_cetak' => 'penuh',
            'finishing' => 'Stitching',
            'items' => [
                ['nama_barang' => 'Pendidikan Pancasila', 'halaman' => '64', 'kelas' => '7', 'cetak_isi' => 6000, 'cetak_sheet' => 6000],
            ],
            'pj' => [['jabatan' => 'PPIC', 'nama' => 'Andri']],
        ], $overrides);
    }

    public function test_index_renders_for_permitted_user(): void
    {
        $this->actingAs($this->admin())
            ->get(route('produksi.kalender.index'))
            ->assertOk()
            ->assertSee('Kalender Produksi');
    }

    public function test_index_forbidden_without_permission(): void
    {
        $user = User::factory()->create(['role' => 'user', 'permissions' => ['dashboard.view']]);
        $this->actingAs($user)->get(route('produksi.kalender.index'))->assertForbidden();
    }

    public function test_menu_visible_only_with_permission(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))->assertOk()->assertSee(route('produksi.kalender.index'), false);

        $user = User::factory()->create(['role' => 'user', 'permissions' => ['dashboard.view']]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee(route('produksi.kalender.index'), false);
    }

    public function test_store_auto_generates_no_spk_and_resets_per_semester(): void
    {
        $this->actingAs($this->admin())->post(route('produksi.spk.store'), $this->spkPayload())->assertRedirect();
        $this->actingAs($this->admin())->post(route('produksi.spk.store'), $this->spkPayload(['tanggal_order' => '2026-07-10', 'deadline_kirim' => '2026-07-15']))->assertRedirect();

        // Both July 2026 -> semester S1-2627, sequence 1 then 2.
        $this->assertSame('1/SPK/VII/2026', Spk::query()->orderBy('id')->first()->no_spk);
        $this->assertSame('2/SPK/VII/2026', Spk::query()->orderByDesc('id')->first()->no_spk);

        // A January order is a different semester -> sequence resets to 1.
        $this->actingAs($this->admin())->post(route('produksi.spk.store'), $this->spkPayload(['tanggal_order' => '2026-01-05', 'deadline_kirim' => '2026-01-09']))->assertRedirect();
        $jan = Spk::query()->whereDate('tanggal_order', '2026-01-05')->first();
        $this->assertSame('1/SPK/I/2026', $jan->no_spk);
        $this->assertSame(1, $jan->nomor_urut);
    }

    public function test_store_creates_four_stages_with_planned_quantities(): void
    {
        $this->actingAs($this->admin())->post(route('produksi.spk.store'), $this->spkPayload());
        $spk = Spk::query()->with('stages')->first();

        $this->assertCount(4, $spk->stages);
        $web = $spk->stages->firstWhere('tahap', SpkStage::TAHAP_WEB);
        $sheet = $spk->stages->firstWhere('tahap', SpkStage::TAHAP_SHEET);
        $this->assertSame(6000, (int) $web->qty_rencana);
        $this->assertSame(6000, (int) $sheet->qty_rencana);
    }

    public function test_finishing_realisasi_adds_finished_qty_to_general_stock_idempotently(): void
    {
        $product = $this->generalProduct(0);
        $this->actingAs($this->admin())->post(route('produksi.spk.store'), $this->spkPayload([
            'items' => [['nama_barang' => 'Pendidikan Pancasila', 'product_id' => $product->id, 'cetak_isi' => 6000, 'cetak_sheet' => 6000]],
        ]));
        $spk = Spk::query()->with(['stages', 'items'])->first();
        $finishing = $spk->stages->firstWhere('tahap', SpkStage::TAHAP_FINISHING);
        $item = $spk->items->first();

        $realisasi = fn (bool $finishingDone) => [
            'stages' => [['id' => $finishing->id, 'qty_realisasi' => 5900, 'tanggal_realisasi' => '2026-07-06', 'selesai' => $finishingDone ? '1' : '0']],
            'items' => [['id' => $item->id, 'jumlah_jadi_realisasi' => 5000]],
        ];

        // Finishing selesai -> 5000 masuk stok.
        $this->actingAs($this->admin())->post(route('produksi.spk.realisasi', $spk), $realisasi(true))->assertRedirect();
        $this->assertSame(5000, (int) $product->fresh()->stock);
        $this->assertSame(1, StockMutation::query()->where('reference_type', Spk::class)->count());

        // Simpan ulang nilai sama -> tidak dobel.
        $this->actingAs($this->admin())->post(route('produksi.spk.realisasi', $spk), $realisasi(true))->assertRedirect();
        $this->assertSame(5000, (int) $product->fresh()->stock);

        // Un-check finishing -> stok dikembalikan ke 0.
        $this->actingAs($this->admin())->post(route('produksi.spk.realisasi', $spk), $realisasi(false))->assertRedirect();
        $this->assertSame(0, (int) $product->fresh()->stock);
    }

    public function test_realisasi_forbidden_without_permission(): void
    {
        $this->actingAs($this->admin())->post(route('produksi.spk.store'), $this->spkPayload());
        $spk = Spk::query()->first();

        $viewer = User::factory()->create(['role' => 'user', 'permissions' => ['produksi.view']]);
        $this->actingAs($viewer)->post(route('produksi.spk.realisasi', $spk), ['stages' => []])->assertForbidden();
    }

    public function test_show_drawer_and_print_render(): void
    {
        $this->actingAs($this->admin())->post(route('produksi.spk.store'), $this->spkPayload());
        $spk = Spk::query()->first();

        $this->actingAs($this->admin())->get(route('produksi.spk.show', $spk))->assertOk()->assertSee($spk->no_spk);
        $this->actingAs($this->admin())->get(route('produksi.spk.cetak', $spk))->assertOk()->assertSee('SURAT PERINTAH KERJA');
    }

    public function test_month_grid_renders_spk_bar_for_its_month(): void
    {
        // today() is 2026-07-24 in this suite, so the default month is July 2026.
        $this->actingAs($this->admin())->post(route('produksi.spk.store'), $this->spkPayload());

        $this->actingAs($this->admin())
            ->get(route('produksi.kalender.index', ['year' => 2026, 'month' => 7]))
            ->assertOk()
            ->assertSee('1/SPK/VII/2026')
            ->assertSee('pk-bar', false);
    }

    public function test_ajax_month_navigation_returns_region_fragment(): void
    {
        $this->actingAs($this->admin())
            ->get(route('produksi.kalender.index', ['year' => 2026, 'month' => 8]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('pk-data-json', false)
            ->assertDontSee('<html', false);
    }
}
