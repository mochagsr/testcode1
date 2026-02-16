<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use App\Support\AppCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemCategoryPageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $categories = ItemCategory::query()
            ->onlyListColumns()
            ->searchKeyword($search)
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('item_categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('item_categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        ItemCategory::create($data);
        AppCache::bumpLookupVersion();

        return redirect()->route('item-categories.index')->with('success', 'Category created successfully.');
    }

    public function edit(ItemCategory $itemCategory): View
    {
        return view('item_categories.edit', ['category' => $itemCategory]);
    }

    public function update(Request $request, ItemCategory $itemCategory): RedirectResponse
    {
        $data = $this->validatePayload($request, $itemCategory->id);
        $itemCategory->update($data);
        AppCache::bumpLookupVersion();

        return redirect()->route('item-categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(ItemCategory $itemCategory): RedirectResponse
    {
        $itemCategory->delete();
        AppCache::bumpLookupVersion();

        return redirect()->route('item-categories.index')->with('success', 'Category deleted successfully.');
    }

    /**
     * @return array<string, string|null>
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('item_categories', 'code')->ignore($ignoreId)],
            'description' => ['nullable', 'string'],
        ]);

        // Keep compatibility with existing schema that still has `name`.
        $data['name'] = $data['code'];

        return $data;
    }
}
