<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CustomerLevel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerLevelPageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $levels = CustomerLevel::query()
            ->onlyListColumns()
            ->searchKeyword($search)
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('customer_levels.index', [
            'levels' => $levels,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('customer_levels.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        CustomerLevel::create($data);

        return redirect()->route('customer-levels-web.index')->with('success', 'Customer level created successfully.');
    }

    public function edit(CustomerLevel $customerLevel): View
    {
        return view('customer_levels.edit', ['level' => $customerLevel]);
    }

    public function update(Request $request, CustomerLevel $customerLevel): RedirectResponse
    {
        $data = $this->validatePayload($request, $customerLevel->id);
        $customerLevel->update($data);

        return redirect()->route('customer-levels-web.index')->with('success', 'Customer level updated successfully.');
    }

    public function destroy(CustomerLevel $customerLevel): RedirectResponse
    {
        $customerLevel->delete();

        return redirect()->route('customer-levels-web.index')->with('success', 'Customer level deleted successfully.');
    }

    /**
     * @return array<string, string|null>
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('customer_levels', 'code')->ignore($ignoreId),
                Rule::unique('customer_levels', 'name')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string'],
        ]);

        // Keep compatibility with existing schema that still has `name`.
        $data['name'] = $data['code'];

        return $data;
    }
}
