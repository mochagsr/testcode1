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
            __('ui.audit_desc_product_created', [
                'name' => (string) $product->name,
                'code' => (string) ($product->code ?? '-'),
            ])
        );
    }

    public function updated(Product $product): void
    {
        $this->logUpdated($product);
    }

    public function deleted(Product $product): void
    {
        $this->logDeleted($product, __('ui.audit_desc_product_deleted', [
            'name' => (string) $product->name,
        ]));
    }
}
