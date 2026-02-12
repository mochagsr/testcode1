<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLevel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        $filename = 'customers-'.now()->format('Ymd-His').'.xlsx';

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
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Customer');

            $sheet->setCellValue('A1', __('ui.customers_title'));
            $sheet->setCellValue('A2', __('report.printed').': '.now()->format('d-m-Y H:i:s'));
            $sheet->setCellValue('A4', 'No');
            $sheet->setCellValue('B4', __('ui.name'));
            $sheet->setCellValue('C4', __('ui.phone'));
            $sheet->setCellValue('D4', __('ui.city'));
            $sheet->setCellValue('E4', __('ui.address'));
            $sheet->setCellValue('F4', __('ui.receivable'));

            $row = 5;
            $number = 1;
            foreach ($customers as $customer) {
                $phoneRaw = (string) ($customer->phone ?? '');
                $phoneNumber = preg_replace('/[^0-9]/', '', $phoneRaw);

                $sheet->setCellValue('A'.$row, $number++);
                $sheet->setCellValue('B'.$row, (string) $customer->name);
                $sheet->setCellValueExplicit(
                    'C'.$row,
                    $phoneNumber !== '' ? $phoneNumber : '-',
                    DataType::TYPE_STRING
                );
                $sheet->setCellValue('D'.$row, (string) ($customer->city ?: '-'));
                $sheet->setCellValue('E'.$row, (string) ($customer->address ?: '-'));
                $sheet->setCellValue('F'.$row, (int) round((float) $customer->outstanding_receivable));
                $row++;
            }

            $lastRow = max(4, $row - 1);
            $sheet->getStyle('A4:F4')->getFont()->setBold(true);
            $sheet->getStyle('A4:F'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('F5:F'.$lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $sheet->getStyle('C5:C'.$lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getColumnDimension('B')->setWidth(36);
            $sheet->getColumnDimension('C')->setWidth(20);
            $sheet->getColumnDimension('D')->setWidth(22);
            $sheet->getColumnDimension('E')->setWidth(50);
            $sheet->getColumnDimension('F')->setWidth(18);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
