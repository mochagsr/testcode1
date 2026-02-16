<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CustomerLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerLevelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 25), 1), 25);
        $search = trim((string) $request->string('search', ''));

        $levels = CustomerLevel::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->paginate($perPage);

        return response()->json($levels);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:customer_levels,code'],
            'description' => ['nullable', 'string'],
        ]);
        $data['name'] = $data['code'];

        $level = CustomerLevel::create($data);

        return response()->json($level, 201);
    }

    public function show(CustomerLevel $customerLevel): JsonResponse
    {
        return response()->json($customerLevel);
    }

    public function update(Request $request, CustomerLevel $customerLevel): JsonResponse
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('customer_levels', 'code')->ignore($customerLevel->id),
            ],
            'description' => ['nullable', 'string'],
        ]);
        $data['name'] = $data['code'];

        $customerLevel->update($data);

        return response()->json($customerLevel->fresh());
    }

    public function destroy(CustomerLevel $customerLevel): JsonResponse
    {
        $customerLevel->delete();

        return response()->json(status: 204);
    }
}
