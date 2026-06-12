<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerShipLocation;
use App\Support\AppCache;
use App\Support\ValidatesSearchTokens;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use SanderMuller\FluentValidation\FluentRule;

class CustomerShipLocationPageController extends Controller
{
    use ValidatesSearchTokens;

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $customerId = $request->integer('customer_id');
        $allowedSorts = ['customer', 'school_name', 'city'];
        $sort = in_array((string) $request->string('sort', ''), $allowedSorts, true)
            ? (string) $request->string('sort', '') : '';
        $direction = strtolower((string) $request->string('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $locations = CustomerShipLocation::query()
            ->when($sort === 'customer', function ($query) use ($direction): void {
                $query->leftJoin('customers', 'customer_ship_locations.customer_id', '=', 'customers.id')
                    ->select([
                        'customer_ship_locations.id', 'customer_ship_locations.customer_id',
                        'customer_ship_locations.school_name', 'customer_ship_locations.recipient_phone',
                        'customer_ship_locations.city', 'customer_ship_locations.address',
                        'customer_ship_locations.is_active', 'customer_ship_locations.updated_at',
                    ])
                    ->orderBy('customers.name', $direction)
                    ->orderBy('customer_ship_locations.school_name');
            }, function ($query) use ($sort, $direction): void {
                $query->select(['id', 'customer_id', 'school_name', 'recipient_phone', 'city', 'address', 'is_active', 'updated_at']);
                match ($sort) {
                    'school_name' => $query->orderBy('school_name', $direction)->orderBy('id'),
                    'city' => $query->orderBy('city', $direction)->orderBy('school_name'),
                    default => $query->orderBy('school_name')->orderBy('id'),
                };
            })
            ->with('customer:id,name,city')
            ->when($customerId > 0, fn (Builder $query) => $query->where('customer_ship_locations.customer_id', $customerId))
            ->searchKeyword($search)
            ->paginate((int) config('pagination.master_per_page', 20))
            ->withQueryString();

        $viewData = [
            'locations' => $locations,
            'search' => $search,
            'selectedCustomerId' => $customerId > 0 ? $customerId : null,
            'sort' => $sort,
            'direction' => $direction,
        ];

        if ($request->ajax()) {
            return view('customer_ship_locations.partials.results', $viewData);
        }

        $customers = Customer::query()
            ->onlyOptionColumns()
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('customer_ship_locations.index', $viewData + [
            'customers' => $customers,
        ]);
    }

    public function create(): View
    {
        return view('customer_ship_locations.create', [
            'location' => null,
            'customers' => Customer::query()->onlyOptionColumns()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        CustomerShipLocation::create($data);
        AppCache::bumpLookupVersion();

        return redirect()
            ->route('customer-ship-locations.index')
            ->with('success', __('school_bulk.ship_location_created'));
    }

    public function edit(CustomerShipLocation $customerShipLocation): View
    {
        return view('customer_ship_locations.edit', [
            'location' => $customerShipLocation,
            'customers' => Customer::query()->onlyOptionColumns()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CustomerShipLocation $customerShipLocation): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $customerShipLocation->update($data);
        AppCache::bumpLookupVersion();

        return redirect()
            ->route('customer-ship-locations.index')
            ->with('success', __('school_bulk.ship_location_updated'));
    }

    public function updateStatus(Request $request, CustomerShipLocation $customerShipLocation): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => FluentRule::boolean()->required(),
        ]);

        $customerShipLocation->update([
            'is_active' => (bool) $data['is_active'],
        ]);
        AppCache::bumpLookupVersion();

        return back()->with('success', __('school_bulk.ship_location_status_updated'));
    }

    public function destroy(CustomerShipLocation $customerShipLocation): RedirectResponse
    {
        $customerShipLocation->delete();
        AppCache::bumpLookupVersion();

        return redirect()
            ->route('customer-ship-locations.index')
            ->with('success', __('school_bulk.ship_location_deleted'));
    }

    public function lookup(Request $request): JsonResponse
    {
        $perPage = $this->resolveLookupPerPage($request, 20, 25);
        $page = $this->resolveLookupPage($request);
        $customerId = $request->integer('customer_id');
        $search = trim((string) $request->string('search', ''));
        $hasSearch = $search !== '';
        $now = now();

        if ($customerId <= 0) {
            return response()->json($this->emptyLookupPage($page, $perPage));
        }
        if ($hasSearch && ! $this->hasValidSearchTokens($search)) {
            return response()->json($this->emptyLookupPage($page, $perPage));
        }

        $cacheKey = AppCache::lookupCacheKey('lookups.customer_ship_locations', [
            'customer_id' => $customerId,
            'search' => mb_strtolower($search),
            'per_page' => $perPage,
            'page' => $page,
        ]);

        $payload = Cache::remember($cacheKey, $now->copy()->addSeconds(20), function () use ($customerId, $search, $hasSearch, $perPage) {
            return CustomerShipLocation::query()
                ->select(['id', 'customer_id', 'school_name', 'recipient_phone', 'city', 'address'])
                ->where('customer_id', $customerId)
                ->where('is_active', true)
                ->when($hasSearch, fn (Builder $query) => $query->searchKeyword($search))
                ->orderBy('school_name')
                ->orderBy('id')
                ->paginate($perPage)
                ->toArray();
        });

        return response()->json($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'customer_id' => FluentRule::integer()->required()->exists('customers', 'id'),
            'school_name' => FluentRule::string()->required()->max(150),
            'recipient_phone' => FluentRule::string()->nullable()->max(30),
            'city' => FluentRule::string()->nullable()->max(100),
            'address' => FluentRule::string()->nullable(),
            'notes' => FluentRule::string()->nullable(),
            'is_active' => FluentRule::boolean()->nullable(),
        ]);
    }
}
