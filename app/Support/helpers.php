<?php

declare(strict_types=1);

use App\Support\OptimizedCache;
use App\Support\PerformanceMonitor;
use Illuminate\Database\Eloquent\Model;

/**
 * VPS-Optimized Helper Functions
 * Reduce boilerplate code and standardize performance-conscious queries
 */

/**
 * Get customer summary with caching.
 *
 * @param  int  $customerId
 * @return array<string, mixed>
 */
function getCustomerSummary(int $customerId): array
{
    return OptimizedCache::customerSummary($customerId);
}

/**
 * Get product inventory with caching.
 *
 * @param  int  $productId
 * @return array<string, mixed>
 */
function getProductInventory(int $productId): array
{
    return OptimizedCache::productInventory($productId);
}

/**
 * Invalidate customer cache when customer is modified.
 *
 * @param  int  $customerId
 * @return void
 */
function invalidateCustomerCache(int $customerId): void
{
    OptimizedCache::forgetCustomerSummary($customerId);
}

/**
 * Invalidate product cache when product is modified.
 *
 * @param  int  $productId
 * @return void
 */
function invalidateProductCache(int $productId): void
{
    OptimizedCache::forgetProductInventory($productId);
}

/**
 * Log memory usage for debugging (development only).
 *
 * @param  string  $label
 * @return void
 */
function logMemory(string $label): void
{
    PerformanceMonitor::logMemoryUsage($label);
}

/**
 * Get current memory usage in MB.
 *
 * @return float
 */
function getMemoryUsage(): float
{
    return PerformanceMonitor::getCurrentMemoryUsage();
}

/**
 * Format memory usage for display.
 *
 * @param  float  $mb
 * @return string
 */
function formatMemory(float $mb): string
{
    return round($mb, 2) . ' MB';
}

/**
 * Apply common list view query optimizations.
 * Usage: $items = applyListOptimization($query)->paginate(15);
 *
 * @param  Illuminate\Database\Eloquent\Builder  $query
 * @return Illuminate\Database\Eloquent\Builder
 */
function applyListOptimization($query)
{
    // Override this in specific queries if needed
    return $query;
}

/**
 * Safely fetch a model by ID with caching.
 *
 * @param  class-string<T>  $modelClass
 * @param  int  $id
 * @param  int  $cacheTtl Cache duration in seconds
 * @return T|null
 *
 * @template T of Model
 */
function getCachedModel(string $modelClass, int $id, int $cacheTtl = 3600): ?Model
{
    $cacheKey = strtolower(class_basename($modelClass)) . ".{$id}";

    return \Illuminate\Support\Facades\Cache::remember(
        $cacheKey,
        $cacheTtl,
        fn() => $modelClass::find($id)
    );
}

/**
 * Forget a specific model from cache.
 *
 * @param  class-string  $modelClass
 * @param  int  $id
 * @return void
 */
function forgetCachedModel(string $modelClass, int $id): void
{
    $cacheKey = strtolower(class_basename($modelClass)) . ".{$id}";
    \Illuminate\Support\Facades\Cache::forget($cacheKey);
}
