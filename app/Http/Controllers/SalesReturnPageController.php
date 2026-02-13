<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockMutation;
use App\Services\AuditLogService;
use App\Services\ReceivableLedgerService;
use App\Support\ExcelExportStyler;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReturnPageController extends Controller
{
    public function __construct(
        private readonly ReceivableLedgerService $receivableLedgerService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): View
    {
        $isAdminUser = (string) ($request->user()?->role ?? '') === 'admin';
        $search = trim((string) $request->string('search', ''));
        $semester = trim((string) $request->string('semester', ''));
        $status = trim((string) $request->string('status', ''));
        $returnDate = trim((string) $request->string('return_date', ''));
        $selectedStatus = in_array($status, ['active', 'canceled'], true) ? $status : null;
        $selectedSemester = $semester !== '' ? $semester : null;
        $selectedReturnDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $returnDate) === 1 ? $returnDate : null;
        $selectedReturnDateRange = $selectedReturnDate !== null
            ? [
                Carbon::parse($selectedReturnDate)->startOfDay(),
                Carbon::parse($selectedReturnDate)->endOfDay(),
            ]
            : null;
        $isDefaultRecentMode = $selectedReturnDateRange === null && $selectedSemester === null && $search === '';
        $recentRangeStart = now()->subDays(6)->startOfDay();
        $todayRange = [now()->startOfDay(), now()->endOfDay()];

        $currentSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);

        $semesterOptions = SalesReturn::query()
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->merge($this->configuredSemesterOptions())
            ->push($currentSemester)
            ->push($previousSemester)
            ->unique()
            ->sortDesc()
            ->values();
        $semesterOptions = $isAdminUser
            ? $semesterOptions->values()
            : collect($this->semesterBookService()->filterToActiveSemesters($semesterOptions->all()));
        if ($selectedSemester !== null && ! $semesterOptions->contains($selectedSemester)) {
            $selectedSemester = null;
        }

        $returns = SalesReturn::query()
            ->select([
                'id',
                'return_number',
                'customer_id',
                'return_date',
                'semester_period',
                'total',
                'is_canceled',
            ])
            ->with('customer:id,name,city')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('return_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('city', 'like', "%{$search}%");
                        });
                });
            })
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->where('semester_period', $selectedSemester);
            })
            ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                $query->where('is_canceled', $selectedStatus === 'canceled');
            })
            ->when($selectedReturnDateRange !== null, function ($query) use ($selectedReturnDateRange): void {
                $query->whereBetween('return_date', $selectedReturnDateRange);
            })
            ->when($isDefaultRecentMode, function ($query) use ($recentRangeStart): void {
                $query->where('return_date', '>=', $recentRangeStart);
            })
            ->latest('return_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $todaySummary = SalesReturn::query()
            ->selectRaw('COUNT(*) as total_return, COALESCE(SUM(total), 0) as grand_total')
            ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                $query->where('is_canceled', $selectedStatus === 'canceled');
            })
            ->whereBetween('return_date', $todayRange)
            ->first();
        $customerSemesterLockMap = $this->customerSemesterLockMap(collect($returns->items()));
        $returnIds = collect($returns->items())
            ->map(fn (SalesReturn $salesReturn): int => (int) $salesReturn->id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $returnAdminActionMap = [];
        if ($returnIds->isNotEmpty()) {
            $actionRows = AuditLog::query()
                ->select(['subject_id', 'action'])
                ->where('subject_type', SalesReturn::class)
                ->whereIn('subject_id', $returnIds->all())
                ->whereIn('action', ['sales.return.admin_update', 'sales.return.cancel'])
                ->get();

            foreach ($actionRows as $row) {
                $returnId = (int) ($row->subject_id ?? 0);
                if ($returnId <= 0) {
                    continue;
                }
                if (! isset($returnAdminActionMap[$returnId])) {
                    $returnAdminActionMap[$returnId] = [
                        'edited' => false,
                        'canceled' => false,
                    ];
                }
                if ((string) $row->action === 'sales.return.admin_update') {
                    $returnAdminActionMap[$returnId]['edited'] = true;
                }
                if ((string) $row->action === 'sales.return.cancel') {
                    $returnAdminActionMap[$returnId]['canceled'] = true;
                }
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
        $semesterOptions = SalesReturn::query()
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->merge($this->configuredSemesterOptions())
            ->push($currentSemester)
            ->push($previousSemester)
            ->unique()
            ->sortDesc()
            ->values();
        $semesterOptions = collect($this->semesterBookService()->filterToActiveSemesters($semesterOptions->all()));
        if (! $semesterOptions->contains($currentSemester)) {
            $currentSemester = (string) ($semesterOptions->first() ?? $currentSemester);
        }

        $oldCustomerId = (int) old('customer_id', 0);
        $initialCustomers = Customer::query()
            ->select(['id', 'name', 'city', 'customer_level_id'])
            ->with('level:id,code,name')
            ->orderBy('name')
            ->limit(20)
            ->get();
        if ($oldCustomerId > 0 && ! $initialCustomers->contains('id', $oldCustomerId)) {
            $oldCustomer = Customer::query()
                ->select(['id', 'name', 'city', 'customer_level_id'])
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
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $initialProducts = Product::query()
            ->select(['id', 'code', 'name', 'stock', 'price_agent', 'price_sales', 'price_general'])
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(20)
            ->get();
        if ($oldProductIds->isNotEmpty()) {
            $oldProducts = Product::query()
                ->select(['id', 'code', 'name', 'stock', 'price_agent', 'price_sales', 'price_general'])
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
                    'notes' => __('txn.return').' '.$salesReturn->return_number,
                    'created_by_user_id' => null,
                ]);
            }

            $this->receivableLedgerService->addCredit(
                customerId: (int) $salesReturn->customer_id,
                invoiceId: null,
                entryDate: $returnDate,
                amount: $total,
                periodCode: $salesReturn->semester_period,
                description: __('txn.return').' '.$salesReturn->return_number
            );

            return $salesReturn;
        });

        $this->auditLogService->log(
            'sales.return.create',
            $salesReturn,
            __('txn.audit_return_created', ['number' => $salesReturn->return_number]),
            $request
        );

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
        $semesterOptions = SalesReturn::query()
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->merge($this->configuredSemesterOptions())
            ->push($currentSemester)
            ->push($previousSemester)
            ->push((string) $salesReturn->semester_period)
            ->unique()
            ->sortDesc()
            ->values();
        $semesterOptions = collect($this->semesterBookService()->filterToActiveSemesters($semesterOptions->all()));
        if (! $semesterOptions->contains((string) $salesReturn->semester_period)) {
            $semesterOptions = $semesterOptions->push((string) $salesReturn->semester_period)->unique()->values();
        }
        $customerSemesterLockState = $this->customerSemesterLockState(
            (int) $salesReturn->customer_id,
            (string) $salesReturn->semester_period
        );
        $itemProductIds = $salesReturn->items
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $products = Product::query()
            ->select(['id', 'code', 'name', 'stock', 'price_agent', 'price_sales', 'price_general'])
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(20)
            ->get();
        if ($itemProductIds->isNotEmpty()) {
            $itemProducts = Product::query()
                ->select(['id', 'code', 'name', 'stock', 'price_agent', 'price_sales', 'price_general'])
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
                ->map(fn (SalesReturnItem $item): string => "{$item->product_name}:qty{$item->quantity}:price".(int) round((float) $item->unit_price))
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
                        'notes' => '[ADMIN EDIT '.strtoupper(__('txn.return')).'] '
                            .__('txn.return').' '.$return->return_number,
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
                        'notes' => '[ADMIN EDIT '.strtoupper(__('txn.return')).'] '
                            .__('txn.return').' '.$return->return_number,
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
                    description: '[ADMIN EDIT '.strtoupper(__('txn.return'))." +] "
                        .__('txn.return').' '.$return->return_number,
                );
            } elseif ($difference < 0) {
                $this->receivableLedgerService->addDebit(
                    customerId: (int) $return->customer_id,
                    invoiceId: null,
                    entryDate: Carbon::parse((string) $data['return_date']),
                    amount: abs($difference),
                    periodCode: $return->semester_period,
                    description: '[ADMIN EDIT '.strtoupper(__('txn.return'))." -] "
                        .__('txn.return').' '.$return->return_number,
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
                    'notes' => __('txn.cancel').' '.__('txn.return').' '.$return->return_number,
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

        $filename = $salesReturn->return_number.'.pdf';
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

        $filename = $salesReturn->return_number.'.xlsx';

        return response()->streamDownload(function () use ($salesReturn): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Retur');
            $rows = [];
            $rows[] = [__('txn.return').' '.__('txn.note_number'), $salesReturn->return_number];
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
        $prefix = 'RTR-'.date('Ymd', strtotime($date));
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

    private function configuredSemesterOptions()
    {
        return collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('semester_period_options', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '');
    }

    private function semesterBookService(): SemesterBookService
    {
        return app(SemesterBookService::class);
    }

    private function resolvePriceByCustomerLevel(Product $product, Customer $customer): float
    {
        $levelCode = strtolower(trim((string) ($customer->level?->code ?? '')));
        $levelName = strtolower(trim((string) ($customer->level?->name ?? '')));
        $combined = trim($levelCode.' '.$levelName);

        if (str_contains($combined, 'agent') || str_contains($combined, 'agen')) {
            return (float) round((float) ($product->price_agent ?? $product->price_general ?? 0));
        }

        if (str_contains($combined, 'sales')) {
            return (float) round((float) ($product->price_sales ?? $product->price_general ?? 0));
        }

        return (float) round((float) ($product->price_general ?? 0));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SalesReturn>  $returns
     * @return array<string, array{locked:bool,manual:bool,auto:bool}>
     */
    private function customerSemesterLockMap(\Illuminate\Support\Collection $returns): array
    {
        $pairs = $returns
            ->map(function (SalesReturn $salesReturn): ?array {
                $customerId = (int) $salesReturn->customer_id;
                $semester = trim((string) $salesReturn->semester_period);
                if ($customerId <= 0 || $semester === '') {
                    return null;
                }

                return [
                    'customer_id' => $customerId,
                    'semester' => $semester,
                ];
            })
            ->filter()
            ->values();
        if ($pairs->isEmpty()) {
            return [];
        }

        $customerIds = $pairs->pluck('customer_id')->unique()->values();
        $semesterCodes = $pairs->pluck('semester')->unique()->values();

        $aggregates = \App\Models\SalesInvoice::query()
            ->selectRaw('customer_id, semester_period, COUNT(*) as invoice_count, COALESCE(SUM(balance), 0) as outstanding')
            ->where('is_canceled', false)
            ->whereIn('customer_id', $customerIds->all())
            ->whereIn('semester_period', $semesterCodes->all())
            ->groupBy('customer_id', 'semester_period')
            ->get();

        $autoMap = [];
        foreach ($aggregates as $aggregate) {
            $key = ((int) $aggregate->customer_id).':'.(string) $aggregate->semester_period;
            $invoiceCount = (int) ($aggregate->invoice_count ?? 0);
            $outstanding = (float) ($aggregate->outstanding ?? 0);
            $autoMap[$key] = $invoiceCount > 0 && round($outstanding) <= 0;
        }

        $manualMap = collect($this->semesterBookService()->closedCustomerSemesters())
            ->mapWithKeys(fn (string $item): array => [$item => true])
            ->all();

        $result = [];
        foreach ($pairs as $pair) {
            $key = ((int) $pair['customer_id']).':'.(string) $pair['semester'];
            $manual = (bool) ($manualMap[$key] ?? false);
            $auto = (bool) ($autoMap[$key] ?? false);
            $result[$key] = [
                'locked' => $manual || $auto,
                'manual' => $manual,
                'auto' => $auto,
            ];
        }

        return $result;
    }

    /**
     * @return array{locked:bool,manual:bool,auto:bool}
     */
    private function customerSemesterLockState(int $customerId, string $semester): array
    {
        $normalizedSemester = trim($semester);
        if ($customerId <= 0 || $normalizedSemester === '') {
            return ['locked' => false, 'manual' => false, 'auto' => false];
        }

        $manual = $this->semesterBookService()->isCustomerClosed($customerId, $normalizedSemester);
        $auto = $this->semesterBookService()->isCustomerAutoClosed($customerId, $normalizedSemester);

        return [
            'locked' => $manual || $auto,
            'manual' => $manual,
            'auto' => $auto,
        ];
    }
}
