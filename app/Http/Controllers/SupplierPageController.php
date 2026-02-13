<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Support\ValidatesSearchTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierPageController extends Controller
{
    use ValidatesSearchTokens;

    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $suppliers = Supplier::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('suppliers.create', [
            'supplier' => null,
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 25);
        $search = trim((string) $request->string('search', ''));

        $query = Supplier::query()
            ->select(['id', 'name', 'company_name', 'phone', 'address']);

        if ($search !== '') {
            if (! $this->hasValidSearchTokens($search)) {
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ]);
            }

            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $suppliers = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);

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
            "Supplier created: {$supplier->name}",
            $request
        );

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
            "Supplier updated: {$supplier->name}",
            $request
        );

        return redirect()
            ->route('suppliers.index')
            ->with('success', __('ui.supplier_updated_success'));
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $name = (string) $supplier->name;
        $supplier->delete();

        $this->auditLogService->log(
            'master.supplier.delete',
            null,
            "Supplier deleted: {$name}",
            $request
        );

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
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

}
