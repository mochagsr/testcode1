<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(): View
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('customers') || ! Schema::hasTable('sales_invoices')) {
            return view('dashboard', [
                'summary' => [
                    'total_products' => 0,
                    'total_customers' => 0,
                    'total_receivable' => 0,
                    'invoice_this_month' => 0,
                ],
                'uncollectedCustomers' => new LengthAwarePaginator(
                    items: [],
                    total: 0,
                    perPage: 20,
                    currentPage: 1,
                    options: ['path' => request()->url(), 'query' => request()->query()]
                ),
            ]);
        }

        $summary = [
            'total_products' => Product::count(),
            'total_customers' => Customer::count(),
            'total_receivable' => Customer::sum('outstanding_receivable'),
            'invoice_this_month' => SalesInvoice::query()
                ->whereYear('invoice_date', now()->year)
                ->whereMonth('invoice_date', now()->month)
                ->sum('total'),
        ];

        $uncollectedCustomers = Customer::query()
            ->select(['id', 'name', 'city', 'outstanding_receivable'])
            ->where('outstanding_receivable', '>', 0)
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard', [
            'summary' => $summary,
            'uncollectedCustomers' => $uncollectedCustomers,
        ]);
    }
}
