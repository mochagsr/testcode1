<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Product;

/**
 * Observer for Product model.
 */
final class ProductAuditObserver extends BaseModelAuditObserver
{
    public function created(Product $product): void
    {
        $this->logCreated(
            $product,
            "Product '{$product->name}' created with code '{$product->code}'"
        );
    }

    public function updated(Product $product): void
    {
        $this->logUpdated($product);
    }

    public function deleted(Product $product): void
    {
        $this->logDeleted($product, "Product '{$product->name}' deleted");
    }
}
