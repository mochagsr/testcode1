<?php

namespace App\Http\Controllers;

use App\Support\ExcelCsv;
use App\Models\Customer;
use App\Models\CustomerLevel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerPageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $customers = Customer::query()
            ->with('level:id,code,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $search = trim((string) $request->string('search', ''));
        $filename = 'customers-'.now()->format('Ymd-His').'.csv';

        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get(['name', 'phone', 'city', 'address', 'outstanding_receivable']);

        return response()->streamDownload(function () use ($customers): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            ExcelCsv::start($handle);
            ExcelCsv::row($handle, [
                __('ui.name'),
                __('ui.phone'),
                __('ui.city'),
                __('ui.address'),
                __('ui.receivable'),
            ]);

            foreach ($customers as $customer) {
                ExcelCsv::row($handle, [
                    (string) $customer->name,
                    (string) ($customer->phone ?: '-'),
                    (string) ($customer->city ?: '-'),
                    (string) ($customer->address ?: '-'),
                    (int) round((float) $customer->outstanding_receivable),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        return view('customers.create', [
            'levels' => CustomerLevel::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $data['code'] = $this->generateCustomerCode();

        if ($request->hasFile('id_card_photo')) {
            $data['id_card_photo_path'] = $request->file('id_card_photo')->store('ktp', 'public');
        }

        unset($data['id_card_photo']);
        Customer::create($data);

        return redirect()->route('customers-web.index')->with('success', 'Customer created successfully.');
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', [
            'customer' => $customer,
            'levels' => CustomerLevel::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->validatePayload($request, $customer->id);

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

        return redirect()->route('customers-web.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->id_card_photo_path) {
            Storage::disk('public')->delete($customer->id_card_photo_path);
        }
        $customer->delete();

        return redirect()->route('customers-web.index')->with('success', 'Customer deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
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
