<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesProductUnits;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Support\AppCache;
use App\Support\ExcelExportStyler;
use App\Support\ProductCodeGenerator;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->select([
                'id',
                'item_category_id',
                'code',
                'name',
                'stock',
                'price_agent',
                'price_sales',
                'price_general',
                'is_active',
            ])
            ->active()
            ->withCategoryInfo()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($search): void {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->paginate(20)
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
            ->select(['id', 'code', 'name', 'stock'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
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
        $this->auditLogService->log('master.product.create', $product, "Product created: {$product->code}", $request);
        AppCache::forgetAfterFinancialMutation();
        AppCache::bumpLookupVersion();

        return redirect()
            ->route('products.index')
            ->with('success', __('ui.product_created_success'));
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product,
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'code', 'name']),
            'unitOptions' => $this->configuredProductUnitOptions(),
            'defaultUnit' => $this->defaultProductUnitCode(),
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
        $product->update($data);
        $this->auditLogService->log('master.product.update', $product, "Product updated: {$product->code}", $request);
        AppCache::forgetAfterFinancialMutation();
        AppCache::bumpLookupVersion();

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
        AppCache::bumpLookupVersion();

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

}
