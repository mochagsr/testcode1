<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use App\Support\AppCache;
use App\Support\ValidatesSearchTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemCategoryController extends Controller
{
    use ValidatesSearchTokens;

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolveLookupPerPage($request, 25, 25);
        $search = trim((string) $request->string('search', ''));

        $categories = ItemCategory::query()
            ->onlyListColumns()
            ->searchKeyword($search)
            ->orderBy('code')
            ->paginate($perPage);

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:item_categories,code'],
            'description' => ['nullable', 'string'],
        ]);
        $data['name'] = $data['code'];

        $category = ItemCategory::create($data);
        AppCache::bumpLookupVersion();

        return response()->json($category, 201);
    }

    public function show(ItemCategory $itemCategory): JsonResponse
    {
        return response()->json($itemCategory);
    }

    public function update(Request $request, ItemCategory $itemCategory): JsonResponse
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('item_categories', 'code')->ignore($itemCategory->id),
            ],
            'description' => ['nullable', 'string'],
        ]);
        $data['name'] = $data['code'];

        $itemCategory->update($data);
        AppCache::bumpLookupVersion();

        return response()->json($itemCategory->fresh());
    }

    public function destroy(ItemCategory $itemCategory): JsonResponse
    {
        $itemCategory->delete();
        AppCache::bumpLookupVersion();

        return response()->json(status: 204);
    }
}
