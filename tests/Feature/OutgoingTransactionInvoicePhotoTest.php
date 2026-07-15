<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemCategory;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OutgoingTransactionInvoicePhotoTest extends TestCase
{
    use RefreshDatabase;

    private ?User $adminUser = null;

    private function admin(): User
    {
        return $this->adminUser ??= User::factory()->create(['role' => 'admin', 'permissions' => ['*']]);
    }

    private function supplier(): Supplier
    {
        return Supplier::query()->create(['name' => 'pt kober tenan']);
    }

    private function product(): Product
    {
        $category = ItemCategory::query()->create([
            'code' => 'rolweb',
            'name' => 'roll web',
            'type' => ItemCategory::TYPE_RAW_MATERIAL,
        ]);

        return Product::query()->create([
            'item_category_id' => $category->id,
            'code' => 'RW-68',
            'name' => 'kertas web 68gr',
            'unit' => 'exp',
            'stock' => 0,
            'price_agent' => 0,
            'price_sales' => 0,
            'price_general' => 0,
            'is_active' => true,
            'product_type' => 'raw_material',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Product $product, Supplier $supplier): array
    {
        return [
            'transaction_date' => '2026-07-15',
            'supplier_id' => $supplier->id,
            'note_number' => 'NOTA-1',
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit' => 'exp',
                'quantity' => 5,
                'unit_cost' => 1000,
            ]],
        ];
    }

    private function createTransactionWithPhoto(): OutgoingTransaction
    {
        $this->actingAs($this->admin())->post(
            route('outgoing-transactions.store'),
            $this->payload($this->product(), $this->supplier()) + [
                'supplier_invoice_photo' => UploadedFile::fake()->image('nota.jpg', 400, 300),
            ]
        );

        return OutgoingTransaction::query()->firstOrFail();
    }

    public function test_index_list_shows_view_photo_button_when_photo_exists(): void
    {
        Storage::fake('public');
        $transaction = $this->createTransactionWithPhoto();

        $this->actingAs($this->admin())
            ->get(route('outgoing-transactions.index'))
            ->assertOk()
            ->assertSee(__('supplier_payable.supplier_invoice_photo'))
            ->assertSee(__('supplier_payable.view_photo'))
            ->assertSee(asset('storage/'.$transaction->supplier_invoice_photo_path), false);
    }

    public function test_index_list_shows_no_photo_label_when_absent(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())->post(
            route('outgoing-transactions.store'),
            $this->payload($this->product(), $this->supplier())
        );

        $this->actingAs($this->admin())
            ->get(route('outgoing-transactions.index'))
            ->assertOk()
            ->assertSee(__('supplier_payable.no_photo'))
            ->assertDontSee(__('supplier_payable.view_photo'));
    }

    public function test_index_list_keeps_photo_column_when_sorted_by_supplier(): void
    {
        Storage::fake('public');
        $transaction = $this->createTransactionWithPhoto();

        $this->actingAs($this->admin())
            ->get(route('outgoing-transactions.index', ['sort' => 'supplier', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee(asset('storage/'.$transaction->supplier_invoice_photo_path), false);
    }

    public function test_admin_edit_can_add_photo_to_transaction_without_one(): void
    {
        Storage::fake('public');
        $supplier = $this->supplier();
        $product = $this->product();

        $this->actingAs($this->admin())->post(route('outgoing-transactions.store'), $this->payload($product, $supplier));
        $transaction = OutgoingTransaction::query()->firstOrFail();
        $this->assertNull($transaction->supplier_invoice_photo_path);

        $this->actingAs($this->admin())
            ->put(route('outgoing-transactions.admin-update', $transaction), [
                'transaction_date' => '2026-07-15',
                'supplier_id' => $supplier->id,
                'note_number' => 'NOTA-1',
                'supplier_invoice_photo' => UploadedFile::fake()->image('nota-baru.jpg', 400, 300),
                'items' => [[
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => 'exp',
                    'quantity' => 5,
                    'unit_cost' => 1000,
                ]],
            ])
            ->assertRedirect(route('outgoing-transactions.show', $transaction));

        $path = (string) $transaction->fresh()->supplier_invoice_photo_path;
        $this->assertNotSame('', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_admin_edit_replaces_photo_and_removes_the_old_file(): void
    {
        Storage::fake('public');
        $transaction = $this->createTransactionWithPhoto();
        $oldPath = (string) $transaction->supplier_invoice_photo_path;
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($this->admin())
            ->put(route('outgoing-transactions.admin-update', $transaction), [
                'transaction_date' => '2026-07-15',
                'supplier_id' => $transaction->supplier_id,
                'note_number' => 'NOTA-1',
                'supplier_invoice_photo' => UploadedFile::fake()->image('nota-ganti.jpg', 400, 300),
                'items' => [[
                    'product_id' => Product::query()->value('id'),
                    'product_name' => 'kertas web 68gr',
                    'unit' => 'exp',
                    'quantity' => 5,
                    'unit_cost' => 1000,
                ]],
            ])
            ->assertRedirect();

        $newPath = (string) $transaction->fresh()->supplier_invoice_photo_path;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_admin_edit_without_upload_keeps_existing_photo(): void
    {
        Storage::fake('public');
        $transaction = $this->createTransactionWithPhoto();
        $oldPath = (string) $transaction->supplier_invoice_photo_path;

        $this->actingAs($this->admin())
            ->put(route('outgoing-transactions.admin-update', $transaction), [
                'transaction_date' => '2026-07-15',
                'supplier_id' => $transaction->supplier_id,
                'note_number' => 'NOTA-DIUBAH',
                'items' => [[
                    'product_id' => Product::query()->value('id'),
                    'product_name' => 'kertas web 68gr',
                    'unit' => 'exp',
                    'quantity' => 7,
                    'unit_cost' => 1000,
                ]],
            ])
            ->assertRedirect();

        $fresh = $transaction->fresh();
        $this->assertSame($oldPath, (string) $fresh->supplier_invoice_photo_path);
        $this->assertSame('NOTA-DIUBAH', $fresh->note_number);
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_show_page_puts_edit_behind_a_button_instead_of_an_inline_form(): void
    {
        Storage::fake('public');
        $transaction = $this->createTransactionWithPhoto();

        $content = (string) $this->actingAs($this->admin())
            ->get(route('outgoing-transactions.show', $transaction))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="open-admin-edit-modal"', $content);
        $this->assertStringContainsString('id="admin-edit-modal"', $content);
        // The edit form must live inside the modal, not inline on the page.
        $this->assertLessThan(
            strpos($content, 'id="admin-outgoing-edit-form"'),
            strpos($content, 'id="admin-edit-modal"'),
        );

        // Without the overlay styles the modal markup just renders inline.
        $this->assertStringContainsString('.txn-modal {', $content);
        $this->assertStringContainsString('.txn-modal.open {', $content);
    }
}
