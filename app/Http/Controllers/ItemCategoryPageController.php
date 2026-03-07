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
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ]);

        $data['name'] = trim((string) $data['name']);
        $data['code'] = $this->resolveCategoryCode(
            (string) ($data['code'] ?? ''),
            $data['name'],
            $ignoreId,
        );

        return $data;
    }

    private function resolveCategoryCode(string $manualCode, string $name, ?int $ignoreId = null): string
    {
        $baseCode = $manualCode !== ''
            ? $this->normalizeCode($manualCode)
            : $this->generateBaseCodeFromName($name);

        if ($baseCode === '') {
            $baseCode = 'kategori';
        }

        $candidate = $baseCode;
        $suffix = 2;

        while ($this->codeExists($candidate, $ignoreId)) {
            $remainingLength = max(1, 50 - strlen((string) $suffix));
            $candidate = substr($baseCode, 0, $remainingLength).$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function codeExists(string $code, ?int $ignoreId = null): bool
    {
        return ItemCategory::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('code', $code)
            ->exists();
    }

    private function normalizeCode(string $code): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', '', strtolower(trim($code))) ?? '';

        return substr($normalized, 0, 50);
    }

    private function generateBaseCodeFromName(string $name): string
    {
        $normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $name)));
        $parts = array_values(array_filter(explode(' ', $normalized)));

        if ($parts === []) {
            return 'kategori';
        }

        if (count($parts) === 1) {
            return substr($parts[0], 0, 6);
        }

        $code = '';
        foreach ($parts as $part) {
            $code .= substr($part, 0, 3);
        }

        return substr($code, 0, 12);
    }
}
