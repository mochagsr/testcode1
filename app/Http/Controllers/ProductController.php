<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Product;
use App\Support\ProductCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductCodeGenerator $productCodeGenerator
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 25), 1), 25);
        $search = trim((string) $request->string('search', ''));

        $products = Product::query()
            ->with('category:id,code,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($search): void {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('item_category_id'), function ($query) use ($request): void {
                $query->where('item_category_id', $request->integer('item_category_id'));
            })
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'code' => $this->productCodeGenerator->normalizeInput($request->input('code')),
            'unit' => $this->normalizeUnitInput($request->input('unit')),
        ]);

        $data = $request->validate([
            'item_category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'code' => ['nullable', 'string', 'max:60', 'unique:products,code'],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['required', 'string', 'max:30', Rule::in($this->configuredUnitCodes())],
            'stock' => ['nullable', 'integer', 'min:0'],
            'price_agent' => ['nullable', 'numeric', 'min:0'],
            'price_sales' => ['nullable', 'numeric', 'min:0'],
            'price_general' => ['nullable', 'numeric', 'min:0'],
        ], [
            'code.unique' => __('ui.product_code_unique_error'),
        ]);

        $data['code'] = $this->productCodeGenerator->resolve($data['code'] ?? null, (string) $data['name']);
        $data['is_active'] = true;
        $product = Product::create($data);

        return response()->json($product->load('category:id,code,name'), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load('category:id,code,name'));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $request->merge([
            'code' => $this->productCodeGenerator->normalizeInput($request->input('code')),
            'unit' => $this->normalizeUnitInput($request->input('unit')),
        ]);

        $data = $request->validate([
            'item_category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'code' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('products', 'code')->ignore($product->id),
            ],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['required', 'string', 'max:30', Rule::in($this->configuredUnitCodes())],
            'stock' => ['nullable', 'integer', 'min:0'],
            'price_agent' => ['nullable', 'numeric', 'min:0'],
            'price_sales' => ['nullable', 'numeric', 'min:0'],
            'price_general' => ['nullable', 'numeric', 'min:0'],
        ], [
            'code.unique' => __('ui.product_code_unique_error'),
        ]);

        $data['code'] = $this->productCodeGenerator->resolve($data['code'] ?? null, (string) $data['name'], $product->id);
        $data['is_active'] = true;
        $product->update($data);

        return response()->json($product->fresh()->load('category:id,code,name'));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(status: 204);
    }

    private function configuredUnitCodes(): array
    {
        $raw = (string) AppSetting::getValue('product_unit_options', 'exp|Exemplar');
        $codes = collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(function (string $item): string {
                [$code] = array_pad(array_map('trim', explode('|', $item, 2)), 2, '');

                return strtolower((string) preg_replace('/[^a-z0-9\-]/', '', $code));
            })
            ->filter(fn (string $code): bool => $code !== '')
            ->values();

        if (! $codes->contains('exp')) {
            $codes->prepend('exp');
        }
        return $codes->unique()->values()->all();
    }

    private function defaultUnitCode(): string
    {
        $default = strtolower((string) AppSetting::getValue('product_default_unit', 'exp'));

        return $default !== '' ? $default : 'exp';
    }

    private function normalizeUnitInput(mixed $unit): string
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', (string) $unit));

        return $normalized !== '' ? $normalized : $this->defaultUnitCode();
    }

}
