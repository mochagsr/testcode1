<?php

declare(strict_types=1);

use App\Models\OutgoingTransaction;
use App\Models\OutgoingTransactionItem;
use App\Models\Product;
use App\Models\StockMutation;
use App\Support\ProductCodeGenerator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$trxNumberFilter = null;
foreach (array_slice($_SERVER['argv'] ?? [], 1) as $arg) {
    if (str_starts_with((string) $arg, '--trx=')) {
        $trxNumberFilter = trim((string) substr((string) $arg, 6));
    }
}

/** @var ProductCodeGenerator $generator */
$generator = $app->make(ProductCodeGenerator::class);

$query = OutgoingTransactionItem::query()
    ->with(['outgoingTransaction', 'category'])
    ->whereNull('product_id')
    ->whereNotNull('item_category_id');

if ($trxNumberFilter !== null && $trxNumberFilter !== '') {
    $query->whereHas('outgoingTransaction', fn ($trxQuery) => $trxQuery->where('transaction_number', $trxNumberFilter));
}

$items = $query->orderBy('id')->get();

if ($items->isEmpty()) {
    echo "No outgoing manual items need backfill.\n";
    exit(0);
}

$backfilled = 0;

DB::transaction(function () use ($items, $generator, &$backfilled): void {
    foreach ($items as $item) {
        $name = trim((string) $item->product_name);
        $categoryId = (int) ($item->item_category_id ?? 0);
        if ($name === '' || $categoryId <= 0) {
            continue;
        }

        $product = Product::query()
            ->lockForUpdate()
            ->where('item_category_id', $categoryId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name, 'UTF-8')])
            ->first();

        if ($product === null) {
            $categoryName = trim((string) ($item->category?->name ?? ''));
            $unitCost = (int) round((float) $item->unit_cost);
            $product = Product::query()->create([
                'item_category_id' => $categoryId,
                'code' => $generator->resolve(null, $name, null, $categoryName !== '' ? $categoryName : null),
                'name' => $name,
                'unit' => trim((string) ($item->unit ?? '')) !== '' ? trim((string) $item->unit) : 'exp',
                'stock' => 0,
                'price_agent' => max(0, $unitCost),
                'price_sales' => max(0, $unitCost),
                'price_general' => max(0, $unitCost),
                'is_active' => true,
            ]);
        }

        $item->update([
            'product_id' => (int) $product->id,
            'product_code' => (string) $product->code,
            'product_name' => (string) $product->name,
            'unit' => trim((string) ($product->unit ?? $item->unit)) !== ''
                ? trim((string) ($product->unit ?? $item->unit))
                : 'exp',
        ]);

        $qty = (int) round((float) $item->quantity);
        if ($qty > 0) {
            $product->increment('stock', $qty);
            StockMutation::query()->create([
                'product_id' => (int) $product->id,
                'reference_type' => OutgoingTransaction::class,
                'reference_id' => (int) $item->outgoing_transaction_id,
                'mutation_type' => 'in',
                'quantity' => $qty,
                'notes' => __('txn.outgoing_stock_mutation_note', [
                    'number' => (string) ($item->outgoingTransaction?->transaction_number ?? '-'),
                ]),
                'created_by_user_id' => null,
            ]);
        }

        $backfilled++;
        echo "Backfilled item #{$item->id} -> product #{$product->id} ({$product->code})\n";
    }
});

echo "Done. Backfilled {$backfilled} item(s).\n";
