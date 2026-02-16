<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service to monitor and log performance metrics.
 * Helps identify bottlenecks on limited VPS resources.
 */
final class PerformanceMonitor
{
    private static bool $queryLoggingEnabled = false;

    /**
     * Enable query logging to identify slow queries.
     * WARNING: Only use in development/staging, not production!
     *
     * @return void
     */
    public static function enableQueryLogging(): void
    {
        if (!config('app.debug') || self::$queryLoggingEnabled) {
            return;
        }

        $thresholdMs = max(1, (int) config('app.slow_query_threshold_ms', 100));

        DB::listen(function ($query) use ($thresholdMs): void {
            if ($query->time > $thresholdMs) {
                Log::warning('Slow Query Detected', [
                    'query' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                    'threshold_ms' => $thresholdMs,
                ]);
            }
        });

        self::$queryLoggingEnabled = true;
    }

    /**
     * Log memory usage for monitoring VPS consumption.
     *
     * @param  string  $label Description of operation
     * @return void
     */
    public static function logMemoryUsage(string $label): void
    {
        if (config('app.debug')) {
            $usage = memory_get_usage(true) / 1024 / 1024;
            $peak = memory_get_peak_usage(true) / 1024 / 1024;

            Log::info("Memory Usage - {$label}", [
                'current_mb' => round($usage, 2),
                'peak_mb' => round($peak, 2),
            ]);
        }
    }

    /**
     * Get current memory usage in MB.
     *
     * @return float
     */
    public static function getCurrentMemoryUsage(): float
    {
        return memory_get_usage(true) / 1024 / 1024;
    }

    /**
     * Get peak memory usage in MB.
     *
     * @return float
     */
    public static function getPeakMemoryUsage(): float
    {
        return memory_get_peak_usage(true) / 1024 / 1024;
    }
}
