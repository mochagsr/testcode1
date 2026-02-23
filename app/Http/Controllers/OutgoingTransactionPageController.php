<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateFilters;
use App\Http\Controllers\Concerns\ResolvesSemesterOptions;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\AccountingService;
use App\Services\SupplierLedgerService;
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

class OutgoingTransactionPageController extends Controller
{
    use ResolvesDateFilters;
    use ResolvesSemesterOptions;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SemesterBookService $semesterBookService,
        private readonly SupplierLedgerService $supplierLedgerService,
        private readonly AccountingService $accountingService
    ) {}

    public function index(Request $request): View
    {
        $now = now();
        $isAdminUser = (string) ($request->user()?->role ?? '') === 'admin';
        $search = trim((string) $request->string('search', ''));
        $semester = (string) $request->string('semester', '');
        $transactionDate = trim((string) $request->string('transaction_date', ''));
        $supplierId = $request->integer('supplier_id');

        $defaultSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($defaultSemester);
        $semesterOptionsBase = $this->cachedSemesterOptionsFromPeriodColumn(
            'outgoing_transactions.index.semester_options.base',
            OutgoingTransaction::class
        );
        $semesterOptions = $this->semesterOptionsForIndex($semesterOptionsBase, $isAdminUser);
        $selectedSemester = $this->normalizedSemesterInput($semester);
        $selectedSemester = $this->selectedSemesterIfAvailable($selectedSemester, $semesterOptions);
        $selectedTransactionDate = $this->selectedDateFilter($transactionDate);
        $selectedTransactionDateRange = $this->selectedDateRange($selectedTransactionDate);
        $isDefaultRecentMode = $selectedTransactionDateRange === null && $selectedSemester === null && $search === '';
        $recentRangeStart = $now->copy()->subDays(6)->startOfDay();
        $selectedSupplierId = $supplierId > 0 ? $supplierId : null;

        $baseQuery = OutgoingTransaction::query()
            ->searchKeyword($search)
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->forSemester($selectedSemester);
            })
            ->when($selectedSupplierId !== null, function ($query) use ($selectedSupplierId): void {
                $query->forSupplier($selectedSupplierId);
            })
            ->when($selectedTransactionDateRange !== null, function ($query) use ($selectedTransactionDateRange): void {
                $query->whereBetween('transaction_date', $selectedTransactionDateRange);
            });

        $baseQuery->when($isDefaultRecentMode, function ($query) use ($recentRangeStart): void {
            $query->where('transaction_date', '>=', $recentRangeStart);
        });

        $transactions = (clone $baseQuery)
            ->onlyListColumns()
            ->withSupplierInfo()
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $supplierRecap = (clone $baseQuery)
            ->join('suppliers', 'suppliers.id', '=', 'outgoing_transactions.supplier_id')
            ->selectRaw('suppliers.id as supplier_id, suppliers.name as supplier_name, suppliers.company_name as supplier_company_name, COUNT(outgoing_transactions.id) as transaction_count, COALESCE(SUM(outgoing_transactions.total), 0) as total_amount')
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.company_name')
            ->orderBy('suppliers.name')
            ->paginate(20, ['*'], 'recap_page')
            ->withQueryString();

        $supplierRecapSummary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_transactions, COALESCE(SUM(total), 0) as total_amount')
            ->first();
        $transactionAdminActionMap = [];
        $transactionIds = collect($transactions->items())
            ->map(fn(OutgoingTransaction $transaction): int => (int) $transaction->id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values();
        if ($transactionIds->isNotEmpty()) {
            $actionRows = AuditLog::query()
                ->selectRaw("subject_id, MAX(CASE WHEN action = 'outgoing.transaction.admin_update' THEN 1 ELSE 0 END) as edited")
                ->where('subject_type', OutgoingTransaction::class)
                ->whereIn('subject_id', $transactionIds->all())
                ->where('action', 'outgoing.transaction.admin_update')
                ->groupBy('subject_id')
                ->get();
            foreach ($actionRows as $row) {
                $transactionId = (int) ($row->subject_id ?? 0);
                if ($transactionId <= 0) {
                    continue;
                }
                $transactionAdminActionMap[$transactionId] = [
                    'edited' => (int) ($row->edited ?? 0) === 1,
                ];
            }
        }
        foreach ($transactions->items() as $transactionRow) {
            $transactionId = (int) $transactionRow->id;
            if (! isset($transactionAdminActionMap[$transactionId])) {
                $transactionAdminActionMap[$transactionId] = [
                    'edited' => false,
                ];
            }
        }
        $supplierSemesterClosedMap = [];
        $selectedSupplierSemesterClosed = false;
        if ($selectedSemester !== null) {
            $supplierSemesterClosedMap = $this->semesterBookService->supplierSemesterClosedStates(
                $supplierRecap->pluck('supplier_id')->all(),
                $selectedSemester
            );
            if ($selectedSupplierId !== null) {
                $selectedSupplierSemesterClosed = $this->semesterBookService->isSupplierClosed($selectedSupplierId, $selectedSemester);
            }
        }

        return view('outgoing_transactions.index', [
            'transactions' => $transactions,
            'supplierRecap' => $supplierRecap,
            'supplierRecapSummary' => $supplierRecapSummary,
            'search' => $search,
            'semesterOptions' => $semesterOptions,
            'selectedSemester' => $selectedSemester,
            'selectedTransactionDate' => $selectedTransactionDate,
            'isDefaultRecentMode' => $isDefaultRecentMode,
            'selectedSupplierId' => $selectedSupplierId,
            'supplierOptions' => Cache::remember(
                AppCache::lookupCacheKey('outgoing_transactions.index.supplier_options'),
                $now->copy()->addSeconds(60),
                fn() => Supplier::query()->onlyLookupColumns()->orderBy('name')->get()
            ),
            'currentSemester' => $defaultSemester,
            'previousSemester' => $previousSemester,
            'selectedSupplierSemesterClosed' => $selectedSupplierSemesterClosed,
            'supplierSemesterClosedMap' => $supplierSemesterClosedMap,
            'transactionAdminActionMap' => $transactionAdminActionMap,
        ]);
    }

    public function create(): View
    {
        $now = now();
        $defaultSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($defaultSemester);
        $semesterOptionsBase = $this->cachedSemesterOptionsFromPeriodColumn(
            'outgoing_transactions.index.semester_options.base',
            OutgoingTransaction::class
        );
        $semesterOptions = $this->semesterOptionsForForm($semesterOptionsBase);
        if (! $semesterOptions->contains($defaultSemester)) {
            $defaultSemester = (string) ($semesterOptions->first() ?? $defaultSemester);
        }
        $outgoingUnitOptions = $this->configuredOutgoingUnitOptions();
        $oldSupplierId = (int) old('supplier_id', 0);
        $initialSuppliers = Cache::remember(
            AppCache::lookupCacheKey('forms.outgoing_transactions.suppliers', ['limit' => 20]),
            $now->copy()->addSeconds(60),
            fn() => Supplier::query()
                ->onlyLookupColumns()
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($oldSupplierId > 0 && ! $initialSuppliers->contains('id', $oldSupplierId)) {
            $oldSupplier = Supplier::query()
                ->onlyLookupColumns()
                ->whereKey($oldSupplierId)
                ->first();
            if ($oldSupplier !== null) {
                $initialSuppliers->prepend($oldSupplier);
            }
        }
        $initialSuppliers = $initialSuppliers->unique('id')->values();

        $oldProductIds = collect(old('items', []))
            ->pluck('product_id')
            ->map(fn($id): int => (int) $id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values();
        $initialProducts = Cache::remember(
            AppCache::lookupCacheKey('forms.outgoing_transactions.products', ['limit' => 20, 'active_only' => 1]),
            $now->copy()->addSeconds(60),
            fn() => Product::query()
                ->onlyOutgoingFormColumns()
                ->active()
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($oldProductIds->isNotEmpty()) {
            $oldProducts = Product::query()
                ->onlyOutgoingFormColumns()
                ->whereIn('id', $oldProductIds->all())
                ->get();
            $initialProducts = $oldProducts->concat($initialProducts)->unique('id')->values();
        }

        return view('outgoing_transactions.create', [
            'suppliers' => $initialSuppliers,
            'products' => $initialProducts,
            'defaultSemesterPeriod' => $defaultSemester,
            'semesterOptions' => $semesterOptions,
            'outgoingUnitOptions' => $outgoingUnitOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'transaction_date' => ['required', 'date'],
            'semester_period' => ['nullable', 'string', 'max:30'],
            'note_number' => ['nullable', 'string', 'max:80'],
            'supplier_invoice_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['required', 'string', 'max:200'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);
        $selectedSemester = $this->normalizeSemesterPeriod(
            (string) ($data['semester_period'] ?? ''),
            (string) $data['transaction_date']
        );
        if ($this->semesterBookService->isSupplierClosed((int) $data['supplier_id'], $selectedSemester)) {
            throw ValidationException::withMessages([
                'semester_period' => __('txn.supplier_semester_closed_error', ['semester' => $selectedSemester]),
            ]);
        }

        $supplierInvoicePhotoPath = $request->hasFile('supplier_invoice_photo')
            ? $request->file('supplier_invoice_photo')->store('supplier_invoices', 'public')
            : null;

        $transaction = DB::transaction(function () use ($data, $request, $selectedSemester, $supplierInvoicePhotoPath): OutgoingTransaction {
            $transactionDate = Carbon::parse($data['transaction_date']);
            $transactionNumber = $this->generateTransactionNumber($transactionDate->toDateString());
            $rows = collect($data['items']);

            $products = Product::query()
                ->whereIn('id', $rows->pluck('product_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $grandTotal = 0;
            $computedRows = [];

            foreach ($rows as $index => $row) {
                $quantity = (int) $row['quantity'];
                $unitCost = (int) round((float) ($row['unit_cost'] ?? 0));
                $lineTotal = $quantity * $unitCost;
                $grandTotal += $lineTotal;
                $product = null;
                $productId = (int) ($row['product_id'] ?? 0);
                if ($productId > 0) {
                    $product = $products->get($productId);
                    if (! $product) {
                        abort(422, "Product not found for row {$index}");
                    }
                }

                $computedRows[] = [
                    'product' => $product,
                    'product_name' => trim((string) ($row['product_name'] ?? '')),
                    'unit' => trim((string) ($row['unit'] ?? '')),
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                    'notes' => (string) ($row['notes'] ?? ''),
                ];
            }

            $transaction = OutgoingTransaction::create([
                'transaction_number' => $transactionNumber,
                'transaction_date' => $transactionDate->toDateString(),
                'supplier_id' => (int) $data['supplier_id'],
                'semester_period' => $selectedSemester,
                'note_number' => $data['note_number'] ?? null,
                'supplier_invoice_photo_path' => $supplierInvoicePhotoPath,
                'total' => $grandTotal,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $request->user()?->id,
            ]);

            $supplierId = (int) $data['supplier_id'];
            $beforeOutstanding = (int) (Supplier::query()->whereKey($supplierId)->value('outstanding_payable') ?? 0);

            foreach ($computedRows as $row) {
                $product = $row['product'];
                $quantity = $row['quantity'];

                $transaction->items()->create([
                    'product_id' => $product?->id,
                    'product_code' => $product?->code,
                    'product_name' => $product?->name ?: $row['product_name'],
                    'unit' => $product?->unit ?: ($row['unit'] !== '' ? $row['unit'] : null),
                    'quantity' => $quantity,
                    'unit_cost' => $row['unit_cost'],
                    'line_total' => $row['line_total'],
                    'notes' => $row['notes'] !== '' ? $row['notes'] : null,
                ]);

                if ($product) {
                    $this->fillMissingProductSellingPricesFromUnitCost($product, (int) $row['unit_cost']);
                    $product->increment('stock', $quantity);

                    StockMutation::create([
                        'product_id' => $product->id,
                        'reference_type' => OutgoingTransaction::class,
                        'reference_id' => $transaction->id,
                        'mutation_type' => 'in',
                        'quantity' => $quantity,
                        'notes' => __('txn.outgoing_stock_mutation_note', ['number' => $transaction->transaction_number]),
                        'created_by_user_id' => $request->user()?->id,
                    ]);
                }
            }

            $ledger = $this->supplierLedgerService->addDebit(
                supplierId: $supplierId,
                outgoingTransactionId: (int) $transaction->id,
                entryDate: $transactionDate,
                amount: (float) $grandTotal,
                periodCode: $selectedSemester,
                description: __('supplier_payable.outgoing_debit_ledger_note', ['number' => $transaction->transaction_number])
            );

            $this->auditLogService->log(
                'supplier.payable.debit.create',
                $transaction,
                __('txn.audit_outgoing_created', ['number' => $transaction->transaction_number]),
                $request,
                ['outstanding_payable' => $beforeOutstanding],
                ['outstanding_payable' => (int) $ledger->balance_after],
                ['supplier_id' => $supplierId]
            );

            $this->accountingService->postOutgoingTransaction(
                transactionId: (int) $transaction->id,
                date: $transactionDate,
                amount: (int) round($grandTotal)
            );

            return $transaction;
        });
        
        AppCache::forgetAfterFinancialMutation([(string) $transaction->transaction_date]);

        return redirect()
            ->route('outgoing-transactions.show', $transaction)
            ->with('success', __('txn.outgoing_created_success', ['number' => $transaction->transaction_number]));
    }

    public function adminUpdate(Request $request, OutgoingTransaction $outgoingTransaction): RedirectResponse
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'semester_period' => ['nullable', 'string', 'max:30'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'note_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['required', 'string', 'max:200'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $auditBefore = '';
        $auditAfter = '';

        DB::transaction(function () use ($outgoingTransaction, $data, &$auditBefore, &$auditAfter): void {
            $transaction = OutgoingTransaction::query()
                ->with('items')
                ->whereKey($outgoingTransaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $requestedSupplierId = (int) ($data['supplier_id'] ?? (int) $transaction->supplier_id);
            if ($requestedSupplierId !== (int) $transaction->supplier_id) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Supplier transaksi keluar tidak bisa diubah saat edit admin.',
                ]);
            }

            $rows = collect($data['items'] ?? []);
            $oldTotal = (float) $transaction->total;
            $transactionDate = Carbon::parse((string) $data['transaction_date']);
            $selectedSemester = $this->normalizeSemesterPeriod(
                (string) ($data['semester_period'] ?? ''),
                (string) $data['transaction_date']
            );

            $auditBefore = $transaction->items
                ->map(fn($item): string => "{$item->product_name}:qty{$item->quantity}:cost" . (int) round((float) $item->unit_cost))
                ->implode(' | ');

            $oldQtyByProduct = [];
            foreach ($transaction->items as $existingItem) {
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
                $delta = $newQty - $oldQty;

                if ($delta < 0 && (int) $product->stock < abs($delta)) {
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
                $delta = $newQty - $oldQty;
                if ($delta === 0) {
                    continue;
                }

                if ($delta > 0) {
                    $product->increment('stock', $delta);
                    StockMutation::create([
                        'product_id' => $product->id,
                        'reference_type' => OutgoingTransaction::class,
                        'reference_id' => $transaction->id,
                        'mutation_type' => 'in',
                        'quantity' => $delta,
                        'notes' => "Admin edit outgoing {$transaction->transaction_number}",
                        'created_by_user_id' => auth()->id(),
                    ]);
                } else {
                    $outQty = abs($delta);
                    $product->decrement('stock', $outQty);
                    StockMutation::create([
                        'product_id' => $product->id,
                        'reference_type' => OutgoingTransaction::class,
                        'reference_id' => $transaction->id,
                        'mutation_type' => 'out',
                        'quantity' => $outQty,
                        'notes' => "Admin edit outgoing {$transaction->transaction_number}",
                        'created_by_user_id' => auth()->id(),
                    ]);
                }
            }

            $transaction->items()->delete();
            $newTotal = 0.0;

            foreach ($rows as $row) {
                $quantity = (int) ($row['quantity'] ?? 0);
                $unitCost = (int) round((float) ($row['unit_cost'] ?? 0));
                $lineTotal = $quantity * $unitCost;
                $newTotal += $lineTotal;

                $productId = (int) ($row['product_id'] ?? 0);
                $product = $productId > 0 ? $products->get($productId) : null;
                if ($product) {
                    $this->fillMissingProductSellingPricesFromUnitCost($product, $unitCost);
                }

                $transaction->items()->create([
                    'product_id' => $product?->id,
                    'product_code' => $product?->code,
                    'product_name' => $product?->name ?: (string) ($row['product_name'] ?? ''),
                    'unit' => $product?->unit ?: (string) ($row['unit'] ?? ''),
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                    'notes' => (string) ($row['notes'] ?? ''),
                ]);
            }

            $transaction->update([
                'transaction_date' => $transactionDate->toDateString(),
                'semester_period' => $selectedSemester,
                'note_number' => $data['note_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'total' => (int) round($newTotal),
            ]);

            $difference = (float) round($newTotal - $oldTotal);
            if ($difference > 0) {
                $this->supplierLedgerService->addDebit(
                    supplierId: (int) $transaction->supplier_id,
                    outgoingTransactionId: (int) $transaction->id,
                    entryDate: $transactionDate,
                    amount: $difference,
                    periodCode: $selectedSemester,
                    description: "[ADMIN EDIT +] {$transaction->transaction_number}"
                );
            } elseif ($difference < 0) {
                $this->supplierLedgerService->addCredit(
                    supplierId: (int) $transaction->supplier_id,
                    supplierPaymentId: null,
                    entryDate: $transactionDate,
                    amount: abs($difference),
                    periodCode: $selectedSemester,
                    description: "[ADMIN EDIT -] {$transaction->transaction_number}"
                );
            }

            $auditAfter = $rows
                ->map(function (array $row) use ($products): string {
                    $product = $products->get((int) ($row['product_id'] ?? 0));
                    $name = $product?->name ?: (string) ($row['product_name'] ?? '-');
                    $qty = (int) ($row['quantity'] ?? 0);
                    $cost = (int) round((float) ($row['unit_cost'] ?? 0));

                    return "{$name}:qty{$qty}:cost{$cost}";
                })
                ->implode(' | ');
        });

        $outgoingTransaction->refresh();
        $this->auditLogService->log(
            'outgoing.transaction.admin_update',
            $outgoingTransaction,
            "Admin update outgoing {$outgoingTransaction->transaction_number} | before={$auditBefore} | after={$auditAfter}",
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $outgoingTransaction->transaction_date]);

        return redirect()
            ->route('outgoing-transactions.show', $outgoingTransaction)
            ->with('success', __('txn.admin_update_saved'));
    }

    public function closeSupplierSemester(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'semester' => ['required', 'string', 'max:30'],
            'search' => ['nullable', 'string'],
            'supplier_id' => ['nullable', 'integer'],
        ]);

        $semester = $this->semesterBookService->normalizeSemester((string) ($data['semester'] ?? ''));
        if ($semester === null) {
            return redirect()
                ->route('outgoing-transactions.index')
                ->withErrors(['semester' => __('ui.invalid_semester_format')]);
        }

        $this->semesterBookService->closeSupplierSemester((int) $supplier->id, $semester);

        return redirect()
            ->route('outgoing-transactions.index', [
                'search' => $data['search'] ?? null,
                'semester' => $semester,
                'supplier_id' => (int) $supplier->id,
            ])
            ->with('success', __('txn.supplier_semester_closed_success', [
                'semester' => $semester,
                'supplier' => $supplier->name,
            ]));
    }

    public function openSupplierSemester(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'semester' => ['required', 'string', 'max:30'],
            'search' => ['nullable', 'string'],
            'supplier_id' => ['nullable', 'integer'],
        ]);

        $semester = $this->semesterBookService->normalizeSemester((string) ($data['semester'] ?? ''));
        if ($semester === null) {
            return redirect()
                ->route('outgoing-transactions.index')
                ->withErrors(['semester' => __('ui.invalid_semester_format')]);
        }

        $this->semesterBookService->openSupplierSemester((int) $supplier->id, $semester);

        return redirect()
            ->route('outgoing-transactions.index', [
                'search' => $data['search'] ?? null,
                'semester' => $semester,
                'supplier_id' => (int) $supplier->id,
            ])
            ->with('success', __('txn.supplier_semester_opened_success', [
                'semester' => $semester,
                'supplier' => $supplier->name,
            ]));
    }

    public function show(OutgoingTransaction $outgoingTransaction): View
    {
        $outgoingTransaction->load([
            'supplier:id,name,company_name,phone,address',
            'creator:id,name',
            'items.product:id,code,name,unit',
        ]);
        $itemProductIds = $outgoingTransaction->items
            ->pluck('product_id')
            ->map(fn($id): int => (int) $id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values();
        $products = Product::query()
            ->onlyOutgoingFormColumns()
            ->active()
            ->orderBy('name')
            ->limit(50)
            ->get();
        if ($itemProductIds->isNotEmpty()) {
            $itemProducts = Product::query()
                ->onlyOutgoingFormColumns()
                ->whereIn('id', $itemProductIds->all())
                ->get();
            $products = $itemProducts->concat($products)->unique('id')->values();
        }

        return view('outgoing_transactions.show', [
            'transaction' => $outgoingTransaction,
            'products' => $products,
        ]);
    }

    public function print(OutgoingTransaction $outgoingTransaction): View
    {
        $outgoingTransaction->load([
            'supplier:id,name,company_name,phone,address',
            'creator:id,name',
            'items.product:id,code,name,unit',
        ]);

        return view('outgoing_transactions.print', [
            'transaction' => $outgoingTransaction,
        ]);
    }

    public function exportPdf(OutgoingTransaction $outgoingTransaction)
    {
        $outgoingTransaction->load([
            'supplier:id,name,company_name,phone,address',
            'creator:id,name',
            'items.product:id,code,name,unit',
        ]);

        $pdf = Pdf::loadView('outgoing_transactions.print', [
            'transaction' => $outgoingTransaction,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($outgoingTransaction->transaction_number . '-' . $this->nowWib()->format('Ymd-His') . '.pdf');
    }

    public function exportExcel(OutgoingTransaction $outgoingTransaction): StreamedResponse
    {
        $outgoingTransaction->load([
            'supplier:id,name,company_name,phone,address',
            'creator:id,name',
            'items.product:id,code,name,unit',
        ]);

        $filename = $outgoingTransaction->transaction_number . '-' . $this->nowWib()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($outgoingTransaction): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tanda Terima Barang');

            $rowsOut = [];
            $rowsOut[] = [__('txn.outgoing_receipt_title')];
            $rowsOut[] = [__('txn.transaction_number'), $outgoingTransaction->transaction_number];
            $rowsOut[] = [__('txn.date'), optional($outgoingTransaction->transaction_date)->format('d-m-Y')];
            $rowsOut[] = [__('txn.note_number'), (string) ($outgoingTransaction->note_number ?: '-')];
            $rowsOut[] = [__('txn.supplier'), (string) ($outgoingTransaction->supplier?->name ?: '-')];
            $rowsOut[] = [__('ui.supplier_company_name'), (string) ($outgoingTransaction->supplier?->company_name ?: '-')];
            $rowsOut[] = [__('txn.phone'), (string) ($outgoingTransaction->supplier?->phone ?: '-')];
            $rowsOut[] = [__('txn.address'), (string) ($outgoingTransaction->supplier?->address ?: '-')];
            $rowsOut[] = [];
            $rowsOut[] = [
                __('txn.no'),
                __('txn.code'),
                __('txn.name'),
                __('txn.unit'),
                __('txn.qty'),
                __('txn.price'),
                __('txn.subtotal'),
                __('txn.notes'),
            ];

            foreach ($outgoingTransaction->items as $index => $item) {
                $rowsOut[] = [
                    $index + 1,
                    (string) ($item->product_code ?: '-'),
                    (string) $item->product_name,
                    (string) ($item->unit ?: '-'),
                    (int) round((float) $item->quantity),
                    (int) round((float) $item->unit_cost),
                    (int) round((float) $item->line_total),
                    (string) ($item->notes ?: '-'),
                ];
            }

            $rowsOut[] = [];
            $rowsOut[] = [__('txn.grand_total'), (int) round((float) $outgoingTransaction->total)];
            $rowsOut[] = [__('txn.notes'), (string) ($outgoingTransaction->notes ?: '-')];

            $sheet->fromArray($rowsOut, null, 'A1');
            $itemsCount = $outgoingTransaction->items->count();
            $itemsHeaderRow = 10;
            ExcelExportStyler::styleTable($sheet, $itemsHeaderRow, 8, $itemsCount, true);
            ExcelExportStyler::formatNumberColumns($sheet, $itemsHeaderRow + 1, $itemsHeaderRow + $itemsCount, [1, 5, 6, 7], '#,##0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function defaultSemesterPeriod(): string
    {
        return $this->semesterBookService->currentSemester();
    }

    private function previousSemesterPeriod(string $semester): string
    {
        return $this->semesterBookService->previousSemester($semester);
    }

    private function normalizeSemesterPeriod(string $value, ?string $transactionDate = null): string
    {
        $normalized = $this->semesterBookService->normalizeSemester(trim($value));
        if ($normalized !== null) {
            return $normalized;
        }

        $fromDate = $this->semesterBookService->semesterFromDate($transactionDate);
        if ($fromDate !== null) {
            return $fromDate;
        }

        return $this->defaultSemesterPeriod();
    }

    private function generateTransactionNumber(string $date): string
    {
        $formattedDate = Carbon::parse($date)->format('Ymd');
        $prefix = 'TRXK-' . $formattedDate . '-';

        $lastNumber = OutgoingTransaction::query()
            ->where('transaction_number', 'like', $prefix . '%')
            ->max('transaction_number');

        $sequence = 1;
        if (is_string($lastNumber) && $lastNumber !== '') {
            $suffix = (int) substr($lastNumber, -4);
            $sequence = $suffix + 1;
        }

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function semesterBookService(): SemesterBookService
    {
        return $this->semesterBookService;
    }

    private function configuredOutgoingUnitOptions()
    {
        $options = collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('outgoing_unit_options', 'exp|Exemplar')) ?: [])
            ->map(fn(string $item): string => trim($item))
            ->filter(fn(string $item): bool => $item !== '')
            ->map(function (string $item): array {
                $rawCode = '';
                $rawLabel = $item;
                if (str_contains($item, '|')) {
                    [$rawCode, $rawLabel] = array_pad(array_map('trim', explode('|', $item, 2)), 2, '');
                }
                $code = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', (string) $rawCode));
                if ($code === '' && $rawLabel !== '') {
                    $code = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', $rawLabel));
                }
                $label = trim($rawLabel) !== '' ? trim($rawLabel) : ucfirst($code);

                return [
                    'code' => $code,
                    'label' => $label,
                ];
            })
            ->filter(fn(array $item): bool => $item['code'] !== '' && $item['label'] !== '')
            ->unique('code')
            ->values();

        if ($options->isEmpty()) {
            return collect([['code' => 'exp', 'label' => 'Exemplar']]);
        }

        return $options;
    }

    private function fillMissingProductSellingPricesFromUnitCost(Product $product, int $unitCost): void
    {
        if ($unitCost <= 0) {
            return;
        }

        $updates = [];
        if ((int) round((float) $product->price_agent) <= 0) {
            $updates['price_agent'] = $unitCost;
        }
        if ((int) round((float) $product->price_sales) <= 0) {
            $updates['price_sales'] = $unitCost;
        }
        if ((int) round((float) $product->price_general) <= 0) {
            $updates['price_general'] = $unitCost;
        }

        if ($updates === []) {
            return;
        }

        $product->fill($updates);
        $product->save();
    }

    private function nowWib(): Carbon
    {
        return now('Asia/Jakarta');
    }
}
