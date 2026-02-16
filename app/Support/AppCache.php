<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

final class AppCache
{
    private const LOOKUP_CACHE_VERSION_KEY = 'lookups.cache.version';
    private const DEFAULT_LOOKUP_VERSION = 1;

    /**
     * Forget all report option caches.
     */
    public static function forgetReportOptionCaches(): void
    {
        $lookupPrefixes = [
            'reports.receivable_customers.options',
            'reports.outgoing_suppliers.options',
            'reports.semester_options',
            'sales_invoices.index.semester_options.base',
            'receivables.index.semester_options.base',
            'sales_returns.index.semester_options.base',
            'delivery_notes.index.semester_options.base',
            'order_notes.index.semester_options.base',
            'outgoing_transactions.index.semester_options.base',
            'outgoing_transactions.index.supplier_options',
        ];

        foreach ($lookupPrefixes as $prefix) {
            // Legacy/non-versioned key.
            Cache::forget($prefix);

            // Versioned key used by AppCache::lookupCacheKey(...).
            self::forgetLookupKey($prefix);
        }
    }
    /**
     * Forget sales invoice and related summary caches.
     */
    public static function forgetSalesInvoiceTodaySummaryCaches(): void
    {
        $today = now('Asia/Jakarta')->toDateString();

        foreach (['all', 'active', 'canceled'] as $status) {
            self::forgetLookupKey('sales_invoices.index.today_summary', [
                'status' => $status,
                'date' => $today,
            ]);
            self::forgetLookupKey('sales_returns.index.today_summary', [
                'status' => $status,
                'date' => $today,
            ]);
            self::forgetLookupKey('delivery_notes.index.today_summary', [
                'status' => $status,
                'date' => $today,
            ]);
            self::forgetLookupKey('order_notes.index.today_summary', [
                'status' => $status,
                'date' => $today,
            ]);

            // Legacy/non-versioned keys.
            Cache::forget('sales_invoices.index.today_summary.' . $status);
            Cache::forget('sales_returns.index.today_summary.' . $status);
            Cache::forget('delivery_notes.index.today_summary.' . $status);
            Cache::forget('order_notes.index.today_summary.' . $status);
        }
    }

    /**
     * @param  array<int, string|null>  $dates
     */
    public static function forgetDashboardSummaryCaches(array $dates = []): void
    {
        $monthKeys = collect($dates)
            ->prepend(now('Asia/Jakarta')->format('Y-m'))
            ->map(function (?string $date): ?string {
                if ($date === null || trim($date) === '') {
                    return null;
                }

                try {
                    return Carbon::parse($date, 'Asia/Jakarta')->format('Y-m');
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter(fn(?string $month): bool => $month !== null)
            ->unique()
            ->values();

        foreach ($monthKeys as $month) {
            Cache::forget('dashboard.summary.' . $month . '.with_outgoing');
            Cache::forget('dashboard.summary.' . $month . '.without_outgoing');
        }
    }

    /**
     * @param  array<int, string|null>  $dates
     */
    public static function forgetAfterFinancialMutation(array $dates = []): void
    {
        self::forgetReportOptionCaches();
        self::forgetSalesInvoiceTodaySummaryCaches();
        self::forgetDashboardSummaryCaches($dates);
        self::bumpLookupVersion();
    }

    /**
     * Get the current lookup cache version.
     *
     * @return int The current cache version number
     */
    public static function lookupVersion(): int
    {
        return max(self::DEFAULT_LOOKUP_VERSION, (int) Cache::get(self::LOOKUP_CACHE_VERSION_KEY, self::DEFAULT_LOOKUP_VERSION));
    }

    /**
     * Increment the lookup cache version to invalidate existing caches.
     *
     * @return int The new cache version number
     */
    public static function bumpLookupVersion(): int
    {
        $next = self::lookupVersion() + 1;
        Cache::forever(self::LOOKUP_CACHE_VERSION_KEY, $next);

        return $next;
    }

    /**
     * Generate a versioned cache key with parameters.
     *
     * @param  string  $prefix The cache key prefix
     * @param  array<string, mixed>  $params Cache parameters
     * @return string The generated cache key
     */
    public static function lookupCacheKey(string $prefix, array $params = []): string
    {
        ksort($params);
        $jsonEncoded = json_encode($params, JSON_UNESCAPED_UNICODE) ?: '';
        return $prefix . '.v' . self::lookupVersion() . '.' . md5($jsonEncoded);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function forgetLookupKey(string $prefix, array $params = []): void
    {
        Cache::forget(self::lookupCacheKey($prefix, $params));
    }
}
