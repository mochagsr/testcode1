<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Trait for efficient data chunking and streaming to reduce memory usage on limited VPS.
 * Useful for processing large datasets without loading everything into memory.
 */
trait EfficientQueryProcessing
{
    /**
     * Process large dataset in chunks without loading all into memory.
     *
     * @param  callable  $callback The function to process each chunk
     * @param  int  $chunkSize The size of each chunk (default 500 for 2GB RAM)
     * @return void
     */
    public static function processInChunks(callable $callback, int $chunkSize = 500): void
    {
        static::query()->chunk($chunkSize, $callback);
    }

    /**
     * Lazy load results using cursor for memory efficiency.
     * Useful for exporting large datasets to CSV/Excel.
     *
     * @return \Generator
     */
    public static function lazyLoad(): \Generator
    {
        foreach (static::query()->cursor() as $item) {
            yield $item;
        }
    }

    /**
     * Get paginated results with optimized memory usage.
     *
     * @param  int  $perPage Items per page (default 15 for limited RAM)
     * @param  string  $pageName Page query string name
     * @param  int  $page Current page number
     * @return LengthAwarePaginator
     */
    public static function paginateEfficiently(
        int $perPage = 15,
        string $pageName = 'page',
        int $page = 1
    ): LengthAwarePaginator {
        return static::query()->paginate($perPage, ['*'], $pageName, $page);
    }
}
