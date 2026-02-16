<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesProductUnits;
use App\Models\Product;
use App\Support\AppCache;
use App\Support\ProductCodeGenerator;
use App\Support\ValidatesSearchTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use ResolvesProductUnits;
    use ValidatesSearchTokens;

    public function __construct(
        private readonly ProductCodeGenerator $productCodeGenerator
    ) {}

    /**
     * Retrieve paginated list of products with optional filtering and search.
     *
     * @param  Request  $request The HTTP request containing query parameters
     * @return JsonResponse The JSON response with products
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 25);
        $page = max(1, (int) $request->integer('page', 1));
        $search = trim((string) $request->string('search', ''));
        $hasSearch = $search !== '';
        $activeOnly = $request->boolean('active_only');
        $itemCategoryId = $request->filled('item_category_id') ? (int) $request->integer('item_category_id') : null;

        if ($hasSearch && ! $this->hasValidSearchTokens($search)) {
            return response()->json([
                'data' => [],
                'current_page' => $page,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
            ]);
        }

        $productsQuery = Product::query()
            ->select([
                'id',
                'item_category_id',
                'code',
                'name',
                'unit',
                'stock',
                'price_agent',
                'price_sales',
                'price_general',
                'is_active',
            ])
            ->with('category:id,code,name')
            ->when($activeOnly, function ($query): void {
                $query->where('is_active', true);
            })
            ->when($hasSearch, function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($search): void {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($itemCategoryId !== null, function ($query) use ($itemCategoryId): void {
                $query->where('item_category_id', $itemCategoryId);
            })
            ->orderBy('name')
            ->orderBy('id');

        $cacheKey = AppCache::lookupCacheKey('lookups.products', [
            'per_page' => $perPage,
            'page' => $page,
            'search' => mb_strtolower($search),
            'active_only' => $activeOnly ? 1 : 0,
            'item_category_id' => $itemCategoryId ?? 0,
        ]);
        $products = Cache::remember($cacheKey, now()->addSeconds(20), function () use ($productsQuery, $perPage) {
            return $productsQuery->paginate($perPage)->toArray();
        });

        return response()->json($products);
    }

    /**
     * Create a new product.
     *
     * @param  Request  $request The HTTP request with product data
     * @return JsonResponse The JSON response with created product
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'code' => $this->productCodeGenerator->normalizeInput($request->input('code')),
            'unit' => $this->normalizeProductUnitInput($request->input('unit')),
        ]);

        $data = $request->validate([
            'item_category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'code' => ['nullable', 'string', 'max:60', 'unique:products,code'],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['required', 'string', 'max:30', Rule::in($this->configuredProductUnitCodes())],
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
        AppCache::bumpLookupVersion();

        return response()->json($product->load('category:id,code,name'), 201);
    }

    /**
     * Retrieve a specific product by ID.
     *
     * @param  Product  $product The product instance
     * @return JsonResponse The JSON response with product details
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load('category:id,code,name'));
    }

    /**
     * Update an existing product.
     *
     * @param  Request  $request The HTTP request with updated data
     * @param  Product  $product The product instance to update
     * @return JsonResponse The JSON response with updated product
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $request->merge([
            'code' => $this->productCodeGenerator->normalizeInput($request->input('code')),
            'unit' => $this->normalizeProductUnitInput($request->input('unit')),
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
            'unit' => ['required', 'string', 'max:30', Rule::in($this->configuredProductUnitCodes())],
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
        AppCache::bumpLookupVersion();

        return response()->json($product->fresh()->load('category:id,code,name'));
    }

    /**
     * Delete a product.
     *
     * @param  Product  $product The product instance to delete
     * @return JsonResponse The JSON response
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();
        AppCache::bumpLookupVersion();

        return response()->json(status: 204);
    }

}
