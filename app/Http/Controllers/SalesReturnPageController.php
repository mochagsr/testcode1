<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateFilters;
use App\Http\Controllers\Concerns\ResolvesSemesterOptions;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockMutation;
use App\Services\AuditLogService;
use App\Services\AccountingService;
use App\Services\ReceivableLedgerService;
use App\Support\AppCache;
use App\Support\ExcelExportStyler;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReturnPageController extends Controller
{
    use ResolvesDateFilters;
    use ResolvesSemesterOptions;

    public function __construct(
        private readonly ReceivableLedgerService $receivableLedgerService,
        private readonly AuditLogService $auditLogService,
        private readonly SemesterBookService $semesterBookService,
        private readonly AccountingService $accountingService
    ) {}

    public function index(Request $request): View
    {
        $now = now();
        $isAdminUser = (string) ($request->user()?->role ?? '') === 'admin';
        $search = trim((string) $request->string('search', ''));
        $semester = (string) $request->string('semester', '');
        $status = trim((string) $request->string('status', ''));
        $returnDate = trim((string) $request->string('return_date', ''));
        $selectedStatus = in_array($status, ['active', 'canceled'], true) ? $status : null;
        $selectedSemester = $this->normalizedSemesterInput($semester);
        $selectedReturnDate = $this->selectedDateFilter($returnDate);
        $selectedReturnDateRange = $this->selectedDateRange($selectedReturnDate);
        $isDefaultRecentMode = $selectedReturnDateRange === null && $selectedSemester === null && $search === '';
        $recentRangeStart = $now->copy()->subDays(6)->startOfDay();
        $todayRange = [$now->copy()->startOfDay(), $now->copy()->endOfDay()];

        $currentSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);

        $semesterOptionsBase = $this->cachedSemesterOptionsFromPeriodColumn(
            'sales_returns.index.semester_options.base',
            SalesReturn::class
        );
        $semesterOptions = $this->semesterOptionsForIndex($semesterOptionsBase, $isAdminUser);
        $selectedSemester = $this->selectedSemesterIfAvailable($selectedSemester, $semesterOptions);

        $returns = SalesReturn::query()
            ->onlyListColumns()
            ->withCustomerInfo()
            ->withInvoiceInfo()
            ->searchKeyword($search)
            ->when($selectedStatus === 'active', fn($q) => $q->active())
            ->when($selectedStatus === 'canceled', fn($q) => $q->canceled())
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->forSemester($selectedSemester);
            })
            ->when($selectedReturnDateRange !== null, function ($query) use ($selectedReturnDateRange): void {
                $query->whereBetween('return_date', $selectedReturnDateRange);
            })
            ->when($isDefaultRecentMode, function ($query) use ($recentRangeStart): void {
                $query->where('return_date', '>=', $recentRangeStart);
            })
            ->orderByDate()
            ->paginate(20)
            ->withQueryString();

        $todaySummary = Cache::remember(
            AppCache::lookupCacheKey('sales_returns.index.today_summary', [
                'status' => $selectedStatus ?? 'all',
                'date' => $now->toDateString(),
            ]),
            $now->copy()->addSeconds(30),
            function () use ($selectedStatus, $todayRange) {
                return SalesReturn::query()
                    ->selectRaw('COUNT(*) as total_return, COALESCE(SUM(total), 0) as grand_total')
                    ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                        $query->where('is_canceled', $selectedStatus === 'canceled');
                    })
                    ->whereBetween('return_date', $todayRange)
                    ->first();
            }
        );
        $lockPairs = collect($returns->items())
            ->map(function (SalesReturn $salesReturn): array {
                return [
                    'customer_id' => (int) $salesReturn->customer_id,
                    'semester' => trim((string) $salesReturn->semester_period),
                ];
            })
            ->filter(fn(array $pair): bool => (int) ($pair['customer_id'] ?? 0) > 0 && (string) ($pair['semester'] ?? '') !== '')
            ->values();
        $customerSemesterLockMap = [];
        if ($lockPairs->isNotEmpty()) {
            $pairStates = $this->semesterBookService()->customerSemesterLockStatesByPairs(
                $lockPairs->all()
            );

            foreach ($pairStates as $key => $state) {
                $customerSemesterLockMap[$key] = [
                    'locked' => (bool) ($state['locked'] ?? false),
                    'manual' => (bool) ($state['manual'] ?? false),
                    'auto' => (bool) ($state['auto'] ?? false),
                ];
            }
        }
        $returnIds = collect($returns->items())
            ->map(fn(SalesReturn $salesReturn): int => (int) $salesReturn->id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values();
        $returnAdminActionMap = [];
        if ($returnIds->isNotEmpty()) {
            $actionRows = AuditLog::query()
                ->selectRaw("subject_id, MAX(CASE WHEN action = 'sales.return.admin_update' THEN 1 ELSE 0 END) as edited, MAX(CASE WHEN action = 'sales.return.cancel' THEN 1 ELSE 0 END) as canceled")
                ->where('subject_type', SalesReturn::class)
                ->whereIn('subject_id', $returnIds->all())
                ->whereIn('action', ['sales.return.admin_update', 'sales.return.cancel'])
                ->groupBy('subject_id')
                ->get();

            foreach ($actionRows as $row) {
                $returnId = (int) ($row->subject_id ?? 0);
                if ($returnId <= 0) {
                    continue;
                }
                $returnAdminActionMap[$returnId] = [
                    'edited' => (int) ($row->edited ?? 0) === 1,
                    'canceled' => (int) ($row->canceled ?? 0) === 1,
                ];
            }
        }
        foreach ($returns->items() as $returnRow) {
            $returnId = (int) $returnRow->id;
            if (! isset($returnAdminActionMap[$returnId])) {
                $returnAdminActionMap[$returnId] = [
                    'edited' => false,
                    'canceled' => false,
                ];
            }
            if ((bool) $returnRow->is_canceled) {
                $returnAdminActionMap[$returnId]['canceled'] = true;
            }
        }

        return view('sales_returns.index', [
            'returns' => $returns,
            'search' => $search,
            'semesterOptions' => $semesterOptions,
            'selectedSemester' => $selectedSemester,
            'selectedStatus' => $selectedStatus,
            'selectedReturnDate' => $selectedReturnDate,
            'isDefaultRecentMode' => $isDefaultRecentMode,
            'currentSemester' => $currentSemester,
            'previousSemester' => $previousSemester,
            'todaySummary' => $todaySummary,
            'customerSemesterLockMap' => $customerSemesterLockMap,
            'returnAdminActionMap' => $returnAdminActionMap,
        ]);
    }

    public function create(): View
    {
        $currentSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);
        $semesterOptionsBase = $this->cachedSemesterOptionsFromPeriodColumn(
            'sales_returns.index.semester_options.base',
            SalesReturn::class
        );
        $semesterOptions = $this->semesterOptionsForForm($semesterOptionsBase);
        if (! $semesterOptions->contains($currentSemester)) {
            $currentSemester = (string) ($semesterOptions->first() ?? $currentSemester);
        }

        $oldCustomerId = (int) old('customer_id', 0);
        $initialCustomers = Cache::remember(
            AppCache::lookupCacheKey('forms.sales_returns.customers', ['limit' => 20]),
            now()->addSeconds(60),
            fn() => Customer::query()
                ->onlySalesFormColumns()
                ->with('level:id,code,name')
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($oldCustomerId > 0 && ! $initialCustomers->contains('id', $oldCustomerId)) {
            $oldCustomer = Customer::query()
                ->onlySalesFormColumns()
                ->with('level:id,code,name')
                ->whereKey($oldCustomerId)
                ->first();
            if ($oldCustomer !== null) {
                $initialCustomers->prepend($oldCustomer);
            }
        }
        $initialCustomers = $initialCustomers->unique('id')->values();

        $oldProductIds = collect(old('items', []))
            ->pluck('product_id')
            ->map(fn($id): int => (int) $id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values();
        $initialProducts = Cache::remember(
            AppCache::lookupCacheKey('forms.sales_returns.products', ['limit' => 20, 'active_only' => 1]),
            now()->addSeconds(60),
            fn() => Product::query()
                ->onlySalesFormColumns()
                ->active()
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($oldProductIds->isNotEmpty()) {
            $oldProducts = Product::query()
                ->onlySalesFormColumns()
                ->whereIn('id', $oldProductIds->all())
                ->get();
            $initialProducts = $oldProducts->concat($initialProducts)->unique('id')->values();
        }

        return view('sales_returns.create', [
            'customers' => $initialCustomers,
            'products' => $initialProducts,
            'semesterOptions' => $semesterOptions,
            'defaultSemesterPeriod' => $this->defaultSemesterPeriod(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'return_date' => ['required', 'date'],
            'semester_period' => ['nullable', 'string', 'max:30'],
            'reason' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        $normalizedSemester = $this->semesterBookService()->normalizeSemester((string) ($data['semester_period'] ?? ''));
        $semesterFromDate = $this->semesterBookService()->semesterFromDate((string) $data['return_date']);
        $selectedSemester = $normalizedSemester ?? $semesterFromDate ?? $this->defaultSemesterPeriod();

        $salesReturn = DB::transaction(function () use ($data, $selectedSemester): SalesReturn {
            $returnDate = Carbon::parse($data['return_date']);
            $returnNumber = $this->generateReturnNumber($returnDate->toDateString());
            $rows = collect($data['items']);
            $customer = Customer::query()
                ->with('level:id,code,name')
                ->whereKey((int) $data['customer_id'])
                ->firstOrFail();

            $products = Product::query()
                ->whereIn('id', $rows->pluck('product_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $total = 0.0;
            $computedRows = [];

            foreach ($rows as $index => $row) {
                $product = $products->get((int) $row['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => __('txn.product_not_found'),
                    ]);
                }

                $quantity = (int) $row['quantity'];
                $unitPrice = $this->resolvePriceByCustomerLevel($product, $customer);
                $lineTotal = (float) round($quantity * $unitPrice);
                $total += $lineTotal;

                $computedRows[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $salesReturn = SalesReturn::create([
                'return_number' => $returnNumber,
                'customer_id' => $data['customer_id'],
                'return_date' => $returnDate->toDateString(),
                'semester_period' => $selectedSemester,
                'total' => (float) round($total),
                'reason' => $data['reason'] ?? null,
            ]);

            foreach ($computedRows as $row) {
                /** @var Product $product */
                $product = $row['product'];

                SalesReturnItem::create([
                    'sales_return_id' => $salesReturn->id,
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'product_name' => $product->name,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'line_total' => $row['line_total'],
                ]);

                $product->increment('stock', $row['quantity']);

                StockMutation::create([
                    'product_id' => $product->id,
                    'reference_type' => SalesReturn::class,
                    'reference_id' => $salesReturn->id,
                    'mutation_type' => 'in',
                    'quantity' => $row['quantity'],
                    'notes' => __('txn.return') . ' ' . $salesReturn->return_number,
                    'created_by_user_id' => null,
                ]);
            }

            $this->receivableLedgerService->addCredit(
                customerId: (int) $salesReturn->customer_id,
                invoiceId: null,
                entryDate: $returnDate,
                amount: $total,
                periodCode: $salesReturn->semester_period,
                description: __('txn.return') . ' ' . $salesReturn->return_number
            );

            $this->accountingService->postSalesReturn(
                returnId: (int) $salesReturn->id,
                date: $returnDate,
                amount: (int) round($total)
            );

            return $salesReturn;
        });

        $this->auditLogService->log(
            'sales.return.create',
            $salesReturn,
            __('txn.audit_return_created', ['number' => $salesReturn->return_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $salesReturn->return_date]);

        return redirect()
            ->route('sales-returns.show', $salesReturn)
            ->with('success', __('txn.return_created_success', ['number' => $salesReturn->return_number]));
    }

    public function show(SalesReturn $salesReturn): View
    {
        $salesReturn->load([
            'customer:id,name,city,phone',
            'items',
        ]);
        $currentSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);
        $semesterOptionsBase = $this->cachedSemesterOptionsFromPeriodColumn(
            'sales_returns.index.semester_options.base',
            SalesReturn::class
        );
        $semesterOptions = $this->semesterOptionsForForm(
            $semesterOptionsBase->push((string) $salesReturn->semester_period)
        );
        if (! $semesterOptions->contains((string) $salesReturn->semester_period)) {
            $semesterOptions = $semesterOptions->push((string) $salesReturn->semester_period)->unique()->values();
        }
        $customerSemesterLockState = ['locked' => false, 'manual' => false, 'auto' => false];
        $returnSemester = trim((string) $salesReturn->semester_period);
        if ((int) $salesReturn->customer_id > 0 && $returnSemester !== '') {
            $states = $this->semesterBookService()->customerSemesterLockStates([(int) $salesReturn->customer_id], $returnSemester);
            $customerSemesterLockState = [
                'locked' => (bool) ($states[(int) $salesReturn->customer_id]['locked'] ?? false),
                'manual' => (bool) ($states[(int) $salesReturn->customer_id]['manual'] ?? false),
                'auto' => (bool) ($states[(int) $salesReturn->customer_id]['auto'] ?? false),
            ];
        }
        $itemProductIds = $salesReturn->items
            ->pluck('product_id')
            ->map(fn($id): int => (int) $id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values();
        $products = Product::query()
            ->onlySalesFormColumns()
            ->active()
            ->orderBy('name')
            ->limit(20)
            ->get();
        if ($itemProductIds->isNotEmpty()) {
            $itemProducts = Product::query()
                ->onlySalesFormColumns()
                ->whereIn('id', $itemProductIds->all())
                ->get();
            $products = $itemProducts->concat($products)->unique('id')->values();
        }

        return view('sales_returns.show', [
            'salesReturn' => $salesReturn,
            'customerSemesterLockState' => $customerSemesterLockState,
            'semesterOptions' => $semesterOptions,
            'products' => $products,
        ]);
    }

    public function adminUpdate(Request $request, SalesReturn $salesReturn): RedirectResponse
    {
        $data = $request->validate([
            'return_date' => ['required', 'date'],
            'semester_period' => ['nullable', 'string', 'max:30'],
            'reason' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $auditBefore = '';
        $auditAfter = '';

        DB::transaction(function () use ($salesReturn, $data, &$auditBefore, &$auditAfter): void {
            $return = SalesReturn::query()
                ->with('items')
                ->whereKey($salesReturn->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($return->is_canceled) {
                throw ValidationException::withMessages([
                    'items' => __('txn.canceled_info'),
                ]);
            }

            $rows = collect($data['items'] ?? []);
            $oldTotal = (float) $return->total;
            $auditBefore = $return->items
                ->map(fn(SalesReturnItem $item): string => "{$item->product_name}:qty{$item->quantity}:price" . (int) round((float) $item->unit_price))
                ->implode(' | ');

            $oldQtyByProduct = [];
            foreach ($return->items as $existingItem) {
                $productId = (int) ($existingItem->product_id ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $oldQtyByProduct[$productId] = ($oldQtyByProduct[$productId] ?? 0) + (int) $existingItem->quantity;
            }

            $newQtyByProduct = [];
            foreach ($rows as $row) {
                $productId = (int) ($row['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $newQtyByProduct[$productId] = ($newQtyByProduct[$productId] ?? 0) + (int) ($row['quantity'] ?? 0);
            }

            $productIds = collect(array_merge(array_keys($oldQtyByProduct), array_keys($newQtyByProduct)))
                ->unique()
                ->values()
                ->all();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($productIds as $productId) {
                $product = $products->get((int) $productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => __('txn.product_not_found'),
                    ]);
                }

                $oldQty = (int) ($oldQtyByProduct[(int) $productId] ?? 0);
                $newQty = (int) ($newQtyByProduct[(int) $productId] ?? 0);
                $delta = $oldQty - $newQty;

                if ($delta > 0 && (int) $product->stock < $delta) {
                    throw ValidationException::withMessages([
                        'items' => __('txn.insufficient_stock_for', ['product' => $product->name]),
                    ]);
                }
            }

            foreach ($productIds as $productId) {
                $product = $products->get((int) $productId);
                if (! $product) {
                    continue;
                }

                $oldQty = (int) ($oldQtyByProduct[(int) $productId] ?? 0);
                $newQty = (int) ($newQtyByProduct[(int) $productId] ?? 0);
                $delta = $oldQty - $newQty;
                if ($delta === 0) {
                    continue;
                }

                if ($delta > 0) {
                    $product->decrement('stock', $delta);
                    StockMutation::create([
                        'product_id' => $product->id,
                        'reference_type' => SalesReturn::class,
                        'reference_id' => $return->id,
                        'mutation_type' => 'out',
                        'quantity' => $delta,
                        'notes' => '[ADMIN EDIT ' . strtoupper(__('txn.return')) . '] '
                            . __('txn.return') . ' ' . $return->return_number,
                        'created_by_user_id' => auth()->id(),
                    ]);
                } else {
                    $inQty = abs($delta);
                    $product->increment('stock', $inQty);
                    StockMutation::create([
                        'product_id' => $product->id,
                        'reference_type' => SalesReturn::class,
                        'reference_id' => $return->id,
                        'mutation_type' => 'in',
                        'quantity' => $inQty,
                        'notes' => '[ADMIN EDIT ' . strtoupper(__('txn.return')) . '] '
                            . __('txn.return') . ' ' . $return->return_number,
                        'created_by_user_id' => auth()->id(),
                    ]);
                }
            }

            $return->items()->delete();

            $total = 0.0;
            foreach ($rows as $index => $row) {
                $product = $products->get((int) $row['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => __('txn.product_not_found'),
                    ]);
                }

                $quantity = (int) $row['quantity'];
                $unitPrice = (float) round((float) $row['unit_price']);
                $lineTotal = (float) round($quantity * $unitPrice);
                $total += $lineTotal;

                SalesReturnItem::create([
                    'sales_return_id' => $return->id,
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);
            }

            $auditAfter = $rows
                ->map(function (array $row) use ($products): string {
                    $product = $products->get((int) ($row['product_id'] ?? 0));
                    $name = $product?->name ?: (string) ($row['product_id'] ?? '-');
                    $qty = (int) ($row['quantity'] ?? 0);
                    $price = (int) round((float) ($row['unit_price'] ?? 0));

                    return "{$name}:qty{$qty}:price{$price}";
                })
                ->implode(' | ');

            $return->update([
                'return_date' => $data['return_date'],
                'semester_period' => $data['semester_period'] ?? null,
                'reason' => $data['reason'] ?? null,
                'total' => (float) round($total),
            ]);

            $difference = (float) round($total - $oldTotal);
            if ($difference > 0) {
                $this->receivableLedgerService->addCredit(
                    customerId: (int) $return->customer_id,
                    invoiceId: null,
                    entryDate: Carbon::parse((string) $data['return_date']),
                    amount: $difference,
                    periodCode: $return->semester_period,
                    description: '[ADMIN EDIT ' . strtoupper(__('txn.return')) . " +] "
                        . __('txn.return') . ' ' . $return->return_number,
                );
            } elseif ($difference < 0) {
                $this->receivableLedgerService->addDebit(
                    customerId: (int) $return->customer_id,
                    invoiceId: null,
                    entryDate: Carbon::parse((string) $data['return_date']),
                    amount: abs($difference),
                    periodCode: $return->semester_period,
                    description: '[ADMIN EDIT ' . strtoupper(__('txn.return')) . " -] "
                        . __('txn.return') . ' ' . $return->return_number,
                );
            }
        });

        $salesReturn->refresh();
        $this->auditLogService->log(
            'sales.return.admin_update',
            $salesReturn,
            __('txn.audit_return_admin_updated', [
                'number' => $salesReturn->return_number,
                'before' => $auditBefore !== '' ? $auditBefore : '-',
                'after' => $auditAfter !== '' ? $auditAfter : '-',
            ]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $salesReturn->return_date]);

        return redirect()
            ->route('sales-returns.show', $salesReturn)
            ->with('success', __('txn.admin_update_saved'));
    }

    public function cancel(Request $request, SalesReturn $salesReturn): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($salesReturn, $data): void {
            $return = SalesReturn::query()
                ->with('items')
                ->whereKey($salesReturn->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($return->is_canceled) {
                return;
            }

            foreach ($return->items as $item) {
                if (! $item->product_id || (int) $item->quantity <= 0) {
                    continue;
                }

                $product = Product::query()
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    continue;
                }

                if ((int) $product->stock < (int) $item->quantity) {
                    throw ValidationException::withMessages([
                        'cancel_reason' => __('txn.cancel_return_insufficient_stock', ['product' => $product->name]),
                    ]);
                }

                $product->decrement('stock', (int) $item->quantity);

                StockMutation::create([
                    'product_id' => $product->id,
                    'reference_type' => SalesReturn::class,
                    'reference_id' => $return->id,
                    'mutation_type' => 'out',
                    'quantity' => (int) $item->quantity,
                    'notes' => __('txn.cancel') . ' ' . __('txn.return') . ' ' . $return->return_number,
                    'created_by_user_id' => auth()->id(),
                ]);
            }

            $returnTotal = max(0, (float) $return->total);
            if ($returnTotal > 0) {
                $this->receivableLedgerService->addDebit(
                    customerId: (int) $return->customer_id,
                    invoiceId: null,
                    entryDate: now(),
                    amount: $returnTotal,
                    periodCode: $return->semester_period,
                    description: __('txn.cancel_return_ledger_note', ['number' => $return->return_number]),
                );
            }

            $return->update([
                'is_canceled' => true,
                'canceled_at' => now(),
                'canceled_by_user_id' => auth()->id(),
                'cancel_reason' => $data['cancel_reason'],
            ]);
        });

        $this->auditLogService->log(
            'sales.return.cancel',
            $salesReturn,
            __('txn.audit_return_canceled', ['number' => $salesReturn->return_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $salesReturn->return_date]);

        return redirect()
            ->route('sales-returns.show', $salesReturn)
            ->with('success', __('txn.transaction_canceled_success'));
    }

    public function print(SalesReturn $salesReturn): View
    {
        $salesReturn->load([
            'customer:id,name,city,phone,address',
            'items',
        ]);

        return view('sales_returns.print', [
            'salesReturn' => $salesReturn,
        ]);
    }

    public function exportPdf(SalesReturn $salesReturn)
    {
        $salesReturn->load([
            'customer:id,name,city,phone,address',
            'items',
        ]);

        $filename = $salesReturn->return_number . '.pdf';
        $pdf = Pdf::loadView('sales_returns.print', [
            'salesReturn' => $salesReturn,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function exportExcel(SalesReturn $salesReturn): StreamedResponse
    {
        $salesReturn->load([
            'customer:id,name,city,phone,address',
            'items',
        ]);

        $filename = $salesReturn->return_number . '.xlsx';

        return response()->streamDownload(function () use ($salesReturn): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Retur');
            $rows = [];
            $rows[] = [__('txn.return') . ' ' . __('txn.note_number'), $salesReturn->return_number];
            $rows[] = [__('txn.return_date'), $salesReturn->return_date?->format('d-m-Y')];
            $rows[] = [__('txn.customer'), $salesReturn->customer?->name];
            $rows[] = [__('txn.city'), $salesReturn->customer?->city];
            $rows[] = [__('txn.semester_period'), $salesReturn->semester_period];
            $rows[] = [__('txn.total'), number_format((int) round((float) $salesReturn->total), 0, ',', '.')];
            $rows[] = [__('txn.reason'), $salesReturn->reason];
            $rows[] = [];
            $rows[] = [__('txn.items')];
            $rows[] = [__('txn.name'), __('txn.qty'), __('txn.line_total')];

            foreach ($salesReturn->items as $item) {
                $rows[] = [
                    $item->product_name,
                    $item->quantity,
                    number_format((int) round((float) $item->line_total), 0, ',', '.'),
                ];
            }

            $sheet->fromArray($rows, null, 'A1');
            $itemsCount = $salesReturn->items->count();
            $itemsHeaderRow = 10;
            ExcelExportStyler::styleTable($sheet, $itemsHeaderRow, 3, $itemsCount, true);
            ExcelExportStyler::formatNumberColumns($sheet, $itemsHeaderRow + 1, $itemsHeaderRow + $itemsCount, [2, 3], '#,##0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function generateReturnNumber(string $date): string
    {
        $prefix = 'RTR-' . date('Ymd', strtotime($date));
        $count = SalesReturn::query()
            ->whereDate('return_date', $date)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    private function defaultSemesterPeriod(): string
    {
        return $this->semesterBookService()->currentSemester();
    }

    private function previousSemesterPeriod(string $period): string
    {
        return $this->semesterBookService()->previousSemester($period);
    }

    private function semesterBookService(): SemesterBookService
    {
        return $this->semesterBookService;
    }

    private function resolvePriceByCustomerLevel(Product $product, Customer $customer): float
    {
        $levelCode = strtolower(trim((string) ($customer->level?->code ?? '')));
        $levelName = strtolower(trim((string) ($customer->level?->name ?? '')));
        $combined = trim($levelCode . ' ' . $levelName);

        if (str_contains($combined, 'agent') || str_contains($combined, 'agen')) {
            return (float) round((float) ($product->price_agent ?? $product->price_general ?? 0));
        }

        if (str_contains($combined, 'sales')) {
            return (float) round((float) ($product->price_sales ?? $product->price_general ?? 0));
        }

        return (float) round((float) ($product->price_general ?? 0));
    }
}
