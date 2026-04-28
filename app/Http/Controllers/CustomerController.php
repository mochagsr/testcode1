<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\AppCache;
use App\Support\UploadedImageCompressor;
use App\Support\ValidatesSearchTokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use SanderMuller\FluentValidation\FluentRule;

class CustomerController extends Controller
{
    use ValidatesSearchTokens;

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->resolveLookupPerPage($request, 20, 25);
        $page = $this->resolveLookupPage($request);
        $search = trim((string) $request->string('search', ''));
        $hasSearch = $search !== '';
        $now = now();

        if ($hasSearch && ! $this->hasValidSearchTokens($search)) {
            return response()->json($this->emptyLookupPage($page, $perPage));
        }

        $customersQuery = Customer::query()
            ->select([
                'id',
                'customer_level_id',
                'name',
                'phone',
                'phone_secondary',
                'city',
                'address',
                'outstanding_receivable',
                'credit_balance',
            ])
            ->with('level:id,code,name')
            ->when($hasSearch, fn (Builder $query) => $query->searchKeyword($search))
            ->orderBy('name')
            ->orderBy('id');

        $cacheKey = AppCache::lookupCacheKey('lookups.customers', [
            'per_page' => $perPage,
            'page' => $page,
            'search' => mb_strtolower($search),
        ]);
        $customers = Cache::remember($cacheKey, $now->copy()->addSeconds(20), function () use ($customersQuery, $perPage) {
            return $customersQuery->paginate($perPage)->toArray();
        });

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_level_id' => FluentRule::integer()->required()->exists('customer_levels', 'id'),
            'name' => FluentRule::string()->required()->max(150),
            'phone' => FluentRule::string()->required()->max(30),
            'phone_secondary' => FluentRule::string()->nullable()->max(30),
            'city' => FluentRule::string()->required()->max(100),
            'address' => FluentRule::string()->required(),
            'id_card_photo' => FluentRule::image()->nullable()->max(3072),
            'outstanding_receivable' => FluentRule::numeric()->nullable()->min(0),
            'notes' => FluentRule::string()->nullable(),
        ]);
        $data['code'] = $this->generateCustomerCode();

        if ($request->hasFile('id_card_photo')) {
            $data['id_card_photo_path'] = UploadedImageCompressor::storeJpeg($request->file('id_card_photo'), 'ktp');
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
            'customer_level_id' => FluentRule::integer()->required()->exists('customer_levels', 'id'),
            'name' => FluentRule::string()->required()->max(150),
            'phone' => FluentRule::string()->required()->max(30),
            'phone_secondary' => FluentRule::string()->nullable()->max(30),
            'city' => FluentRule::string()->required()->max(100),
            'address' => FluentRule::string()->required(),
            'id_card_photo' => FluentRule::image()->nullable()->max(3072),
            'outstanding_receivable' => FluentRule::numeric()->nullable()->min(0),
            'notes' => FluentRule::string()->nullable(),
            'remove_id_card_photo' => FluentRule::boolean()->nullable(),
        ]);

        if ($request->boolean('remove_id_card_photo') && $customer->id_card_photo_path) {
            Storage::disk('public')->delete($customer->id_card_photo_path);
            $data['id_card_photo_path'] = null;
        }

        if ($request->hasFile('id_card_photo')) {
            if ($customer->id_card_photo_path) {
                Storage::disk('public')->delete($customer->id_card_photo_path);
            }

            $data['id_card_photo_path'] = UploadedImageCompressor::storeJpeg($request->file('id_card_photo'), 'ktp');
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
        $prefix = 'CUS-'.now()->format('Ymd');

        do {
            $code = $prefix.'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Customer::query()->where('code', $code)->exists());

        return $code;
    }
}
