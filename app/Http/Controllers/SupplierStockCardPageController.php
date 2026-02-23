<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Support\ProductCodeGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierStockCardPageController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ProductCodeGenerator $productCodeGenerator
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $selectedSupplierId = max(0, (int) $request->integer('supplier_id'));
        $selectedProductId = max(0, (int) $request->integer('product_id'));
        $dateFrom = $this->normalizeDate((string) $request->string('date_from', ''));
        $dateTo = $this->normalizeDate((string) $request->string('date_to', ''));

        $suppliers = Supplier::query()
            ->onlyOptionColumns()
            ->orderBy('name')
            ->get();

        $selectedSupplier = $selectedSupplierId > 0
            ? Supplier::query()->onlyListColumns()->find($selectedSupplierId)
            : null;
        $selectedSupplierIdOrNull = $selectedSupplier?->id !== null ? (int) $selectedSupplier->id : null;

        [$movements, $summaryRows] = $this->buildStockCardData(
            $selectedSupplierIdOrNull,
            $selectedProductId > 0 ? $selectedProductId : null,
            $search,
            $dateFrom,
            $dateTo
        );

        $supplierNameMap = $suppliers->pluck('name', 'id');
        $summaryRows = $summaryRows
            ->map(function (array $row) use ($supplierNameMap): array {
                $supplierId = (int) ($row['supplier_id'] ?? 0);
                $row['supplier_name'] = (string) ($supplierNameMap->get($supplierId) ?? '-');
                $row['sort_key'] = mb_strtolower($row['supplier_name'] . '|' . (string) ($row['product_name'] ?? ''));

                return $row;
            })
            ->values();
        $summaryRows = $this->attachEditableProductIds($summaryRows);

        $totals = [
            'qty_in' => $summaryRows->sum('qty_in'),
            'qty_out' => $summaryRows->sum('qty_out'),
            'balance' => $summaryRows->sum('balance'),
        ];

        $summaryPaginator = $this->paginateCollection(
            $summaryRows->sortBy('sort_key'),
            (int) config('pagination.default_per_page', 20),
            'summary_page',
            $request
        );

        $movementPaginator = $selectedSupplierIdOrNull !== null
            ? $this->paginateCollection(
                $movements->sortByDesc(fn(array $row): string => $row['sort_key']),
                (int) config('pagination.default_per_page', 20),
                'movement_page',
                $request
            )
            : $this->emptyPaginator($request, 'movement_page');

        return view('supplier_stock_cards.index', [
            'suppliers' => $suppliers,
            'selectedSupplierId' => $selectedSupplierIdOrNull,
            'selectedSupplier' => $selectedSupplier,
            'selectedProductId' => $selectedProductId > 0 ? $selectedProductId : null,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'movementPaginator' => $movementPaginator,
            'summaryPaginator' => $summaryPaginator,
            'totals' => $totals,
        ]);
    }

    /**
     * @param Collection<int, array<string, mixed>> $summaryRows
     * @return Collection<int, array<string, mixed>>
     */
    private function attachEditableProductIds(Collection $summaryRows): Collection
    {
        $rows = $summaryRows->values();
        if ($rows->isEmpty()) {
            return $rows;
        }

        $codeKeys = $rows
            ->filter(fn(array $row): bool => (int) ($row['product_id'] ?? 0) <= 0 && trim((string) ($row['product_code'] ?? '')) !== '')
            ->map(fn(array $row): string => mb_strtolower(trim((string) ($row['product_code'] ?? ''))))
            ->unique()
            ->values();
        $nameKeys = $rows
            ->filter(fn(array $row): bool => (int) ($row['product_id'] ?? 0) <= 0 && trim((string) ($row['product_name'] ?? '')) !== '')
            ->map(fn(array $row): string => mb_strtolower(trim((string) ($row['product_name'] ?? ''))))
            ->unique()
            ->values();

        if ($codeKeys->isEmpty() && $nameKeys->isEmpty()) {
            return $rows->map(function (array $row): array {
                $row['editable_product_id'] = (int) ($row['product_id'] ?? 0);

                return $row;
            });
        }

        $products = Product::query()
            ->select(['id', 'code', 'name'])
            ->where(function ($query) use ($codeKeys, $nameKeys): void {
                if ($codeKeys->isNotEmpty()) {
                    $query->whereIn(DB::raw('LOWER(code)'), $codeKeys->all());
                }
                if ($nameKeys->isNotEmpty()) {
                    $query->orWhereIn(DB::raw('LOWER(name)'), $nameKeys->all());
                }
            })
            ->get();

        $productByCode = $products
            ->filter(fn(Product $product): bool => trim((string) $product->code) !== '')
            ->groupBy(fn(Product $product): string => mb_strtolower(trim((string) $product->code)));
        $productByName = $products
            ->filter(fn(Product $product): bool => trim((string) $product->name) !== '')
            ->groupBy(fn(Product $product): string => mb_strtolower(trim((string) $product->name)));

        return $rows->map(function (array $row) use ($productByCode, $productByName): array {
            $baseProductId = (int) ($row['product_id'] ?? 0);
            $editableProductId = $baseProductId > 0 ? $baseProductId : 0;

            if ($editableProductId <= 0) {
                $codeKey = mb_strtolower(trim((string) ($row['product_code'] ?? '')));
                if ($codeKey !== '' && $productByCode->has($codeKey)) {
                    $matches = $productByCode->get($codeKey);
                    if ($matches !== null && $matches->count() >= 1) {
                        $editableProductId = (int) optional($matches->first())->id;
                    }
                }
            }

            if ($editableProductId <= 0) {
                $nameKey = mb_strtolower(trim((string) ($row['product_name'] ?? '')));
                if ($nameKey !== '' && $productByName->has($nameKey)) {
                    $matches = $productByName->get($nameKey);
                    if ($matches !== null && $matches->count() >= 1) {
                        $editableProductId = (int) optional($matches->first())->id;
                    }
                }
            }

            $row['editable_product_id'] = $editableProductId;

            return $row;
        });
    }

    public function updateStock(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer'],
            'product_code' => ['nullable', 'string', 'max:60'],
            'product_name' => ['required', 'string', 'max:200'],
            'stock' => ['required', 'integer', 'min:0'],
            'supplier_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string'],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
        ]);
        $flashMeta = [
            'type' => 'edit',
            'message' => __('supplier_stock.stock_updated_success', ['product' => (string) ($data['product_name'] ?? '-')]),
        ];

        $result = DB::transaction(function () use ($data, $request, &$flashMeta): array {
            $product = $this->resolveProductForStockUpdate($data);
            $supplierId = max(0, (int) ($data['supplier_id'] ?? 0));
            $targetStock = (int) $data['stock'];

            $oldStock = (int) $product->stock;
            if ($supplierId > 0) {
                $displayStockBefore = $this->resolveSupplierProductBalance($supplierId, $product);
                $supplierDelta = $targetStock - $displayStockBefore;
                if ($supplierDelta === 0) {
                    $flashMeta = $this->buildSupplierStockFlashMeta($product, $displayStockBefore, $targetStock);
                    return [
                        'product' => $product,
                        'display_stock' => $displayStockBefore,
                    ];
                }

                $newStock = max(0, $oldStock + $supplierDelta);
                $product->update([
                    'stock' => $newStock,
                ]);

                StockMutation::query()->create([
                    'product_id' => (int) $product->id,
                    'reference_type' => Supplier::class,
                    'reference_id' => $supplierId,
                    'mutation_type' => $supplierDelta > 0 ? 'in' : 'out',
                    'quantity' => abs($supplierDelta),
                    'notes' => __('supplier_stock.manual_stock_adjust_note'),
                    'created_by_user_id' => $request->user()?->id,
                ]);

                $this->auditLogService->log(
                    'supplier.stock.manual_adjust',
                    $product,
                    "Manual stock adjusted via supplier stock card: {$product->name} ({$displayStockBefore} -> {$targetStock})",
                    $request,
                    ['stock' => $oldStock, 'supplier_stock' => $displayStockBefore],
                    ['stock' => $newStock, 'supplier_stock' => $targetStock]
                );
                $product->refresh();
                $product->load('category:id,code,name');
                $flashMeta = $this->buildSupplierStockFlashMeta($product, $displayStockBefore, $targetStock);

                return [
                    'product' => $product->fresh(),
                    'display_stock' => $targetStock,
                ];
            }

            if ($oldStock === $targetStock) {
                $flashMeta = $this->buildSupplierStockFlashMeta($product, $oldStock, $targetStock);
                return [
                    'product' => $product,
                    'display_stock' => $oldStock,
                ];
            }

            $globalDelta = $targetStock - $oldStock;
            $product->update([
                'stock' => $targetStock,
            ]);

            StockMutation::query()->create([
                'product_id' => (int) $product->id,
                'reference_type' => Product::class,
                'reference_id' => (int) $product->id,
                'mutation_type' => $globalDelta > 0 ? 'in' : 'out',
                'quantity' => abs($globalDelta),
                'notes' => __('supplier_stock.manual_stock_adjust_note'),
                'created_by_user_id' => $request->user()?->id,
            ]);

            $this->auditLogService->log(
                'supplier.stock.manual_adjust',
                $product,
                "Manual stock adjusted via supplier stock card: {$product->name} ({$oldStock} -> {$targetStock})",
                $request,
                ['stock' => $oldStock],
                ['stock' => $targetStock]
            );
            $product->refresh();
            $product->load('category:id,code,name');
            $flashMeta = $this->buildSupplierStockFlashMeta($product, $oldStock, $targetStock);

            return [
                'product' => $product->fresh(),
                'display_stock' => $targetStock,
            ];
        });
        /** @var Product $product */
        $product = $result['product'];
        $displayStock = max(0, (int) ($result['display_stock'] ?? $product->stock));

        $query = [
            'supplier_id' => max(0, (int) ($data['supplier_id'] ?? 0)) ?: null,
            'search' => trim((string) ($data['search'] ?? '')) ?: null,
            'date_from' => trim((string) ($data['date_from'] ?? '')) ?: null,
            'date_to' => trim((string) ($data['date_to'] ?? '')) ?: null,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => (string) ($flashMeta['message'] ?? __('supplier_stock.stock_updated_success', ['product' => $product->name])),
                'message_type' => (string) ($flashMeta['type'] ?? 'edit'),
                'product' => [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                ],
                'stock' => $displayStock,
                'product_stock' => (int) $product->stock,
            ]);
        }

        return redirect()
            ->route('supplier-stock-cards.index', array_filter($query, fn($value): bool => $value !== null && $value !== ''))
            ->with('success', (string) ($flashMeta['message'] ?? __('supplier_stock.stock_updated_success', ['product' => $product->name])))
            ->with('success_type', (string) ($flashMeta['type'] ?? 'edit'));
    }

    /**
     * @return array{type:string,message:string}
     */
    private function buildSupplierStockFlashMeta(Product $product, int $oldStock, int $newStock): array
    {
        $priceSegment = __('ui.stock_change_price_segment', [
            'agent' => number_format((int) round((float) $product->price_agent), 0, ',', '.'),
            'sales' => number_format((int) round((float) $product->price_sales), 0, ',', '.'),
            'general' => number_format((int) round((float) $product->price_general), 0, ',', '.'),
        ]);

        $category = $this->productCategoryLabel($product);
        $code = trim((string) ($product->code ?? '')) !== '' ? (string) $product->code : '-';
        $name = (string) $product->name;
        $stockText = number_format($newStock, 0, ',', '.');
        $delta = $newStock - $oldStock;

        if ($delta > 0) {
            return [
                'type' => 'increase',
                'message' => __('ui.stock_change_increase_message', [
                    'code' => $code,
                    'category' => $category,
                    'name' => $name,
                    'stock' => $stockText,
                    'delta' => number_format($delta, 0, ',', '.'),
                    'price_segment' => $priceSegment,
                ]),
            ];
        }

        if ($delta < 0) {
            return [
                'type' => 'decrease',
                'message' => __('ui.stock_change_decrease_message', [
                    'code' => $code,
                    'category' => $category,
                    'name' => $name,
                    'stock' => $stockText,
                    'delta' => number_format(abs($delta), 0, ',', '.'),
                    'price_segment' => $priceSegment,
                ]),
            ];
        }

        return [
            'type' => 'edit',
            'message' => __('ui.stock_change_edit_message', [
                'code' => $code,
                'category' => $category,
                'name' => $name,
                'changes' => __('ui.stock_change_fields_none'),
                'price_segment' => $priceSegment,
            ]),
        ];
    }

    private function productCategoryLabel(Product $product): string
    {
        $product->loadMissing('category:id,code,name');
        $category = $product->category;
        if (! $category) {
            return '-';
        }

        $code = trim((string) ($category->code ?? ''));
        $name = trim((string) ($category->name ?? ''));
        if ($code !== '' && $name !== '') {
            return "{$code} - {$name}";
        }
        if ($name !== '') {
            return $name;
        }
        if ($code !== '') {
            return $code;
        }

        return '-';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveProductForStockUpdate(array $data): Product
    {
        $productId = max(0, (int) ($data['product_id'] ?? 0));
        if ($productId > 0) {
            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->first();
            if ($product !== null) {
                return $product;
            }
        }

        $productCode = mb_strtolower(trim((string) ($data['product_code'] ?? '')));
        if ($productCode !== '') {
            $byCode = Product::query()
                ->whereRaw('LOWER(code) = ?', [$productCode])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($byCode->count() >= 1) {
                /** @var Product $matched */
                $matched = $byCode->first();

                return $matched;
            }
        }

        $productName = mb_strtolower(trim((string) ($data['product_name'] ?? '')));
        if ($productName !== '') {
            $byName = Product::query()
                ->whereRaw('LOWER(name) = ?', [$productName])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($byName->count() >= 1) {
                /** @var Product $matched */
                $matched = $byName->first();

                return $matched;
            }
        }

        $normalizedCode = $this->normalizeLookup((string) ($data['product_code'] ?? ''));
        $normalizedName = $this->normalizeLookup((string) ($data['product_name'] ?? ''));
        if ($normalizedCode !== '' || $normalizedName !== '') {
            $candidates = Product::query()
                ->select(['id', 'code', 'name'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($normalizedCode !== '') {
                $byCode = $candidates->first(function (Product $product) use ($normalizedCode): bool {
                    return $this->normalizeLookup((string) $product->code) === $normalizedCode;
                });
                if ($byCode instanceof Product) {
                    return $byCode;
                }
            }

            if ($normalizedName !== '') {
                $byName = $candidates->first(function (Product $product) use ($normalizedName): bool {
                    return $this->normalizeLookup((string) $product->name) === $normalizedName;
                });
                if ($byName instanceof Product) {
                    return $byName;
                }
            }
        }

        $fallbackCategoryId = (int) (ItemCategory::query()->orderBy('id')->value('id') ?? 0);
        if ($fallbackCategoryId <= 0) {
            $defaultCategory = ItemCategory::query()->create([
                'code' => 'AUTO',
                'name' => 'Auto',
                'description' => 'Auto-created category for supplier stock mapping',
            ]);
            $fallbackCategoryId = (int) $defaultCategory->id;
        }

        $productName = trim((string) ($data['product_name'] ?? ''));
        if ($productName === '') {
            throw ValidationException::withMessages([
                'product_name' => __('supplier_stock.product_mapping_not_found'),
            ]);
        }

        $requestedCode = trim((string) ($data['product_code'] ?? ''));
        $resolvedCode = $this->productCodeGenerator->resolve(
            $requestedCode !== '' ? $requestedCode : null,
            $productName
        );

        $created = Product::query()->create([
            'item_category_id' => $fallbackCategoryId,
            'code' => $resolvedCode,
            'name' => $productName,
            'unit' => 'exp',
            'stock' => 0,
            'price_agent' => 0,
            'price_sales' => 0,
            'price_general' => 0,
            'is_active' => true,
        ]);

        return Product::query()
            ->whereKey($created->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function normalizeLookup(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9]+/u', '', $normalized) ?? $normalized;

        return $normalized;
    }

    /**
     * @return array{byCode: array<string, array{id:int, code:string, name:string}>, byName: array<string, array{id:int, code:string, name:string}>}
     */
    private function buildProductLookupMaps(): array
    {
        $byCode = [];
        $byName = [];
        $products = Product::query()
            ->select(['id', 'code', 'name'])
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            $id = (int) $product->id;
            $code = trim((string) $product->code);
            $name = trim((string) $product->name);
            $row = ['id' => $id, 'code' => $code, 'name' => $name];

            $codeKey = $this->normalizeLookup($code);
            if ($codeKey !== '' && ! isset($byCode[$codeKey])) {
                $byCode[$codeKey] = $row;
            }

            $nameKey = $this->normalizeLookup($name);
            if ($nameKey !== '' && ! isset($byName[$nameKey])) {
                $byName[$nameKey] = $row;
            }
        }

        return ['byCode' => $byCode, 'byName' => $byName];
    }

    /**
     * @param array{byCode: array<string, array{id:int, code:string, name:string}>, byName: array<string, array{id:int, code:string, name:string}>} $lookup
     * @return array{product_id: ?int, product_code: string, product_name: string, product_key: string}
     */
    private function mapEventProductIdentity(
        int $rawProductId,
        string $rawProductCode,
        string $rawProductName,
        array $lookup
    ): array {
        $productId = $rawProductId > 0 ? $rawProductId : null;
        $productCode = trim($rawProductCode);
        $productName = trim($rawProductName);

        if ($productId === null) {
            $codeKey = $this->normalizeLookup($productCode);
            if ($codeKey !== '' && isset($lookup['byCode'][$codeKey])) {
                $mapped = $lookup['byCode'][$codeKey];
                $productId = (int) $mapped['id'];
                if ($productCode === '') {
                    $productCode = (string) $mapped['code'];
                }
                if ($productName === '') {
                    $productName = (string) $mapped['name'];
                }
            }
        }

        if ($productId === null) {
            $nameKey = $this->normalizeLookup($productName);
            if ($nameKey !== '' && isset($lookup['byName'][$nameKey])) {
                $mapped = $lookup['byName'][$nameKey];
                $productId = (int) $mapped['id'];
                if ($productCode === '') {
                    $productCode = (string) $mapped['code'];
                }
                if ($productName === '') {
                    $productName = (string) $mapped['name'];
                }
            }
        }

        return [
            'product_id' => $productId,
            'product_code' => $productCode,
            'product_name' => $productName,
            'product_key' => $this->resolveProductKey($productId, $productCode, $productName),
        ];
    }

    private function resolveSupplierProductBalance(int $supplierId, Product $product): int
    {
        if ($supplierId <= 0) {
            return (int) $product->stock;
        }

        [, $summaryRows] = $this->buildStockCardData($supplierId, null, '', null, null);
        $matchedById = $summaryRows->first(fn(array $row): bool => (int) ($row['product_id'] ?? 0) === (int) $product->id);
        if (is_array($matchedById)) {
            return max(0, (int) ($matchedById['balance'] ?? 0));
        }

        $targetCode = $this->normalizeLookup((string) $product->code);
        $targetName = $this->normalizeLookup((string) $product->name);
        $matched = $summaryRows->first(function (array $row) use ($targetCode, $targetName): bool {
            return (
                $targetCode !== ''
                && $this->normalizeLookup((string) ($row['product_code'] ?? '')) === $targetCode
            ) || (
                $targetName !== ''
                && $this->normalizeLookup((string) ($row['product_name'] ?? '')) === $targetName
            );
        });

        if (is_array($matched)) {
            return max(0, (int) ($matched['balance'] ?? 0));
        }

        return 0;
    }

    /**
     * @return array{0:\Illuminate\Support\Collection<int, array<string, mixed>>, 1:\Illuminate\Support\Collection<int, array<string, mixed>>}
     */
    private function buildStockCardData(
        ?int $selectedSupplierId,
        ?int $selectedProductId,
        string $search,
        ?string $dateFrom,
        ?string $dateTo
    ): array {
        $productLookup = $this->buildProductLookupMaps();

        $manualSupplierEvents = DB::table('stock_mutations as sm')
            ->join('products as p', 'p.id', '=', 'sm.product_id')
            ->where('sm.reference_type', Supplier::class)
            ->when($selectedSupplierId !== null, fn($query) => $query->where('sm.reference_id', $selectedSupplierId))
            ->when($selectedProductId !== null, fn($query) => $query->where('sm.product_id', $selectedProductId))
            ->when($dateFrom !== null, fn($query) => $query->whereDate('sm.created_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn($query) => $query->whereDate('sm.created_at', '<=', $dateTo))
            ->select([
                DB::raw('DATE(sm.created_at) as event_date'),
                DB::raw('sm.id as reference_id'),
                DB::raw("('MNL-' || sm.id) as reference_number"),
                DB::raw('CAST(sm.reference_id AS INTEGER) as supplier_id'),
                DB::raw('sm.product_id as product_id'),
                DB::raw('p.code as product_code'),
                DB::raw('p.name as product_name'),
                DB::raw("COALESCE(p.unit, 'exp') as unit"),
                DB::raw('CAST(sm.quantity AS INTEGER) as quantity'),
                DB::raw('sm.mutation_type as mutation_type'),
            ])
            ->orderBy('sm.created_at')
            ->orderBy('sm.id')
            ->get();

        $incomingEvents = DB::table('outgoing_transaction_items as oti')
            ->join('outgoing_transactions as ot', 'ot.id', '=', 'oti.outgoing_transaction_id')
            ->whereNull('ot.deleted_at')
            ->when($selectedProductId !== null, fn($query) => $query->where('oti.product_id', $selectedProductId))
            ->when($dateTo !== null, fn($query) => $query->whereDate('ot.transaction_date', '<=', $dateTo))
            ->select([
                'ot.transaction_date as event_date',
                'ot.id as reference_id',
                'ot.transaction_number as reference_number',
                'ot.supplier_id',
                'oti.product_id',
                'oti.product_code',
                'oti.product_name',
                'oti.unit',
                DB::raw('CAST(oti.quantity AS INTEGER) as quantity'),
            ])
            ->orderBy('ot.transaction_date')
            ->orderBy('ot.id')
            ->orderBy('oti.id')
            ->get();

        $outgoingEvents = DB::table('sales_invoice_items as sii')
            ->join('sales_invoices as si', 'si.id', '=', 'sii.sales_invoice_id')
            ->whereNull('si.deleted_at')
            ->where('si.is_canceled', false)
            ->when($selectedProductId !== null, fn($query) => $query->where('sii.product_id', $selectedProductId))
            ->when($dateTo !== null, fn($query) => $query->whereDate('si.invoice_date', '<=', $dateTo))
            ->select([
                'si.invoice_date as event_date',
                'si.id as reference_id',
                'si.invoice_number as reference_number',
                'sii.product_id',
                'sii.product_code',
                'sii.product_name',
                DB::raw('CAST(sii.quantity AS INTEGER) as quantity'),
            ])
            ->orderBy('si.invoice_date')
            ->orderBy('si.id')
            ->orderBy('sii.id')
            ->get();

        $returnEvents = DB::table('sales_return_items as sri')
            ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
            ->whereNull('sr.deleted_at')
            ->where('sr.is_canceled', false)
            ->when($selectedProductId !== null, fn($query) => $query->where('sri.product_id', $selectedProductId))
            ->when($dateTo !== null, fn($query) => $query->whereDate('sr.return_date', '<=', $dateTo))
            ->select([
                'sr.return_date as event_date',
                'sr.id as reference_id',
                'sr.return_number as reference_number',
                'sri.product_id',
                'sri.product_code',
                'sri.product_name',
                DB::raw('CAST(sri.quantity AS INTEGER) as quantity'),
            ])
            ->orderBy('sr.return_date')
            ->orderBy('sr.id')
            ->orderBy('sri.id')
            ->get();

        $events = collect();
        foreach ($incomingEvents as $incoming) {
            $mappedProduct = $this->mapEventProductIdentity(
                (int) ($incoming->product_id ?? 0),
                (string) ($incoming->product_code ?? ''),
                (string) ($incoming->product_name ?? ''),
                $productLookup
            );
            $events->push([
                'type' => 'in',
                'event_date' => (string) $incoming->event_date,
                'priority' => 1,
                'reference_id' => (int) $incoming->reference_id,
                'reference_number' => (string) $incoming->reference_number,
                'supplier_id' => (int) $incoming->supplier_id,
                'product_id' => $mappedProduct['product_id'],
                'product_key' => $mappedProduct['product_key'],
                'product_code' => $mappedProduct['product_code'],
                'product_name' => $mappedProduct['product_name'],
                'unit' => (string) ($incoming->unit ?? ''),
                'quantity' => max(0, (int) $incoming->quantity),
                'label' => __('supplier_stock.incoming_label'),
                'route_name' => 'outgoing-transactions.show',
            ]);
        }
        foreach ($outgoingEvents as $outgoing) {
            $mappedProduct = $this->mapEventProductIdentity(
                (int) ($outgoing->product_id ?? 0),
                (string) ($outgoing->product_code ?? ''),
                (string) ($outgoing->product_name ?? ''),
                $productLookup
            );
            $events->push([
                'type' => 'out',
                'event_date' => (string) $outgoing->event_date,
                'priority' => 2,
                'reference_id' => (int) $outgoing->reference_id,
                'reference_number' => (string) $outgoing->reference_number,
                'supplier_id' => null,
                'product_id' => $mappedProduct['product_id'],
                'product_key' => $mappedProduct['product_key'],
                'product_code' => $mappedProduct['product_code'],
                'product_name' => $mappedProduct['product_name'],
                'unit' => '',
                'quantity' => max(0, (int) $outgoing->quantity),
                'label' => __('supplier_stock.outgoing_label'),
                'route_name' => 'sales-invoices.show',
            ]);
        }
        foreach ($returnEvents as $returnItem) {
            $mappedProduct = $this->mapEventProductIdentity(
                (int) ($returnItem->product_id ?? 0),
                (string) ($returnItem->product_code ?? ''),
                (string) ($returnItem->product_name ?? ''),
                $productLookup
            );
            $events->push([
                'type' => 'return_in',
                'event_date' => (string) $returnItem->event_date,
                'priority' => 3,
                'reference_id' => (int) $returnItem->reference_id,
                'reference_number' => (string) $returnItem->reference_number,
                'supplier_id' => null,
                'product_id' => $mappedProduct['product_id'],
                'product_key' => $mappedProduct['product_key'],
                'product_code' => $mappedProduct['product_code'],
                'product_name' => $mappedProduct['product_name'],
                'unit' => '',
                'quantity' => max(0, (int) $returnItem->quantity),
                'label' => __('supplier_stock.return_label'),
                'route_name' => 'sales-returns.show',
            ]);
        }
        foreach ($manualSupplierEvents as $manualItem) {
            $mappedProduct = $this->mapEventProductIdentity(
                (int) ($manualItem->product_id ?? 0),
                (string) ($manualItem->product_code ?? ''),
                (string) ($manualItem->product_name ?? ''),
                $productLookup
            );
            $mutationType = (string) ($manualItem->mutation_type ?? 'in');
            $eventType = $mutationType === 'out' ? 'manual_out' : 'manual_in';
            $events->push([
                'type' => $eventType,
                'event_date' => (string) $manualItem->event_date,
                'priority' => 4,
                'reference_id' => (int) $manualItem->reference_id,
                'reference_number' => (string) $manualItem->reference_number,
                'supplier_id' => (int) ($manualItem->supplier_id ?? 0),
                'product_id' => $mappedProduct['product_id'],
                'product_key' => $mappedProduct['product_key'],
                'product_code' => $mappedProduct['product_code'],
                'product_name' => $mappedProduct['product_name'],
                'unit' => (string) ($manualItem->unit ?? ''),
                'quantity' => max(0, (int) ($manualItem->quantity ?? 0)),
                'label' => $mutationType === 'out'
                    ? __('supplier_stock.manual_adjust_out_label')
                    : __('supplier_stock.manual_adjust_in_label'),
                'route_name' => '',
            ]);
        }

        $events = $events
            ->sortBy(fn(array $event): string => sprintf(
                '%s|%d|%010d',
                $event['event_date'],
                $event['priority'],
                $event['reference_id']
            ))
            ->values();

        $poolsByProduct = [];
        $poolOffsetByProduct = [];
        $runningBalances = [];
        $consumedStacks = [];
        $summary = [];
        $movements = collect();
        $sequence = 0;

        foreach ($events as $event) {
            $productId = isset($event['product_id']) && $event['product_id'] !== null
                ? (int) $event['product_id']
                : null;
            $productKey = (string) ($event['product_key'] ?? '');
            $quantity = (int) $event['quantity'];
            if ($productKey === '' || $quantity <= 0) {
                continue;
            }

            if (($event['type'] ?? '') === 'in') {
                $supplierId = (int) ($event['supplier_id'] ?? 0);
                if ($supplierId <= 0) {
                    continue;
                }

                $poolsByProduct[$productKey] = $poolsByProduct[$productKey] ?? [];
                $poolsByProduct[$productKey][] = [
                    'supplier_id' => $supplierId,
                    'remaining' => $quantity,
                    'unit' => (string) ($event['unit'] ?? ''),
                    'product_code' => (string) ($event['product_code'] ?? ''),
                    'product_name' => (string) ($event['product_name'] ?? ''),
                    'product_id' => $productId,
                    'product_key' => $productKey,
                ];
                $poolOffsetByProduct[$productKey] = $poolOffsetByProduct[$productKey] ?? 0;

                $runningBalances[$supplierId][$productKey] = (int) ($runningBalances[$supplierId][$productKey] ?? 0) + $quantity;
                $summary[$supplierId][$productKey] = $summary[$supplierId][$productKey] ?? [
                    'product_id' => $productId,
                    'product_key' => $productKey,
                    'product_code' => (string) ($event['product_code'] ?? ''),
                    'product_name' => (string) ($event['product_name'] ?? ''),
                    'unit' => (string) ($event['unit'] ?? ''),
                    'qty_in' => 0,
                    'qty_out' => 0,
                    'balance' => 0,
                ];
                $summary[$supplierId][$productKey]['qty_in'] += $quantity;
                $summary[$supplierId][$productKey]['balance'] = (int) $runningBalances[$supplierId][$productKey];

                $movements->push($this->movementRow(
                    supplierId: $supplierId,
                    productId: $productId,
                    productKey: $productKey,
                    productCode: (string) ($event['product_code'] ?? ''),
                    productName: (string) ($event['product_name'] ?? ''),
                    unit: (string) ($event['unit'] ?? ''),
                    eventDate: (string) $event['event_date'],
                    referenceNumber: (string) $event['reference_number'],
                    referenceId: (int) $event['reference_id'],
                    referenceRoute: (string) $event['route_name'],
                    description: (string) $event['label'],
                    qtyIn: $quantity,
                    qtyOut: 0,
                    balanceAfter: (int) $runningBalances[$supplierId][$productKey],
                    sequence: $sequence++
                ));
                continue;
            }

            if (($event['type'] ?? '') === 'manual_in' || ($event['type'] ?? '') === 'manual_out') {
                $supplierId = (int) ($event['supplier_id'] ?? 0);
                if ($supplierId <= 0) {
                    continue;
                }

                $delta = (int) $quantity;
                if (($event['type'] ?? '') === 'manual_out') {
                    $delta *= -1;
                }

                $runningBalances[$supplierId][$productKey] = max(
                    0,
                    (int) ($runningBalances[$supplierId][$productKey] ?? 0) + $delta
                );
                $summary[$supplierId][$productKey] = $summary[$supplierId][$productKey] ?? [
                    'product_id' => $productId,
                    'product_key' => $productKey,
                    'product_code' => (string) ($event['product_code'] ?? ''),
                    'product_name' => (string) ($event['product_name'] ?? ''),
                    'unit' => (string) ($event['unit'] ?? ''),
                    'qty_in' => 0,
                    'qty_out' => 0,
                    'balance' => 0,
                ];
                if ($delta >= 0) {
                    $summary[$supplierId][$productKey]['qty_in'] += $delta;
                } else {
                    $summary[$supplierId][$productKey]['qty_out'] += abs($delta);
                }
                $summary[$supplierId][$productKey]['balance'] = (int) $runningBalances[$supplierId][$productKey];

                $movements->push($this->movementRow(
                    supplierId: $supplierId,
                    productId: $productId,
                    productKey: $productKey,
                    productCode: (string) ($event['product_code'] ?? ''),
                    productName: (string) ($event['product_name'] ?? ''),
                    unit: (string) ($event['unit'] ?? ''),
                    eventDate: (string) $event['event_date'],
                    referenceNumber: (string) ($event['reference_number'] ?? ''),
                    referenceId: (int) $event['reference_id'],
                    referenceRoute: '',
                    description: (string) $event['label'],
                    qtyIn: $delta >= 0 ? $delta : 0,
                    qtyOut: $delta < 0 ? abs($delta) : 0,
                    balanceAfter: (int) $runningBalances[$supplierId][$productKey],
                    sequence: $sequence++
                ));
                continue;
            }

            if (($event['type'] ?? '') === 'out') {
                $remainingOut = $quantity;
                $poolOffsetByProduct[$productKey] = $poolOffsetByProduct[$productKey] ?? 0;
                $poolsByProduct[$productKey] = $poolsByProduct[$productKey] ?? [];
                $consumedStacks[$productKey] = $consumedStacks[$productKey] ?? [];

                while ($remainingOut > 0) {
                    $offset = (int) ($poolOffsetByProduct[$productKey] ?? 0);
                    if (! isset($poolsByProduct[$productKey][$offset])) {
                        break;
                    }

                    $pool = &$poolsByProduct[$productKey][$offset];
                    $poolRemaining = (int) ($pool['remaining'] ?? 0);
                    if ($poolRemaining <= 0) {
                        $poolOffsetByProduct[$productKey] = $offset + 1;
                        unset($pool);
                        continue;
                    }

                    $allocated = min($remainingOut, $poolRemaining);
                    $pool['remaining'] = $poolRemaining - $allocated;
                    $remainingOut -= $allocated;
                    $supplierId = (int) ($pool['supplier_id'] ?? 0);
                    if ($supplierId <= 0) {
                        unset($pool);
                        continue;
                    }

                    $runningBalances[$supplierId][$productKey] = max(
                        0,
                        (int) ($runningBalances[$supplierId][$productKey] ?? 0) - $allocated
                    );
                    $summary[$supplierId][$productKey] = $summary[$supplierId][$productKey] ?? [
                        'product_id' => $productId,
                        'product_key' => $productKey,
                        'product_code' => (string) ($pool['product_code'] ?? ''),
                        'product_name' => (string) ($pool['product_name'] ?? ''),
                        'unit' => (string) ($pool['unit'] ?? ''),
                        'qty_in' => 0,
                        'qty_out' => 0,
                        'balance' => 0,
                    ];
                    $summary[$supplierId][$productKey]['qty_out'] += $allocated;
                    $summary[$supplierId][$productKey]['balance'] = (int) $runningBalances[$supplierId][$productKey];
                    $consumedStacks[$productKey][] = [
                        'supplier_id' => $supplierId,
                        'quantity' => $allocated,
                        'unit' => (string) ($pool['unit'] ?? ''),
                        'product_code' => (string) ($pool['product_code'] ?? ($event['product_code'] ?? '')),
                        'product_name' => (string) ($pool['product_name'] ?? ($event['product_name'] ?? '')),
                        'product_id' => $productId,
                        'product_key' => $productKey,
                    ];

                    $movements->push($this->movementRow(
                        supplierId: $supplierId,
                        productId: $productId,
                        productKey: $productKey,
                        productCode: (string) ($pool['product_code'] ?? ($event['product_code'] ?? '')),
                        productName: (string) ($pool['product_name'] ?? ($event['product_name'] ?? '')),
                        unit: (string) ($pool['unit'] ?? ''),
                        eventDate: (string) $event['event_date'],
                        referenceNumber: (string) $event['reference_number'],
                        referenceId: (int) $event['reference_id'],
                        referenceRoute: (string) $event['route_name'],
                        description: (string) $event['label'],
                        qtyIn: 0,
                        qtyOut: $allocated,
                        balanceAfter: (int) $runningBalances[$supplierId][$productKey],
                        sequence: $sequence++
                    ));

                    if ((int) ($pool['remaining'] ?? 0) <= 0) {
                        $poolOffsetByProduct[$productKey] = $offset + 1;
                    }
                    unset($pool);
                }
                continue;
            }

            if (($event['type'] ?? '') === 'return_in') {
                $consumedStacks[$productKey] = $consumedStacks[$productKey] ?? [];
                $remainingReturn = $quantity;

                while ($remainingReturn > 0 && ! empty($consumedStacks[$productKey])) {
                    $stackIndex = count($consumedStacks[$productKey]) - 1;
                    $stackRow = $consumedStacks[$productKey][$stackIndex];
                    $stackQty = (int) ($stackRow['quantity'] ?? 0);
                    if ($stackQty <= 0) {
                        array_pop($consumedStacks[$productKey]);
                        continue;
                    }

                    $allocated = min($remainingReturn, $stackQty);
                    $remainingReturn -= $allocated;
                    $stackRow['quantity'] = $stackQty - $allocated;
                    if ((int) $stackRow['quantity'] <= 0) {
                        array_pop($consumedStacks[$productKey]);
                    } else {
                        $consumedStacks[$productKey][$stackIndex] = $stackRow;
                    }

                    $supplierId = (int) ($stackRow['supplier_id'] ?? 0);
                    if ($supplierId <= 0) {
                        continue;
                    }

                    $runningBalances[$supplierId][$productKey] = (int) ($runningBalances[$supplierId][$productKey] ?? 0) + $allocated;
                    $summary[$supplierId][$productKey] = $summary[$supplierId][$productKey] ?? [
                        'product_id' => isset($stackRow['product_id']) && $stackRow['product_id'] !== null ? (int) $stackRow['product_id'] : $productId,
                        'product_key' => (string) ($stackRow['product_key'] ?? $productKey),
                        'product_code' => (string) ($stackRow['product_code'] ?? ($event['product_code'] ?? '')),
                        'product_name' => (string) ($stackRow['product_name'] ?? ($event['product_name'] ?? '')),
                        'unit' => (string) ($stackRow['unit'] ?? ''),
                        'qty_in' => 0,
                        'qty_out' => 0,
                        'balance' => 0,
                    ];
                    $summary[$supplierId][$productKey]['qty_in'] += $allocated;
                    $summary[$supplierId][$productKey]['balance'] = (int) $runningBalances[$supplierId][$productKey];

                    $movements->push($this->movementRow(
                        supplierId: $supplierId,
                        productId: isset($stackRow['product_id']) && $stackRow['product_id'] !== null ? (int) $stackRow['product_id'] : $productId,
                        productKey: (string) ($stackRow['product_key'] ?? $productKey),
                        productCode: (string) ($stackRow['product_code'] ?? ($event['product_code'] ?? '')),
                        productName: (string) ($stackRow['product_name'] ?? ($event['product_name'] ?? '')),
                        unit: (string) ($stackRow['unit'] ?? ''),
                        eventDate: (string) $event['event_date'],
                        referenceNumber: (string) $event['reference_number'],
                        referenceId: (int) $event['reference_id'],
                        referenceRoute: (string) $event['route_name'],
                        description: (string) $event['label'],
                        qtyIn: $allocated,
                        qtyOut: 0,
                        balanceAfter: (int) $runningBalances[$supplierId][$productKey],
                        sequence: $sequence++
                    ));
                }
            }
        }

        if ($selectedSupplierId !== null && $selectedSupplierId > 0) {
            $movements = $movements
                ->where('supplier_id', $selectedSupplierId)
                ->values();
        }

        if ($selectedProductId !== null) {
            $movements = $movements
                ->filter(fn(array $row): bool => (int) ($row['product_id'] ?? 0) === $selectedProductId)
                ->values();
        }

        if ($dateFrom !== null) {
            $movements = $movements
                ->filter(fn(array $row): bool => (string) $row['event_date'] >= $dateFrom)
                ->values();
        }

        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $movements = $movements
                ->filter(function (array $row) use ($searchLower): bool {
                    return str_contains(mb_strtolower((string) ($row['reference_number'] ?? '')), $searchLower)
                        || str_contains(mb_strtolower((string) ($row['product_code'] ?? '')), $searchLower)
                        || str_contains(mb_strtolower((string) ($row['product_name'] ?? '')), $searchLower);
                })
                ->values();
        }

        if ($selectedSupplierId !== null && $selectedSupplierId > 0) {
            $summaryRows = collect($summary[$selectedSupplierId] ?? [])
                ->map(function (array $row) use ($selectedSupplierId): array {
                    $row['supplier_id'] = $selectedSupplierId;

                    return $row;
                })
                ->values();
        } else {
            $summaryRows = collect($summary)
                ->flatMap(function (array $rows, $supplierId) {
                    return collect($rows)->map(function (array $row) use ($supplierId): array {
                        $row['supplier_id'] = (int) $supplierId;

                        return $row;
                    });
                })
                ->values();
        }
        if ($selectedProductId !== null) {
            $summaryRows = $summaryRows
                ->filter(fn(array $row): bool => (int) ($row['product_id'] ?? 0) === $selectedProductId)
                ->values();
        }
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $summaryRows = $summaryRows
                ->filter(function (array $row) use ($searchLower): bool {
                    return str_contains(mb_strtolower((string) ($row['product_code'] ?? '')), $searchLower)
                        || str_contains(mb_strtolower((string) ($row['product_name'] ?? '')), $searchLower);
                })
                ->values();
        }

        return [$movements, $summaryRows];
    }

    /**
     * @return array<string, mixed>
     */
    private function movementRow(
        int $supplierId,
        ?int $productId,
        string $productKey,
        string $productCode,
        string $productName,
        string $unit,
        string $eventDate,
        string $referenceNumber,
        int $referenceId,
        string $referenceRoute,
        string $description,
        int $qtyIn,
        int $qtyOut,
        int $balanceAfter,
        int $sequence
    ): array {
        return [
            'supplier_id' => $supplierId,
            'product_id' => $productId,
            'product_key' => $productKey,
            'product_code' => $productCode,
            'product_name' => $productName,
            'unit' => $unit,
            'event_date' => $eventDate,
            'reference_number' => $referenceNumber,
            'reference_id' => $referenceId,
            'reference_route' => $referenceRoute,
            'description' => $description,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'balance_after' => $balanceAfter,
            'sort_key' => sprintf('%s|%010d', $eventDate, $sequence),
        ];
    }

    private function resolveProductKey(?int $productId, string $productCode, string $productName): string
    {
        if ($productId !== null && $productId > 0) {
            return 'id:' . $productId;
        }

        $code = mb_strtolower(trim($productCode));
        $name = mb_strtolower(trim($productName));
        $base = trim($code . '|' . $name, '|');

        return $base !== '' ? 'manual:' . $base : '';
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $items
     */
    private function paginateCollection(
        $items,
        int $perPage,
        string $pageName,
        Request $request
    ): LengthAwarePaginator {
        $page = max(1, (int) $request->integer($pageName, 1));
        $total = $items->count();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values()->all(),
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
                'query' => $request->query(),
            ]
        );
    }

    private function emptyPaginator(Request $request, string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            (int) config('pagination.default_per_page', 20),
            max(1, (int) $request->integer($pageName, 1)),
            [
                'path' => $request->url(),
                'pageName' => $pageName,
                'query' => $request->query(),
            ]
        );
    }

    private function normalizeDate(string $raw): ?string
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }
}
