<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Optimized caching service for VPS with limited resources.
 * Reduces database queries through strategic caching.
 */
final class OptimizedCache
{
    /**
     * Cache duration for frequently accessed data (1 hour).
     */
    private const DURATION_FREQUENT = 3600;

    /**
     * Cache duration for moderately accessed data (1 day).
     */
    private const DURATION_MODERATE = 86400;

    /**
     * Cache duration for rarely changing data (1 week).
     */
    private const DURATION_STABLE = 604800;

    /**
     * Get or compute customer summary stats (outstanding receivable, invoice count).
     *
     * @param  int  $customerId
     * @return array<string, mixed>
     */
    public static function customerSummary(int $customerId): array
    {
        $key = "customer.{$customerId}.summary";

        return Cache::remember($key, self::DURATION_FREQUENT, function () use ($customerId) {
            $customer = \App\Models\Customer::query()
                ->select(['id', 'outstanding_receivable', 'credit_balance'])
                ->find($customerId);

            if (!$customer) {
                return [];
            }

            $invoiceCount = \App\Models\SalesInvoice::query()
                ->where('customer_id', $customerId)
                ->where('is_canceled', false)
                ->count();

            return [
                'outstanding' => $customer->outstanding_receivable,
                'credit_balance' => $customer->credit_balance,
                'invoice_count' => $invoiceCount,
            ];
        });
    }

    /**
     * Get or compute product inventory stats.
     *
     * @param  int  $productId
     * @return array<string, mixed>
     */
    public static function productInventory(int $productId): array
    {
        $key = "product.{$productId}.inventory";

        return Cache::remember($key, self::DURATION_FREQUENT, function () use ($productId) {
            $product = \App\Models\Product::query()
                ->select(['id', 'stock', 'price_agent', 'price_sales', 'price_general'])
                ->find($productId);

            if (!$product) {
                return [];
            }

            return [
                'stock' => $product->stock,
                'price_agent' => $product->price_agent,
                'price_sales' => $product->price_sales,
                'price_general' => $product->price_general,
            ];
        });
    }

    /**
     * Forget customer summary cache when customer is modified.
     *
     * @param  int  $customerId
     * @return void
     */
    public static function forgetCustomerSummary(int $customerId): void
    {
        Cache::forget("customer.{$customerId}.summary");
    }

    /**
     * Forget product inventory cache when product is modified.
     *
     * @param  int  $productId
     * @return void
     */
    public static function forgetProductInventory(int $productId): void
    {
        Cache::forget("product.{$productId}.inventory");
    }

    /**
     * Clear all application caches (use sparingly).
     *
     * @return void
     */
    public static function clearAllCaches(): void
    {
        Cache::flush();
    }
}