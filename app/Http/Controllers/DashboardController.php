<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use App\Models\SalesInvoice;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now = now();

        if (! Schema::hasTable('products') || ! Schema::hasTable('customers') || ! Schema::hasTable('sales_invoices')) {
            return view('dashboard', [
                'summary' => [
                    'total_products' => 0,
                    'total_customers' => 0,
                    'total_receivable' => 0,
                    'invoice_this_month' => 0,
                    'outgoing_this_month' => 0,
                ],
                'uncollectedCustomers' => new LengthAwarePaginator(
                    items: [],
                    total: 0,
                    perPage: 20,
                    currentPage: 1,
                    options: ['path' => request()->url(), 'query' => request()->query()]
                ),
                'supplierExpenseRecap' => new LengthAwarePaginator(
                    items: [],
                    total: 0,
                    perPage: 20,
                    currentPage: 1,
                    options: ['path' => request()->url(), 'query' => request()->query()]
                ),
            ]);
        }

        $hasOutgoingTable = Schema::hasTable('outgoing_transactions') && Schema::hasTable('suppliers');

        $monthKey = $now->format('Y-m');
        $summaryCacheKey = 'dashboard.summary.' . $monthKey . '.' . ($hasOutgoingTable ? 'with_outgoing' : 'without_outgoing');
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
            ->select(['id', 'name', 'city', 'outstanding_receivable'])
            ->withOutstanding()
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $supplierExpenseRecap = new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: 20,
            currentPage: 1,
            options: ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('dashboard', [
            'summary' => $summary,
            'uncollectedCustomers' => $uncollectedCustomers,
            'supplierExpenseRecap' => $supplierExpenseRecap,
        ]);
    }
}
