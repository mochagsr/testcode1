<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AppCache
{
    private const LOOKUP_CACHE_VERSION_KEY = 'lookups.cache.version';

    public static function forgetReportOptionCaches(): void
    {
        $keys = [
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

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    public static function forgetSalesInvoiceTodaySummaryCaches(): void
    {
        foreach (['all', 'active', 'canceled'] as $status) {
            Cache::forget('sales_invoices.index.today_summary.'.$status);
            Cache::forget('sales_returns.index.today_summary.'.$status);
            Cache::forget('delivery_notes.index.today_summary.'.$status);
            Cache::forget('order_notes.index.today_summary.'.$status);
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
            ->filter(fn (?string $month): bool => $month !== null)
            ->unique()
            ->values();

        foreach ($monthKeys as $month) {
            Cache::forget('dashboard.summary.'.$month.'.with_outgoing');
            Cache::forget('dashboard.summary.'.$month.'.without_outgoing');
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

    public static function lookupVersion(): int
    {
        return max(1, (int) Cache::get(self::LOOKUP_CACHE_VERSION_KEY, 1));
    }

    public static function bumpLookupVersion(): int
    {
        $next = self::lookupVersion() + 1;
        Cache::forever(self::LOOKUP_CACHE_VERSION_KEY, $next);

        return $next;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function lookupCacheKey(string $prefix, array $params = []): string
    {
        ksort($params);
        return $prefix.'.v'.self::lookupVersion().'.'.md5(json_encode($params, JSON_UNESCAPED_UNICODE) ?: '');
    }
}
