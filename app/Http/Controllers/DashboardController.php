<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Support\AppCache;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now = now();
        $currentPath = request()->url();
        $currentQuery = request()->query();

        if (! $this->hasRequiredDashboardTables()) {
            return view('dashboard', [
                'summary' => [
                    'total_products' => 0,
                    'total_customers' => 0,
                    'total_receivable' => 0,
                    'invoice_this_month' => 0,
                    'outgoing_this_month' => 0,
                ],
                'uncollectedCustomers' => $this->emptyPaginator(20, $currentPath, $currentQuery),
                'supplierExpenseRecap' => $this->emptyPaginator(20, $currentPath, $currentQuery),
            ]);
        }

        $hasOutgoingTable = $this->hasOutgoingDashboardTables();

        $monthKey = $now->format('Y-m');
        $summaryCacheKey = AppCache::lookupCacheKey('dashboard.summary', [
            'month' => $monthKey,
            'mode' => $hasOutgoingTable ? 'with_outgoing' : 'without_outgoing',
        ]);
        $summary = Cache::remember($summaryCacheKey, $now->copy()->addSeconds(60), function () use ($hasOutgoingTable, $now): array {
            return [
                'total_products' => Product::count(),
                'total_customers' => Customer::count(),
                'total_receivable' => Customer::sum('outstanding_receivable'),
                'invoice_this_month' => SalesInvoice::query()
                    ->whereYear('invoice_date', $now->year)
                    ->whereMonth('invoice_date', $now->month)
                    ->sum('total'),
                'outgoing_this_month' => $hasOutgoingTable
                    ? OutgoingTransaction::query()
                    ->whereYear('transaction_date', $now->year)
                    ->whereMonth('transaction_date', $now->month)
                    ->sum('total')
                    : 0,
            ];
        });

        $uncollectedCustomers = Customer::query()
            ->onlyOutstandingColumns()
            ->withOutstanding()
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $supplierExpenseRecap = $this->emptyPaginator(20, $currentPath, $currentQuery);

        return view('dashboard', [
            'summary' => $summary,
            'uncollectedCustomers' => $uncollectedCustomers,
            'supplierExpenseRecap' => $supplierExpenseRecap,
        ]);
    }

    private function hasRequiredDashboardTables(): bool
    {
        return Cache::remember(AppCache::lookupCacheKey('dashboard.schema.required_tables'), now()->addMinutes(5), function (): bool {
            return Schema::hasTable('products')
                && Schema::hasTable('customers')
                && Schema::hasTable('sales_invoices');
        });
    }

    private function hasOutgoingDashboardTables(): bool
    {
        return Cache::remember(AppCache::lookupCacheKey('dashboard.schema.outgoing_tables'), now()->addMinutes(5), function (): bool {
            return Schema::hasTable('outgoing_transactions')
                && Schema::hasTable('suppliers');
        });
    }

    /**
     * @param array<string, mixed> $query
     */
    private function emptyPaginator(int $perPage, string $path, array $query): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: $perPage,
            currentPage: Paginator::resolveCurrentPage(),
            options: [
                'path' => $path,
                'query' => $query,
            ]
        );
    }
}
