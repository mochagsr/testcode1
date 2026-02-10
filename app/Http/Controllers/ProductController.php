<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 25), 1), 25);
        $search = trim((string) $request->string('search', ''));

        $products = Product::query()
            ->with('category:id,code,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
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
        $data = $request->validate([
            'item_category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'code' => ['required', 'string', 'max:60', 'unique:products,code'],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:30'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'price_agent' => ['nullable', 'numeric', 'min:0'],
            'price_sales' => ['nullable', 'numeric', 'min:0'],
            'price_general' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product = Product::create($data);

        return response()->json($product->load('category:id,code,name'), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load('category:id,code,name'));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'item_category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('products', 'code')->ignore($product->id),
            ],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:30'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'price_agent' => ['nullable', 'numeric', 'min:0'],
            'price_sales' => ['nullable', 'numeric', 'min:0'],
            'price_general' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product->update($data);

        return response()->json($product->fresh()->load('category:id,code,name'));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(status: 204);
    }
}
