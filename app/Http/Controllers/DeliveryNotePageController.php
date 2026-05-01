<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDateFilters;
use App\Http\Controllers\Concerns\ResolvesSemesterOptions;
use App\Models\Customer;
use App\Models\CustomerShipLocation;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\OrderNote;
use App\Models\OrderNoteItem;
use App\Models\Product;
use App\Models\StockMutation;
use App\Services\AuditLogService;
use App\Support\AppCache;
use App\Support\CustomerPrintingSubtypeResolver;
use App\Support\ExcelExportStyler;
use App\Support\SemesterBookService;
use App\Support\TransactionType;
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
use SanderMuller\FluentValidation\FluentRule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryNotePageController extends Controller
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
            'delivery_notes.index.semester_options.base',
            DeliveryNote::class,
            'note_date'
        );
        $semesterOptions = $this->semesterOptionsForIndex($semesterOptionsBase, $isAdminUser);
        $selectedSemester = $this->selectedSemesterIfAvailable($selectedSemester, $semesterOptions);
        if ($selectedSemester === null) {
            $semesterRange = null;
        }

        $notes = DeliveryNote::query()
            ->onlyListColumns()
            ->withCustomerInfo()
            ->searchKeyword($search)
            ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                $query->betweenDates($semesterRange['start'], $semesterRange['end']);
            })
            ->when($selectedStatus === 'active', fn ($query) => $query->active())
            ->when($selectedStatus === 'canceled', fn ($query) => $query->canceled())
            ->when($selectedNoteDateRange !== null, function ($query) use ($selectedNoteDateRange): void {
                $query->betweenDates($selectedNoteDateRange[0], $selectedNoteDateRange[1]);
            })
            ->latest('note_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $todaySummary = Cache::remember(
            AppCache::lookupCacheKey('delivery_notes.index.today_summary', [
                'status' => $selectedStatus ?? 'all',
                'date' => $now->toDateString(),
            ]),
            $now->copy()->addSeconds(30),
            function () use ($todayRange, $selectedStatus) {
                return (object) [
                    'total_notes' => (int) DeliveryNote::query()
                        ->betweenDates($todayRange[0], $todayRange[1])
                        ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                            $query->where('is_canceled', $selectedStatus === 'canceled');
                        })
                        ->count(),
                    'total_qty' => (int) DeliveryNoteItem::query()
                        ->join('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id')
                        ->whereBetween('delivery_notes.note_date', $todayRange)
                        ->when($selectedStatus !== null, function ($query) use ($selectedStatus): void {
                            $query->where('delivery_notes.is_canceled', $selectedStatus === 'canceled');
                        })
                        ->sum('delivery_note_items.quantity'),
                ];
            }
        );

        return view('delivery_notes.index', [
            'notes' => $notes,
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

    public function create(Request $request): View
    {
        $now = now();
        $selectedOrderNoteId = (int) old('order_note_id', (int) $request->integer('order_note_id'));
        $selectedOrderNote = null;
        $bootItems = collect(old('items', []));
        if ($selectedOrderNoteId > 0) {
            $selectedOrderNote = OrderNote::query()
                ->with(['items.product:id,code,name,unit,stock'])
                ->whereKey($selectedOrderNoteId)
                ->where('is_canceled', false)
                ->first();
            if ($selectedOrderNote !== null && $bootItems->isEmpty()) {
                $orderItemIds = $selectedOrderNote->items->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $deliveredByItem = $orderItemIds === []
                    ? collect()
                    : DB::table('delivery_note_items as dni')
                        ->join('delivery_notes as dn', 'dn.id', '=', 'dni.delivery_note_id')
                        ->where('dn.is_canceled', false)
                        ->whereIn('dni.order_note_item_id', $orderItemIds)
                        ->selectRaw('dni.order_note_item_id, COALESCE(SUM(dni.quantity), 0) as delivered_qty')
                        ->groupBy('dni.order_note_item_id')
                        ->pluck('delivered_qty', 'dni.order_note_item_id');
                $bootItems = $selectedOrderNote->items
                    ->map(function (OrderNoteItem $item) use ($deliveredByItem): ?array {
                        $remaining = max(0, (int) $item->quantity - (int) round((float) ($deliveredByItem[(int) $item->id] ?? 0)));
                        if ($remaining <= 0) {
                            return null;
                        }

                        return [
                            'order_note_item_id' => (int) $item->id,
                            'product_id' => (int) ($item->product_id ?? 0),
                            'product_code' => (string) ($item->product_code ?? $item->product?->code ?? ''),
                            'product_name' => (string) ($item->product_name ?? $item->product?->name ?? ''),
                            'unit' => (string) ($item->product?->unit ?? ''),
                            'quantity' => $remaining,
                            'notes' => (string) ($item->notes ?? ''),
                        ];
                    })
                    ->filter();
            }
        }
        $oldCustomerId = (int) old('customer_id', (int) ($selectedOrderNote?->customer_id ?? 0));
        $customers = Cache::remember(
            AppCache::lookupCacheKey('forms.delivery_notes.customers', ['limit' => 20]),
            $now->copy()->addSeconds(60),
            fn () => Customer::query()
                ->onlyDeliveryFormColumns()
                ->with('level:id,code,name')
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($oldCustomerId > 0 && ! $customers->contains('id', $oldCustomerId)) {
            $oldCustomer = Customer::query()
                ->onlyDeliveryFormColumns()
                ->with('level:id,code,name')
                ->whereKey($oldCustomerId)
                ->first();
            if ($oldCustomer !== null) {
                $customers->prepend($oldCustomer);
            }
        }
        $customers = $customers->unique('id')->values();

        $oldProductIds = collect(old('items', []))
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $products = Cache::remember(
            AppCache::lookupCacheKey('forms.delivery_notes.products', ['limit' => 20, 'active_only' => 1]),
            $now->copy()->addSeconds(60),
            fn () => Product::query()
                ->onlyDeliveryFormColumns()
                ->active()
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($oldProductIds->isNotEmpty()) {
            $oldProducts = Product::query()
                ->onlyDeliveryFormColumns()
                ->whereIn('id', $oldProductIds->all())
                ->get();
            $products = $oldProducts->concat($products)->unique('id')->values();
        }

        $shipLocations = collect();
        if ($oldCustomerId > 0) {
            $shipLocations = CustomerShipLocation::query()
                ->select(['id', 'customer_id', 'school_name', 'recipient_name', 'recipient_phone', 'city', 'address'])
                ->where('customer_id', $oldCustomerId)
                ->where('is_active', true)
                ->orderBy('school_name')
                ->limit(20)
                ->get();
        }

        return view('delivery_notes.create', [
            'customers' => $customers,
            'products' => $products,
            'shipLocations' => $shipLocations,
            'selectedOrderNote' => $selectedOrderNote,
            'bootItems' => $bootItems->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'note_date' => FluentRule::date()->required(),
            'customer_id' => FluentRule::integer()->required()->exists('customers', 'id'),
            'order_note_id' => FluentRule::integer()->nullable()->exists('order_notes', 'id'),
            'customer_ship_location_id' => FluentRule::integer()->nullable()->exists('customer_ship_locations', 'id'),
            'transaction_type' => FluentRule::field()->nullable()->rule('in:product,printing'),
            'customer_printing_subtype_id' => FluentRule::integer()->nullable()->exists('customer_printing_subtypes', 'id'),
            'recipient_name' => FluentRule::string()->nullable()->max(150),
            'recipient_phone' => FluentRule::string()->nullable()->max(30),
            'city' => FluentRule::string()->nullable()->max(100),
            'address' => FluentRule::string()->nullable(),
            'notes' => FluentRule::string()->nullable(),
            'items' => FluentRule::array()->required()->min(1),
            'items.*.product_id' => FluentRule::integer()->required()->exists('products', 'id'),
            'items.*.order_note_item_id' => FluentRule::integer()->nullable()->exists('order_note_items', 'id'),
            'items.*.product_code' => FluentRule::string()->nullable()->max(60),
            'items.*.product_name' => FluentRule::string()->required()->max(200),
            'items.*.unit' => FluentRule::string()->nullable()->max(30),
            'items.*.quantity' => FluentRule::integer()->required()->min(1),
            'items.*.notes' => FluentRule::string()->nullable(),
        ]);

        $selectedTransactionType = TransactionType::normalize((string) ($data['transaction_type'] ?? TransactionType::PRODUCT));
        $printingSubtype = CustomerPrintingSubtypeResolver::resolve(
            customerId: (int) $data['customer_id'],
            transactionType: $selectedTransactionType,
            subtypeId: isset($data['customer_printing_subtype_id']) ? (int) $data['customer_printing_subtype_id'] : null,
        );

        $note = DB::transaction(function () use ($data, $selectedTransactionType, $printingSubtype): DeliveryNote {
            $noteDate = $data['note_date'];
            $noteNumber = $this->generateNoteNumber($noteDate);
            $customerId = (int) $data['customer_id'];
            $selectedOrderNoteId = (int) ($data['order_note_id'] ?? 0);
            $selectedOrderNote = null;
            $orderNoteItemsById = collect();
            if ($selectedOrderNoteId > 0) {
                $selectedOrderNote = OrderNote::query()
                    ->with('items:id,order_note_id,product_id,quantity')
                    ->whereKey($selectedOrderNoteId)
                    ->where('is_canceled', false)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ((int) ($selectedOrderNote->customer_id ?? 0) !== $customerId) {
                    throw ValidationException::withMessages([
                        'order_note_id' => __('txn.order_note_customer_mismatch'),
                    ]);
                }
                $orderNoteItemsById = $selectedOrderNote->items->keyBy('id');
            }
            $deliveredByOrderItem = $selectedOrderNote === null
                ? collect()
                : DB::table('delivery_note_items as dni')
                    ->join('delivery_notes as dn', 'dn.id', '=', 'dni.delivery_note_id')
                    ->where('dn.is_canceled', false)
                    ->where('dn.order_note_id', (int) $selectedOrderNote->id)
                    ->whereNotNull('dni.order_note_item_id')
                    ->selectRaw('dni.order_note_item_id, COALESCE(SUM(dni.quantity), 0) as delivered_qty')
                    ->groupBy('dni.order_note_item_id')
                    ->pluck('delivered_qty', 'dni.order_note_item_id');
            $shipLocationId = (int) ($data['customer_ship_location_id'] ?? 0);
            $shipLocation = null;
            if ($shipLocationId > 0) {
                $shipLocationQuery = CustomerShipLocation::query()->where('is_active', true);
                $shipLocationQuery->where('customer_id', $customerId);
                $shipLocation = $shipLocationQuery->find($shipLocationId);
                if ($shipLocation === null) {
                    throw ValidationException::withMessages([
                        'customer_ship_location_id' => __('school_bulk.invalid_ship_location_customer'),
                    ]);
                }
            }

            $customer = Customer::query()
                ->onlyOrderFormColumns()
                ->with('level:id,code,name')
                ->findOrFail($customerId);

            $recipientName = trim((string) ($data['recipient_name'] ?? ''));
            $recipientPhone = trim((string) ($data['recipient_phone'] ?? ''));
            $city = trim((string) ($data['city'] ?? ''));
            $address = trim((string) ($data['address'] ?? ''));
            if ($recipientName === '') {
                $recipientName = (string) $customer->name;
            }
            if ($recipientPhone === '') {
                $recipientPhone = (string) ($customer->phone ?? '');
            }
            if ($city === '') {
                $city = (string) ($customer->city ?? '');
            }
            if ($address === '') {
                $address = (string) ($customer->address ?? '');
            }
            if ($recipientName === '' && $shipLocation !== null) {
                $recipientName = (string) ($shipLocation->school_name ?: $shipLocation->recipient_name);
            }
            if ($recipientPhone === '' && $shipLocation !== null) {
                $recipientPhone = (string) ($shipLocation->recipient_phone ?? '');
            }
            if ($city === '' && $shipLocation !== null) {
                $city = (string) ($shipLocation->city ?? '');
            }
            if ($address === '' && $shipLocation !== null) {
                $address = (string) ($shipLocation->address ?? '');
            }

            $note = DeliveryNote::create([
                'note_number' => $noteNumber,
                'note_date' => $noteDate,
                'customer_id' => $customerId,
                'customer_ship_location_id' => $shipLocation?->id,
                'order_note_id' => $selectedOrderNote?->id,
                'transaction_type' => $selectedTransactionType,
                'customer_printing_subtype_id' => $printingSubtype['id'],
                'printing_subtype_name' => $printingSubtype['name'],
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone !== '' ? $recipientPhone : null,
                'city' => $city !== '' ? $city : null,
                'address' => $address !== '' ? $address : null,
                'notes' => $data['notes'] ?? null,
                'created_by_name' => auth()->user()?->name ?? __('txn.system_user'),
            ]);

            $stockUsageByProduct = [];
            foreach ($data['items'] as $row) {
                $productId = (int) $row['product_id'];
                $productCode = $row['product_code'] ?? null;
                $productName = $row['product_name'];
                $unit = $row['unit'] ?? null;
                $quantity = (int) ($row['quantity'] ?? 0);
                $orderNoteItemId = max(0, (int) ($row['order_note_item_id'] ?? 0));
                if ($selectedOrderNote !== null && $orderNoteItemId > 0) {
                    $linkedItem = $orderNoteItemsById->get($orderNoteItemId);
                    if (! $linkedItem || (int) ($linkedItem->order_note_id ?? 0) !== (int) $selectedOrderNote->id) {
                        throw ValidationException::withMessages([
                            'items' => __('txn.order_note_item_invalid'),
                        ]);
                    }
                    $remaining = max(0, (int) $linkedItem->quantity - (int) round((float) ($deliveredByOrderItem[$orderNoteItemId] ?? 0)));
                    if ($quantity > $remaining) {
                        throw ValidationException::withMessages([
                            'items' => __('txn.order_note_delivery_qty_exceeds_remaining'),
                        ]);
                    }
                }

                $product = $this->resolveProductFromInput($productId, $productName);
                if ($product) {
                    $product = Product::query()
                        ->whereKey((int) $product->id)
                        ->lockForUpdate()
                        ->first();
                    if ($product && (int) $product->stock < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => __('txn.insufficient_stock_for', ['product' => $product->name]),
                        ]);
                    }
                    if ($product) {
                        $productId = (int) $product->id;
                        $productCode = $productCode ?: $product->code;
                        $productName = trim((string) ($product->name ?: $productName));
                        $unit = $unit ?: $product->unit;
                        $stockUsageByProduct[$product->id] = ($stockUsageByProduct[$product->id] ?? 0) + $quantity;
                    }
                }

                DeliveryNoteItem::create([
                    'delivery_note_id' => $note->id,
                    'order_note_item_id' => $orderNoteItemId > 0 ? $orderNoteItemId : null,
                    'product_id' => $productId > 0 ? $productId : null,
                    'product_code' => $productCode,
                    'product_name' => $productName,
                    'unit' => $unit,
                    'quantity' => $quantity,
                    'unit_price' => null,
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            foreach ($stockUsageByProduct as $productId => $quantity) {
                $product = Product::query()
                    ->whereKey((int) $productId)
                    ->lockForUpdate()
                    ->first();
                if (! $product || $quantity <= 0) {
                    continue;
                }
                if ((int) $product->stock < (int) $quantity) {
                    throw ValidationException::withMessages([
                        'items' => __('txn.insufficient_stock_for', ['product' => $product->name]),
                    ]);
                }

                $product->decrement('stock', (int) $quantity);
                StockMutation::query()->create([
                    'product_id' => (int) $product->id,
                    'reference_type' => DeliveryNote::class,
                    'reference_id' => (int) $note->id,
                    'mutation_type' => 'out',
                    'quantity' => (int) $quantity,
                    'notes' => 'Delivery note '.$note->note_number,
                    'created_by_user_id' => auth()->id(),
                ]);
            }

            return $note;
        });

        $this->auditLogService->log(
            'delivery.note.create',
            $note,
            __('txn.audit_delivery_created', ['number' => $note->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $note->note_date]);

        return redirect()
            ->route('delivery-notes.show', $note)
            ->with('success', __('txn.delivery_note_created_success', ['number' => $note->note_number]));
    }

    public function show(DeliveryNote $deliveryNote): View
    {
        $now = now();
        $deliveryNote->load([
            'customer:id,name,city,phone,address,customer_level_id',
            'customer.level:id,code,name',
            'shipLocation:id,school_name,recipient_name,recipient_phone,city,address',
            'items',
        ]);
        $itemProductIds = $deliveryNote->items
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $products = Cache::remember(
            AppCache::lookupCacheKey('forms.delivery_notes.products', ['limit' => 20, 'active_only' => 1]),
            $now->copy()->addSeconds(60),
            fn () => Product::query()
                ->onlyDeliveryFormColumns()
                ->active()
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($itemProductIds->isNotEmpty()) {
            $itemProducts = Product::query()
                ->onlyDeliveryFormColumns()
                ->whereIn('id', $itemProductIds->all())
                ->get();
            $products = $itemProducts->concat($products)->unique('id')->values();
        }

        return view('delivery_notes.show', [
            'note' => $deliveryNote,
            'products' => $products,
        ]);
    }

    public function adminUpdate(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        $data = $request->validate([
            'note_date' => FluentRule::date()->required(),
            'recipient_name' => FluentRule::string()->required()->max(150),
            'recipient_phone' => FluentRule::string()->nullable()->max(30),
            'transaction_type' => FluentRule::field()->nullable()->rule('in:product,printing'),
            'customer_printing_subtype_id' => FluentRule::integer()->nullable()->exists('customer_printing_subtypes', 'id'),
            'city' => FluentRule::string()->nullable()->max(100),
            'address' => FluentRule::string()->nullable(),
            'notes' => FluentRule::string()->nullable(),
            'items' => FluentRule::array()->required()->min(1),
            'items.*.product_id' => FluentRule::integer()->required()->exists('products', 'id'),
            'items.*.product_name' => FluentRule::string()->required()->max(200),
            'items.*.unit' => FluentRule::string()->nullable()->max(30),
            'items.*.quantity' => FluentRule::integer()->required()->min(1),
            'items.*.notes' => FluentRule::string()->nullable(),
        ]);

        DB::transaction(function () use ($deliveryNote, $data): void {
            $note = DeliveryNote::query()
                ->with([
                    'items',
                    'customer:id,name,customer_level_id',
                    'customer.level:id,code,name',
                ])
                ->whereKey($deliveryNote->id)
                ->lockForUpdate()
                ->firstOrFail();
            $selectedTransactionType = TransactionType::normalize((string) ($data['transaction_type'] ?? (string) $note->transaction_type));
            $printingSubtype = CustomerPrintingSubtypeResolver::resolve(
                customerId: (int) ($note->customer_id ?? 0),
                transactionType: $selectedTransactionType,
                subtypeId: isset($data['customer_printing_subtype_id']) ? (int) $data['customer_printing_subtype_id'] : null,
            );

            if ($note->is_canceled) {
                throw ValidationException::withMessages([
                    'items' => __('txn.canceled_info'),
                ]);
            }

            $oldQtyByProduct = [];
            foreach ($note->items as $existingItem) {
                $productId = (int) ($existingItem->product_id ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $oldQtyByProduct[$productId] = ($oldQtyByProduct[$productId] ?? 0) + (int) $existingItem->quantity;
            }

            $newQtyByProduct = [];
            $resolvedRows = [];
            foreach (($data['items'] ?? []) as $row) {
                $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;
                $productName = trim((string) ($row['product_name'] ?? ''));
                $resolvedProduct = $this->resolveProductFromInput($productId, $productName);
                $resolvedProductId = (int) ($resolvedProduct?->id ?? 0);

                if ($resolvedProductId > 0) {
                    $row['product_id'] = $resolvedProductId;
                }
                $resolvedRows[] = $row;

                $productId = $resolvedProductId > 0 ? $resolvedProductId : $productId;
                if ($productId <= 0) {
                    continue;
                }
                $newQtyByProduct[$productId] = ($newQtyByProduct[$productId] ?? 0) + (int) ($row['quantity'] ?? 0);
            }

            $affectedProductIds = collect(array_merge(array_keys($oldQtyByProduct), array_keys($newQtyByProduct)))
                ->unique()
                ->values()
                ->all();

            $products = Product::query()
                ->whereIn('id', $affectedProductIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($affectedProductIds as $productId) {
                $product = $products->get((int) $productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => __('txn.product_not_found'),
                    ]);
                }

                $oldQty = (int) ($oldQtyByProduct[(int) $productId] ?? 0);
                $newQty = (int) ($newQtyByProduct[(int) $productId] ?? 0);
                $delta = $oldQty - $newQty;
                if ($delta < 0 && (int) $product->stock < abs($delta)) {
                    throw ValidationException::withMessages([
                        'items' => __('txn.insufficient_stock_for', ['product' => $product->name]),
                    ]);
                }
            }

            foreach ($affectedProductIds as $productId) {
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
                    StockMutation::query()->create([
                        'product_id' => (int) $product->id,
                        'reference_type' => DeliveryNote::class,
                        'reference_id' => (int) $note->id,
                        'mutation_type' => 'in',
                        'quantity' => (int) $delta,
                        'notes' => 'Admin edit delivery note '.$note->note_number,
                        'created_by_user_id' => auth()->id(),
                    ]);
                } else {
                    $outQty = abs($delta);
                    $product->decrement('stock', $outQty);
                    StockMutation::query()->create([
                        'product_id' => (int) $product->id,
                        'reference_type' => DeliveryNote::class,
                        'reference_id' => (int) $note->id,
                        'mutation_type' => 'out',
                        'quantity' => (int) $outQty,
                        'notes' => 'Admin edit delivery note '.$note->note_number,
                        'created_by_user_id' => auth()->id(),
                    ]);
                }
            }

            $note->update([
                'note_date' => $data['note_date'],
                'transaction_type' => $selectedTransactionType,
                'customer_printing_subtype_id' => $printingSubtype['id'],
                'printing_subtype_name' => $printingSubtype['name'],
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'city' => $data['city'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $note->items()->delete();
            $customer = $note->customer;

            foreach ($resolvedRows as $row) {
                $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;
                $productCode = null;
                $productName = $row['product_name'];
                $unit = $row['unit'] ?? null;
                $product = $productId > 0
                    ? $products->get($productId)
                    : $this->resolveProductFromInput(0, $productName);

                if ($product) {
                    $productId = (int) $product->id;
                    $productCode = $product->code;
                    $productName = $product->name;
                    $unit = $unit ?: $product->unit;
                }

                DeliveryNoteItem::create([
                    'delivery_note_id' => $note->id,
                    'product_id' => $productId > 0 ? $productId : null,
                    'product_code' => $productCode,
                    'product_name' => $productName,
                    'unit' => $unit,
                    'quantity' => $row['quantity'],
                    'unit_price' => null,
                    'notes' => $row['notes'] ?? null,
                ]);
            }
        });

        $deliveryNote->refresh();
        $this->auditLogService->log(
            'delivery.note.admin_update',
            $deliveryNote,
            __('txn.audit_delivery_admin_updated', ['number' => $deliveryNote->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $deliveryNote->note_date]);

        return redirect()
            ->route('delivery-notes.show', $deliveryNote)
            ->with('success', __('txn.admin_update_saved'));
    }

    public function cancel(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => FluentRule::string()->required()->max(1000),
        ]);

        DB::transaction(function () use ($deliveryNote, $data): void {
            $note = DeliveryNote::query()
                ->with('items')
                ->whereKey($deliveryNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($note->is_canceled) {
                return;
            }

            $restoreQtyByProduct = [];
            foreach ($note->items as $item) {
                $productId = (int) ($item->product_id ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $restoreQtyByProduct[$productId] = ($restoreQtyByProduct[$productId] ?? 0) + (int) $item->quantity;
            }

            foreach ($restoreQtyByProduct as $productId => $quantity) {
                $product = Product::query()
                    ->whereKey((int) $productId)
                    ->lockForUpdate()
                    ->first();
                if (! $product || $quantity <= 0) {
                    continue;
                }

                $product->increment('stock', (int) $quantity);
                StockMutation::query()->create([
                    'product_id' => (int) $product->id,
                    'reference_type' => DeliveryNote::class,
                    'reference_id' => (int) $note->id,
                    'mutation_type' => 'in',
                    'quantity' => (int) $quantity,
                    'notes' => 'Cancel delivery note '.$note->note_number,
                    'created_by_user_id' => auth()->id(),
                ]);
            }

            $note->update([
                'is_canceled' => true,
                'canceled_at' => now(),
                'canceled_by_user_id' => auth()->id(),
                'cancel_reason' => $data['cancel_reason'],
            ]);
        });

        $this->auditLogService->log(
            'delivery.note.cancel',
            $deliveryNote,
            __('txn.audit_delivery_canceled', ['number' => $deliveryNote->note_number]),
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $deliveryNote->note_date]);

        return redirect()
            ->route('delivery-notes.show', $deliveryNote)
            ->with('success', __('txn.transaction_canceled_success'));
    }

    public function print(DeliveryNote $deliveryNote): View
    {
        $deliveryNote->load(['customer:id,name,city,phone,address', 'shipLocation:id,school_name,recipient_name,recipient_phone,city,address', 'items']);

        return view('delivery_notes.print', [
            'note' => $deliveryNote,
        ]);
    }

    public function exportPdf(DeliveryNote $deliveryNote)
    {
        $deliveryNote->load(['customer:id,name,city,phone,address', 'shipLocation:id,school_name,recipient_name,recipient_phone,city,address', 'items']);

        $filename = $deliveryNote->note_number.'.pdf';
        $pdf = Pdf::loadView('delivery_notes.print', [
            'note' => $deliveryNote,
            'isPdf' => true,
        ])->setPaper(\App\Support\PrintPaperSize::continuousForm95x11());

        return $pdf->download($filename);
    }

    public function exportExcel(DeliveryNote $deliveryNote): StreamedResponse
    {
        $deliveryNote->load(['customer:id,name,city,phone,address', 'shipLocation:id,school_name,recipient_name,recipient_phone,city,address', 'items']);
        $filename = $deliveryNote->note_number.'.xlsx';

        return response()->streamDownload(function () use ($deliveryNote): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Surat Jalan');
            $address = \App\Support\PrintTextFormatter::wrapWords((string) ($deliveryNote->address ?: ''), 5);
            $notes = \App\Support\PrintTextFormatter::wrapWords(
                trim((string) ($deliveryNote->notes ?: \App\Models\AppSetting::getValue('company_invoice_notes', ''))),
                4
            );
            $rows = [];
            $rows[] = [__('txn.delivery_notes_title').' '.__('txn.note_number'), $deliveryNote->note_number];
            $rows[] = [__('txn.date'), $deliveryNote->note_date?->format('d-m-Y')];
            if (($deliveryNote->shipLocation?->school_name ?? '') !== '') {
                $rows[] = [__('school_bulk.ship_to_school'), $deliveryNote->shipLocation->school_name];
            }
            $rows[] = [__('txn.name'), $deliveryNote->recipient_name ?: ($deliveryNote->customer?->name ?: '-')];
            $rows[] = [__('txn.phone'), $deliveryNote->recipient_phone];
            $rows[] = [__('txn.city'), $deliveryNote->city];
            $rows[] = [__('txn.address'), $address !== '' ? $address : '-'];
            $rows[] = [];
            $rows[] = [__('txn.items')];
            $rows[] = [__('txn.name'), __('txn.unit'), __('txn.qty'), __('txn.notes')];

            foreach ($deliveryNote->items as $item) {
                $rows[] = [
                    $item->product_name,
                    $item->unit,
                    $item->quantity,
                    $item->notes,
                ];
            }

            $rows[] = [];
            $rows[] = [__('txn.notes'), $notes !== '' ? $notes : '-'];
            $rows[] = [__('txn.summary_total_qty'), (int) round((float) $deliveryNote->items->sum('quantity'), 0)];

            $sheet->fromArray($rows, null, 'A1');
            $itemsCount = $deliveryNote->items->count();
            $itemsHeaderRow = (($deliveryNote->shipLocation?->school_name ?? '') !== '') ? 9 : 8;
            ExcelExportStyler::styleTable($sheet, $itemsHeaderRow, 4, $itemsCount, true);
            ExcelExportStyler::formatNumberColumns($sheet, $itemsHeaderRow + 1, $itemsHeaderRow + $itemsCount, [3], '#,##0');
            $sheet->getStyle('B1:B'.(14 + $itemsCount))->getAlignment()->setWrapText(true);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function generateNoteNumber(string $date): string
    {
        $prefix = 'SJ-'.date('dmY', strtotime($date));
        $count = DeliveryNote::query()
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

    private function resolvePriceByCustomerLevel(Product $product, ?Customer $customer): float
    {
        $levelCode = strtolower(trim((string) ($customer?->level?->code ?? '')));
        $levelName = strtolower(trim((string) ($customer?->level?->name ?? '')));
        $combined = trim($levelCode.' '.$levelName);

        if (str_contains($combined, 'agent') || str_contains($combined, 'agen')) {
            return (float) round((float) ($product->price_agent ?? $product->price_general ?? 0));
        }

        if (str_contains($combined, 'sales')) {
            return (float) round((float) ($product->price_sales ?? $product->price_general ?? 0));
        }

        return (float) round((float) ($product->price_general ?? 0));
    }

    private function resolveProductFromInput(int $productId, ?string $productName): ?Product
    {
        if ($productId > 0) {
            return Product::query()->find($productId);
        }

        $rawName = trim((string) $productName);
        if ($rawName === '') {
            return null;
        }

        $codeCandidate = $rawName;
        $nameCandidate = '';
        $separatorPos = mb_strpos($rawName, ' - ');
        if ($separatorPos !== false) {
            $codeCandidate = trim((string) mb_substr($rawName, 0, $separatorPos));
            $nameCandidate = trim((string) mb_substr($rawName, $separatorPos + 3));
        }

        if ($codeCandidate !== '') {
            $byCode = Product::query()->where('code', $codeCandidate)->first();
            if ($byCode) {
                return $byCode;
            }
        }

        if ($nameCandidate !== '') {
            $byName = Product::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($nameCandidate)])
                ->first();
            if ($byName) {
                return $byName;
            }
        }

        return Product::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($rawName)])
            ->first();
    }
}
