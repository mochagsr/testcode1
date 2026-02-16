<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Support\AppCache;
use App\Support\ExcelExportStyler;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerPageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $customers = Customer::query()
            ->select([
                'id',
                'customer_level_id',
                'name',
                'phone',
                'city',
                'address',
                'outstanding_receivable',
                'id_card_photo_path',
            ])
            ->withLevel()
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
        $printedAt = $this->nowWib();
        $filename = 'customers-' . $printedAt->format('Ymd-His') . '.xlsx';

        $customerQuery = Customer::query()
            ->select(['id', 'name', 'phone', 'city', 'address', 'outstanding_receivable'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('id');

        $customerCount = (clone $customerQuery)->count();

        return response()->streamDownload(function () use ($customerQuery, $customerCount, $printedAt): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Customer');

            $sheet->setCellValue('A1', __('ui.customers_title'));
            $sheet->setCellValue('A2', __('report.printed') . ': ' . $printedAt->format('d-m-Y H:i:s') . ' WIB');
            $sheet->setCellValue('A4', 'No');
            $sheet->setCellValue('B4', __('ui.name'));
            $sheet->setCellValue('C4', __('ui.phone'));
            $sheet->setCellValue('D4', __('ui.city'));
            $sheet->setCellValue('E4', __('ui.address'));
            $sheet->setCellValue('F4', __('ui.receivable'));

            $row = 5;
            $number = 1;
            $customerQuery->chunkById(500, function ($customers) use ($sheet, &$row, &$number): void {
                foreach ($customers as $customer) {
                    $phoneRaw = (string) ($customer->phone ?? '');
                    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneRaw);

                    $sheet->setCellValue('A' . $row, $number++);
                    $sheet->setCellValue('B' . $row, (string) $customer->name);
                    $sheet->setCellValueExplicit(
                        'C' . $row,
                        $phoneNumber !== '' ? $phoneNumber : '-',
                        DataType::TYPE_STRING
                    );
                    $sheet->setCellValue('D' . $row, (string) ($customer->city ?: '-'));
                    $sheet->setCellValue('E' . $row, (string) ($customer->address ?: '-'));
                    $sheet->setCellValue('F' . $row, (int) round((float) $customer->outstanding_receivable));
                    $row++;
                }
            }, 'id', 'id');

            $itemCount = $customerCount;
            ExcelExportStyler::styleTable($sheet, 4, 6, $itemCount, true);
            if ($itemCount > 0) {
                ExcelExportStyler::formatNumberColumns($sheet, 5, 4 + $itemCount, [1, 6], '#,##0');
                $sheet->getStyle('C5:C' . (4 + $itemCount))->getNumberFormat()->setFormatCode('@');
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function nowWib(): Carbon
    {
        return now('Asia/Jakarta');
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
        AppCache::forgetAfterFinancialMutation();
        AppCache::bumpLookupVersion();

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
        $data = $this->validatePayload($request);

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
        AppCache::forgetAfterFinancialMutation();
        AppCache::bumpLookupVersion();

        return redirect()->route('customers-web.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->id_card_photo_path) {
            Storage::disk('public')->delete($customer->id_card_photo_path);
        }
        $customer->delete();
        AppCache::forgetAfterFinancialMutation();
        AppCache::bumpLookupVersion();

        return redirect()->route('customers-web.index')->with('success', 'Customer deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
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
        $prefix = 'CUS-' . now()->format('Ymd');

        do {
            $code = $prefix . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Customer::query()->where('code', $code)->exists());

        return $code;
    }
}
