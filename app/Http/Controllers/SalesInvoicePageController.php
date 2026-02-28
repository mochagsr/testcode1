<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateFilters;
use App\Http\Controllers\Concerns\ResolvesSemesterOptions;
use App\Models\Customer;
use App\Models\AuditLog;
use App\Models\InvoicePayment;
use App\Models\OrderNote;
use App\Models\OrderNoteItem;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\StockMutation;
use App\Services\ReceivableLedgerService;
use App\Services\AuditLogService;
use App\Services\AccountingService;
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

class SalesInvoicePageController extends Controller
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
        $invoiceDate = trim((string) $request->string('invoice_date', ''));
        $selectedStatus = in_array($status, ['active', 'canceled'], true) ? $status : null;
        $selectedSemester = $this->normalizedSemesterInput($semester);
        $selectedInvoiceDate = $this->selectedDateFilter($invoiceDate);
        $selectedInvoiceDateRange = $this->selectedDateRange($selectedInvoiceDate);
        $isDefaultRecentMode = $selectedInvoiceDateRange === null && $selectedSemester === null && $search === '';
        $recentRangeStart = $now->copy()->subDays(6)->startOfDay();
        $todayRange = [$now->copy()->startOfDay(), $now->copy()->endOfDay()];

        $currentSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);

        $semesterOptionsBase = $this->cachedSemesterOptionsFromPeriodColumn(
            'sales_invoices.index.semester_options.base',
            SalesInvoice::class
        );
        $semesterOptions = $this->semesterOptionsForIndex($semesterOptionsBase, $isAdminUser);
        $selectedSemester = $this->selectedSemesterIfAvailable($selectedSemester, $semesterOptions);

        $invoices = SalesInvoice::query()
            ->onlyListColumns()
            ->withCustomerInfo()
            ->searchKeyword($search)
            ->when($selectedStatus === 'active', fn($q) => $q->active())
            ->when($selectedStatus === 'canceled', fn($q) => $q->canceled())
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->forSemester($selectedSemester);
            })
            ->when($selectedInvoiceDateRange !== null, function ($query) use ($selectedInvoiceDateRange): void {
                $query->whereBetween('invoice_date', $selectedInvoiceDateRange);
            })
            ->when($isDefaultRecentMode, function ($query) use ($recentRangeStart): void {
                $query->where('invoice_date', '>=', $recentRangeStart);
            })
            ->orderByDate()
            ->paginate((int) config('pagination.default_per_page', 20))
            ->withQueryString();

        $todaySummary = Cache::remember(
            AppCache::lookupCacheKey('sales_invoices.index.today_summary', [
                'status' => $selectedStatus ?? 'all',
                'date' => $now->toDateString(),
            ]),
            $now->copy()->addSeconds(30),
            function () use ($selectedStatus, $todayRange) {
                return SalesInvoice::query()
                    ->selectRaw('COUNT(*) as total_invoice, COALESCE(SUM(total), 0) as grand_total')
                    ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                        $query->where('is_canceled', $selectedStatus === 'canceled');
                    })
                    ->whereBetween('invoice_date', $todayRange)
                    ->first();
            }
        );
        $lockPairs = collect($invoices->items())
            ->map(function (SalesInvoice $invoice): array {
                return [
                    'customer_id' => (int) $invoice->customer_id,
                    'semester' => trim((string) $invoice->semester_period),
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
        $invoiceIds = collect($invoices->items())
            ->map(fn(SalesInvoice $invoice): int => (int) $invoice->id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values();
        $invoiceAdminActionMap = [];
        if ($invoiceIds->isNotEmpty()) {
            $actionRows = AuditLog::query()
                ->selectRaw("subject_id, MAX(CASE WHEN action = 'sales.invoice.admin_update' THEN 1 ELSE 0 END) as edited, MAX(CASE WHEN action = 'sales.invoice.cancel' THEN 1 ELSE 0 END) as canceled")
                ->where('subject_type', SalesInvoice::class)
                ->whereIn('subject_id', $invoiceIds->all())
                ->whereIn('action', ['sales.invoice.admin_update', 'sales.invoice.cancel'])
                ->groupBy('subject_id')
                ->get();

            foreach ($actionRows as $row) {
                $invoiceId = (int) ($row->subject_id ?? 0);
                if ($invoiceId <= 0) {
                    continue;
                }
                $invoiceAdminActionMap[$invoiceId] = [
                    'edited' => (int) ($row->edited ?? 0) === 1,
                    'canceled' => (int) ($row->canceled ?? 0) === 1,
                ];
            }
        }
        foreach ($invoices->items() as $invoiceRow) {
            $invoiceId = (int) $invoiceRow->id;
            if (! isset($invoiceAdminActionMap[$invoiceId])) {
                $invoiceAdminActionMap[$invoiceId] = [
                    'edited' => false,
                    'canceled' => false,
                ];
            }
            if ((bool) $invoiceRow->is_canceled) {
                $invoiceAdminActionMap[$invoiceId]['canceled'] = true;
            }
        }

        return view('sales_invoices.index', [
            'invoices' => $invoices,
            'search' => $search,
            'semesterOptions' => $semesterOptions,
            'selectedSemester' => $selectedSemester,
            'selectedStatus' => $selectedStatus,
            'selectedInvoiceDate' => $selectedInvoiceDate,
            'isDefaultRecentMode' => $isDefaultRecentMode,
            'currentSemester' => $currentSemester,
            'previousSemester' => $previousSemester,
            'todaySummary' => $todaySummary,
            'customerSemesterLockMap' => $customerSemesterLockMap,
            'invoiceAdminActionMap' => $invoiceAdminActionMap,
        ]);
    }

    public function create(): View
    {
        $defaultSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($defaultSemester);
        $semesterOptionsBase = $this->cachedSemesterOptionsFromPeriodColumn(
            'sales_invoices.index.semester_options.base',
            SalesInvoice::class
        );
        $semesterOptions = $this->semesterOptionsForForm($semesterOptionsBase);
        if (! $semesterOptions->contains($defaultSemester)) {
            $defaultSemester = (string) ($semesterOptions->first() ?? $defaultSemester);
        }

        $oldCustomerId = (int) old('customer_id', 0);
        $initialCustomers = Cache::remember(
            AppCache::lookupCacheKey('forms.sales_invoices.customers', ['limit' => 20]),
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
            AppCache::lookupCacheKey('forms.sales_invoices.products', ['limit' => 20, 'active_only' => 1]),
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
        $initialOrderNotes = collect();
        if ($oldCustomerId > 0) {
            $initialOrderNotes = $this->openOrderNotesForCustomer($oldCustomerId, 50);
        }

        return view('sales_invoices.create', [
            'customers' => $initialCustomers,
            'products' => $initialProducts,
            'orderNotes' => $initialOrderNotes,
            'defaultSemesterPeriod' => $defaultSemester,
            'semesterOptions' => $semesterOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'order_note_id' => ['nullable', 'integer', 'exists:order_notes,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'semester_period' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'payment_method' => ['required', 'in:tunai,kredit'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.order_note_item_id' => ['nullable', 'integer', 'exists:order_note_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $normalizedSemester = $this->semesterBookService()->normalizeSemester((string) ($data['semester_period'] ?? ''));
        $semesterFromDate = $this->semesterBookService()->semesterFromDate((string) $data['invoice_date']);
        $selectedSemester = $normalizedSemester ?? $semesterFromDate ?? $this->defaultSemesterPeriod();

        $invoice = DB::transaction(function () use ($data, $selectedSemester): SalesInvoice {
            $invoiceDate = Carbon::parse($data['invoice_date']);
            $invoiceNumber = $this->generateInvoiceNumber($invoiceDate->toDateString());
            $rows = collect($data['items']);
            $customerId = (int) $data['customer_id'];
            $selectedCustomerName = trim((string) (Customer::query()->whereKey($customerId)->value('name') ?? ''));
            $selectedOrderNoteId = max(0, (int) ($data['order_note_id'] ?? 0));
            $selectedOrderNote = null;
            $orderNoteItemsById = collect();
            $orderNoteItemsByProductId = [];
            $orderNoteItemProductOffset = [];

            if ($selectedOrderNoteId > 0) {
                $selectedOrderNote = OrderNote::query()
                    ->with(['items:id,order_note_id,product_id,product_name,quantity'])
                    ->whereKey($selectedOrderNoteId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $orderNoteCustomerId = (int) ($selectedOrderNote->customer_id ?? 0);
                if ($orderNoteCustomerId > 0 && $orderNoteCustomerId !== $customerId) {
                    throw ValidationException::withMessages([
                        'order_note_id' => __('txn.order_note_customer_mismatch'),
                    ]);
                }
                if ($orderNoteCustomerId <= 0) {
                    $normalizedOrderNoteCustomer = mb_strtolower(trim((string) ($selectedOrderNote->customer_name ?? '')));
                    $normalizedSelectedCustomer = mb_strtolower($selectedCustomerName);
                    if ($normalizedSelectedCustomer === '' || $normalizedOrderNoteCustomer !== $normalizedSelectedCustomer) {
                        throw ValidationException::withMessages([
                            'order_note_id' => __('txn.order_note_customer_mismatch'),
                        ]);
                    }

                    $selectedOrderNote->update([
                        'customer_id' => $customerId,
                    ]);
                }
                if ((bool) ($selectedOrderNote->is_canceled ?? false)) {
                    throw ValidationException::withMessages([
                        'order_note_id' => __('txn.order_note_unavailable'),
                    ]);
                }

                $orderNoteItemsById = $selectedOrderNote->items->keyBy('id');
                $orderNoteItemsByProductId = $selectedOrderNote->items
                    ->filter(fn(OrderNoteItem $item): bool => (int) ($item->product_id ?? 0) > 0)
                    ->groupBy(fn(OrderNoteItem $item): int => (int) $item->product_id)
                    ->all();
            }

            $products = Product::query()
                ->whereIn('id', $rows->pluck('product_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0.0;
            $computedRows = [];

            foreach ($rows as $index => $row) {
                $product = $products->get((int) $row['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => __('txn.product_not_found'),
                    ]);
                }

                $quantity = (int) $row['quantity'];
                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => __('txn.insufficient_stock_for', ['product' => $product->name]),
                    ]);
                }

                $unitPrice = (float) round((float) $row['unit_price']);
                $discountPercent = max(0.0, min(100.0, (float) ($row['discount'] ?? 0)));
                $gross = $quantity * $unitPrice;
                $discount = (float) round($gross * ($discountPercent / 100));
                $lineTotal = max(0, $gross - $discount);
                $subtotal += $lineTotal;
                $orderNoteItemId = max(0, (int) ($row['order_note_item_id'] ?? 0));

                if ($selectedOrderNote !== null) {
                    if ($orderNoteItemId > 0) {
                        $linkedItem = $orderNoteItemsById->get($orderNoteItemId);
                        if (! $linkedItem || (int) ($linkedItem->order_note_id ?? 0) !== (int) $selectedOrderNote->id) {
                            throw ValidationException::withMessages([
                                "items.{$index}.order_note_item_id" => __('txn.order_note_item_invalid'),
                            ]);
                        }
                    } else {
                        $productId = (int) $product->id;
                        $candidates = $orderNoteItemsByProductId[$productId] ?? collect();
                        if ($candidates instanceof \Illuminate\Support\Collection && $candidates->isNotEmpty()) {
                            $offset = (int) ($orderNoteItemProductOffset[$productId] ?? 0);
                            $candidate = $candidates->get($offset) ?? $candidates->last();
                            if ($candidate instanceof OrderNoteItem) {
                                $orderNoteItemId = (int) $candidate->id;
                                $orderNoteItemProductOffset[$productId] = $offset + 1;
                            }
                        }
                    }
                }

                $computedRows[] = [
                    'product' => $product,
                    'order_note_item_id' => $orderNoteItemId > 0 ? $orderNoteItemId : null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ];
            }

            $invoice = SalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customerId,
                'customer_ship_location_id' => null,
                'order_note_id' => $selectedOrderNote?->id,
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'semester_period' => $selectedSemester,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'total_paid' => 0,
                'balance' => $subtotal,
                'payment_status' => 'unpaid',
                'ship_to_name' => null,
                'ship_to_phone' => null,
                'ship_to_city' => null,
                'ship_to_address' => null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($computedRows as $row) {
                /** @var Product $product */
                $product = $row['product'];

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'order_note_item_id' => $row['order_note_item_id'] ?? null,
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'product_name' => $product->name,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'discount' => $row['discount'],
                    'line_total' => $row['line_total'],
                ]);

                $product->decrement('stock', $row['quantity']);

                StockMutation::create([
                    'product_id' => $product->id,
                    'reference_type' => SalesInvoice::class,
                    'reference_id' => $invoice->id,
                    'mutation_type' => 'out',
                    'quantity' => $row['quantity'],
                    'notes' => "Sales invoice {$invoice->invoice_number}",
                    'created_by_user_id' => null,
                ]);
            }

            $this->receivableLedgerService->addDebit(
                customerId: (int) $invoice->customer_id,
                invoiceId: (int) $invoice->id,
                entryDate: $invoiceDate,
                amount: $subtotal,
                periodCode: $invoice->semester_period,
                description: __('receivable.invoice_label') . ' ' . $invoice->invoice_number
            );

            $initialPayment = $data['payment_method'] === 'tunai' ? (float) $invoice->total : 0.0;
            if ($initialPayment > 0) {
                InvoicePayment::create([
                    'sales_invoice_id' => $invoice->id,
                    'payment_date' => $invoiceDate->toDateString(),
                    'amount' => $initialPayment,
                    'method' => 'cash',
                    'notes' => __('txn.full_payment_on_create'),
                ]);

                $balance = max(0, (float) $invoice->total - $initialPayment);
                $invoice->update([
                    'total_paid' => $initialPayment,
                    'balance' => $balance,
                    'payment_status' => $balance <= 0 ? 'paid' : 'unpaid',
                ]);

                $this->receivableLedgerService->addCredit(
                    customerId: (int) $invoice->customer_id,
                    invoiceId: (int) $invoice->id,
                    entryDate: $invoiceDate,
                    amount: $initialPayment,
                    periodCode: $invoice->semester_period,
                    description: __('receivable.payment_for_invoice', [
                        'invoice' => $invoice->invoice_number,
                    ])
                );
            }

            $customer = Customer::query()
                ->lockForUpdate()
                ->findOrFail((int) $invoice->customer_id);

            $availableCustomerBalance = (float) $customer->credit_balance;
            $invoiceBalance = (float) $invoice->balance;
            $appliedFromBalance = min($availableCustomerBalance, $invoiceBalance);
            if ($appliedFromBalance > 0) {
                InvoicePayment::create([
                    'sales_invoice_id' => $invoice->id,
                    'payment_date' => $invoiceDate->toDateString(),
                    'amount' => $appliedFromBalance,
                    'method' => 'customer_balance',
                    'notes' => __('txn.used_customer_balance'),
                ]);

                $newTotalPaid = (float) $invoice->total_paid + $appliedFromBalance;
                $newBalance = max(0, (float) $invoice->total - $newTotalPaid);
                $invoice->update([
                    'total_paid' => $newTotalPaid,
                    'balance' => $newBalance,
                    'payment_status' => $newBalance <= 0 ? 'paid' : 'unpaid',
                ]);

                $customer->update([
                    'credit_balance' => max(0, $availableCustomerBalance - $appliedFromBalance),
                ]);

                $this->receivableLedgerService->addCredit(
                    customerId: (int) $invoice->customer_id,
                    invoiceId: (int) $invoice->id,
                    entryDate: $invoiceDate,
                    amount: $appliedFromBalance,
                    periodCode: $invoice->semester_period,
                    description: __('txn.customer_balance_applied_for_invoice', [
                        'invoice' => $invoice->invoice_number,
                    ])
                );
            }

            $this->accountingService->postSalesInvoice(
                invoiceId: (int) $invoice->id,
                date: $invoiceDate,
                amount: (int) round($subtotal),
                paymentMethod: (string) $data['payment_method']
            );

            return $invoice;
        });

        $this->auditLogService->log(
            'sales.invoice.create',
            $invoice,
            __('txn.audit_invoice_created', ['number' => $invoice->invoice_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $invoice->invoice_date]);

        return redirect()
            ->route('sales-invoices.show', $invoice)
            ->with('success', __('txn.invoice_created_success', ['number' => $invoice->invoice_number]));
    }

    public function show(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load([
            'customer:id,name,city,phone,address',
            'items.product:id,code,name',
            'payments',
            'orderNote:id,note_number,note_date',
        ]);
        $orderNoteProgress = null;
        if ((int) ($salesInvoice->order_note_id ?? 0) > 0) {
            $orderNoteId = (int) $salesInvoice->order_note_id;
            $orderedTotal = max(0, (int) OrderNoteItem::query()
                ->where('order_note_id', $orderNoteId)
                ->sum('quantity'));
            $fulfilledTotal = max(0, (int) round((float) DB::table('sales_invoice_items as sii')
                ->join('sales_invoices as si', 'si.id', '=', 'sii.sales_invoice_id')
                ->whereNull('si.deleted_at')
                ->where('si.is_canceled', false)
                ->where('si.order_note_id', $orderNoteId)
                ->sum('sii.quantity')));
            $remainingTotal = max(0, $orderedTotal - $fulfilledTotal);
            $progressPercent = $orderedTotal > 0 ? min(100, round(($fulfilledTotal / $orderedTotal) * 100, 2)) : 0;
            $orderNoteProgress = [
                'ordered_total' => $orderedTotal,
                'fulfilled_total' => $fulfilledTotal,
                'remaining_total' => $remainingTotal,
                'progress_percent' => $progressPercent,
                'status' => $remainingTotal > 0 ? 'open' : 'finished',
            ];
        }
        $currentSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);
        $semesterOptionsBase = $this->cachedSemesterOptionsFromPeriodColumn(
            'sales_invoices.index.semester_options.base',
            SalesInvoice::class
        );
        $semesterOptions = $this->semesterOptionsForForm(
            $semesterOptionsBase->push((string) $salesInvoice->semester_period)
        );
        if (! $semesterOptions->contains((string) $salesInvoice->semester_period)) {
            $semesterOptions = $semesterOptions->push((string) $salesInvoice->semester_period)->unique()->values();
        }
        $customerSemesterLockState = ['locked' => false, 'manual' => false, 'auto' => false];
        $invoiceSemester = trim((string) $salesInvoice->semester_period);
        if ((int) $salesInvoice->customer_id > 0 && $invoiceSemester !== '') {
            $states = $this->semesterBookService()->customerSemesterLockStates([(int) $salesInvoice->customer_id], $invoiceSemester);
            $customerSemesterLockState = [
                'locked' => (bool) ($states[(int) $salesInvoice->customer_id]['locked'] ?? false),
                'manual' => (bool) ($states[(int) $salesInvoice->customer_id]['manual'] ?? false),
                'auto' => (bool) ($states[(int) $salesInvoice->customer_id]['auto'] ?? false),
            ];
        }
        $itemProductIds = $salesInvoice->items
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

        return view('sales_invoices.show', [
            'invoice' => $salesInvoice,
            'orderNoteProgress' => $orderNoteProgress,
            'customerSemesterLockState' => $customerSemesterLockState,
            'semesterOptions' => $semesterOptions,
            'products' => $products,
        ]);
    }

    public function adminUpdate(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $data = $request->validate([
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'semester_period' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['nullable', 'in:tunai,kredit'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.order_note_item_id' => ['nullable', 'integer', 'exists:order_note_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $auditBefore = '';
        $auditAfter = '';

        DB::transaction(function () use ($salesInvoice, $data, &$auditBefore, &$auditAfter): void {
            $invoice = SalesInvoice::query()
                ->with(['items', 'payments'])
                ->whereKey($salesInvoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->is_canceled) {
                throw ValidationException::withMessages([
                    'items' => __('txn.canceled_info'),
                ]);
            }

            $rows = collect($data['items'] ?? []);
            $invoiceDateValue = Carbon::parse((string) $data['invoice_date'])->toDateString();
            $linkedOrderNote = null;
            $orderNoteItemsById = collect();
            $orderNoteItemsByProductId = [];
            $orderNoteItemProductOffset = [];
            if ((int) ($invoice->order_note_id ?? 0) > 0) {
                $linkedOrderNote = OrderNote::query()
                    ->with(['items:id,order_note_id,product_id,product_name,quantity'])
                    ->whereKey((int) $invoice->order_note_id)
                    ->lockForUpdate()
                    ->first();
                if ($linkedOrderNote !== null) {
                    $orderNoteItemsById = $linkedOrderNote->items->keyBy('id');
                    $orderNoteItemsByProductId = $linkedOrderNote->items
                        ->filter(fn(OrderNoteItem $item): bool => (int) ($item->product_id ?? 0) > 0)
                        ->groupBy(fn(OrderNoteItem $item): int => (int) $item->product_id)
                        ->all();
                }
            }
            $oldBalance = (float) $invoice->balance;
            $selectedPaymentMethod = in_array((string) ($data['payment_method'] ?? ''), ['tunai', 'kredit'], true)
                ? (string) $data['payment_method']
                : (
                    $invoice->payments->contains(function (InvoicePayment $payment) use ($invoice): bool {
                        return strtolower((string) $payment->method) === 'cash'
                            && optional($payment->payment_date)->format('Y-m-d') === optional($invoice->invoice_date)->format('Y-m-d')
                            && (float) $payment->amount >= (float) $invoice->total;
                    }) ? 'tunai' : 'kredit'
                );
            $auditBefore = $invoice->items
                ->map(fn(SalesInvoiceItem $item): string => "{$item->product_name}:qty{$item->quantity}:price" . (int) round((float) $item->unit_price))
                ->implode(' | ');

            $oldQtyByProduct = [];
            foreach ($invoice->items as $existingItem) {
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

                if ($delta < 0) {
                    $need = abs($delta);
                    if ((int) $product->stock < $need) {
                        throw ValidationException::withMessages([
                            'items' => __('txn.insufficient_stock_for', ['product' => $product->name]),
                        ]);
                    }
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
                    $product->increment('stock', $delta);
                    StockMutation::create([
                        'product_id' => $product->id,
                        'reference_type' => SalesInvoice::class,
                        'reference_id' => $invoice->id,
                        'mutation_type' => 'in',
                        'quantity' => $delta,
                        'notes' => "Admin edit invoice {$invoice->invoice_number}",
                        'created_by_user_id' => auth()->id(),
                    ]);
                } else {
                    $outQty = abs($delta);
                    $product->decrement('stock', $outQty);
                    StockMutation::create([
                        'product_id' => $product->id,
                        'reference_type' => SalesInvoice::class,
                        'reference_id' => $invoice->id,
                        'mutation_type' => 'out',
                        'quantity' => $outQty,
                        'notes' => "Admin edit invoice {$invoice->invoice_number}",
                        'created_by_user_id' => auth()->id(),
                    ]);
                }
            }

            $invoice->items()->delete();

            $subtotal = 0.0;
            foreach ($rows as $index => $row) {
                $product = $products->get((int) $row['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => __('txn.product_not_found'),
                    ]);
                }

                $quantity = (int) $row['quantity'];
                $unitPrice = (float) round((float) $row['unit_price']);
                $discountPercent = max(0.0, min(100.0, (float) ($row['discount'] ?? 0)));
                $gross = $quantity * $unitPrice;
                $discount = (float) round($gross * ($discountPercent / 100));
                $lineTotal = max(0, $gross - $discount);
                $subtotal += $lineTotal;
                $orderNoteItemId = max(0, (int) ($row['order_note_item_id'] ?? 0));

                if ($linkedOrderNote !== null) {
                    if ($orderNoteItemId > 0) {
                        $linkedItem = $orderNoteItemsById->get($orderNoteItemId);
                        if (! $linkedItem || (int) ($linkedItem->order_note_id ?? 0) !== (int) $linkedOrderNote->id) {
                            throw ValidationException::withMessages([
                                "items.{$index}.order_note_item_id" => __('txn.order_note_item_invalid'),
                            ]);
                        }
                    } else {
                        $productId = (int) $product->id;
                        $candidates = $orderNoteItemsByProductId[$productId] ?? collect();
                        if ($candidates instanceof \Illuminate\Support\Collection && $candidates->isNotEmpty()) {
                            $offset = (int) ($orderNoteItemProductOffset[$productId] ?? 0);
                            $candidate = $candidates->get($offset) ?? $candidates->last();
                            if ($candidate instanceof OrderNoteItem) {
                                $orderNoteItemId = (int) $candidate->id;
                                $orderNoteItemProductOffset[$productId] = $offset + 1;
                            }
                        }
                    }
                }

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'order_note_item_id' => $orderNoteItemId > 0 ? $orderNoteItemId : null,
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
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

            $paymentNotesForFull = array_unique(array_filter([
                __('txn.full_payment_on_create'),
                'Full payment on invoice creation',
                'Pelunasan penuh saat membuat faktur',
            ]));
            $fullCashOnCreatePayments = $invoice->payments->filter(function (InvoicePayment $payment) use ($invoiceDateValue, $paymentNotesForFull, $invoice): bool {
                $paymentDate = optional($payment->payment_date)->format('Y-m-d');
                $isCash = strtolower((string) $payment->method) === 'cash';
                $hasMarkerNote = in_array((string) ($payment->notes ?? ''), $paymentNotesForFull, true);

                return $isCash && (
                    $hasMarkerNote
                    || ($paymentDate === $invoiceDateValue && (float) $payment->amount >= (float) $invoice->total)
                );
            })->values();
            $fullCashIds = $fullCashOnCreatePayments->pluck('id')->map(fn($id): int => (int) $id)->all();
            $nonSyntheticPaid = (float) $invoice->payments
                ->filter(fn(InvoicePayment $payment): bool => ! in_array((int) $payment->id, $fullCashIds, true))
                ->sum('amount');

            $syntheticAmount = 0.0;
            if ($selectedPaymentMethod === 'tunai') {
                $syntheticAmount = max(0, $subtotal - $nonSyntheticPaid);
                if ($syntheticAmount > 0) {
                    /** @var InvoicePayment|null $primarySynthetic */
                    $primarySynthetic = $fullCashOnCreatePayments->first();
                    if ($primarySynthetic !== null) {
                        $primarySynthetic->update([
                            'payment_date' => $invoiceDateValue,
                            'amount' => $syntheticAmount,
                            'method' => 'cash',
                            'notes' => __('txn.full_payment_on_create'),
                        ]);
                        $extraSyntheticIds = $fullCashOnCreatePayments
                            ->skip(1)
                            ->pluck('id')
                            ->map(fn($id): int => (int) $id)
                            ->all();
                        if ($extraSyntheticIds !== []) {
                            InvoicePayment::query()->whereIn('id', $extraSyntheticIds)->delete();
                        }
                    } else {
                        InvoicePayment::create([
                            'sales_invoice_id' => $invoice->id,
                            'payment_date' => $invoiceDateValue,
                            'amount' => $syntheticAmount,
                            'method' => 'cash',
                            'notes' => __('txn.full_payment_on_create'),
                        ]);
                    }
                } elseif ($fullCashIds !== []) {
                    InvoicePayment::query()->whereIn('id', $fullCashIds)->delete();
                }
            } elseif ($fullCashIds !== []) {
                InvoicePayment::query()->whereIn('id', $fullCashIds)->delete();
            }

            $paymentsTotal = max(0, $nonSyntheticPaid + $syntheticAmount);
            $newBalance = max(0, $subtotal - $paymentsTotal);

            $invoice->update([
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'semester_period' => $data['semester_period'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'total_paid' => $paymentsTotal,
                'balance' => $newBalance,
                'payment_status' => $newBalance <= 0 ? 'paid' : 'unpaid',
            ]);

            $balanceDifference = (float) round($newBalance - $oldBalance);
            if ($balanceDifference > 0) {
                $this->receivableLedgerService->addDebit(
                    customerId: (int) $invoice->customer_id,
                    invoiceId: (int) $invoice->id,
                    entryDate: Carbon::parse((string) $data['invoice_date']),
                    amount: $balanceDifference,
                    periodCode: $invoice->semester_period,
                    description: __('txn.admin_invoice_edit_ledger_increase', ['invoice' => $invoice->invoice_number]),
                );
            } elseif ($balanceDifference < 0) {
                $this->receivableLedgerService->addCredit(
                    customerId: (int) $invoice->customer_id,
                    invoiceId: (int) $invoice->id,
                    entryDate: Carbon::parse((string) $data['invoice_date']),
                    amount: abs($balanceDifference),
                    periodCode: $invoice->semester_period,
                    description: __('txn.admin_invoice_edit_ledger_decrease', ['invoice' => $invoice->invoice_number]),
                );
            }
        });

        $salesInvoice->refresh();
        $this->auditLogService->log(
            'sales.invoice.admin_update',
            $salesInvoice,
            __('txn.audit_invoice_admin_updated', [
                'number' => $salesInvoice->invoice_number,
                'before' => $auditBefore !== '' ? $auditBefore : '-',
                'after' => $auditAfter !== '' ? $auditAfter : '-',
            ]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $salesInvoice->invoice_date]);

        return redirect()
            ->route('sales-invoices.show', $salesInvoice)
            ->with('success', __('txn.admin_update_saved'));
    }

    public function cancel(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($salesInvoice, $data): void {
            $invoice = SalesInvoice::query()
                ->with('items')
                ->whereKey($salesInvoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->is_canceled) {
                return;
            }

            foreach ($invoice->items as $item) {
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

                $product->increment('stock', (int) $item->quantity);

                StockMutation::create([
                    'product_id' => $product->id,
                    'reference_type' => SalesInvoice::class,
                    'reference_id' => $invoice->id,
                    'mutation_type' => 'in',
                    'quantity' => (int) $item->quantity,
                    'notes' => "Cancel invoice {$invoice->invoice_number}",
                    'created_by_user_id' => auth()->id(),
                ]);
            }

            $openBalance = max(0, (float) $invoice->balance);
            if ($openBalance > 0) {
                $this->receivableLedgerService->addCredit(
                    customerId: (int) $invoice->customer_id,
                    invoiceId: (int) $invoice->id,
                    entryDate: now(),
                    amount: $openBalance,
                    periodCode: $invoice->semester_period,
                    description: __('txn.admin_invoice_cancel_ledger_note', ['invoice' => $invoice->invoice_number]),
                );
            }

            $invoice->update([
                'balance' => 0,
                'payment_status' => 'paid',
                'is_canceled' => true,
                'canceled_at' => now(),
                'canceled_by_user_id' => auth()->id(),
                'cancel_reason' => $data['cancel_reason'],
            ]);
        });

        $this->auditLogService->log(
            'sales.invoice.cancel',
            $salesInvoice,
            __('txn.audit_invoice_canceled', ['number' => $salesInvoice->invoice_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $salesInvoice->invoice_date]);

        return redirect()
            ->route('sales-invoices.show', $salesInvoice)
            ->with('success', __('txn.transaction_canceled_success'));
    }

    public function print(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load([
            'customer:id,name,city,phone,address',
            'items',
            'payments',
        ]);

        return view('sales_invoices.print', [
            'invoice' => $salesInvoice,
        ]);
    }

    public function exportPdf(SalesInvoice $salesInvoice)
    {
        $salesInvoice->load([
            'customer:id,name,city,phone,address',
            'items',
            'payments',
        ]);

        $filename = $salesInvoice->invoice_number . '.pdf';
        $pdf = Pdf::loadView('sales_invoices.print', [
            'invoice' => $salesInvoice,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function exportExcel(SalesInvoice $salesInvoice): StreamedResponse
    {
        $salesInvoice->load([
            'customer:id,name,city,phone,address',
            'items',
            'payments',
        ]);

        $filename = $salesInvoice->invoice_number . '.xlsx';

        return response()->streamDownload(function () use ($salesInvoice): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Invoice');

            $rows = [];
            $rows[] = [__('txn.note_number'), $salesInvoice->invoice_number];
            $rows[] = [__('txn.invoice_date'), $salesInvoice->invoice_date?->format('d-m-Y')];
            $rows[] = [__('txn.customer'), $salesInvoice->customer?->name];
            $rows[] = [__('txn.city'), $salesInvoice->customer?->city];
            $paymentStatusLabel = match ((string) $salesInvoice->payment_status) {
                'paid' => __('txn.status_paid'),
                default => __('txn.status_unpaid'),
            };
            $rows[] = [__('txn.status'), $paymentStatusLabel];
            $paidFromCustomerBalance = (float) $salesInvoice->payments
                ->where('method', 'customer_balance')
                ->sum('amount');
            $paidCash = max(0, (float) $salesInvoice->total_paid - $paidFromCustomerBalance);
            $rows[] = [__('txn.total'), number_format((int) round((float) $salesInvoice->total), 0, ',', '.')];
            $rows[] = [__('txn.paid'), number_format((int) round((float) $salesInvoice->total_paid), 0, ',', '.')];
            $rows[] = [__('txn.paid_cash'), number_format((int) round($paidCash), 0, ',', '.')];
            $rows[] = [__('txn.paid_customer_balance'), number_format((int) round($paidFromCustomerBalance), 0, ',', '.')];
            $rows[] = [__('txn.balance'), number_format((int) round((float) $salesInvoice->balance), 0, ',', '.')];
            $rows[] = [];
            $rows[] = [__('txn.items')];
            $rows[] = [__('txn.name'), __('txn.qty'), __('txn.price'), __('txn.discount') . ' (%)', __('txn.line_total')];

            foreach ($salesInvoice->items as $item) {
                $gross = (float) $item->quantity * (float) $item->unit_price;
                $discountPercent = $gross > 0 ? (float) $item->discount / $gross * 100 : 0;
                $rows[] = [
                    $item->product_name,
                    $item->quantity,
                    number_format((int) round((float) $item->unit_price), 0, ',', '.'),
                    (int) round($discountPercent),
                    number_format((int) round((float) $item->line_total), 0, ',', '.'),
                ];
            }

            $rows[] = [];
            $rows[] = [__('txn.record_payment')];
            $rows[] = [__('txn.date'), __('txn.method'), __('txn.amount'), __('txn.notes')];
            foreach ($salesInvoice->payments as $payment) {
                $rows[] = [
                    $payment->payment_date?->format('d-m-Y'),
                    $this->paymentMethodLabel((string) $payment->method),
                    number_format((int) round((float) $payment->amount), 0, ',', '.'),
                    $payment->notes,
                ];
            }

            $sheet->fromArray($rows, null, 'A1');
            $itemsCount = $salesInvoice->items->count();
            $paymentsCount = $salesInvoice->payments->count();
            $itemsHeaderRow = 13;
            $paymentHeaderRow = 16 + $itemsCount;

            ExcelExportStyler::styleTable($sheet, $itemsHeaderRow, 5, $itemsCount, true);
            ExcelExportStyler::formatNumberColumns($sheet, $itemsHeaderRow + 1, $itemsHeaderRow + $itemsCount, [2, 3, 4, 5], '#,##0');
            ExcelExportStyler::styleTable($sheet, $paymentHeaderRow, 4, $paymentsCount, false);
            ExcelExportStyler::formatNumberColumns($sheet, $paymentHeaderRow + 1, $paymentHeaderRow + $paymentsCount, [3], '#,##0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function openOrderNotesForCustomer(int $customerId, int $limit = 50): \Illuminate\Support\Collection
    {
        $customerName = trim((string) (Customer::query()->whereKey($customerId)->value('name') ?? ''));
        $normalizedCustomerName = mb_strtolower($customerName);

        $notes = OrderNote::query()
            ->select(['id', 'note_number', 'note_date'])
            ->where(function ($query) use ($customerId, $normalizedCustomerName): void {
                $query->where('customer_id', $customerId);
                if ($normalizedCustomerName !== '') {
                    $query->orWhere(function ($fallbackQuery) use ($normalizedCustomerName): void {
                        $fallbackQuery->whereNull('customer_id')
                            ->whereRaw('LOWER(TRIM(customer_name)) = ?', [$normalizedCustomerName]);
                    });
                }
            })
            ->active()
            ->orderByDesc('note_date')
            ->orderByDesc('id')
            ->limit(max(1, $limit * 3))
            ->get();

        if ($notes->isEmpty()) {
            return collect();
        }

        $noteIds = $notes->pluck('id')->map(fn($id): int => (int) $id)->all();
        $orderedByNote = DB::table('order_note_items')
            ->whereIn('order_note_id', $noteIds)
            ->selectRaw('order_note_id, COALESCE(SUM(quantity), 0) as ordered_qty')
            ->groupBy('order_note_id')
            ->pluck('ordered_qty', 'order_note_id');
        $fulfilledByNote = DB::table('sales_invoice_items as sii')
            ->join('sales_invoices as si', 'si.id', '=', 'sii.sales_invoice_id')
            ->whereNull('si.deleted_at')
            ->where('si.is_canceled', false)
            ->whereIn('si.order_note_id', $noteIds)
            ->selectRaw('si.order_note_id, COALESCE(SUM(sii.quantity), 0) as fulfilled_qty')
            ->groupBy('si.order_note_id')
            ->pluck('fulfilled_qty', 'si.order_note_id');

        return $notes
            ->map(function (OrderNote $note) use ($orderedByNote, $fulfilledByNote): array {
                $orderedQty = max(0, (int) round((float) ($orderedByNote[(int) $note->id] ?? 0)));
                $fulfilledQty = max(0, (int) round((float) ($fulfilledByNote[(int) $note->id] ?? 0)));
                $remainingQty = max(0, $orderedQty - $fulfilledQty);
                $progress = $orderedQty > 0 ? min(100, round(($fulfilledQty / $orderedQty) * 100, 2)) : 0;

                return [
                    'id' => (int) $note->id,
                    'note_number' => (string) $note->note_number,
                    'note_date' => $note->note_date?->format('d-m-Y'),
                    'ordered_total' => $orderedQty,
                    'fulfilled_total' => $fulfilledQty,
                    'remaining_total' => $remainingQty,
                    'progress_percent' => $progress,
                ];
            })
            ->filter(fn(array $row): bool => (int) ($row['remaining_total'] ?? 0) > 0)
            ->values()
            ->take(max(1, $limit))
            ->values();
    }

    private function generateInvoiceNumber(string $date): string
    {
        $prefix = 'INV-' . date('Ymd', strtotime($date));
        $count = SalesInvoice::query()
            ->whereDate('invoice_date', $date)
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

    private function paymentMethodLabel(string $method): string
    {
        return match (strtolower($method)) {
            'customer_balance' => __('txn.customer_balance'),
            'cash' => __('txn.cash'),
            'writeoff' => __('txn.writeoff'),
            'discount' => __('receivable.method_discount'),
            default => __('txn.credit'),
        };
    }
}
