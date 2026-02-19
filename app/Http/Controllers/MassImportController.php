<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Support\AppCache;
use App\Support\ProductCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MassImportController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ProductCodeGenerator $productCodeGenerator
    ) {}

    public function templateProducts(): StreamedResponse
    {
        return $this->downloadTemplate('template-import-products.xlsx', [
            ['code', 'name', 'category', 'unit', 'stock', 'price_agent', 'price_sales', 'price_general'],
            ['', 'Matematika 1 Edisi 5 Smt 1 25/26', 'Buku', 'exp', 100, 50000, 55000, 60000],
        ], 'Products');
    }

    public function templateCustomers(): StreamedResponse
    {
        return $this->downloadTemplate('template-import-customers.xlsx', [
            ['name', 'level', 'phone', 'city', 'address', 'notes'],
            ['Toko Sumber Ilmu', 'Agen', '08123456789', 'Malang', 'Jl. Soekarno Hatta 10', 'Customer lama'],
        ], 'Customers');
    }

    public function templateSuppliers(): StreamedResponse
    {
        return $this->downloadTemplate('template-import-suppliers.xlsx', [
            ['name', 'company_name', 'phone', 'address', 'notes'],
            ['PT Kertas Maju', 'PT Kertas Maju', '081212121212', 'Surabaya', 'Pembayaran 30 hari'],
        ], 'Suppliers');
    }

    public function importProducts(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $rows = $this->readSpreadsheetRows($request->file('import_file')->getRealPath());
        if ($rows === []) {
            return back()->with('error', 'File import kosong.');
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $errors = [];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $headers, &$errors, &$created, &$updated): void {
            foreach ($rows as $rowIndex => $row) {
                $data = $this->mapRow($headers, $row);
                if ($this->isEmptyRow($data)) {
                    continue;
                }

                $validator = Validator::make($data, [
                    'name' => ['required', 'string', 'max:200'],
                    'category' => ['required', 'string', 'max:200'],
                    'unit' => ['required', 'string', 'max:30'],
                    'stock' => ['required', 'integer', 'min:0'],
                    'price_agent' => ['required', 'numeric', 'min:0'],
                    'price_sales' => ['required', 'numeric', 'min:0'],
                    'price_general' => ['required', 'numeric', 'min:0'],
                ]);

                if ($validator->fails()) {
                    $errors[] = 'Baris '.($rowIndex + 2).': '.implode('; ', $validator->errors()->all());
                    continue;
                }

                $categoryId = $this->resolveCategoryId((string) $data['category']);
                if ($categoryId === null) {
                    $errors[] = 'Baris '.($rowIndex + 2).': kategori tidak ditemukan.';
                    continue;
                }

                $code = $this->productCodeGenerator->resolve(
                    $this->productCodeGenerator->normalizeInput((string) ($data['code'] ?? '')),
                    (string) $data['name']
                );

                $payload = [
                    'item_category_id' => $categoryId,
                    'name' => (string) $data['name'],
                    'code' => $code,
                    'unit' => (string) $data['unit'],
                    'stock' => (int) $data['stock'],
                    'price_agent' => (int) round((float) $data['price_agent']),
                    'price_sales' => (int) round((float) $data['price_sales']),
                    'price_general' => (int) round((float) $data['price_general']),
                    'is_active' => true,
                ];

                $existing = Product::query()->where('code', $code)->first();
                if ($existing !== null) {
                    $existing->update($payload);
                    $updated++;
                    continue;
                }

                Product::create($payload);
                $created++;
            }
        });

        $this->auditLogService->log(
            'master.product.import',
            null,
            "Import products: created={$created}, updated={$updated}, errors=".count($errors),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return back()->with('success', "Import selesai. Baru: {$created}, Update: {$updated}, Error: ".count($errors))
            ->with('import_errors', $errors);
    }

    public function importCustomers(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $rows = $this->readSpreadsheetRows($request->file('import_file')->getRealPath());
        if ($rows === []) {
            return back()->with('error', 'File import kosong.');
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $errors = [];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $headers, &$errors, &$created, &$updated): void {
            foreach ($rows as $rowIndex => $row) {
                $data = $this->mapRow($headers, $row);
                if ($this->isEmptyRow($data)) {
                    continue;
                }

                $validator = Validator::make($data, [
                    'name' => ['required', 'string', 'max:150'],
                    'level' => ['nullable', 'string', 'max:120'],
                    'phone' => ['nullable', 'string', 'max:30'],
                    'city' => ['nullable', 'string', 'max:100'],
                    'address' => ['nullable', 'string'],
                    'notes' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    $errors[] = 'Baris '.($rowIndex + 2).': '.implode('; ', $validator->errors()->all());
                    continue;
                }

                $levelId = $this->resolveCustomerLevelId((string) ($data['level'] ?? ''));
                $payload = [
                    'customer_level_id' => $levelId,
                    'name' => (string) $data['name'],
                    'phone' => (string) ($data['phone'] ?? ''),
                    'city' => (string) ($data['city'] ?? ''),
                    'address' => (string) ($data['address'] ?? ''),
                    'notes' => (string) ($data['notes'] ?? ''),
                ];

                $existing = Customer::query()->where('name', $payload['name'])->where('phone', $payload['phone'])->first();
                if ($existing !== null) {
                    $existing->update($payload);
                    $updated++;
                    continue;
                }

                $payload['code'] = $this->generateCustomerCode();
                $payload['outstanding_receivable'] = 0;
                $payload['credit_balance'] = 0;
                Customer::create($payload);
                $created++;
            }
        });

        $this->auditLogService->log(
            'master.customer.import',
            null,
            "Import customers: created={$created}, updated={$updated}, errors=".count($errors),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return back()->with('success', "Import selesai. Baru: {$created}, Update: {$updated}, Error: ".count($errors))
            ->with('import_errors', $errors);
    }

    public function importSuppliers(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $rows = $this->readSpreadsheetRows($request->file('import_file')->getRealPath());
        if ($rows === []) {
            return back()->with('error', 'File import kosong.');
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $errors = [];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $headers, &$errors, &$created, &$updated): void {
            foreach ($rows as $rowIndex => $row) {
                $data = $this->mapRow($headers, $row);
                if ($this->isEmptyRow($data)) {
                    continue;
                }

                $validator = Validator::make($data, [
                    'name' => ['required', 'string', 'max:150'],
                    'company_name' => ['nullable', 'string', 'max:200'],
                    'phone' => ['nullable', 'string', 'max:30'],
                    'address' => ['nullable', 'string', 'max:255'],
                    'notes' => ['nullable', 'string'],
                ]);

                if ($validator->fails()) {
                    $errors[] = 'Baris '.($rowIndex + 2).': '.implode('; ', $validator->errors()->all());
                    continue;
                }

                $payload = [
                    'name' => (string) $data['name'],
                    'company_name' => (string) ($data['company_name'] ?? ''),
                    'phone' => (string) ($data['phone'] ?? ''),
                    'address' => (string) ($data['address'] ?? ''),
                    'notes' => (string) ($data['notes'] ?? ''),
                    'bank_account_notes' => null,
                ];

                $existing = Supplier::query()->where('name', $payload['name'])->first();
                if ($existing !== null) {
                    $existing->update($payload);
                    $updated++;
                    continue;
                }

                $payload['outstanding_payable'] = 0;
                Supplier::create($payload);
                $created++;
            }
        });

        $this->auditLogService->log(
            'master.supplier.import',
            null,
            "Import suppliers: created={$created}, updated={$updated}, errors=".count($errors),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return back()->with('success', "Import selesai. Baru: {$created}, Update: {$updated}, Error: ".count($errors))
            ->with('import_errors', $errors);
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @return array<int, string>
     */
    private function normalizeHeaders(array $rows): array
    {
        return array_map(static function ($header): string {
            $normalized = strtolower(trim((string) $header));
            $normalized = str_replace([' ', '-'], '_', $normalized);

            return preg_replace('/[^a-z0-9_]/', '', $normalized) ?: '';
        }, $rows);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRow(array $headers, array $row): array
    {
        $mapped = [];
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $mapped[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $mapped;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readSpreadsheetRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        $highestRow = max(1, $sheet->getHighestRow());
        $rows = [];

        for ($row = 1; $row <= $highestRow; $row++) {
            $cells = [];
            for ($column = 1; $column <= $highestColumn; $column++) {
                $cells[] = $sheet->getCell([$column, $row])->getCalculatedValue();
            }
            $rows[] = $cells;
        }
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function resolveCategoryId(string $category): ?int
    {
        $value = trim($category);
        if ($value === '') {
            return null;
        }

        return ItemCategory::query()
            ->where('name', $value)
            ->orWhere('code', $value)
            ->value('id');
    }

    private function resolveCustomerLevelId(string $level): ?int
    {
        $value = trim($level);
        if ($value === '') {
            return null;
        }

        return CustomerLevel::query()
            ->where('name', $value)
            ->orWhere('code', $value)
            ->value('id');
    }

    private function generateCustomerCode(): string
    {
        $prefix = 'CUS-' . now()->format('Ymd');
        do {
            $code = $prefix . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Customer::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    private function downloadTemplate(string $filename, array $rows, string $sheetTitle): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $sheetTitle): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($sheetTitle);
            $sheet->fromArray($rows, null, 'A1');
            foreach (range(1, count($rows[0] ?? [])) as $columnIndex) {
                $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
                $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
            }
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
