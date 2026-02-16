<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\AppCache;
use App\Support\ValidatesSearchTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    use ValidatesSearchTokens;

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 25);
        $page = max(1, (int) $request->integer('page', 1));
        $search = trim((string) $request->string('search', ''));
        $hasSearch = $search !== '';

        if ($hasSearch && ! $this->hasValidSearchTokens($search)) {
            return response()->json([
                'data' => [],
                'current_page' => $page,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
            ]);
        }

        $customersQuery = Customer::query()
            ->select([
                'id',
                'customer_level_id',
                'name',
                'phone',
                'city',
                'address',
                'outstanding_receivable',
                'credit_balance',
            ])
            ->with('level:id,code,name')
            ->when($hasSearch, function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->orderBy('id');

        $cacheKey = AppCache::lookupCacheKey('lookups.customers', [
            'per_page' => $perPage,
            'page' => $page,
            'search' => mb_strtolower($search),
        ]);
        $customers = Cache::remember($cacheKey, now()->addSeconds(20), function () use ($customersQuery, $perPage) {
            return $customersQuery->paginate($perPage)->toArray();
        });

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_level_id' => ['nullable', 'integer', 'exists:customer_levels,id'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'id_card_photo' => ['nullable', 'image', 'max:3072'],
            'outstanding_receivable' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['code'] = $this->generateCustomerCode();

        if ($request->hasFile('id_card_photo')) {
            $data['id_card_photo_path'] = $request->file('id_card_photo')->store('ktp', 'public');
        }

        unset($data['id_card_photo']);
        $customer = Customer::create($data);
        AppCache::bumpLookupVersion();

        return response()->json($customer->load('level:id,code,name'), 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer->load('level:id,code,name'));
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'customer_level_id' => ['nullable', 'integer', 'exists:customer_levels,id'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'id_card_photo' => ['nullable', 'image', 'max:3072'],
            'outstanding_receivable' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'remove_id_card_photo' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_id_card_photo') && $customer->id_card_photo_path) {
            Storage::disk('public')->delete($customer->id_card_photo_path);
            $data['id_card_photo_path'] = null;
        }

        if ($request->hasFile('id_card_photo')) {
            if ($customer->id_card_photo_path) {
                Storage::disk('public')->delete($customer->id_card_photo_path);
            }

            $data['id_card_photo_path'] = $request->file('id_card_photo')->store('ktp', 'public');
        }

        unset($data['id_card_photo'], $data['remove_id_card_photo']);
        $customer->update($data);
        AppCache::bumpLookupVersion();

        return response()->json($customer->fresh()->load('level:id,code,name'));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        if ($customer->id_card_photo_path) {
            Storage::disk('public')->delete($customer->id_card_photo_path);
        }

        $customer->delete();
        AppCache::bumpLookupVersion();

        return response()->json(status: 204);
    }

    private function generateCustomerCode(): string
    {
        $prefix = 'CUS-' . now()->format('Ymd');

        do {
            $code = $prefix . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Customer::query()->where('code', $code)->exists());

        return $code;
    }
}
