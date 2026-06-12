<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Support\AppCache;
use App\Support\ValidatesSearchTokens;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use SanderMuller\FluentValidation\FluentRule;

class SupplierPageController extends Controller
{
    use ValidatesSearchTokens;

    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $allowedSorts = ['name', 'company_name'];
        $sort = in_array((string) $request->string('sort', ''), $allowedSorts, true)
            ? (string) $request->string('sort', '')
            : '';
        $direction = strtolower((string) $request->string('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $suppliers = Supplier::query()
            ->onlyListColumns()
            ->searchKeyword($search)
            ->when($sort === 'name', fn ($q) => $q->orderBy('name', $direction)->orderBy('id', 'desc'))
            ->when($sort === 'company_name', fn ($q) => $q->orderBy('company_name', $direction)->orderBy('id', 'desc'))
            ->when($sort === '', fn ($q) => $q->orderBy('name')->orderBy('id', 'desc'))
            ->paginate((int) config('pagination.master_per_page', 20))
            ->withQueryString();

        $viewData = [
            'suppliers' => $suppliers,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ];

        if ($request->ajax()) {
            return view('suppliers.partials.results', $viewData);
        }

        return view('suppliers.index', $viewData);
    }

    public function create(): View
    {
        return view('suppliers.create', [
            'supplier' => null,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $perPage = $this->resolveLookupPerPage($request, 20, 25);
        $page = $this->resolveLookupPage($request);
        $search = trim((string) $request->string('search', ''));
        $now = now();

        $query = Supplier::query()
            ->onlyLookupColumns();

        if ($search !== '') {
            if (! $this->hasValidSearchTokens($search)) {
                return response()->json($this->emptyLookupPage($page, $perPage));
            }

            $query->searchKeyword($search);
        }

        $cacheKey = AppCache::lookupCacheKey('lookups.suppliers', [
            'per_page' => $perPage,
            'page' => $page,
            'search' => mb_strtolower($search),
        ]);
        $suppliers = Cache::remember($cacheKey, $now->copy()->addSeconds(20), function () use ($query, $perPage) {
            return $query
                ->orderBy('name')
                ->orderBy('id')
                ->paginate($perPage)
                ->toArray();
        });

        return response()->json($suppliers);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $data['bank_account_notes'] = null;
        $supplier = Supplier::create($data);

        $this->auditLogService->log(
            'master.supplier.create',
            $supplier,
            __('ui.audit_desc_supplier_created', ['name' => (string) $supplier->name]),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return redirect()
            ->route('suppliers.index')
            ->with('success', __('ui.supplier_created_success'));
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $data['bank_account_notes'] = null;
        $supplier->update($data);

        $this->auditLogService->log(
            'master.supplier.update',
            $supplier,
            __('ui.audit_desc_supplier_updated', ['name' => (string) $supplier->name]),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return redirect()
            ->route('suppliers.index')
            ->with('success', __('ui.supplier_updated_success'));
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $name = (string) $supplier->name;

        try {
            $supplier->delete();
        } catch (\Illuminate\Database\QueryException) {
            return back()->with('error', __('ui.cannot_delete_supplier_has_transactions'));
        }

        $this->auditLogService->log(
            'master.supplier.delete',
            null,
            __('ui.audit_desc_supplier_deleted', ['name' => (string) $name]),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return redirect()
            ->route('suppliers.index')
            ->with('success', __('ui.supplier_deleted_success'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => FluentRule::string()->required()->max(150),
            'company_name' => FluentRule::string()->nullable()->max(200),
            'phone' => FluentRule::string()->nullable()->max(30),
            'address' => FluentRule::string()->nullable()->max(255),
            'notes' => FluentRule::string()->nullable(),
        ]);
    }
}
