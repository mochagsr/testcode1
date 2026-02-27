<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerShipLocation;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SchoolBulkTransaction;
use App\Models\SchoolBulkTransactionItem;
use App\Models\SchoolBulkTransactionLocation;
use App\Models\StockMutation;
use App\Services\AccountingService;
use App\Services\AuditLogService;
use App\Services\ReceivableLedgerService;
use App\Support\AppCache;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolBulkTransactionPageController extends Controller
{
    public function __construct(
        private readonly SemesterBookService $semesterBookService,
        private readonly AuditLogService $auditLogService,
        private readonly ReceivableLedgerService $receivableLedgerService,
        private readonly AccountingService $accountingService
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $customerId = $request->integer('customer_id');

        $transactions = SchoolBulkTransaction::query()
            ->select([
                'id',
                'transaction_number',
                'transaction_date',
                'customer_id',
                'semester_period',
                'total_locations',
                'total_items',
            ])
            ->with('customer:id,name,city')
            ->when($customerId > 0, fn(Builder $query) => $query->where('customer_id', $customerId))
            ->searchKeyword($search)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate((int) config('pagination.default_per_page', 20))
            ->withQueryString();

        $customers = Customer::query()
            ->onlyOptionColumns()
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('school_bulk_transactions.index', [
            'transactions' => $transactions,
            'customers' => $customers,
            'search' => $search,
            'selectedCustomerId' => $customerId > 0 ? $customerId : null,
        ]);
    }

    public function create(): View
    {
        $defaultSemester = $this->semesterBookService->currentSemester();
        $oldCustomerId = (int) old('customer_id', 0);

        $customers = Cache::remember(
            AppCache::lookupCacheKey('forms.school_bulk.customers', ['limit' => 200]),
            now()->addSeconds(60),
            fn() => Customer::query()
                ->onlyOptionColumns()
                ->orderBy('name')
                ->limit(200)
                ->get()
        );
        if ($oldCustomerId > 0 && ! $customers->contains('id', $oldCustomerId)) {
            $oldCustomer = Customer::query()
                ->onlyOptionColumns()
                ->whereKey($oldCustomerId)
                ->first();
            if ($oldCustomer !== null) {
                $customers->prepend($oldCustomer);
            }
        }
        $customers = $customers->unique('id')->values();

        $products = Cache::remember(
            AppCache::lookupCacheKey('forms.school_bulk.products', ['limit' => 100, 'active_only' => 1]),
            now()->addSeconds(60),
            fn() => Product::query()
                ->onlyDeliveryFormColumns()
                ->active()
                ->orderBy('name')
                ->limit(100)
                ->get()
        );

        $shipLocations = collect();
        if ($oldCustomerId > 0) {
            $shipLocations = CustomerShipLocation::query()
                ->select(['id', 'customer_id', 'school_name', 'recipient_phone', 'city', 'address'])
                ->where('customer_id', $oldCustomerId)
                ->where('is_active', true)
                ->orderBy('school_name')
                ->limit(20)
                ->get();
        }

        return view('school_bulk_transactions.create', [
            'customers' => $customers,
            'products' => $products,
            'shipLocations' => $shipLocations,
            'defaultSemesterPeriod' => $defaultSemester,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'transaction_date' => ['required', 'date'],
            'semester_period' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'locations' => ['required', 'array', 'min:1'],
            'locations.*.uid' => ['required', 'string', 'max:50'],
            'locations.*.customer_ship_location_id' => ['nullable', 'integer', 'exists:customer_ship_locations,id'],
            'locations.*.school_name' => ['required', 'string', 'max:150'],
            'locations.*.recipient_phone' => ['nullable', 'string', 'max:30'],
            'locations.*.city' => ['nullable', 'string', 'max:100'],
            'locations.*.address' => ['nullable', 'string'],
            'location_items' => ['required', 'array', 'min:1'],
            'location_items.*' => ['required', 'array', 'min:1'],
            'location_items.*.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'location_items.*.*.product_name' => ['required', 'string', 'max:200'],
            'location_items.*.*.unit' => ['nullable', 'string', 'max:30'],
            'location_items.*.*.quantity' => ['required', 'integer', 'min:1'],
            'location_items.*.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'location_items.*.*.notes' => ['nullable', 'string'],
        ]);

        $transaction = DB::transaction(function () use ($data): SchoolBulkTransaction {
            $transactionDate = Carbon::parse((string) $data['transaction_date']);
            $semesterPeriod = $this->semesterBookService->normalizeSemester((string) ($data['semester_period'] ?? ''))
                ?? $this->semesterBookService->semesterFromDate($transactionDate->toDateString())
                ?? $this->semesterBookService->currentSemester();
            $transactionNumber = $this->generateTransactionNumber($transactionDate);
            $locationRows = collect($data['locations'])->values();
            $locationItemsMap = collect((array) ($data['location_items'] ?? []));
            $allItemRows = $locationItemsMap
                ->flatMap(function ($rows): Collection {
                    return collect((array) $rows)->values();
                })
                ->values();
            $customerId = (int) $data['customer_id'];

            $shipLocationIds = $locationRows
                ->pluck('customer_ship_location_id')
                ->map(fn($id): int => (int) $id)
                ->filter(fn(int $id): bool => $id > 0)
                ->unique()
                ->values();
            $shipLocations = CustomerShipLocation::query()
                ->where('customer_id', $customerId)
                ->whereIn('id', $shipLocationIds->all())
                ->get()
                ->keyBy('id');
            if ($shipLocationIds->count() !== $shipLocations->count()) {
                throw ValidationException::withMessages([
                    'locations' => __('school_bulk.invalid_ship_location_customer'),
                ]);
            }

            $productIds = $allItemRows
                ->pluck('product_id')
                ->map(fn($id): int => (int) $id)
                ->filter(fn(int $id): bool => $id > 0)
                ->unique()
                ->values();
            $products = Product::query()
                ->onlyDeliveryFormColumns()
                ->whereIn('id', $productIds->all())
                ->get()
                ->keyBy('id');

            $transaction = SchoolBulkTransaction::create([
                'transaction_number' => $transactionNumber,
                'transaction_date' => $transactionDate->toDateString(),
                'customer_id' => $customerId,
                'semester_period' => $semesterPeriod,
                'total_locations' => (int) $locationRows->count(),
                'total_items' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => auth()->id(),
            ]);

            $totalItems = 0;
            $locationRows->each(function (array $row, int $index) use ($transaction, $shipLocations, $locationItemsMap, $products, &$totalItems): void {
                $shipLocation = null;
                $shipLocationId = (int) ($row['customer_ship_location_id'] ?? 0);
                if ($shipLocationId > 0) {
                    $shipLocation = $shipLocations->get($shipLocationId);
                }
                $uid = trim((string) ($row['uid'] ?? ''));
                $locationItemRows = collect((array) $locationItemsMap->get($uid, []))->values();
                if ($locationItemRows->isEmpty()) {
                    throw ValidationException::withMessages([
                        'location_items' => __('school_bulk.fill_items'),
                    ]);
                }

                $schoolName = trim((string) ($row['school_name'] ?? ''));
                $recipientPhone = trim((string) ($row['recipient_phone'] ?? ''));
                $city = trim((string) ($row['city'] ?? ''));
                $address = trim((string) ($row['address'] ?? ''));

                $createdLocation = SchoolBulkTransactionLocation::create([
                    'school_bulk_transaction_id' => $transaction->id,
                    'customer_ship_location_id' => $shipLocation?->id,
                    'school_name' => $schoolName !== '' ? $schoolName : (string) ($shipLocation?->school_name ?: '-'),
                    'recipient_name' => null,
                    'recipient_phone' => $recipientPhone !== '' ? $recipientPhone : ($shipLocation?->recipient_phone ?: null),
                    'city' => $city !== '' ? $city : ($shipLocation?->city ?: null),
                    'address' => $address !== '' ? $address : ($shipLocation?->address ?: null),
                    'sort_order' => $index,
                ]);

                $locationItemRows->each(function (array $itemRow, int $itemIndex) use ($transaction, $products, $createdLocation, &$totalItems): void {
                    $productId = (int) ($itemRow['product_id'] ?? 0);
                    $product = $productId > 0 ? $products->get($productId) : null;
                    $productName = trim((string) ($itemRow['product_name'] ?? ''));
                    $unit = trim((string) ($itemRow['unit'] ?? ''));
                    $unitPriceInput = $itemRow['unit_price'] ?? null;
                    $unitPrice = $unitPriceInput === null || $unitPriceInput === ''
                        ? null
                        : (int) round((float) $unitPriceInput);
                    if ($unitPrice === null && $product !== null) {
                        $unitPrice = (int) round((float) ($product->price_general ?? 0));
                    }

                    SchoolBulkTransactionItem::create([
                        'school_bulk_transaction_id' => $transaction->id,
                        'school_bulk_transaction_location_id' => $createdLocation->id,
                        'product_id' => $product?->id,
                        'product_code' => $product?->code,
                        'product_name' => $productName !== '' ? $productName : (string) ($product?->name ?: '-'),
                        'unit' => $unit !== '' ? $unit : ($product?->unit ?: null),
                        'quantity' => max(1, (int) ($itemRow['quantity'] ?? 1)),
                        'unit_price' => $unitPrice,
                        'notes' => $itemRow['notes'] ?? null,
                        'sort_order' => $itemIndex,
                    ]);
                    $totalItems++;
                });
            });

            $transaction->update([
                'total_items' => $totalItems,
            ]);

            return $transaction;
        });

        $this->auditLogService->log(
            'school.bulk.create',
            $transaction,
            __('school_bulk.audit_bulk_created', ['number' => $transaction->transaction_number]),
            $request
        );
        AppCache::bumpLookupVersion();

        return redirect()
            ->route('school-bulk-transactions.show', $transaction)
            ->with('success', __('school_bulk.bulk_transaction_created', ['number' => $transaction->transaction_number]));
    }

    public function show(SchoolBulkTransaction $schoolBulkTransaction): View
    {
        $schoolBulkTransaction->load([
            'customer:id,name,city,phone,address',
            'createdByUser:id,name',
            'locations',
            'items',
            'generatedInvoices:id,invoice_number,invoice_date,school_bulk_location_id,balance,payment_status,is_canceled',
            'generatedInvoices.schoolBulkLocation:id,school_name',
        ]);

        return view('school_bulk_transactions.show', [
            'transaction' => $schoolBulkTransaction,
        ]);
    }

    public function generateInvoices(Request $request, SchoolBulkTransaction $schoolBulkTransaction): RedirectResponse
    {
        $data = $request->validate([
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ]);

        $result = DB::transaction(function () use ($schoolBulkTransaction, $data): array {
            $transaction = SchoolBulkTransaction::query()
                ->with([
                    'customer:id,name',
                    'locations',
                    'items',
                ])
                ->whereKey($schoolBulkTransaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoiceDate = isset($data['invoice_date']) && $data['invoice_date'] !== ''
                ? Carbon::parse((string) $data['invoice_date'])
                : Carbon::parse((string) $transaction->transaction_date);
            $dueDate = isset($data['due_date']) && $data['due_date'] !== ''
                ? Carbon::parse((string) $data['due_date'])
                : null;
            if ($dueDate !== null && $dueDate->lt($invoiceDate)) {
                throw ValidationException::withMessages([
                    'due_date' => __('txn.due_date_after_invoice'),
                ]);
            }

            if ($transaction->locations->isEmpty()) {
                throw ValidationException::withMessages([
                    'invoice_date' => __('school_bulk.no_locations_to_generate'),
                ]);
            }
            if ($transaction->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'invoice_date' => __('school_bulk.no_items_to_generate'),
                ]);
            }

            $existingLocationIds = SalesInvoice::query()
                ->withTrashed()
                ->where('school_bulk_transaction_id', $transaction->id)
                ->whereNotNull('school_bulk_location_id')
                ->pluck('school_bulk_location_id')
                ->map(fn($id): int => (int) $id)
                ->filter(fn(int $id): bool => $id > 0)
                ->unique()
                ->values();

            $targetLocations = $transaction->locations
                ->reject(fn(SchoolBulkTransactionLocation $location): bool => $existingLocationIds->contains((int) $location->id))
                ->values();

            if ($targetLocations->isEmpty()) {
                return [
                    'created' => 0,
                    'skipped' => (int) $transaction->locations->count(),
                    'numbers' => [],
                ];
            }

            $itemsByLocation = $transaction->items
                ->groupBy(fn(SchoolBulkTransactionItem $item): int => (int) ($item->school_bulk_transaction_location_id ?? 0));
            /** @var Collection<int, SchoolBulkTransactionItem> $globalItems */
            $globalItems = $itemsByLocation->get(0, collect());

            $resolveItemsForLocation = function (SchoolBulkTransactionLocation $location) use ($itemsByLocation, $globalItems): Collection {
                /** @var Collection<int, SchoolBulkTransactionItem> $specific */
                $specific = $itemsByLocation->get((int) $location->id, collect());
                if ($specific->isNotEmpty()) {
                    return $specific->values();
                }
                if ($globalItems->isNotEmpty()) {
                    return $globalItems->values();
                }

                return collect();
            };

            $requiredByProduct = [];
            $allLocationItems = collect();
            foreach ($targetLocations as $location) {
                $locationItems = $resolveItemsForLocation($location)
                    ->filter(fn(SchoolBulkTransactionItem $item): bool => max(1, (int) $item->quantity) > 0)
                    ->values();
                if ($locationItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'invoice_date' => __('school_bulk.no_items_to_generate'),
                    ]);
                }
                if ($locationItems->contains(fn(SchoolBulkTransactionItem $item): bool => (int) ($item->product_id ?? 0) <= 0)) {
                    throw ValidationException::withMessages([
                        'invoice_date' => __('school_bulk.bulk_items_require_master_products'),
                    ]);
                }

                $allLocationItems = $allLocationItems->merge($locationItems);
                foreach ($locationItems as $item) {
                    $productId = (int) $item->product_id;
                    $requiredByProduct[$productId] = (int) ($requiredByProduct[$productId] ?? 0) + max(1, (int) $item->quantity);
                }
            }
            $productIds = $allLocationItems
                ->pluck('product_id')
                ->map(fn($id): int => (int) $id)
                ->filter(fn(int $id): bool => $id > 0)
                ->unique()
                ->values();
            $products = Product::query()
                ->whereIn('id', $productIds->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'invoice_date' => __('txn.product_not_found'),
                ]);
            }
            foreach ($requiredByProduct as $productId => $requiredQty) {
                $product = $products->get($productId);
                if (! $product) {
                    continue;
                }
                if ((int) $product->stock < (int) $requiredQty) {
                    throw ValidationException::withMessages([
                        'invoice_date' => __('txn.insufficient_stock_for', ['product' => $product->name]),
                    ]);
                }
            }

            $semesterPeriod = $this->semesterBookService->normalizeSemester((string) ($transaction->semester_period ?? ''))
                ?? $this->semesterBookService->semesterFromDate($invoiceDate->toDateString())
                ?? $this->semesterBookService->currentSemester();

            $createdNumbers = [];
            foreach ($targetLocations as $location) {
                $invoiceNumber = $this->generateInvoiceNumber($invoiceDate->toDateString());
                $subtotal = 0;
                $computedRows = [];
                $locationItems = $resolveItemsForLocation($location);

                foreach ($locationItems as $item) {
                    $product = $products->get((int) $item->product_id);
                    if (! $product) {
                        continue;
                    }
                    $quantity = max(1, (int) $item->quantity);
                    $unitPrice = (int) round((float) ($item->unit_price ?? $product->price_general ?? 0));
                    $lineTotal = $quantity * $unitPrice;
                    $subtotal += $lineTotal;
                    $computedRows[] = [
                        'product' => $product,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                $invoice = SalesInvoice::create([
                    'invoice_number' => $invoiceNumber,
                    'customer_id' => (int) $transaction->customer_id,
                    'customer_ship_location_id' => $location->customer_ship_location_id,
                    'school_bulk_transaction_id' => (int) $transaction->id,
                    'school_bulk_location_id' => (int) $location->id,
                    'invoice_date' => $invoiceDate->toDateString(),
                    'due_date' => $dueDate?->toDateString(),
                    'semester_period' => $semesterPeriod,
                    'subtotal' => (int) round((float) $subtotal),
                    'total' => (int) round((float) $subtotal),
                    'total_paid' => 0,
                    'balance' => (int) round((float) $subtotal),
                    'payment_status' => 'unpaid',
                    'ship_to_name' => (string) ($location->school_name ?: ($location->recipient_name ?: '')),
                    'ship_to_phone' => $location->recipient_phone ?: null,
                    'ship_to_city' => $location->city ?: null,
                    'ship_to_address' => $location->address ?: null,
                    'notes' => __('school_bulk.generated_from_bulk', ['number' => $transaction->transaction_number]),
                ]);

                foreach ($computedRows as $row) {
                    /** @var Product $product */
                    $product = $row['product'];
                    SalesInvoiceItem::create([
                        'sales_invoice_id' => $invoice->id,
                        'product_id' => $product->id,
                        'product_code' => $product->code,
                        'product_name' => $product->name,
                        'quantity' => (int) $row['quantity'],
                        'unit_price' => (int) $row['unit_price'],
                        'discount' => 0,
                        'line_total' => (int) $row['line_total'],
                    ]);

                    $product->decrement('stock', (int) $row['quantity']);

                    StockMutation::create([
                        'product_id' => $product->id,
                        'reference_type' => SalesInvoice::class,
                        'reference_id' => $invoice->id,
                        'mutation_type' => 'out',
                        'quantity' => (int) $row['quantity'],
                        'notes' => __('school_bulk.generated_invoice_stock_note', [
                            'bulk' => $transaction->transaction_number,
                            'invoice' => $invoice->invoice_number,
                        ]),
                        'created_by_user_id' => auth()->id(),
                    ]);
                }

                $this->receivableLedgerService->addDebit(
                    customerId: (int) $invoice->customer_id,
                    invoiceId: (int) $invoice->id,
                    entryDate: $invoiceDate,
                    amount: (float) $subtotal,
                    periodCode: $invoice->semester_period,
                    description: __('receivable.invoice_label') . ' ' . $invoice->invoice_number
                );

                $this->accountingService->postSalesInvoice(
                    invoiceId: (int) $invoice->id,
                    date: $invoiceDate,
                    amount: (int) round((float) $subtotal),
                    paymentMethod: 'kredit'
                );

                $createdNumbers[] = $invoice->invoice_number;
            }

            return [
                'created' => count($createdNumbers),
                'skipped' => (int) $existingLocationIds->count(),
                'numbers' => $createdNumbers,
            ];
        });

        $this->auditLogService->log(
            'school.bulk.generate_invoices',
            $schoolBulkTransaction,
            __('school_bulk.audit_bulk_generate_invoices', [
                'number' => $schoolBulkTransaction->transaction_number,
                'created' => (int) ($result['created'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
            ]),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return redirect()
            ->route('school-bulk-transactions.show', $schoolBulkTransaction)
            ->with('success', __('school_bulk.bulk_generate_invoices_success', [
                'created' => (int) ($result['created'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
            ]));
    }

    public function print(SchoolBulkTransaction $schoolBulkTransaction): View
    {
        $schoolBulkTransaction->load([
            'customer:id,name,city,phone,address',
            'createdByUser:id,name',
            'locations',
            'items',
        ]);

        return view('school_bulk_transactions.print', [
            'transaction' => $schoolBulkTransaction,
        ]);
    }

    public function exportPdf(SchoolBulkTransaction $schoolBulkTransaction)
    {
        $schoolBulkTransaction->load([
            'customer:id,name,city,phone,address',
            'createdByUser:id,name',
            'locations',
            'items',
        ]);
        $filename = 'bulk-' . $schoolBulkTransaction->transaction_number . '.pdf';

        return Pdf::loadView('school_bulk_transactions.print', [
            'transaction' => $schoolBulkTransaction,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait')->download($filename);
    }

    public function exportExcel(SchoolBulkTransaction $schoolBulkTransaction): StreamedResponse
    {
        $schoolBulkTransaction->load([
            'customer:id,name,city,phone,address',
            'createdByUser:id,name',
            'locations',
            'items',
        ]);
        $filename = 'bulk-' . $schoolBulkTransaction->transaction_number . '.xlsx';

        return response()->streamDownload(function () use ($schoolBulkTransaction): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Transaksi Sebar');
            $rows = [];
            $rows[] = [__('school_bulk.bulk_transaction_title')];
            $rows[] = [__('school_bulk.transaction_number'), $schoolBulkTransaction->transaction_number];
            $rows[] = [__('txn.date'), $schoolBulkTransaction->transaction_date?->format('d-m-Y')];
            $rows[] = [__('txn.customer'), $schoolBulkTransaction->customer?->name ?: '-'];
            $rows[] = [__('txn.semester_period'), $schoolBulkTransaction->semester_period ?: '-'];
            $rows[] = [__('school_bulk.total_schools'), (int) $schoolBulkTransaction->locations->count()];
            $itemsByLocation = $schoolBulkTransaction->items
                ->groupBy(fn($item): int => (int) ($item->school_bulk_transaction_location_id ?? 0));
            $rows[] = [];
            $rows[] = [__('school_bulk.school_breakdown_title')];
            $rows[] = [__('school_bulk.school_name'), __('txn.city'), __('txn.address'), __('school_bulk.total_per_school')];
            foreach ($schoolBulkTransaction->locations as $location) {
                $locationItems = collect($itemsByLocation->get((int) $location->id, []))->values();
                if ($locationItems->isEmpty()) {
                    $locationItems = collect($itemsByLocation->get(0, []))->values();
                }
                $locationTotal = (int) $locationItems->sum(function ($item): int {
                    return ((int) ($item->quantity ?? 0)) * ((int) ($item->unit_price ?? 0));
                });
                $rows[] = [
                    $location->school_name,
                    $location->city ?: '-',
                    $location->address ?: '-',
                    number_format($locationTotal, 0, ',', '.'),
                ];

                $rows[] = [];
                $rows[] = [
                    __('txn.items') . ' - ' . ($location->school_name ?: '-'),
                ];
                $rows[] = [__('txn.name'), __('txn.unit'), __('txn.qty'), __('txn.price'), __('txn.subtotal')];
                foreach ($locationItems as $item) {
                    $lineTotal = (int) ($item->quantity ?? 0) * (int) ($item->unit_price ?? 0);
                    $rows[] = [
                        (string) ($item->product_name ?? '-'),
                        (string) (($item->unit ?? '') !== '' ? $item->unit : '-'),
                        (int) ($item->quantity ?? 0),
                        (int) ($item->unit_price ?? 0),
                        $lineTotal,
                    ];
                }
                $rows[] = [__('school_bulk.total_per_school'), '', '', '', number_format($locationTotal, 0, ',', '.')];
                $rows[] = [];
            }

            $sheet->fromArray($rows, null, 'A1');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function generateTransactionNumber(Carbon $transactionDate): string
    {
        $prefix = 'BLK-' . $transactionDate->format('Ymd');
        $lastNumber = SchoolBulkTransaction::query()
            ->whereDate('transaction_date', $transactionDate->toDateString())
            ->where('transaction_number', 'like', $prefix . '-%')
            ->lockForUpdate()
            ->max('transaction_number');

        $sequence = 1;
        if (is_string($lastNumber) && preg_match('/-(\d{4})$/', $lastNumber, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }

    private function generateInvoiceNumber(string $invoiceDate): string
    {
        $date = Carbon::parse($invoiceDate);
        $prefix = 'INV-' . $date->format('Ymd');
        $lastNumber = SalesInvoice::query()
            ->whereDate('invoice_date', $date->toDateString())
            ->where('invoice_number', 'like', $prefix . '-%')
            ->lockForUpdate()
            ->max('invoice_number');

        $sequence = 1;
        if (is_string($lastNumber) && preg_match('/-(\d{4})$/', $lastNumber, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }
}
