<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Spk;
use App\Models\SpkItem;
use App\Models\SpkStage;
use App\Models\StockMutation;
use App\Support\AppCache;
use Illuminate\Support\Facades\DB;

/**
 * Posts finished production output into general-product stock.
 *
 * When an SPK's Finishing stage is marked "Selesai", each item's actual
 * finished quantity (jumlah_jadi_realisasi) is added to the stock of the
 * general product it is linked to. Raw-material products are never touched.
 *
 * The operation is idempotent: each item remembers how much it has already
 * posted (stock_posted_qty), so re-saving realisasi, editing the quantity, or
 * un-checking "Selesai" only applies the difference (which may be negative).
 */
final class ProductionStockService
{
    /**
     * Reconcile an SPK's finished-goods stock with the current finishing state.
     * Safe to call repeatedly; only the delta per item is applied.
     */
    public function syncFinishedGoods(Spk $spk, ?int $userId): void
    {
        $spk->loadMissing(['items', 'stages']);

        $finishing = $spk->stages->firstWhere('tahap', SpkStage::TAHAP_FINISHING);
        $finished = $finishing !== null && (bool) $finishing->selesai;

        DB::transaction(function () use ($spk, $finished, $userId): void {
            foreach ($spk->items as $item) {
                $this->syncItem($spk, $item, $finished, $userId);
            }
        });

        AppCache::forgetAfterFinancialMutation();
    }

    private function syncItem(Spk $spk, SpkItem $item, bool $finished, ?int $userId): void
    {
        $productId = (int) ($item->product_id ?? 0);
        if ($productId <= 0) {
            return; // item not linked to a general product
        }

        // Target stock contribution: the finished quantity once finishing is done,
        // otherwise nothing (so un-checking finishing reverses the earlier post).
        $target = $finished ? (int) ($item->jumlah_jadi_realisasi ?? 0) : 0;
        $posted = (int) ($item->stock_posted_qty ?? 0);
        $delta = $target - $posted;

        if ($delta === 0) {
            return;
        }

        $product = Product::query()->lockForUpdate()->find($productId);
        if ($product === null) {
            return; // product removed; leave posted qty as-is
        }

        $product->update(['stock' => max(0, (int) $product->stock + $delta)]);

        StockMutation::query()->create([
            'product_id' => $productId,
            'reference_type' => Spk::class,
            'reference_id' => (int) $spk->id,
            'mutation_type' => $delta > 0 ? 'in' : 'out',
            'quantity' => abs($delta),
            'notes' => 'Hasil produksi SPK '.$spk->no_spk,
            'created_by_user_id' => $userId,
        ]);

        $item->forceFill(['stock_posted_qty' => $target])->save();
    }
}
