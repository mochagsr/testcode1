<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Support\ProductCodeGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProductPageController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ProductCodeGenerator $productCodeGenerator
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $products = Product::query()
            ->with('category:id,code,name')
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

    public function exportCsv(Request $request): Response
    {
        $search = trim((string) $request->string('search', ''));
        $filename = 'products-'.now()->format('Ymd-His').'.xls';

        $products = Product::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get(['code', 'name', 'stock']);

        $html = view('products.export_excel', [
            'products' => $products,
            'printedAt' => now(),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'code', 'name']),
            'unitOptions' => $this->configuredUnitOptions(),
            'defaultUnit' => $this->defaultUnitCode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'code' => $this->productCodeGenerator->normalizeInput($request->input('code')),
            'unit' => $this->normalizeUnitInput($request->input('unit')),
        ]);

        $data = $this->validatePayload($request);
        $data['code'] = $this->productCodeGenerator->resolve($data['code'] ?? null, (string) $data['name']);
        $data['is_active'] = true;
        $product = Product::create($data);
        $this->auditLogService->log('master.product.create', $product, "Product created: {$product->code}", $request);

        return redirect()
            ->route('products.index')
            ->with('success', __('ui.product_created_success'));
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product,
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'code', 'name']),
            'unitOptions' => $this->configuredUnitOptions(),
            'defaultUnit' => $this->defaultUnitCode(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $request->merge([
            'code' => $this->productCodeGenerator->normalizeInput($request->input('code')),
            'unit' => $this->normalizeUnitInput($request->input('unit')),
        ]);

        $data = $this->validatePayload($request, $product->id);
        $data['code'] = $this->productCodeGenerator->resolve($data['code'] ?? null, (string) $data['name'], $product->id);
        $data['is_active'] = true;
        $product->update($data);
        $this->auditLogService->log('master.product.update', $product, "Product updated: {$product->code}", $request);

        return redirect()
            ->route('products.index')
            ->with('success', __('ui.product_updated_success'));
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $code = $product->code;
        $product->delete();
        $this->auditLogService->log('master.product.delete', null, "Product deleted: {$code}", $request);

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
            'unit' => ['required', 'string', 'max:30', Rule::in($this->configuredUnitCodes())],
            'stock' => ['required', 'integer', 'min:0'],
            'price_agent' => ['required', 'numeric', 'min:0'],
            'price_sales' => ['required', 'numeric', 'min:0'],
            'price_general' => ['required', 'numeric', 'min:0'],
        ], [
            'code.unique' => __('ui.product_code_unique_error'),
        ]);
    }

    private function configuredUnitOptions(): array
    {
        $raw = (string) AppSetting::getValue('product_unit_options', 'exp|Exemplar');
        $options = collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(function (string $item): array {
                [$code, $label] = array_pad(array_map('trim', explode('|', $item, 2)), 2, '');
                $normalizedCode = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', $code));
                $normalizedLabel = $label !== '' ? $label : ucfirst($normalizedCode);

                return [
                    'code' => $normalizedCode,
                    'label' => $normalizedLabel,
                ];
            })
            ->filter(fn (array $item): bool => $item['code'] !== '')
            ->unique('code')
            ->values();

        $withoutExp = $options->filter(fn (array $item): bool => $item['code'] !== 'exp')->values();

        $withDefault = collect([[
            'code' => 'exp',
            'label' => 'Exemplar',
        ]])->merge($withoutExp);

        return $withDefault->values()->all();
    }

    private function defaultUnitCode(): string
    {
        $default = strtolower((string) AppSetting::getValue('product_default_unit', 'exp'));

        return $default !== '' ? $default : 'exp';
    }

    private function configuredUnitCodes(): array
    {
        return collect($this->configuredUnitOptions())
            ->pluck('code')
            ->filter(fn (string $code): bool => $code !== '')
            ->values()
            ->all();
    }

    private function normalizeUnitInput(mixed $unit): string
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', (string) $unit));

        return $normalized !== '' ? $normalized : $this->defaultUnitCode();
    }

}
