<?php

namespace App\Http\Controllers;

use App\Support\ExcelCsv;
use App\Models\Customer;
use App\Models\AuditLog;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\AppSetting;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\StockMutation;
use App\Services\ReceivableLedgerService;
use App\Services\AuditLogService;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesInvoicePageController extends Controller
{
    public function __construct(
        private readonly ReceivableLedgerService $receivableLedgerService,
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $semester = trim((string) $request->string('semester', ''));
        $status = trim((string) $request->string('status', ''));
        $invoiceDate = trim((string) $request->string('invoice_date', ''));
        $selectedStatus = in_array($status, ['active', 'canceled'], true) ? $status : null;
        $selectedSemester = $semester !== '' ? $semester : null;
        $selectedInvoiceDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoiceDate) === 1 ? $invoiceDate : null;

        $currentSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);

        $semesterOptions = SalesInvoice::query()
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
        if ($selectedSemester !== null && ! $semesterOptions->contains($selectedSemester)) {
            $selectedSemester = null;
        }

        $invoices = SalesInvoice::query()
            ->with('customer:id,name,city')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('invoice_number', 'like', "%{$search}%")
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
            ->when($selectedInvoiceDate !== null, function ($query) use ($selectedInvoiceDate): void {
                $query->whereDate('invoice_date', $selectedInvoiceDate);
            })
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $todaySummary = SalesInvoice::query()
            ->selectRaw('COUNT(*) as total_invoice, COALESCE(SUM(total), 0) as grand_total')
            ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                $query->where('is_canceled', $selectedStatus === 'canceled');
            })
            ->whereDate('invoice_date', now()->toDateString())
            ->first();
        $customerSemesterLockMap = $this->customerSemesterLockMap(collect($invoices->items()));
        $invoiceIds = collect($invoices->items())
            ->map(fn (SalesInvoice $invoice): int => (int) $invoice->id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $invoiceAdminActionMap = [];
        if ($invoiceIds->isNotEmpty()) {
            $actionRows = AuditLog::query()
                ->select(['subject_id', 'action'])
                ->where('subject_type', SalesInvoice::class)
                ->whereIn('subject_id', $invoiceIds->all())
                ->whereIn('action', ['sales.invoice.admin_update', 'sales.invoice.cancel'])
                ->get();

            foreach ($actionRows as $row) {
                $invoiceId = (int) ($row->subject_id ?? 0);
                if ($invoiceId <= 0) {
                    continue;
                }
                if (! isset($invoiceAdminActionMap[$invoiceId])) {
                    $invoiceAdminActionMap[$invoiceId] = [
                        'edited' => false,
                        'canceled' => false,
                    ];
                }
                if ((string) $row->action === 'sales.invoice.admin_update') {
                    $invoiceAdminActionMap[$invoiceId]['edited'] = true;
                }
                if ((string) $row->action === 'sales.invoice.cancel') {
                    $invoiceAdminActionMap[$invoiceId]['canceled'] = true;
                }
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
        $configured = collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('semester_period_options', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '');

        $semesterOptions = SalesInvoice::query()
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->merge($configured)
            ->push($defaultSemester)
            ->push($previousSemester)
            ->unique()
            ->sortDesc()
            ->values();
        $semesterOptions = collect($this->semesterBookService()->filterToActiveSemesters($semesterOptions->all()));
        if (! $semesterOptions->contains($defaultSemester)) {
            $defaultSemester = (string) ($semesterOptions->first() ?? $defaultSemester);
        }

        return view('sales_invoices.create', [
            'customers' => Customer::query()
                ->with('level:id,code,name')
                ->orderBy('name')
                ->get(['id', 'name', 'city', 'customer_level_id']),
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'stock', 'price_agent', 'price_sales', 'price_general']),
            'defaultSemesterPeriod' => $defaultSemester,
            'semesterOptions' => $semesterOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'semester_period' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'payment_method' => ['required', 'in:tunai,kredit'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $invoice = DB::transaction(function () use ($data): SalesInvoice {
            $invoiceDate = Carbon::parse($data['invoice_date']);
            $invoiceNumber = $this->generateInvoiceNumber($invoiceDate->toDateString());
            $rows = collect($data['items']);

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

                $computedRows[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ];
            }

            $invoice = SalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $data['customer_id'],
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'semester_period' => $data['semester_period'] ?? $this->defaultSemesterPeriod(),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'total_paid' => 0,
                'balance' => $subtotal,
                'payment_status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($computedRows as $row) {
                /** @var Product $product */
                $product = $row['product'];

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
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
                description: __('receivable.invoice_label').' '.$invoice->invoice_number
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

            return $invoice;
        });

        $this->auditLogService->log(
            'sales.invoice.create',
            $invoice,
            __('txn.audit_invoice_created', ['number' => $invoice->invoice_number]),
            $request
        );

        return redirect()
            ->route('sales-invoices.show', $invoice)
            ->with('success', __('txn.invoice_created_success', ['number' => $invoice->invoice_number]));
    }

    public function show(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load([
            'customer:id,name,city,phone',
            'items.product:id,code,name',
            'payments',
        ]);
        $currentSemester = $this->defaultSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);
        $semesterOptions = SalesInvoice::query()
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->merge($this->configuredSemesterOptions())
            ->push($currentSemester)
            ->push($previousSemester)
            ->push((string) $salesInvoice->semester_period)
            ->unique()
            ->sortDesc()
            ->values();
        $semesterOptions = collect($this->semesterBookService()->filterToActiveSemesters($semesterOptions->all()));
        if (! $semesterOptions->contains((string) $salesInvoice->semester_period)) {
            $semesterOptions = $semesterOptions->push((string) $salesInvoice->semester_period)->unique()->values();
        }
        $customerSemesterLockState = $this->customerSemesterLockState(
            (int) $salesInvoice->customer_id,
            (string) $salesInvoice->semester_period
        );

        return view('sales_invoices.show', [
            'invoice' => $salesInvoice,
            'customerSemesterLockState' => $customerSemesterLockState,
            'semesterOptions' => $semesterOptions,
            'products' => Product::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'stock', 'price_agent', 'price_sales', 'price_general']),
        ]);
    }

    public function adminUpdate(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $data = $request->validate([
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'semester_period' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
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
            $oldTotal = (float) $invoice->total;
            $auditBefore = $invoice->items
                ->map(fn (SalesInvoiceItem $item): string => "{$item->product_name}:qty{$item->quantity}:price".(int) round((float) $item->unit_price))
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

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
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

            $paymentsTotal = (float) $invoice->payments->sum('amount');
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

            $difference = (float) round($subtotal - $oldTotal);
            if ($difference > 0) {
                $this->receivableLedgerService->addDebit(
                    customerId: (int) $invoice->customer_id,
                    invoiceId: (int) $invoice->id,
                    entryDate: Carbon::parse((string) $data['invoice_date']),
                    amount: $difference,
                    periodCode: $invoice->semester_period,
                    description: __('txn.admin_invoice_edit_ledger_increase', ['invoice' => $invoice->invoice_number]),
                );
            } elseif ($difference < 0) {
                $this->receivableLedgerService->addCredit(
                    customerId: (int) $invoice->customer_id,
                    invoiceId: (int) $invoice->id,
                    entryDate: Carbon::parse((string) $data['invoice_date']),
                    amount: abs($difference),
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

        $filename = $salesInvoice->invoice_number.'.pdf';
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

        $filename = $salesInvoice->invoice_number.'.csv';

        return response()->streamDownload(function () use ($salesInvoice): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            ExcelCsv::start($handle);
            ExcelCsv::row($handle, [__('txn.note_number'), $salesInvoice->invoice_number]);
            ExcelCsv::row($handle, [__('txn.invoice_date'), $salesInvoice->invoice_date?->format('d-m-Y')]);
            ExcelCsv::row($handle, [__('txn.customer'), $salesInvoice->customer?->name]);
            ExcelCsv::row($handle, [__('txn.city'), $salesInvoice->customer?->city]);
            $paymentStatusLabel = match ((string) $salesInvoice->payment_status) {
                'paid' => __('txn.status_paid'),
                default => __('txn.status_unpaid'),
            };
            ExcelCsv::row($handle, [__('txn.status'), $paymentStatusLabel]);
            $paidFromCustomerBalance = (float) $salesInvoice->payments
                ->where('method', 'customer_balance')
                ->sum('amount');
            $paidCash = max(0, (float) $salesInvoice->total_paid - $paidFromCustomerBalance);
            ExcelCsv::row($handle, [__('txn.total'), number_format((int) round((float) $salesInvoice->total), 0, ',', '.')]);
            ExcelCsv::row($handle, [__('txn.paid'), number_format((int) round((float) $salesInvoice->total_paid), 0, ',', '.')]);
            ExcelCsv::row($handle, [__('txn.paid_cash'), number_format((int) round($paidCash), 0, ',', '.')]);
            ExcelCsv::row($handle, [__('txn.paid_customer_balance'), number_format((int) round($paidFromCustomerBalance), 0, ',', '.')]);
            ExcelCsv::row($handle, [__('txn.balance'), number_format((int) round((float) $salesInvoice->balance), 0, ',', '.')]);
            ExcelCsv::row($handle, []);
            ExcelCsv::row($handle, [__('txn.items')]);
            ExcelCsv::row($handle, [__('txn.name'), __('txn.qty'), __('txn.price'), __('txn.discount').' (%)', __('txn.line_total')]);

            foreach ($salesInvoice->items as $item) {
                $gross = (float) $item->quantity * (float) $item->unit_price;
                $discountPercent = $gross > 0 ? (float) $item->discount / $gross * 100 : 0;
                ExcelCsv::row($handle, [
                    $item->product_name,
                    $item->quantity,
                    number_format((int) round((float) $item->unit_price), 0, ',', '.'),
                    (int) round($discountPercent),
                    number_format((int) round((float) $item->line_total), 0, ',', '.'),
                ]);
            }

            ExcelCsv::row($handle, []);
            ExcelCsv::row($handle, [__('txn.record_payment')]);
            ExcelCsv::row($handle, [__('txn.date'), __('txn.method'), __('txn.amount'), __('txn.notes')]);
            foreach ($salesInvoice->payments as $payment) {
                ExcelCsv::row($handle, [
                    $payment->payment_date?->format('d-m-Y'),
                    $this->paymentMethodLabel((string) $payment->method),
                    number_format((int) round((float) $payment->amount), 0, ',', '.'),
                    $payment->notes,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function generateInvoiceNumber(string $date): string
    {
        $prefix = 'INV-'.date('Ymd', strtotime($date));
        $count = SalesInvoice::query()
            ->whereDate('invoice_date', $date)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    private function defaultSemesterPeriod(): string
    {
        $year = now()->year;
        $month = (int) now()->format('n');
        $semester = $month <= 6 ? 1 : 2;

        return "S{$semester}-{$year}";
    }

    private function previousSemesterPeriod(string $period): string
    {
        if (preg_match('/^S([12])-(\d{4})$/', $period, $matches) === 1) {
            $semester = (int) $matches[1];
            $year = (int) $matches[2];

            if ($semester === 2) {
                return "S1-{$year}";
            }

            return 'S2-'.($year - 1);
        }

        $previous = now()->subMonths(6);
        $semester = (int) $previous->format('n') <= 6 ? 1 : 2;
        $year = $previous->year;

        return "S{$semester}-{$year}";
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

    /**
     * @param  \Illuminate\Support\Collection<int, SalesInvoice>  $invoices
     * @return array<string, array{locked:bool,manual:bool,auto:bool}>
     */
    private function customerSemesterLockMap(\Illuminate\Support\Collection $invoices): array
    {
        $pairs = $invoices
            ->map(function (SalesInvoice $invoice): ?array {
                $customerId = (int) $invoice->customer_id;
                $semester = trim((string) $invoice->semester_period);
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

        $aggregates = SalesInvoice::query()
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
