<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesProductUnits;
use App\Models\ItemCategory;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMutation;
use App\Services\AuditLogService;
use App\Support\AppCache;
use App\Support\ExcelExportStyler;
use App\Support\ProductCodeGenerator;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductPageController extends Controller
{
    use ResolvesProductUnits;

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ProductCodeGenerator $productCodeGenerator
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $products = Product::query()
            ->onlyListColumns()
            ->active()
            ->withCategoryInfo()
            ->searchKeyword($search)
            ->orderBy('name')
            ->paginate((int) config('pagination.master_per_page', 20))
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'search' => $search,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $search = trim((string) $request->string('search', ''));
        $printedAt = $this->nowWib();
        $filename = 'products-' . $printedAt->format('Ymd-His') . '.xlsx';

        $productQuery = Product::query()
            ->active()
            ->select(['id', 'code', 'name', 'stock'])
            ->searchKeyword($search)
            ->orderBy('id');

        $productCount = (clone $productQuery)->count();

        return response()->streamDownload(function () use ($productQuery, $productCount, $printedAt): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Barang');

            $sheet->setCellValue('A1', __('ui.products_title'));
            $sheet->setCellValue('A2', __('report.printed') . ': ' . $printedAt->format('d-m-Y H:i:s') . ' WIB');
            $sheet->setCellValue('A4', 'No');
            $sheet->setCellValue('B4', __('ui.code'));
            $sheet->setCellValue('C4', __('ui.name'));
            $sheet->setCellValue('D4', __('ui.stock'));

            $row = 5;
            $number = 1;
            $productQuery->chunkById(500, function ($products) use ($sheet, &$row, &$number): void {
                foreach ($products as $product) {
                    $sheet->setCellValue('A' . $row, $number++);
                    $sheet->setCellValue('B' . $row, (string) ($product->code ?: '-'));
                    $sheet->setCellValue('C' . $row, (string) $product->name);
                    $sheet->setCellValue('D' . $row, (int) round((float) $product->stock));
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

    private function nowWib(): Carbon
    {
        return now('Asia/Jakarta');
    }

    public function create(): View
    {
        return view('products.create', [
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'code', 'name']),
            'unitOptions' => $this->configuredProductUnitOptions(),
            'defaultUnit' => $this->defaultProductUnitCode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'code' => $this->productCodeGenerator->normalizeInput($request->input('code')),
            'unit' => $this->normalizeProductUnitInput($request->input('unit')),
        ]);

        $data = $this->validatePayload($request);
        $data['code'] = $this->productCodeGenerator->resolve($data['code'] ?? null, (string) $data['name']);
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
        $this->auditLogService->log('master.product.create', $product, "Product created: {$product->code}", $request);
        AppCache::forgetAfterFinancialMutation();

        return redirect()
            ->route('products.index')
            ->with('success', __('ui.product_created_success'));
    }

    public function edit(Request $request, Product $product): View
    {
        $stockMutations = $product->stockMutations()
            ->with('creator:id,name')
            ->latest('id')
            ->paginate((int) config('pagination.default_per_page', 20), ['*'], 'mutation_page')
            ->withQueryString();
        $mutationReferenceMap = $this->buildStockMutationReferenceMap($stockMutations);

        return view('products.edit', [
            'product' => $product,
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'code', 'name']),
            'unitOptions' => $this->configuredProductUnitOptions(),
            'defaultUnit' => $this->defaultProductUnitCode(),
            'stockMutations' => $stockMutations,
            'mutationReferenceMap' => $mutationReferenceMap,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $request->merge([
            'code' => $this->productCodeGenerator->normalizeInput($request->input('code')),
            'unit' => $this->normalizeProductUnitInput($request->input('unit')),
        ]);

        $data = $this->validatePayload($request, $product->id);
        $data['code'] = $this->productCodeGenerator->resolve($data['code'] ?? null, (string) $data['name'], $product->id);
        $data['is_active'] = true;

        DB::transaction(function () use (&$product, $data, $request): void {
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStock = (int) $lockedProduct->stock;
            $newStock = (int) ($data['stock'] ?? 0);
            $lockedProduct->update($data);

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

            $product = $lockedProduct;
        });
        $this->auditLogService->log('master.product.update', $product, "Product updated: {$product->code}", $request);
        AppCache::forgetAfterFinancialMutation();

        return redirect()
            ->route('products.index')
            ->with('success', __('ui.product_updated_success'));
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $code = $product->code;
        $product->delete();
        $this->auditLogService->log('master.product.delete', null, "Product deleted: {$code}", $request);
        AppCache::forgetAfterFinancialMutation();

        return redirect()
            ->route('products.index')
            ->with('success', __('ui.product_deleted_success'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'item_category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'code' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('products', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['required', 'string', 'max:30', Rule::in($this->configuredProductUnitCodes())],
            'stock' => ['required', 'integer', 'min:0'],
            'price_agent' => ['required', 'numeric', 'min:0'],
            'price_sales' => ['required', 'numeric', 'min:0'],
            'price_general' => ['required', 'numeric', 'min:0'],
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

}
