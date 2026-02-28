<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\OrderNote;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Support\AppCache;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now = now();
        $currentPath = request()->url();
        $currentQuery = request()->query();
        $uncollectedPerPage = 20;
        $pendingOrderNotesPerPage = 20;
        $uncollectedPageName = 'uncollected_customers_page';
        $pendingOrderNotesPageName = 'pending_order_notes_page';

        if (! $this->hasRequiredDashboardTables()) {
            return view('dashboard', [
                'summary' => [
                    'total_products' => 0,
                    'total_customers' => 0,
                    'total_receivable' => 0,
                    'invoice_this_month' => 0,
                    'outgoing_this_month' => 0,
                ],
                'uncollectedCustomers' => $this->emptyPaginator($uncollectedPerPage, $currentPath, $currentQuery, $uncollectedPageName),
                'pendingOrderNotes' => $this->emptyPaginator($pendingOrderNotesPerPage, $currentPath, $currentQuery, $pendingOrderNotesPageName),
                'supplierExpenseRecap' => $this->emptyPaginator(20, $currentPath, $currentQuery, 'supplier_expense_page'),
            ]);
        }

        $hasOutgoingTable = $this->hasOutgoingDashboardTables();
        $hasOrderNoteTable = $this->hasOrderNoteDashboardTables();

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
            ->paginate($uncollectedPerPage, ['*'], $uncollectedPageName)
            ->withQueryString();

        $supplierExpenseRecap = $this->emptyPaginator(20, $currentPath, $currentQuery, 'supplier_expense_page');
        $pendingOrderNotes = $hasOrderNoteTable
            ? $this->pendingOrderNotesPaginator($pendingOrderNotesPerPage, $pendingOrderNotesPageName)
            : $this->emptyPaginator($pendingOrderNotesPerPage, $currentPath, $currentQuery, $pendingOrderNotesPageName);

        return view('dashboard', [
            'summary' => $summary,
            'uncollectedCustomers' => $uncollectedCustomers,
            'pendingOrderNotes' => $pendingOrderNotes,
            'supplierExpenseRecap' => $supplierExpenseRecap,
        ]);
    }

    private function pendingOrderNotesPaginator(int $perPage, string $pageName): LengthAwarePaginator
    {
        $orderedSub = DB::table('order_note_items')
            ->selectRaw('order_note_id, COALESCE(SUM(quantity), 0) as ordered_total')
            ->groupBy('order_note_id');

        $fulfilledSub = DB::table('sales_invoices as si')
            ->join('sales_invoice_items as sii', 'sii.sales_invoice_id', '=', 'si.id')
            ->whereNull('si.deleted_at')
            ->where('si.is_canceled', false)
            ->whereNotNull('si.order_note_id')
            ->selectRaw('si.order_note_id, COALESCE(SUM(sii.quantity), 0) as fulfilled_total')
            ->groupBy('si.order_note_id');

        return OrderNote::query()
            ->from('order_notes')
            ->leftJoinSub($orderedSub, 'ordered_items', function ($join): void {
                $join->on('ordered_items.order_note_id', '=', 'order_notes.id');
            })
            ->leftJoinSub($fulfilledSub, 'fulfilled_items', function ($join): void {
                $join->on('fulfilled_items.order_note_id', '=', 'order_notes.id');
            })
            ->where('order_notes.is_canceled', false)
            ->select([
                'order_notes.id',
                'order_notes.note_number',
                'order_notes.note_date',
                'order_notes.customer_name',
                'order_notes.city',
            ])
            ->selectRaw('COALESCE(ordered_items.ordered_total, 0) as ordered_total')
            ->selectRaw('COALESCE(fulfilled_items.fulfilled_total, 0) as fulfilled_total')
            ->selectRaw('CASE WHEN (COALESCE(ordered_items.ordered_total, 0) - COALESCE(fulfilled_items.fulfilled_total, 0)) > 0 THEN (COALESCE(ordered_items.ordered_total, 0) - COALESCE(fulfilled_items.fulfilled_total, 0)) ELSE 0 END as remaining_total')
            ->whereRaw('(COALESCE(ordered_items.ordered_total, 0) - COALESCE(fulfilled_items.fulfilled_total, 0)) > 0')
            ->orderByDesc('order_notes.note_date')
            ->orderByDesc('order_notes.id')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
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

    private function hasOrderNoteDashboardTables(): bool
    {
        return Cache::remember(AppCache::lookupCacheKey('dashboard.schema.order_note_tables'), now()->addMinutes(5), function (): bool {
            return Schema::hasTable('order_notes')
                && Schema::hasTable('order_note_items')
                && Schema::hasTable('sales_invoice_items');
        });
    }

    /**
     * @param array<string, mixed> $query
     */
    private function emptyPaginator(int $perPage, string $path, array $query, string $pageName = 'page'): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: $perPage,
            currentPage: Paginator::resolveCurrentPage($pageName),
            options: [
                'path' => $path,
                'query' => $query,
                'pageName' => $pageName,
            ]
        );
    }
}
