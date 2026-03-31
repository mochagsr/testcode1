<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ApprovalRequest;
use App\Models\IntegrityCheckLog;
use App\Models\OrderNote;
use App\Models\OutgoingTransaction;
use App\Models\PerformanceProbeLog;
use App\Models\Product;
use App\Models\ReportExportTask;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Support\AppCache;
use App\Support\SemesterBookService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SemesterBookService $semesterBookService
    ) {}

    public function index(): View
    {
        $now = now();
        $user = request()->user();
        $currentPath = request()->url();
        $currentQuery = request()->query();
        $uncollectedPerPage = 20;
        $pendingOrderNotesPerPage = 20;
        $supplierExpensePerPage = 20;
        $lowStockPerPage = 20;
        $uncollectedPageName = 'uncollected_customers_page';
        $pendingOrderNotesPageName = 'pending_order_notes_page';
        $supplierExpensePageName = 'supplier_expense_page';
        $lowStockPageName = 'low_stock_page';

        if (! $this->hasRequiredDashboardTables()) {
            return view('dashboard', [
                'summary' => [
                    'total_products' => 0,
                    'total_customers' => 0,
                    'total_receivable' => 0,
                'total_supplier_payable' => 0,
                'invoice_this_month' => 0,
                'outgoing_this_month' => 0,
                'pending_approvals' => 0,
                'pending_report_tasks' => 0,
                'active_semesters' => 0,
                'closed_semesters' => 0,
                'locked_customer_semesters' => 0,
                'locked_supplier_years' => 0,
                'backup_files' => 0,
            ],
                'opsSnapshot' => $this->defaultOpsSnapshot(),
                'uncollectedCustomers' => $this->emptyPaginator($uncollectedPerPage, $currentPath, $currentQuery, $uncollectedPageName),
                'pendingOrderNotes' => $this->emptyPaginator($pendingOrderNotesPerPage, $currentPath, $currentQuery, $pendingOrderNotesPageName),
                'supplierExpenseRecap' => $this->emptyPaginator($supplierExpensePerPage, $currentPath, $currentQuery, $supplierExpensePageName),
                'lowStockProducts' => $this->emptyPaginator($lowStockPerPage, $currentPath, $currentQuery, $lowStockPageName),
                'quickLinks' => collect(),
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
            $backupFiles = collect(Storage::disk('local')->files('backups'))
                ->merge(Storage::disk('local')->files('backups/db'));

            return [
                'total_products' => Product::count(),
                'total_customers' => Customer::count(),
                'total_receivable' => Customer::sum('outstanding_receivable'),
                'total_supplier_payable' => $hasOutgoingTable ? Supplier::sum('outstanding_payable') : 0,
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
                'pending_approvals' => ApprovalRequest::query()->pending()->count(),
                'pending_report_tasks' => ReportExportTask::query()
                    ->whereIn('status', ['queued', 'processing'])
                    ->count(),
                'active_semesters' => count($this->semesterBookService->activeSemesters()),
                'closed_semesters' => count($this->semesterBookService->closedSemesters()),
                'locked_customer_semesters' => count($this->semesterBookService->closedCustomerSemesters()),
                'locked_supplier_years' => count($this->semesterBookService->closedSupplierYears()),
                'backup_files' => $backupFiles->count(),
            ];
        });
        $opsSnapshot = $this->opsSnapshot();
        $quickLinks = collect([
            [
                'allowed' => $user?->canAccess('receivables.view') ?? false,
                'title' => __('menu.receivable_global'),
                'note' => __('ui.dashboard_quick_receivable_global_note'),
                'route' => route('receivables.global.index'),
            ],
            [
                'allowed' => $user?->canAccess('receivables.view') ?? false,
                'title' => __('menu.receivable_semester'),
                'note' => __('ui.dashboard_quick_receivable_semester_note'),
                'route' => route('receivables.semester.index'),
            ],
            [
                'allowed' => $user?->canAccess('supplier_payables.view') ?? false,
                'title' => __('menu.supplier_payables'),
                'note' => __('ui.dashboard_quick_supplier_payable_note'),
                'route' => route('supplier-payables.index'),
            ],
            [
                'allowed' => $user?->canAccess('audit_logs.view') ?? false,
                'title' => __('ui.audit_logs_title'),
                'note' => __('ui.dashboard_quick_audit_note'),
                'route' => route('audit-logs.index'),
            ],
            [
                'allowed' => $user?->canAccess('settings.admin') ?? false,
                'title' => 'Ops Health',
                'note' => __('ui.dashboard_quick_ops_note'),
                'route' => route('ops-health.index'),
            ],
            [
                'allowed' => $user?->canAccess('reports.view') ?? false,
                'title' => __('menu.reports'),
                'note' => __('ui.dashboard_quick_reports_note'),
                'route' => route('reports.index'),
            ],
        ])->filter(fn (array $item): bool => (bool) ($item['allowed'] ?? false))->values();

        $readyToCloseSemesters = collect();
        if (($user?->canAccess('settings.admin') ?? false) || ((string) ($user?->role ?? '') === 'admin')) {
            $readyToCloseSemesters = collect($this->semesterBookService->activeSemesters())
                ->when(
                    collect($this->semesterBookService->activeSemesters())->isEmpty(),
                    fn ($items) => collect($this->semesterBookService->configuredSemesterOptions()->all())
                )
                ->pipe(fn ($items) => collect($this->semesterBookService->filterToOpenSemesters($items->all(), false)))
                ->map(fn (string $semester): array => $this->semesterBookService->receivableSemesterClosingState($semester))
                ->filter(fn (array $state): bool => (bool) ($state['ready_to_close'] ?? false))
                ->values();
        }

        $uncollectedCustomers = Customer::query()
            ->onlyOutstandingColumns()
            ->withOutstanding()
            ->orderBy('name')
            ->paginate($uncollectedPerPage, ['*'], $uncollectedPageName)
            ->withQueryString();

        $supplierExpenseRecap = $hasOutgoingTable
            ? Supplier::query()
                ->onlyListColumns()
                ->where('outstanding_payable', '>', 0)
                ->orderByDesc('outstanding_payable')
                ->orderBy('name')
                ->paginate($supplierExpensePerPage, ['*'], $supplierExpensePageName)
                ->withQueryString()
            : $this->emptyPaginator($supplierExpensePerPage, $currentPath, $currentQuery, $supplierExpensePageName);

        $lowStockProducts = Product::query()
            ->onlyListColumns()
            ->withCategoryInfo()
            ->where('stock', '<=', 10)
            ->orderBy('stock')
            ->orderBy('name')
            ->paginate($lowStockPerPage, ['*'], $lowStockPageName)
            ->withQueryString();

        $pendingOrderNotes = $hasOrderNoteTable
            ? $this->pendingOrderNotesPaginator($pendingOrderNotesPerPage, $pendingOrderNotesPageName)
            : $this->emptyPaginator($pendingOrderNotesPerPage, $currentPath, $currentQuery, $pendingOrderNotesPageName);

        return view('dashboard', [
            'summary' => $summary,
            'uncollectedCustomers' => $uncollectedCustomers,
            'pendingOrderNotes' => $pendingOrderNotes,
            'supplierExpenseRecap' => $supplierExpenseRecap,
            'lowStockProducts' => $lowStockProducts,
            'opsSnapshot' => $opsSnapshot,
            'quickLinks' => $quickLinks,
            'readyToCloseSemesters' => $readyToCloseSemesters,
        ]);
    }

    /**
     * @return array{latestBackup:string,latestRestoreStatus:string,latestRestoreAt:string,latestIntegrityStatus:string,latestIntegrityAt:string,latestProbeAt:string}
     */
    private function opsSnapshot(): array
    {
        $backupFiles = collect(Storage::disk('local')->files('backups'))
            ->merge(Storage::disk('local')->files('backups/db'))
            ->sort()
            ->values();
        $latestBackup = $backupFiles->last();

        $latestRestoreDrill = Schema::hasTable('restore_drill_logs')
            ? DB::table('restore_drill_logs')->latest('checked_at')->latest('id')->first()
            : null;
        $latestIntegrityLog = Schema::hasTable('integrity_check_logs')
            ? IntegrityCheckLog::query()->latest('checked_at')->latest('id')->first()
            : null;
        $latestPerformanceProbe = Schema::hasTable('performance_probe_logs')
            ? PerformanceProbeLog::query()->latest('probed_at')->latest('id')->first()
            : null;

        return [
            'latestBackup' => $latestBackup ?: '-',
            'latestRestoreStatus' => strtoupper((string) ($latestRestoreDrill->status ?? '-')),
            'latestRestoreAt' => $latestRestoreDrill?->checked_at
                ? (string) \Carbon\Carbon::parse((string) $latestRestoreDrill->checked_at, 'Asia/Jakarta')->format('d-m-Y H:i')
                : '-',
            'latestIntegrityStatus' => $latestIntegrityLog === null
                ? '-'
                : ((bool) $latestIntegrityLog->is_ok ? 'OK' : 'ANOMALI'),
            'latestIntegrityAt' => $latestIntegrityLog?->checked_at
                ? (string) $latestIntegrityLog->checked_at->format('d-m-Y H:i')
                : '-',
            'latestProbeAt' => $latestPerformanceProbe?->probed_at
                ? (string) $latestPerformanceProbe->probed_at->format('d-m-Y H:i')
                : '-',
        ];
    }

    /**
     * @return array{latestBackup:string,latestRestoreStatus:string,latestRestoreAt:string,latestIntegrityStatus:string,latestIntegrityAt:string,latestProbeAt:string}
     */
    private function defaultOpsSnapshot(): array
    {
        return [
            'latestBackup' => '-',
            'latestRestoreStatus' => '-',
            'latestRestoreAt' => '-',
            'latestIntegrityStatus' => '-',
            'latestIntegrityAt' => '-',
            'latestProbeAt' => '-',
        ];
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
