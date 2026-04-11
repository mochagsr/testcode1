<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\AppCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductUnitPageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $units = ProductUnit::query()
            ->onlyListColumns()
            ->searchKeyword($search)
            ->orderByRaw("CASE WHEN code = 'exp' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('product_units.index', [
            'units' => $units,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('product_units.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        ProductUnit::query()->create($data);
        AppCache::bumpLookupVersion();

        return redirect()->route('product-units.index')->with('success', __('ui.product_unit_created_success'));
    }

    public function edit(ProductUnit $productUnit): View
    {
        return view('product_units.edit', ['unit' => $productUnit]);
    }

    public function update(Request $request, ProductUnit $productUnit): RedirectResponse
    {
        $data = $this->validatePayload($request, $productUnit->id);
        $productUnit->update($data);
        AppCache::bumpLookupVersion();

        return redirect()->route('product-units.index')->with('success', __('ui.product_unit_updated_success'));
    }

    public function destroy(ProductUnit $productUnit): RedirectResponse
    {
        if (
            Product::query()->where('unit', $productUnit->code)->exists()
            || DB::table('outgoing_transaction_items')->where('unit', $productUnit->code)->exists()
        ) {
            return redirect()
                ->route('product-units.index')
                ->withErrors(['unit' => __('ui.product_unit_delete_in_use')]);
        }

        $productUnit->delete();
        AppCache::bumpLookupVersion();

        return redirect()->route('product-units.index')->with('success', __('ui.product_unit_deleted_success'));
    }

    /**
     * @return array{code:string,name:string,description:string|null}
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('product_units', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
        ]);

        $data['code'] = ProductUnit::normalizeCode((string) $data['code']);
        $data['name'] = trim((string) $data['name']);
        $data['description'] = trim((string) ($data['description'] ?? '')) ?: null;

        if ($data['code'] === '') {
            throw ValidationException::withMessages([
                'code' => __('validation.required', ['attribute' => __('ui.code')]),
            ]);
        }

        return $data;
    }
}
