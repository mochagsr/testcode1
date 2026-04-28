<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateFilters;
use App\Http\Controllers\Concerns\ResolvesSemesterOptions;
use App\Models\Customer;
use App\Models\OrderNote;
use App\Models\OrderNoteItem;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Support\AppCache;
use App\Support\CustomerPrintingSubtypeResolver;
use App\Support\ExcelExportStyler;
use App\Support\SemesterBookService;
use App\Support\TransactionType;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderNotePageController extends Controller
{
    use ResolvesDateFilters;
    use ResolvesSemesterOptions;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SemesterBookService $semesterBookService
    ) {}

    public function index(Request $request): View
    {
        $now = now();
        $isAdminUser = (string) ($request->user()?->role ?? '') === 'admin';
        $search = trim((string) $request->string('search', ''));
        $semester = (string) $request->string('semester', '');
        $status = trim((string) $request->string('status', ''));
        $noteDate = trim((string) $request->string('note_date', ''));
        $selectedStatus = in_array($status, ['active', 'canceled'], true) ? $status : null;
        $selectedSemester = $this->normalizedSemesterInput($semester);
        $selectedNoteDate = $this->selectedDateFilter($noteDate);
        $selectedNoteDateRange = $this->selectedDateRange($selectedNoteDate);
        $todayRange = [$now->copy()->startOfDay(), $now->copy()->endOfDay()];

        $currentSemester = $this->currentSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);
        $semesterRange = $this->semesterDateRange($selectedSemester);

        $semesterOptionsBase = $this->cachedSemesterOptionsFromDateColumn(
            'order_notes.index.semester_options.base',
            OrderNote::class,
            'note_date'
        );
        $semesterOptions = $this->semesterOptionsForIndex($semesterOptionsBase, $isAdminUser);
        $selectedSemester = $this->selectedSemesterIfAvailable($selectedSemester, $semesterOptions);
        if ($selectedSemester === null) {
            $semesterRange = null;
        }

        $notes = OrderNote::query()
            ->onlyListColumns()
            ->withCustomerInfo()
            ->searchKeyword($search)
            ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                $query->betweenDates($semesterRange['start'], $semesterRange['end']);
            })
            ->when($selectedStatus === 'active', fn($query) => $query->active())
            ->when($selectedStatus === 'canceled', fn($query) => $query->canceled())
            ->when($selectedNoteDateRange !== null, function ($query) use ($selectedNoteDateRange): void {
                $query->betweenDates($selectedNoteDateRange[0], $selectedNoteDateRange[1]);
            })
            ->latest('note_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $todaySummary = Cache::remember(
            AppCache::lookupCacheKey('order_notes.index.today_summary', [
                'status' => $selectedStatus ?? 'all',
                'date' => $now->toDateString(),
            ]),
            $now->copy()->addSeconds(30),
            function () use ($todayRange, $selectedStatus) {
                return (object) [
                    'total_notes' => (int) OrderNote::query()
                        ->betweenDates($todayRange[0], $todayRange[1])
                        ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                            $query->where('is_canceled', $selectedStatus === 'canceled');
                        })
                        ->count(),
                    'total_qty' => (int) OrderNoteItem::query()
                        ->join('order_notes', 'order_note_items.order_note_id', '=', 'order_notes.id')
                        ->whereBetween('order_notes.note_date', $todayRange)
                        ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                            $query->where('order_notes.is_canceled', $selectedStatus === 'canceled');
                        })
                        ->sum('order_note_items.quantity'),
                ];
            }
        );
        $noteIds = collect($notes->items())
            ->map(fn(OrderNote $note): int => (int) $note->id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values()
            ->all();
        $noteProgressMap = $this->buildOrderNoteProgressMap($noteIds);

        return view('order_notes.index', [
            'notes' => $notes,
            'noteProgressMap' => $noteProgressMap,
            'search' => $search,
            'semesterOptions' => $semesterOptions,
            'selectedSemester' => $selectedSemester,
            'selectedStatus' => $selectedStatus,
            'selectedNoteDate' => $selectedNoteDate,
            'currentSemester' => $currentSemester,
            'previousSemester' => $previousSemester,
            'todaySummary' => $todaySummary,
        ]);
    }

    public function create(): View
    {
        $now = now();
        $oldCustomerId = (int) old('customer_id', 0);
        $customers = Cache::remember(
            AppCache::lookupCacheKey('forms.order_notes.customers', ['limit' => 100]),
            $now->copy()->addSeconds(60),
            fn() => Customer::query()
                ->onlyOrderFormColumns()
                ->orderBy('name')
                ->limit(100)
                ->get()
        );
        if ($oldCustomerId > 0 && ! $customers->contains('id', $oldCustomerId)) {
            $oldCustomer = Customer::query()
                ->onlyOrderFormColumns()
                ->whereKey($oldCustomerId)
                ->first();
            if ($oldCustomer !== null) {
                $customers->prepend($oldCustomer);
            }
        }
        $customers = $customers->unique('id')->values();

        $oldProductIds = collect(old('items', []))
            ->pluck('product_id')
            ->map(fn($id): int => (int) $id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values();
        $products = Cache::remember(
            AppCache::lookupCacheKey('forms.order_notes.products', ['limit' => 100, 'active_only' => 1]),
            $now->copy()->addSeconds(60),
            fn() => Product::query()
                ->onlyOrderFormColumns()
                ->active()
                ->orderBy('name')
                ->limit(100)
                ->get()
        );
        if ($oldProductIds->isNotEmpty()) {
            $oldProducts = Product::query()
                ->onlyOrderFormColumns()
                ->whereIn('id', $oldProductIds->all())
                ->get();
            $products = $oldProducts->concat($products)->unique('id')->values();
        }

        return view('order_notes.create', [
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $customerId = (int) $data['customer_id'];
        $customerName = trim((string) (Customer::query()->whereKey($customerId)->value('name') ?? ''));
        $normalizedCustomerName = mb_strtolower($customerName);
        $search = trim((string) ($data['search'] ?? ''));
        $limit = (int) ($data['per_page'] ?? 20);

        $notes = OrderNote::query()
            ->select(['id', 'note_number', 'note_date', 'customer_name', 'city'])
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
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('note_number', 'like', "%{$search}%");
            })
            ->orderByDesc('note_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($notes->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
        }

        $noteIds = $notes->pluck('id')->map(fn($id): int => (int) $id)->all();
        $noteItems = OrderNoteItem::query()
            ->whereIn('order_note_id', $noteIds)
            ->orderBy('order_note_id')
            ->orderBy('id')
            ->get([
                'id',
                'order_note_id',
                'product_id',
                'product_code',
                'product_name',
                'quantity',
                'notes',
            ]);

        $itemIds = $noteItems->pluck('id')->map(fn($id): int => (int) $id)->all();
        $productIds = $noteItems
            ->pluck('product_id')
            ->map(fn($id): int => (int) $id)
            ->filter(fn(int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $productMap = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'code', 'name', 'stock', 'price_agent', 'price_sales', 'price_general'])
            ->keyBy('id');

        $fulfilledByItem = [];
        if ($itemIds !== []) {
            $fulfilledByItem = DB::table('delivery_note_items as dni')
                ->join('delivery_notes as dn', 'dn.id', '=', 'dni.delivery_note_id')
                ->where('dn.is_canceled', false)
                ->whereIn('dni.order_note_item_id', $itemIds)
                ->selectRaw('dni.order_note_item_id, COALESCE(SUM(dni.quantity), 0) as fulfilled_qty')
                ->groupBy('dni.order_note_item_id')
                ->get()
                ->pluck('fulfilled_qty', 'order_note_item_id')
                ->map(fn($qty): int => (int) round((float) $qty))
                ->all();
        }

        $fallbackByNoteProduct = [];
        $fallbackRows = DB::table('delivery_note_items as dni')
            ->join('delivery_notes as dn', 'dn.id', '=', 'dni.delivery_note_id')
            ->where('dn.is_canceled', false)
            ->whereIn('dn.order_note_id', $noteIds)
            ->whereNull('dni.order_note_item_id')
            ->selectRaw('dn.order_note_id as note_id, dni.product_id, COALESCE(SUM(dni.quantity), 0) as fulfilled_qty')
            ->groupBy('dn.order_note_id', 'dni.product_id')
            ->get();
        foreach ($fallbackRows as $row) {
            $noteId = (int) ($row->note_id ?? 0);
            $productId = (int) ($row->product_id ?? 0);
            if ($noteId <= 0 || $productId <= 0) {
                continue;
            }
            $fallbackByNoteProduct[$noteId] = $fallbackByNoteProduct[$noteId] ?? [];
            $fallbackByNoteProduct[$noteId][$productId] = (int) round((float) ($row->fulfilled_qty ?? 0));
        }

        $itemsByNote = $noteItems
            ->groupBy('order_note_id')
            ->map(function ($rows) use ($fulfilledByItem, $fallbackByNoteProduct): array {
                $noteId = (int) (($rows->first()->order_note_id ?? 0));
                $fallbackMap = $fallbackByNoteProduct[$noteId] ?? [];
                $prepared = [];
                $orderedTotal = 0;
                $fulfilledTotal = 0;

                foreach ($rows as $item) {
                    $productId = (int) ($item->product_id ?? 0);
                    $orderedQty = max(0, (int) ($item->quantity ?? 0));
                    $fulfilledQty = max(0, (int) ($fulfilledByItem[(int) $item->id] ?? 0));

                    if ($productId > 0 && $orderedQty > $fulfilledQty && isset($fallbackMap[$productId])) {
                        $remainFromItem = $orderedQty - $fulfilledQty;
                        $alloc = min($remainFromItem, max(0, (int) $fallbackMap[$productId]));
                        if ($alloc > 0) {
                            $fulfilledQty += $alloc;
                            $fallbackMap[$productId] = max(0, (int) $fallbackMap[$productId] - $alloc);
                        }
                    }

                    $remainingQty = max(0, $orderedQty - $fulfilledQty);
                    $orderedTotal += $orderedQty;
                    $fulfilledTotal += $fulfilledQty;
                    $prepared[] = [
                        'id' => (int) $item->id,
                        'product_id' => $productId > 0 ? $productId : null,
                        'product_code' => (string) ($item->product_code ?? ''),
                        'product_name' => (string) ($item->product_name ?? ''),
                        'ordered_qty' => $orderedQty,
                        'fulfilled_qty' => $fulfilledQty,
                        'remaining_qty' => $remainingQty,
                        'notes' => (string) ($item->notes ?? ''),
                    ];
                }

                return [
                    'ordered_total' => $orderedTotal,
                    'fulfilled_total' => $fulfilledTotal,
                    'remaining_total' => max(0, $orderedTotal - $fulfilledTotal),
                    'items' => $prepared,
                ];
            });

        $result = $notes
            ->map(function (OrderNote $note) use ($itemsByNote, $productMap): array {
                $summary = $itemsByNote->get((int) $note->id, [
                    'ordered_total' => 0,
                    'fulfilled_total' => 0,
                    'remaining_total' => 0,
                    'items' => [],
                ]);

                $orderedTotal = max(0, (int) ($summary['ordered_total'] ?? 0));
                $fulfilledTotal = max(0, (int) ($summary['fulfilled_total'] ?? 0));
                $remainingTotal = max(0, (int) ($summary['remaining_total'] ?? 0));
                $progressPercent = $orderedTotal > 0
                    ? min(100, round(($fulfilledTotal / $orderedTotal) * 100, 2))
                    : 0;

                $items = collect((array) ($summary['items'] ?? []))
                    ->map(function (array $row) use ($productMap): array {
                        $productId = (int) ($row['product_id'] ?? 0);
                        $product = $productId > 0 ? $productMap->get($productId) : null;
                        if ($product !== null) {
                            $row['product_code'] = (string) ($product->code ?? $row['product_code'] ?? '');
                            $row['product_name'] = (string) ($product->name ?? $row['product_name'] ?? '');
                            $row['stock'] = (int) round((float) ($product->stock ?? 0));
                            $row['price_agent'] = (int) round((float) ($product->price_agent ?? 0));
                            $row['price_sales'] = (int) round((float) ($product->price_sales ?? 0));
                            $row['price_general'] = (int) round((float) ($product->price_general ?? 0));
                        } else {
                            $row['stock'] = 0;
                            $row['price_agent'] = 0;
                            $row['price_sales'] = 0;
                            $row['price_general'] = 0;
                        }

                        return $row;
                    })
                    ->values()
                    ->all();

                return [
                    'id' => (int) $note->id,
                    'note_number' => (string) $note->note_number,
                    'note_date' => $note->note_date?->format('d-m-Y'),
                    'customer_name' => (string) $note->customer_name,
                    'city' => (string) ($note->city ?? ''),
                    'ordered_total' => $orderedTotal,
                    'fulfilled_total' => $fulfilledTotal,
                    'remaining_total' => $remainingTotal,
                    'progress_percent' => $progressPercent,
                    'status' => $remainingTotal <= 0 ? 'finished' : 'open',
                    'items' => $items,
                ];
            })
            ->filter(fn(array $row): bool => (int) ($row['remaining_total'] ?? 0) > 0)
            ->values();

        return response()->json([
            'data' => $result,
            'meta' => ['total' => $result->count()],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'note_date' => ['required', 'date'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'transaction_type' => ['nullable', 'in:product,printing'],
            'customer_printing_subtype_id' => ['nullable', 'integer', 'exists:customer_printing_subtypes,id'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_code' => ['nullable', 'string', 'max:60'],
            'items.*.product_name' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $customer = Customer::query()
            ->onlyOrderFormColumns()
            ->findOrFail((int) $data['customer_id']);

        $selectedTransactionType = TransactionType::normalize((string) ($data['transaction_type'] ?? TransactionType::PRODUCT));
        $printingSubtype = CustomerPrintingSubtypeResolver::resolve(
            customerId: (int) $data['customer_id'],
            transactionType: $selectedTransactionType,
            subtypeId: isset($data['customer_printing_subtype_id']) ? (int) $data['customer_printing_subtype_id'] : null,
        );

        $note = DB::transaction(function () use ($data, $customer, $selectedTransactionType, $printingSubtype): OrderNote {
            $noteDate = $data['note_date'];
            $noteNumber = $this->generateNoteNumber($noteDate);

            $note = OrderNote::create([
                'note_number' => $noteNumber,
                'note_date' => $noteDate,
                'customer_id' => (int) $data['customer_id'],
                'transaction_type' => $selectedTransactionType,
                'customer_printing_subtype_id' => $printingSubtype['id'],
                'printing_subtype_name' => $printingSubtype['name'],
                'customer_name' => (string) $customer->name,
                'customer_phone' => $data['customer_phone'] ?? (string) ($customer->phone ?? ''),
                'address' => $data['address'] ?? (string) ($customer->address ?? ''),
                'city' => $data['city'] ?? (string) ($customer->city ?? ''),
                'created_by_name' => auth()->user()?->name ?? __('txn.system_user'),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $productId = (int) $row['product_id'];
                $productCode = $row['product_code'] ?? null;
                $productName = $row['product_name'];

                $product = Product::query()->find($productId);
                if ($product) {
                    $productCode = $product->code;
                    $productName = $product->name;
                }

                OrderNoteItem::create([
                    'order_note_id' => $note->id,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                    'product_name' => $productName,
                    'quantity' => $row['quantity'],
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            return $note;
        });

        $this->auditLogService->log(
            'order.note.create',
            $note,
            __('txn.audit_order_created', ['number' => $note->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $note->note_date]);

        return redirect()
            ->route('order-notes.show', $note)
            ->with('success', __('txn.order_note_created_success', ['number' => $note->note_number]));
    }

    public function show(OrderNote $orderNote): View
    {
        $now = now();
        $orderNote->load(['customer:id,name,city,phone,address', 'items']);
        $progressMap = $this->buildOrderNoteProgressMap([(int) $orderNote->id]);
        $noteProgress = $progressMap[(int) $orderNote->id] ?? [
            'ordered_total' => 0,
            'fulfilled_total' => 0,
            'remaining_total' => 0,
            'progress_percent' => 0.0,
            'status' => 'open',
        ];
        $fulfillmentDetails = $this->buildOrderNoteFulfillmentDetails($orderNote);
        $itemProductIds = $orderNote->items
            ->pluck('product_id')
            ->map(fn($id): int => (int) $id)
            ->filter(fn(int $id): bool => $id > 0)
            ->values();
        $products = Cache::remember(
            AppCache::lookupCacheKey('forms.order_notes.products', ['limit' => 100, 'active_only' => 1]),
            $now->copy()->addSeconds(60),
            fn() => Product::query()
                ->onlyOrderFormColumns()
                ->active()
                ->orderBy('name')
                ->limit(100)
                ->get()
        );
        if ($itemProductIds->isNotEmpty()) {
            $itemProducts = Product::query()
                ->onlyOrderFormColumns()
                ->whereIn('id', $itemProductIds->all())
                ->get();
            $products = $itemProducts->concat($products)->unique('id')->values();
        }

        return view('order_notes.show', [
            'note' => $orderNote,
            'products' => $products,
            'noteProgress' => $noteProgress,
            'fulfillmentDetails' => $fulfillmentDetails,
        ]);
    }

    public function adminUpdate(Request $request, OrderNote $orderNote): RedirectResponse
    {
        $data = $request->validate([
            'note_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'transaction_type' => ['nullable', 'in:product,printing'],
            'customer_printing_subtype_id' => ['nullable', 'integer', 'exists:customer_printing_subtypes,id'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($orderNote, $data): void {
            $note = OrderNote::query()
                ->with('items')
                ->whereKey($orderNote->id)
                ->lockForUpdate()
                ->firstOrFail();
            $selectedTransactionType = TransactionType::normalize((string) ($data['transaction_type'] ?? (string) $note->transaction_type));
            $printingSubtype = CustomerPrintingSubtypeResolver::resolve(
                customerId: (int) ($note->customer_id ?? 0),
                transactionType: $selectedTransactionType,
                subtypeId: isset($data['customer_printing_subtype_id']) ? (int) $data['customer_printing_subtype_id'] : null,
            );

            $note->update([
                'note_date' => $data['note_date'],
                'transaction_type' => $selectedTransactionType,
                'customer_printing_subtype_id' => $printingSubtype['id'],
                'printing_subtype_name' => $printingSubtype['name'],
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $note->items()->delete();

            foreach ($data['items'] as $row) {
                $productId = (int) $row['product_id'];
                $productCode = null;
                $productName = $row['product_name'];
                $product = Product::query()->find($productId);
                if ($product) {
                    $productCode = $product->code;
                    $productName = $product->name;
                }

                OrderNoteItem::create([
                    'order_note_id' => $note->id,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                    'product_name' => $productName,
                    'quantity' => $row['quantity'],
                    'notes' => $row['notes'] ?? null,
                ]);
            }
        });

        $orderNote->refresh();
        $this->auditLogService->log(
            'order.note.admin_update',
            $orderNote,
            __('txn.audit_order_admin_updated', ['number' => $orderNote->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $orderNote->note_date]);

        return redirect()
            ->route('order-notes.show', $orderNote)
            ->with('success', __('txn.admin_update_saved'));
    }

    public function cancel(Request $request, OrderNote $orderNote): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ]);

        $orderNote->update([
            'is_canceled' => true,
            'canceled_at' => now(),
            'canceled_by_user_id' => auth()->id(),
            'cancel_reason' => $data['cancel_reason'],
        ]);

        $this->auditLogService->log(
            'order.note.cancel',
            $orderNote,
            __('txn.audit_order_canceled', ['number' => $orderNote->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $orderNote->note_date]);

        return redirect()
            ->route('order-notes.show', $orderNote)
            ->with('success', __('txn.transaction_canceled_success'));
    }

    public function print(OrderNote $orderNote): View
    {
        $orderNote->load(['customer:id,name,city,phone,address', 'items']);

        return view('order_notes.print', [
            'note' => $orderNote,
            'fulfillmentDetails' => $this->buildOrderNoteFulfillmentDetails($orderNote),
        ]);
    }

    public function exportPdf(OrderNote $orderNote)
    {
        $orderNote->load(['customer:id,name,city,phone,address', 'items']);

        $filename = $orderNote->note_number . '.pdf';
        $pdf = Pdf::loadView('order_notes.print', [
            'note' => $orderNote,
            'fulfillmentDetails' => $this->buildOrderNoteFulfillmentDetails($orderNote),
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function exportExcel(OrderNote $orderNote): StreamedResponse
    {
        $orderNote->load(['customer:id,name,city,phone,address', 'items']);
        $filename = $orderNote->note_number . '.xlsx';

        return response()->streamDownload(function () use ($orderNote): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Surat Pesanan');
            $customerName = (string) ($orderNote->customer?->name ?: preg_replace('/\s*\([^)]+\)\s*$/', '', (string) $orderNote->customer_name));
            $address = \App\Support\PrintTextFormatter::wrapWords((string) ($orderNote->address ?: $orderNote->customer?->address ?: ''), 5);
            $notes = \App\Support\PrintTextFormatter::wrapWords(
                trim((string) ($orderNote->notes ?: \App\Models\AppSetting::getValue('company_invoice_notes', ''))),
                4
            );
            $rows = [];
            $rows[] = [__('txn.order_notes_title') . ' ' . __('txn.note_number'), $orderNote->note_number];
            $rows[] = [__('txn.date'), $orderNote->note_date?->format('d-m-Y')];
            $rows[] = [__('txn.name'), $customerName !== '' ? $customerName : '-'];
            $rows[] = [__('txn.phone'), $orderNote->customer_phone];
            $rows[] = [__('txn.city'), $orderNote->city];
            $rows[] = [__('txn.address'), $address !== '' ? $address : '-'];
            $rows[] = [];
            $rows[] = [__('txn.items')];
            $rows[] = [__('txn.name'), __('txn.qty'), __('txn.notes')];

            foreach ($orderNote->items as $item) {
                $rows[] = [
                    $item->product_name,
                    $item->quantity,
                    $item->notes,
                ];
            }

            $rows[] = [];
            $rows[] = [__('txn.notes'), $notes !== '' ? $notes : '-'];
            $rows[] = [__('txn.summary_total_qty'), (int) round((float) $orderNote->items->sum('quantity'), 0)];

            $sheet->fromArray($rows, null, 'A1');
            $itemsCount = $orderNote->items->count();
            $itemsHeaderRow = 8;
            ExcelExportStyler::styleTable($sheet, $itemsHeaderRow, 3, $itemsCount, true);
            ExcelExportStyler::formatNumberColumns($sheet, $itemsHeaderRow + 1, $itemsHeaderRow + $itemsCount, [2], '#,##0');
            $sheet->getStyle('B1:B'.(13 + $itemsCount))->getAlignment()->setWrapText(true);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  list<int>  $noteIds
     * @return array<int, array{ordered_total:int, fulfilled_total:int, remaining_total:int, progress_percent:float, status:string}>
     */
    private function buildOrderNoteProgressMap(array $noteIds): array
    {
        $cleanIds = array_values(array_filter(array_map(static fn($id): int => (int) $id, $noteIds), static fn(int $id): bool => $id > 0));
        if ($cleanIds === []) {
            return [];
        }

        $orderedByNote = OrderNoteItem::query()
            ->whereIn('order_note_id', $cleanIds)
            ->selectRaw('order_note_id, COALESCE(SUM(quantity), 0) as ordered_total')
            ->groupBy('order_note_id')
            ->pluck('ordered_total', 'order_note_id');

        $fulfilledByNote = DB::table('delivery_note_items as dni')
            ->join('delivery_notes as dn', 'dn.id', '=', 'dni.delivery_note_id')
            ->where('dn.is_canceled', false)
            ->whereIn('dn.order_note_id', $cleanIds)
            ->selectRaw('dn.order_note_id, COALESCE(SUM(dni.quantity), 0) as fulfilled_total')
            ->groupBy('dn.order_note_id')
            ->pluck('fulfilled_total', 'dn.order_note_id');

        $result = [];
        foreach ($cleanIds as $noteId) {
            $orderedTotal = max(0, (int) round((float) ($orderedByNote[$noteId] ?? 0)));
            $fulfilledTotal = max(0, (int) round((float) ($fulfilledByNote[$noteId] ?? 0)));
            $remainingTotal = max(0, $orderedTotal - $fulfilledTotal);
            $progressPercent = $orderedTotal > 0 ? min(100, round(($fulfilledTotal / $orderedTotal) * 100, 2)) : 0.0;
            $result[$noteId] = [
                'ordered_total' => $orderedTotal,
                'fulfilled_total' => $fulfilledTotal,
                'remaining_total' => $remainingTotal,
                'progress_percent' => $progressPercent,
                'status' => $remainingTotal > 0 ? 'open' : 'finished',
            ];
        }

        return $result;
    }

    /**
     * @return array{
     *     items:list<array{
     *         id:int,
     *         product_id:int|null,
     *         product_code:string,
     *         product_name:string,
     *         ordered_qty:int,
     *         fulfilled_qty:int,
     *         remaining_qty:int,
     *         notes:string,
     *         status:string,
     *         deliveries:list<array{invoice_id:int,invoice_number:string,invoice_date:string,quantity:int}>
     *     }>,
     *     invoice_summaries:list<array{invoice_id:int,invoice_number:string,invoice_date:string,total_quantity:int,item_count:int}>,
     *     has_deliveries:bool
     * }
     */
    private function buildOrderNoteFulfillmentDetails(OrderNote $orderNote): array
    {
        $items = [];
        $itemIdsByProduct = [];
        $invoiceSummaries = [];
        $invoiceSummaryItemKeys = [];

        foreach ($orderNote->items as $item) {
            $itemId = (int) $item->id;
            $productId = (int) ($item->product_id ?? 0);
            $items[$itemId] = [
                'id' => $itemId,
                'product_id' => $productId > 0 ? $productId : null,
                'product_code' => (string) ($item->product_code ?? ''),
                'product_name' => (string) ($item->product_name ?? ''),
                'ordered_qty' => max(0, (int) ($item->quantity ?? 0)),
                'fulfilled_qty' => 0,
                'remaining_qty' => max(0, (int) ($item->quantity ?? 0)),
                'notes' => (string) ($item->notes ?? ''),
                'status' => 'open',
                'deliveries' => [],
            ];

            if ($productId > 0) {
                $itemIdsByProduct[$productId] = $itemIdsByProduct[$productId] ?? [];
                $itemIdsByProduct[$productId][] = $itemId;
            }
        }

        $invoiceRows = DB::table('delivery_note_items as dni')
            ->join('delivery_notes as dn', 'dn.id', '=', 'dni.delivery_note_id')
            ->where('dn.order_note_id', (int) $orderNote->id)
            ->where('dn.is_canceled', false)
            ->orderBy('dn.note_date')
            ->orderBy('dn.id')
            ->orderBy('dni.id')
            ->get([
                'dni.id',
                'dni.order_note_item_id',
                'dni.product_id',
                'dni.quantity',
                'dn.id as invoice_id',
                'dn.note_number as invoice_number',
                'dn.note_date as invoice_date',
            ]);

        foreach ($invoiceRows as $row) {
            $invoiceId = (int) ($row->invoice_id ?? 0);
            $invoiceNumber = (string) ($row->invoice_number ?? '');
            $invoiceDate = $row->invoice_date !== null
                ? Carbon::parse((string) $row->invoice_date)->format('d-m-Y')
                : '-';
            $remainingAllocation = max(0, (int) round((float) ($row->quantity ?? 0)));

            if ($remainingAllocation <= 0 || $invoiceId <= 0 || $invoiceNumber === '') {
                continue;
            }

            if (! isset($invoiceSummaries[$invoiceId])) {
                $invoiceSummaries[$invoiceId] = [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $invoiceDate,
                    'total_quantity' => 0,
                    'item_count' => 0,
                ];
                $invoiceSummaryItemKeys[$invoiceId] = [];
            }

            $directItemId = (int) ($row->order_note_item_id ?? 0);
            if ($directItemId > 0 && isset($items[$directItemId])) {
                $allocated = min($remainingAllocation, max(0, (int) $items[$directItemId]['remaining_qty']));
                if ($allocated > 0) {
                    $this->applyOrderNoteDeliveryAllocation($items, $directItemId, $invoiceId, $invoiceNumber, $invoiceDate, $allocated);
                    $invoiceSummaries[$invoiceId]['total_quantity'] += $allocated;
                    $invoiceSummaryItemKeys[$invoiceId][$directItemId] = true;
                    $remainingAllocation -= $allocated;
                }
            }

            if ($remainingAllocation <= 0) {
                continue;
            }

            $productId = (int) ($row->product_id ?? 0);
            if ($productId <= 0 || ! isset($itemIdsByProduct[$productId])) {
                continue;
            }

            foreach ($itemIdsByProduct[$productId] as $fallbackItemId) {
                if ($remainingAllocation <= 0) {
                    break;
                }

                $itemRemaining = max(0, (int) ($items[$fallbackItemId]['remaining_qty'] ?? 0));
                if ($itemRemaining <= 0) {
                    continue;
                }

                $allocated = min($remainingAllocation, $itemRemaining);
                if ($allocated <= 0) {
                    continue;
                }

                $this->applyOrderNoteDeliveryAllocation($items, $fallbackItemId, $invoiceId, $invoiceNumber, $invoiceDate, $allocated);
                $invoiceSummaries[$invoiceId]['total_quantity'] += $allocated;
                $invoiceSummaryItemKeys[$invoiceId][$fallbackItemId] = true;
                $remainingAllocation -= $allocated;
            }
        }

        $hasDeliveries = false;
        foreach ($items as &$item) {
            $item['remaining_qty'] = max(0, (int) $item['ordered_qty'] - (int) $item['fulfilled_qty']);
            $item['status'] = $item['fulfilled_qty'] <= 0
                ? 'not_delivered'
                : ($item['remaining_qty'] > 0 ? 'partial' : 'finished');
            $item['deliveries'] = array_values($item['deliveries']);
            if ($item['deliveries'] !== []) {
                $hasDeliveries = true;
            }
        }
        unset($item);

        foreach ($invoiceSummaries as $invoiceId => &$invoiceSummary) {
            $invoiceSummary['item_count'] = count($invoiceSummaryItemKeys[$invoiceId] ?? []);
        }
        unset($invoiceSummary);

        return [
            'items' => array_values($items),
            'invoice_summaries' => array_values($invoiceSummaries),
            'has_deliveries' => $hasDeliveries,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function applyOrderNoteDeliveryAllocation(
        array &$items,
        int $itemId,
        int $invoiceId,
        string $invoiceNumber,
        string $invoiceDate,
        int $quantity
    ): void {
        if (! isset($items[$itemId]) || $quantity <= 0) {
            return;
        }

        $items[$itemId]['fulfilled_qty'] = (int) $items[$itemId]['fulfilled_qty'] + $quantity;
        $items[$itemId]['remaining_qty'] = max(
            0,
            (int) $items[$itemId]['ordered_qty'] - (int) $items[$itemId]['fulfilled_qty']
        );

        $deliveryKey = (string) $invoiceId;
        if (! isset($items[$itemId]['deliveries'][$deliveryKey])) {
            $items[$itemId]['deliveries'][$deliveryKey] = [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'quantity' => 0,
            ];
        }

        $items[$itemId]['deliveries'][$deliveryKey]['quantity'] =
            (int) $items[$itemId]['deliveries'][$deliveryKey]['quantity'] + $quantity;
    }

    private function generateNoteNumber(string $date): string
    {
        $prefix = 'PO-' . date('dmY', strtotime($date));
        $count = OrderNote::query()
            ->whereDate('note_date', $date)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    private function currentSemesterPeriod(): string
    {
        return $this->semesterBookService->currentSemester();
    }

    private function previousSemesterPeriod(string $period): string
    {
        return $this->semesterBookService->previousSemester($period);
    }

    private function semesterDateRange(?string $period): ?array
    {
        return $this->semesterBookService->semesterDateRange($period);
    }

    private function semesterPeriodFromDate(Carbon|string|null $date): string
    {
        $rawDate = $date instanceof Carbon ? $date->format('Y-m-d') : (string) $date;
        return $this->semesterBookService->semesterFromDate($rawDate) ?? $this->currentSemesterPeriod();
    }
}
