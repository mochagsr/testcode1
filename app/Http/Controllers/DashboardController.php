<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use Illuminate\Contracts\View\View;
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
                'recentInvoices' => collect(),
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

        $recentInvoices = SalesInvoice::query()
            ->with('customer:id,name,city')
            ->latest('invoice_date')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('dashboard', [
            'summary' => $summary,
            'recentInvoices' => $recentInvoices,
        ]);
    }
}
