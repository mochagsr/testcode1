<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPrintingSubtype;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SanderMuller\FluentValidation\FluentRule;

class CustomerPrintingSubtypeController extends Controller
{
    public function index(Customer $customer): JsonResponse
    {
        $rows = CustomerPrintingSubtype::query()
            ->where('customer_id', $customer->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $rows,
            'meta' => ['total' => $rows->count()],
        ]);
    }

    public function store(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'name' => FluentRule::string()->required()->max(120),
        ]);

        $normalizedName = CustomerPrintingSubtype::normalizeName((string) $data['name']);

        $subtype = CustomerPrintingSubtype::query()->firstOrCreate(
            [
                'customer_id' => $customer->id,
                'normalized_name' => $normalizedName,
            ],
            [
                'name' => trim((string) $data['name']),
            ]
        );

        if ($subtype->name !== trim((string) $data['name'])) {
            $subtype->update(['name' => trim((string) $data['name'])]);
        }

        return response()->json([
            'data' => $subtype->only(['id', 'name']),
        ], 201);
    }
}
