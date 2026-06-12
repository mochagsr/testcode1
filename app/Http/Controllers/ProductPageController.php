<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesProductUnits;
use App\Models\AppSetting;
use App\Models\DeliveryNote;
use App\Models\ItemCategory;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMutation;
use App\Services\AuditLogService;
use App\Support\AppCache;
use App\Support\ExcelExportStyler;
use App\Support\ProductCodeGenerator;
use App\Support\ProductDeletionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SanderMuller\FluentValidation\FluentRule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductPageController extends Controller
{
    use ResolvesProductUnits;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ProductCodeGenerator $productCodeGenerator,
        private readonly ProductDeletionService $productDeletionService
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $productType = $this->resolveProductType($request);

        $allowedSorts = ['name', 'category', 'stock'];
        $sort = in_array((string) $request->string('sort', ''), $allowedSorts, true)
            ? (string) $request->string('sort', '')
            : '';
        $direction = strtolower((string) $request->string('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $products = Product::query()
            ->onlyListColumns()
            ->active()
            ->withCategoryInfo()
            ->searchKeyword($search)
            ->when($productType === 'raw_material', fn (Builder $query): Builder => $query->supplierSourced())
            ->when($productType === 'general', fn (Builder $query): Builder => $query->generalStock())
            ->when($sort === 'category', function ($query) use ($direction): void {
                $query->leftJoin('item_categories', 'products.item_category_id', '=', 'item_categories.id')
                    ->select([
                        'products.id', 'products.item_category_id', 'products.code',
                        'products.name', 'products.unit', 'products.stock',
                        'products.price_agent', 'products.price_sales', 'products.price_general',
                        'products.is_active', 'products.product_type',
                    ])
                    ->orderBy('item_categories.name', $direction)
                    ->orderBy('products.id', 'desc');
            })
            ->when($sort === 'name', fn ($q) => $q->orderBy('name', $direction)->orderBy('id', 'desc'))
            ->when($sort === 'stock', fn ($q) => $q->orderBy('stock', $direction)->orderBy('id', 'desc'))
            ->when($sort === '', fn ($q) => $q->orderByDesc('id'))
            ->paginate((int) config('pagination.master_per_page', 20))
            ->withQueryString();

        $viewData = [
            'products' => $products,
            'search' => $search,
            'productType' => $productType,
            'productTypeOptions' => $this->productTypeOptions(),
            'sort' => $sort,
            'direction' => $direction,
        ];

        if ($request->ajax()) {
            return view('products.partials.results', $viewData);
        }

        return view('products.index', $viewData);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $search = trim((string) $request->string('search', ''));
        $productType = $this->resolveProductType($request);
        $printedAt = $this->nowWib();
        $filename = 'products-'.$printedAt->format('Ymd-His').'.xlsx';

        $productQuery = $this->reportProductQuery($search, $productType);

        $productCount = (clone $productQuery)->count();

        return response()->streamDownload(function () use ($productQuery, $productCount, $printedAt): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Barang');

            $sheet->setCellValue('A1', __('ui.products_title'));
            $sheet->setCellValue('A2', __('report.printed').': '.$printedAt->format('d-m-Y H:i:s').' WIB');
            $sheet->setCellValue('A4', 'No');
            $sheet->setCellValue('B4', __('ui.code'));
            $sheet->setCellValue('C4', __('ui.name'));
            $sheet->setCellValue('D4', __('ui.stock'));

            $row = 5;
            $number = 1;
            $productQuery->chunkById(500, function ($products) use ($sheet, &$row, &$number): void {
                foreach ($products as $product) {
                    $sheet->setCellValue('A'.$row, $number++);
                    $sheet->setCellValue('B'.$row, (string) ($product->code ?: '-'));
                    $sheet->setCellValue('C'.$row, (string) $product->name);
                    $sheet->setCellValue('D'.$row, (int) round((float) $product->stock));
                    $row++;
                }
            }, 'id', 'id');

            $itemCount = $productCount;
            ExcelExportStyler::styleTable($sheet, 4, 4, $itemCount, true);
            if ($itemCount > 0) {
                ExcelExportStyler::formatNumberColumns($sheet, 5, 4 + $itemCount, [1, 4], '#,##0');
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function printReport(Request $request): View
    {
        return view('products.report', $this->reportViewData($request));
    }

    public function exportPdf(Request $request)
    {
        $data = $this->reportViewData($request) + ['isPdf' => true];
        $pdf = Pdf::loadView('products.report', $data)->setPaper('a4', 'portrait');

        return $pdf->download('products-'.$data['printedAt']->format('Ymd-His').'.pdf');
    }

    public function show(Product $product): View
    {
        $product->load('category:id,code,name');
        $supplierRows = $this->supplierRowsForProduct($product);

        return view('products.show', [
            'product' => $product,
            'supplierRows' => $supplierRows,
            'initialStock' => $this->initialStockForProduct($product),
            'showSupplierSection' => $supplierRows->isNotEmpty(),
        ]);
    }

    private function initialStockForProduct(Product $product): int
    {
        $initialStockNotes = array_values(array_unique([
            __('ui.stock_mutation_initial_stock'),
            trans('ui.stock_mutation_initial_stock', [], 'id'),
            trans('ui.stock_mutation_initial_stock', [], 'en'),
        ]));

        return (int) StockMutation::query()
            ->where('product_id', (int) $product->id)
            ->where('reference_type', Product::class)
            ->where('reference_id', (int) $product->id)
            ->where('mutation_type', 'in')
            ->whereIn('notes', $initialStockNotes)
            ->sum('quantity');
    }

    private function nowWib(): Carbon
    {
        return now('Asia/Jakarta');
    }

    /**
     * @return Collection<int, array{
     *     supplier_id:int,
     *     supplier_name:string,
     *     supplier_company_name:string,
     *     total_quantity:int,
     *     transaction_count:int,
     *     last_transaction_date:string|null,
     *     last_unit_cost:int,
     *     last_unit:string,
     *     last_transaction_id:int,
     *     last_transaction_number:string
     * }>
     */
    private function supplierRowsForProduct(Product $product): Collection
    {
        $summaryRows = DB::table('outgoing_transaction_items as oti')
            ->join('outgoing_transactions as ot', 'ot.id', '=', 'oti.outgoing_transaction_id')
            ->join('suppliers as s', 's.id', '=', 'ot.supplier_id')
            ->where('oti.product_id', (int) $product->id)
            ->whereNull('ot.deleted_at')
            ->select([
                's.id as supplier_id',
                's.name as supplier_name',
                's.company_name as supplier_company_name',
            ])
            ->selectRaw('COALESCE(SUM(oti.quantity), 0) as total_quantity')
            ->selectRaw('COUNT(DISTINCT ot.id) as transaction_count')
            ->selectRaw('MAX(ot.transaction_date) as last_transaction_date')
            ->groupBy('s.id', 's.name', 's.company_name')
            ->orderByDesc('last_transaction_date')
            ->orderBy('s.name')
            ->get();

        $supplierIds = $summaryRows
            ->pluck('supplier_id')
            ->map(fn ($supplierId): int => (int) $supplierId)
            ->filter(fn (int $supplierId): bool => $supplierId > 0)
            ->values();

        $latestRows = $supplierIds->isEmpty()
            ? collect()
            : DB::table('outgoing_transaction_items as oti')
                ->join('outgoing_transactions as ot', 'ot.id', '=', 'oti.outgoing_transaction_id')
                ->where('oti.product_id', (int) $product->id)
                ->whereIn('ot.supplier_id', $supplierIds->all())
                ->whereNull('ot.deleted_at')
                ->select([
                    'ot.supplier_id',
                    'ot.id as transaction_id',
                    'ot.transaction_number',
                    'ot.transaction_date',
                    'oti.unit',
                    'oti.unit_cost',
                ])
                ->orderByDesc('ot.transaction_date')
                ->orderByDesc('ot.id')
                ->orderByDesc('oti.id')
                ->get()
                ->unique('supplier_id')
                ->keyBy('supplier_id');

        return $summaryRows
            ->map(function ($row) use ($latestRows): array {
                $supplierId = (int) ($row->supplier_id ?? 0);
                $latest = $latestRows->get($supplierId);

                return [
                    'supplier_id' => $supplierId,
                    'supplier_name' => (string) ($row->supplier_name ?? '-'),
                    'supplier_company_name' => (string) ($row->supplier_company_name ?? ''),
                    'total_quantity' => (int) round((float) ($row->total_quantity ?? 0)),
                    'transaction_count' => (int) ($row->transaction_count ?? 0),
                    'last_transaction_date' => $latest !== null ? (string) ($latest->transaction_date ?? '') : (string) ($row->last_transaction_date ?? ''),
                    'last_unit_cost' => $latest !== null ? (int) round((float) ($latest->unit_cost ?? 0)) : 0,
                    'last_unit' => $latest !== null ? (string) ($latest->unit ?? '') : '',
                    'last_transaction_id' => $latest !== null ? (int) ($latest->transaction_id ?? 0) : 0,
                    'last_transaction_number' => $latest !== null ? (string) ($latest->transaction_number ?? '') : '',
                ];
            })
            ->values();
    }

    /**
     * @return Builder<Product>
     */
    private function reportProductQuery(string $search, string $productType)
    {
        return Product::query()
            ->active()
            ->withCategoryInfo()
            ->select(['id', 'item_category_id', 'code', 'name', 'stock'])
            ->searchKeyword($search)
            ->when($productType === 'raw_material', fn (Builder $query): Builder => $query->supplierSourced())
            ->when($productType === 'general', fn (Builder $query): Builder => $query->generalStock())
            ->orderBy('id');
    }

    /**
     * @return array{products:Collection<int,Product>,printedAt:Carbon,settings:array<string,string>,search:string,productType:string,productTypeLabel:string}
     */
    private function reportViewData(Request $request): array
    {
        $search = trim((string) $request->string('search', ''));
        $productType = $this->resolveProductType($request);
        $printedAt = $this->nowWib();
        $products = $this->reportProductQuery($search, $productType)->get();
        $settings = AppSetting::getValues([
            'company_name' => '',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
        ]);

        return [
            'products' => $products,
            'printedAt' => $printedAt,
            'settings' => $settings,
            'search' => $search,
            'productType' => $productType,
            'productTypeLabel' => $this->productTypeOptions()[$productType] ?? $this->productTypeOptions()['general'],
        ];
    }

    private function resolveProductType(Request $request): string
    {
        $type = trim((string) $request->string('product_type', 'general'));

        return array_key_exists($type, $this->productTypeOptions()) ? $type : 'general';
    }

    /**
     * @return array{general:string,raw_material:string}
     */
    private function productTypeOptions(): array
    {
        return [
            'general' => __('ui.product_type_general'),
            'raw_material' => __('ui.product_type_raw_material'),
        ];
    }

    public function create(): View
    {
        return view('products.create', [
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'code', 'name']),
            'unitOptions' => $this->configuredProductUnitOptions(),
            'defaultUnit' => $this->defaultProductUnitCode(),
            'productTypeOptions' => $this->productTypeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'code' => $this->productCodeGenerator->normalizeInput($request->input('code')),
            'unit' => $this->normalizeProductUnitInput($request->input('unit')),
        ]);

        $data = $this->validatePayload($request);
        $data['unit'] = ProductUnit::ensureExists((string) ($data['unit'] ?? $this->defaultProductUnitCode()))->code;
        $categoryName = ItemCategory::query()
            ->whereKey((int) ($data['item_category_id'] ?? 0))
            ->value('name');
        $data['code'] = $this->productCodeGenerator->resolve(
            $data['code'] ?? null,
            (string) $data['name'],
            null,
            $categoryName ? (string) $categoryName : null
        );
        $data['is_active'] = true;
        $product = Product::create($data);
        if ((int) $product->stock > 0) {
            StockMutation::query()->create([
                'product_id' => (int) $product->id,
                'reference_type' => Product::class,
                'reference_id' => (int) $product->id,
                'mutation_type' => 'in',
                'quantity' => (int) $product->stock,
                'notes' => __('ui.stock_mutation_initial_stock'),
                'created_by_user_id' => $request->user()?->id,
            ]);
        }
        $this->auditLogService->log(
            'master.product.create',
            $product,
            __('ui.audit_desc_product_created_short', ['code' => (string) ($product->code ?? '-')]),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return redirect()
            ->route('products.index')
            ->with('success', __('ui.product_created_success'));
    }

    public function edit(Request $request, Product $product): View
    {
        [$stockMutations, $mutationReferenceMap] = $this->loadStockMutations($product);

        return view('products.edit', [
            'product' => $product,
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'code', 'name']),
            'unitOptions' => $this->configuredProductUnitOptions(),
            'defaultUnit' => $this->defaultProductUnitCode(),
            'productTypeOptions' => $this->productTypeOptions(),
            'stockMutations' => $stockMutations,
            'mutationReferenceMap' => $mutationReferenceMap,
        ]);
    }

    public function mutations(Request $request, Product $product): View
    {
        [$stockMutations, $mutationReferenceMap] = $this->loadStockMutations($product);

        return view('products.mutations', [
            'product' => $product,
            'stockMutations' => $stockMutations,
            'mutationReferenceMap' => $mutationReferenceMap,
            'initialStock' => $this->initialStockForProduct($product),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $request->merge([
            'code' => $this->productCodeGenerator->normalizeInput($request->input('code')),
            'unit' => $this->normalizeProductUnitInput($request->input('unit')),
        ]);

        $data = $this->validatePayload($request, $product->id);
        $data['unit'] = ProductUnit::ensureExists((string) ($data['unit'] ?? $this->defaultProductUnitCode()))->code;
        $categoryName = ItemCategory::query()
            ->whereKey((int) ($data['item_category_id'] ?? 0))
            ->value('name');
        $data['code'] = $this->productCodeGenerator->resolve(
            $data['code'] ?? null,
            (string) $data['name'],
            $product->id,
            $categoryName ? (string) $categoryName : null
        );
        $data['is_active'] = true;
        $flashMeta = [
            'type' => 'edit',
            'message' => __('ui.product_updated_success'),
        ];

        DB::transaction(function () use (&$product, $data, $request, &$flashMeta): void {
            $lockedProduct = Product::query()
                ->with('category:id,code,name')
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldSnapshot = [
                'name' => (string) $lockedProduct->name,
                'unit' => (string) $lockedProduct->unit,
                'item_category_id' => (int) $lockedProduct->item_category_id,
                'price_agent' => (int) round((float) $lockedProduct->price_agent),
                'price_sales' => (int) round((float) $lockedProduct->price_sales),
                'price_general' => (int) round((float) $lockedProduct->price_general),
            ];
            $oldStock = (int) $lockedProduct->stock;
            $newStock = (int) ($data['stock'] ?? 0);
            $lockedProduct->update($data);
            $lockedProduct->refresh();
            $lockedProduct->load('category:id,code,name');

            if ($newStock !== $oldStock) {
                $delta = $newStock - $oldStock;
                StockMutation::query()->create([
                    'product_id' => (int) $lockedProduct->id,
                    'reference_type' => Product::class,
                    'reference_id' => (int) $lockedProduct->id,
                    'mutation_type' => $delta > 0 ? 'in' : 'out',
                    'quantity' => abs($delta),
                    'notes' => $delta > 0
                        ? __('ui.stock_mutation_manual_add_note')
                        : __('ui.stock_mutation_manual_reduce_note'),
                    'created_by_user_id' => $request->user()?->id,
                ]);
            }

            $flashMeta = $this->buildProductChangeFlashMeta($lockedProduct, $oldSnapshot, $oldStock, $newStock);
            $product = $lockedProduct;
        });
        $this->auditLogService->log(
            'master.product.update',
            $product,
            __('ui.audit_desc_product_updated_short', ['code' => (string) ($product->code ?? '-')]),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return redirect()
            ->route('products.index')
            ->with('success', (string) ($flashMeta['message'] ?? __('ui.product_updated_success')))
            ->with('success_type', (string) ($flashMeta['type'] ?? 'edit'));
    }

    public function quickUpdateStock(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'stock' => FluentRule::integer()->required()->min(0),
        ]);
        $flashMeta = [
            'type' => 'edit',
            'message' => __('ui.product_stock_updated_success', ['product' => $product->name]),
        ];

        DB::transaction(function () use ($request, $product, $data, &$flashMeta): void {
            $lockedProduct = Product::query()
                ->with('category:id,code,name')
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStock = (int) $lockedProduct->stock;
            $newStock = (int) $data['stock'];
            if ($newStock === $oldStock) {
                $flashMeta = $this->buildProductStockFlashMeta($lockedProduct, $oldStock, $newStock);

                return;
            }

            $lockedProduct->update([
                'stock' => $newStock,
            ]);

            $delta = $newStock - $oldStock;
            StockMutation::query()->create([
                'product_id' => (int) $lockedProduct->id,
                'reference_type' => Product::class,
                'reference_id' => (int) $lockedProduct->id,
                'mutation_type' => $delta > 0 ? 'in' : 'out',
                'quantity' => abs($delta),
                'notes' => $delta > 0
                    ? __('ui.stock_mutation_manual_add_note')
                    : __('ui.stock_mutation_manual_reduce_note'),
                'created_by_user_id' => $request->user()?->id,
            ]);

            $this->auditLogService->log(
                'master.product.quick_stock_update',
                $lockedProduct,
                __('ui.audit_desc_product_quick_stock_update', [
                    'code' => (string) ($lockedProduct->code ?? '-'),
                    'from' => (int) $oldStock,
                    'to' => (int) $newStock,
                ]),
                $request,
                ['stock' => $oldStock],
                ['stock' => $newStock]
            );
            $lockedProduct->refresh();
            $lockedProduct->load('category:id,code,name');
            $flashMeta = $this->buildProductStockFlashMeta($lockedProduct, $oldStock, $newStock);
        });

        AppCache::forgetAfterFinancialMutation();

        if ($request->expectsJson()) {
            $fresh = $product->fresh(['category']);

            return response()->json([
                'ok' => true,
                'product' => [
                    'id' => (int) $fresh->id,
                    'name' => (string) $fresh->name,
                    'code' => (string) ($fresh->code ?? ''),
                ],
                'stock' => (int) $fresh->stock,
                'alert' => (int) $fresh->stock <= 0 ? 'low' : 'ok',
                'message' => (string) ($flashMeta['message'] ?? __('ui.product_stock_updated_success', ['product' => $fresh->name])),
                'message_type' => (string) ($flashMeta['type'] ?? 'edit'),
            ]);
        }

        return redirect()
            ->route('products.index')
            ->with('success', (string) ($flashMeta['message'] ?? __('ui.product_stock_updated_success', ['product' => $product->name])))
            ->with('success_type', (string) ($flashMeta['type'] ?? 'edit'));
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $result = $this->productDeletionService->deleteOrDeactivate($product);
        $code = (string) ($result['code'] ?? '-');
        $status = (string) ($result['status'] ?? 'deleted');
        $targetProduct = $result['product'] instanceof Product ? $result['product'] : null;

        $this->auditLogService->log(
            $status === 'deactivated' ? 'master.product.update' : 'master.product.delete',
            $targetProduct,
            $status === 'deactivated'
                ? __('ui.audit_desc_product_deactivated_short', ['code' => $code])
                : __('ui.audit_desc_product_deleted_short', ['code' => $code]),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return redirect()
            ->route('products.index')
            ->with('success', $status === 'deactivated'
                ? __('ui.product_deactivated_success')
                : __('ui.product_deleted_success'))
            ->with('success_type', 'decrease');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => FluentRule::array()->required()->min(1),
            'product_ids.*' => FluentRule::integer()->required(),
        ]);

        $productIds = collect($data['product_ids'])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $deleted = 0;
        $deactivated = 0;
        $codes = [];

        foreach ($productIds as $productId) {
            $product = Product::query()->find($productId);
            if ($product === null) {
                continue;
            }

            $result = $this->productDeletionService->deleteOrDeactivate($product);
            $status = (string) ($result['status'] ?? 'deleted');
            $code = (string) ($result['code'] ?? '-');
            $codes[] = $code;

            if ($status === 'deactivated') {
                $deactivated++;
            } else {
                $deleted++;
            }
        }

        $this->auditLogService->log(
            'master.product.bulk_delete',
            null,
            __('ui.audit_desc_product_bulk_deleted', [
                'deleted' => $deleted,
                'deactivated' => $deactivated,
                'codes' => implode(', ', $codes),
            ]),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return redirect()
            ->route('products.index')
            ->with('success', __('ui.bulk_delete_products_result', [
                'deleted' => $deleted,
                'deactivated' => $deactivated,
            ]))
            ->with('success_type', 'decrease');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'item_category_id' => FluentRule::integer()->required()->exists('item_categories', 'id'),
            'code' => FluentRule::string()->nullable()->max(60)->unique('products', 'code', fn ($rule) => $rule->ignore($ignoreId)),
            'name' => FluentRule::string()->required()->max(200),
            'unit' => FluentRule::string()->required()->max(30),
            'product_type' => FluentRule::string()->required()->in(['general', 'raw_material']),
            'stock' => FluentRule::integer()->required()->min(0),
            'price_agent' => $request->input('product_type') === 'raw_material' ? FluentRule::numeric()->nullable()->min(0) : FluentRule::numeric()->required()->min(0),
            'price_sales' => $request->input('product_type') === 'raw_material' ? FluentRule::numeric()->nullable()->min(0) : FluentRule::numeric()->required()->min(0),
            'price_general' => $request->input('product_type') === 'raw_material' ? FluentRule::numeric()->nullable()->min(0) : FluentRule::numeric()->required()->min(0),
        ], [
            'code.unique' => __('ui.product_code_unique_error'),
        ]);
    }

    /**
     * @return array<string, array{number:string, url:string}>
     */
    private function buildStockMutationReferenceMap(LengthAwarePaginator $paginator): array
    {
        /** @var Collection<int, StockMutation> $mutations */
        $mutations = collect($paginator->items());
        $idsByType = [
            DeliveryNote::class => [],
            SalesInvoice::class => [],
            SalesReturn::class => [],
            OutgoingTransaction::class => [],
        ];

        foreach ($mutations as $mutation) {
            $type = (string) ($mutation->reference_type ?? '');
            $referenceId = (int) ($mutation->reference_id ?? 0);
            if ($referenceId <= 0 || ! array_key_exists($type, $idsByType)) {
                continue;
            }
            $idsByType[$type][] = $referenceId;
        }

        $map = [];
        $salesInvoices = collect();
        $deliveryNotes = collect();
        if ($idsByType[DeliveryNote::class] !== []) {
            $deliveryNotes = DeliveryNote::query()
                ->select(['id', 'note_number'])
                ->whereIn('id', array_values(array_unique($idsByType[DeliveryNote::class])))
                ->get()
                ->keyBy('id');
        }

        if ($idsByType[SalesInvoice::class] !== []) {
            $salesInvoices = SalesInvoice::query()
                ->select(['id', 'invoice_number'])
                ->whereIn('id', array_values(array_unique($idsByType[SalesInvoice::class])))
                ->get()
                ->keyBy('id');
        }

        $salesReturns = collect();
        if ($idsByType[SalesReturn::class] !== []) {
            $salesReturns = SalesReturn::query()
                ->select(['id', 'return_number'])
                ->whereIn('id', array_values(array_unique($idsByType[SalesReturn::class])))
                ->get()
                ->keyBy('id');
        }

        $outgoingTransactions = collect();
        if ($idsByType[OutgoingTransaction::class] !== []) {
            $outgoingTransactions = OutgoingTransaction::query()
                ->select(['id', 'transaction_number'])
                ->whereIn('id', array_values(array_unique($idsByType[OutgoingTransaction::class])))
                ->get()
                ->keyBy('id');
        }

        foreach ($deliveryNotes as $deliveryNote) {
            $map[DeliveryNote::class.'#'.(int) $deliveryNote->id] = [
                'number' => (string) $deliveryNote->note_number,
                'url' => route('delivery-notes.show', $deliveryNote),
            ];
        }
        foreach ($salesInvoices as $invoice) {
            $map[SalesInvoice::class.'#'.(int) $invoice->id] = [
                'number' => (string) $invoice->invoice_number,
                'url' => route('sales-invoices.show', $invoice),
            ];
        }
        foreach ($salesReturns as $salesReturn) {
            $map[SalesReturn::class.'#'.(int) $salesReturn->id] = [
                'number' => (string) $salesReturn->return_number,
                'url' => route('sales-returns.show', $salesReturn),
            ];
        }
        foreach ($outgoingTransactions as $transaction) {
            $map[OutgoingTransaction::class.'#'.(int) $transaction->id] = [
                'number' => (string) $transaction->transaction_number,
                'url' => route('outgoing-transactions.show', $transaction),
            ];
        }

        return $map;
    }

    /**
     * @return array{0:LengthAwarePaginator,1:array<string, array{number:string, url:string}>}
     */
    private function loadStockMutations(Product $product): array
    {
        $stockMutations = $product->stockMutations()
            ->with('creator:id,name')
            ->latest('id')
            ->paginate((int) config('pagination.default_per_page', 20), ['*'], 'mutation_page')
            ->withQueryString();
        $mutationReferenceMap = $this->buildStockMutationReferenceMap($stockMutations);

        return [$stockMutations, $mutationReferenceMap];
    }

    /**
     * @param array{
     *     name:string,
     *     unit:string,
     *     item_category_id:int,
     *     price_agent:int,
     *     price_sales:int,
     *     price_general:int
     * } $oldSnapshot
     * @return array{type:string,message:string}
     */
    private function buildProductChangeFlashMeta(Product $product, array $oldSnapshot, int $oldStock, int $newStock): array
    {
        $category = $this->productCategoryLabel($product);
        $code = trim((string) ($product->code ?? '')) !== '' ? (string) $product->code : '-';
        $name = (string) $product->name;
        $stockText = number_format($newStock, 0, ',', '.');
        $delta = $newStock - $oldStock;
        $priceTail = $this->buildPriceChangeTail($product, $oldSnapshot);

        if ($delta > 0) {
            return [
                'type' => 'increase',
                'message' => __('ui.stock_change_increase_message', [
                    'code' => $code,
                    'category' => $category,
                    'name' => $name,
                    'stock' => $stockText,
                    'delta' => number_format($delta, 0, ',', '.'),
                    'price_tail' => $priceTail,
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
                    'price_tail' => $priceTail,
                ]),
            ];
        }

        $changedFields = [];
        if (($oldSnapshot['name'] ?? '') !== (string) $product->name) {
            $changedFields[] = __('ui.name');
        }
        if ((int) ($oldSnapshot['item_category_id'] ?? 0) !== (int) $product->item_category_id) {
            $changedFields[] = __('ui.category');
        }
        if (($oldSnapshot['unit'] ?? '') !== (string) $product->unit) {
            $changedFields[] = __('ui.unit');
        }

        $changes = ! empty($changedFields)
            ? __('ui.stock_change_fields_segment', ['fields' => implode(', ', $changedFields)])
            : __('ui.stock_change_fields_none');

        return [
            'type' => 'edit',
            'message' => __('ui.stock_change_edit_message', [
                'code' => $code,
                'category' => $category,
                'name' => $name,
                'changes' => $changes,
                'price_tail' => $priceTail,
            ]),
        ];
    }

    /**
     * @param array{
     *     price_agent:int,
     *     price_sales:int,
     *     price_general:int
     * } $oldSnapshot
     */
    private function buildPriceChangeTail(Product $product, array $oldSnapshot): string
    {
        $parts = [];
        $priceAgent = (int) round((float) $product->price_agent);
        $priceSales = (int) round((float) $product->price_sales);
        $priceGeneral = (int) round((float) $product->price_general);

        if ((int) ($oldSnapshot['price_agent'] ?? 0) !== $priceAgent) {
            $parts[] = __('ui.stock_change_price_agent_segment', [
                'value' => number_format($priceAgent, 0, ',', '.'),
            ]);
        }
        if ((int) ($oldSnapshot['price_sales'] ?? 0) !== $priceSales) {
            $parts[] = __('ui.stock_change_price_sales_segment', [
                'value' => number_format($priceSales, 0, ',', '.'),
            ]);
        }
        if ((int) ($oldSnapshot['price_general'] ?? 0) !== $priceGeneral) {
            $parts[] = __('ui.stock_change_price_general_segment', [
                'value' => number_format($priceGeneral, 0, ',', '.'),
            ]);
        }

        if ($parts === []) {
            return '';
        }

        return ' '.__('ui.stock_change_price_changes_segment', [
            'segments' => implode(' | ', $parts),
        ]);
    }

    /**
     * @return array{type:string,message:string}
     */
    private function buildProductStockFlashMeta(Product $product, int $oldStock, int $newStock): array
    {
        return $this->buildProductChangeFlashMeta($product, [
            'name' => (string) $product->name,
            'unit' => (string) $product->unit,
            'item_category_id' => (int) $product->item_category_id,
            'price_agent' => (int) round((float) $product->price_agent),
            'price_sales' => (int) round((float) $product->price_sales),
            'price_general' => (int) round((float) $product->price_general),
        ], $oldStock, $newStock);
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
            if (strcasecmp($code, $name) === 0) {
                return $name;
            }

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
}
