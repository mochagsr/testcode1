<?php

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductPageController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService
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
                            $categoryQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $product = Product::create($data);
        $this->auditLogService->log('master.product.create', $product, "Product created: {$product->code}", $request);

        return redirect()
            ->route('products.index')
            ->with('success', 'Item created successfully.');
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product,
            'categories' => ItemCategory::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validatePayload($request, $product->id);
        $product->update($data);
        $this->auditLogService->log('master.product.update', $product, "Product updated: {$product->code}", $request);

        return redirect()
            ->route('products.index')
            ->with('success', 'Item updated successfully.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $code = $product->code;
        $product->delete();
        $this->auditLogService->log('master.product.delete', null, "Product deleted: {$code}", $request);

        return redirect()
            ->route('products.index')
            ->with('success', 'Item deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'item_category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('products', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:30'],
            'stock' => ['required', 'integer', 'min:0'],
            'price_agent' => ['required', 'numeric', 'min:0'],
            'price_sales' => ['required', 'numeric', 'min:0'],
            'price_general' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
