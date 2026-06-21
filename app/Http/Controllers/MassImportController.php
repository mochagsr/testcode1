<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Models\CustomerShipLocation;
use App\Models\InvoicePayment;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Services\AccountingService;
use App\Services\AuditLogService;
use App\Services\ReceivableLedgerService;
use App\Support\AppCache;
use App\Support\ProductCodeGenerator;
use App\Support\TransactionType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SanderMuller\FluentValidation\FluentRule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MassImportController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ProductCodeGenerator $productCodeGenerator,
        private readonly ReceivableLedgerService $receivableLedgerService,
        private readonly AccountingService $accountingService
    ) {}

    public function templateProducts(): StreamedResponse
    {
        return $this->downloadTemplate('template-import-products.xlsx', [
            ['kode', 'nama', 'kategori', 'satuan', 'stok', 'harga_agen', 'harga_sales', 'harga_umum'],
            ['', 'Matematika 1 Edisi 5 Smt 1 25/26', 'Buku', 'exp', 100, 50000, 55000, 60000],
        ], 'Products');
    }

    public function templateCustomers(): StreamedResponse
    {
        return $this->downloadTemplate('template-import-customers.xlsx', [
            ['nama', 'level_customer', 'no_hp_1', 'no_hp_2', 'kota', 'alamat', 'catatan'],
            ['Toko Sumber Ilmu', 'Agen', '08123456789', '08234567890', 'Malang', 'Jl. Soekarno Hatta 10', 'Customer lama'],
        ], 'Customers');
    }

    /**
     * Export every customer to an .xlsx that matches the import template,
     * so the data can be re-imported as-is (e.g. when moving servers).
     * Phone columns are written as text to preserve leading zeros.
     */
    public function exportCustomers(): StreamedResponse
    {
        $header = ['nama', 'level_customer', 'no_hp_1', 'no_hp_2', 'kota', 'alamat', 'catatan'];
        $filename = 'export-customers-'.now('Asia/Jakarta')->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($header): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Customers');
            $sheet->fromArray([$header], null, 'A1');

            $rowIndex = 2;
            Customer::query()
                ->with('level:id,name')
                ->orderBy('name')
                ->orderBy('id')
                ->chunk(500, function ($customers) use ($sheet, &$rowIndex): void {
                    foreach ($customers as $customer) {
                        $values = [
                            (string) $customer->name,
                            (string) ($customer->level->name ?? ''),
                            (string) $customer->phone,
                            (string) $customer->phone_secondary,
                            (string) $customer->city,
                            (string) $customer->address,
                            (string) $customer->notes,
                        ];
                        $col = 1;
                        foreach ($values as $value) {
                            $sheet->setCellValueExplicit([$col, $rowIndex], $value, DataType::TYPE_STRING);
                            $col++;
                        }
                        $rowIndex++;
                    }
                });

            foreach (range(1, count($header)) as $columnIndex) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function templateCategories(): StreamedResponse
    {
        return $this->downloadTemplate('template-import-item-categories.xlsx', [
            ['kode', 'nama', 'deskripsi'],
            ['', 'Paket SD', 'Kategori paket sekolah dasar'],
        ], 'ItemCategories');
    }

    public function templateCustomerShipLocations(): StreamedResponse
    {
        return $this->downloadTemplate('template-import-customer-ship-locations.xlsx', [
            ['customer', 'nama_sekolah', 'no_hp', 'kota', 'alamat', 'catatan', 'aktif'],
            ['CUS-01012026-0001', 'SDN Prambon 1', '08123456789', 'Sidoarjo', 'Jl. Raya Prambon No. 1', 'Lokasi kirim utama', 1],
        ], 'ShipLocations');
    }

    public function templateSuppliers(): StreamedResponse
    {
        return $this->downloadTemplate('template-import-suppliers.xlsx', [
            ['nama', 'nama_perusahaan', 'no_hp', 'alamat', 'catatan'],
            ['PT Kertas Maju', 'PT Kertas Maju', '081212121212', 'Surabaya', 'Pembayaran 30 hari'],
        ], 'Suppliers');
    }

    public function templateSalesInvoices(): StreamedResponse
    {
        return $this->downloadTemplate('template-import-sales-invoices.xlsx', [
            ['customer', 'tanggal_faktur', 'tanggal_jatuh_tempo', 'semester', 'tipe_transaksi', 'metode_pembayaran', 'barang', 'jumlah', 'harga_satuan', 'diskon', 'catatan'],
            ['Toko Sumber Ilmu', '2026-02-20', '2026-02-27', 'S2-2526', 'product', 'kredit', 'MAT1E5S12526', 10, 50000, 0, 'Import transaksi awal'],
        ], 'SalesInvoices');
    }

    public function importProducts(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => FluentRule::file()->required()->rule('mimes:xlsx,xls,csv,txt'),
        ]);

        $rows = $this->readSpreadsheetRows($request->file('import_file')->getRealPath());
        if ($rows === []) {
            return back()->with('error', 'File import kosong.');
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $missingHeaders = $this->missingHeaders($headers, ['name', 'category', 'unit', 'stock', 'price_agent', 'price_sales', 'price_general']);
        if ($missingHeaders !== []) {
            return back()->with('error', 'Kolom wajib pada file import belum lengkap: '.implode(', ', $missingHeaders).'. Gunakan template import terbaru.');
        }

        $errors = [];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $headers, &$errors, &$created, &$updated, $request): void {
            foreach ($rows as $rowIndex => $row) {
                $data = $this->mapRow($headers, $row);
                if ($this->isEmptyRow($data)) {
                    continue;
                }
                $data = $this->normalizeProductImportNumbers($data);

                $validator = Validator::make($data, [
                    'name' => FluentRule::string()->required()->max(200),
                    'category' => FluentRule::string()->required()->max(200),
                    'unit' => FluentRule::string()->required()->max(30),
                    'stock' => FluentRule::integer()->required()->min(0),
                    'price_agent' => FluentRule::numeric()->required()->min(0),
                    'price_sales' => FluentRule::numeric()->required()->min(0),
                    'price_general' => FluentRule::numeric()->required()->min(0),
                ]);

                if ($validator->fails()) {
                    $errors[] = $this->formatImportRowError($rowIndex + 2, implode('; ', $validator->errors()->all()));

                    continue;
                }

                $categoryId = $this->resolveCategoryId((string) $data['category']);
                if ($categoryId === null) {
                    $errors[] = $this->formatImportRowError($rowIndex + 2, 'Kategori tidak terdaftar. Samakan nama kategori dengan data master.');

                    continue;
                }

                $code = $this->productCodeGenerator->resolve(
                    $this->productCodeGenerator->normalizeInput((string) ($data['code'] ?? '')),
                    (string) $data['name'],
                    null,
                    (string) $data['category']
                );

                $payload = [
                    'item_category_id' => $categoryId,
                    'name' => (string) $data['name'],
                    'code' => $code,
                    'unit' => ProductUnit::ensureExists((string) $data['unit'])->code,
                    'stock' => (int) $data['stock'],
                    'price_agent' => (int) round((float) $data['price_agent']),
                    'price_sales' => (int) round((float) $data['price_sales']),
                    'price_general' => (int) round((float) $data['price_general']),
                    'is_active' => true,
                ];

                $existing = Product::query()
                    ->where('code', $code)
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null) {
                    $oldStock = (int) $existing->stock;
                    $existing->update($payload);
                    $newStock = (int) $existing->stock;
                    if ($oldStock !== $newStock) {
                        $delta = $newStock - $oldStock;
                        StockMutation::query()->create([
                            'product_id' => (int) $existing->id,
                            'reference_type' => Product::class,
                            'reference_id' => (int) $existing->id,
                            'mutation_type' => $delta > 0 ? 'in' : 'out',
                            'quantity' => abs($delta),
                            'notes' => $delta > 0
                                ? __('ui.stock_mutation_import_add_note')
                                : __('ui.stock_mutation_import_reduce_note'),
                            'created_by_user_id' => $request->user()?->id,
                        ]);
                    }
                    $updated++;

                    continue;
                }

                $createdProduct = Product::create($payload);
                if ((int) $createdProduct->stock > 0) {
                    StockMutation::query()->create([
                        'product_id' => (int) $createdProduct->id,
                        'reference_type' => Product::class,
                        'reference_id' => (int) $createdProduct->id,
                        'mutation_type' => 'in',
                        'quantity' => (int) $createdProduct->stock,
                        'notes' => __('ui.stock_mutation_import_initial_note'),
                        'created_by_user_id' => $request->user()?->id,
                    ]);
                }
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

    /**
     * Phase 1: parse the uploaded product file, match existing products by name,
     * stash the file under a per-user token, and return a reconciliation preview.
     * No database writes happen here.
     */
    public function analyzeProducts(Request $request): JsonResponse
    {
        $request->validate([
            'import_file' => FluentRule::file()->required()->rule('mimes:xlsx,xls,csv,txt'),
        ]);

        $rows = $this->readSpreadsheetRows($request->file('import_file')->getRealPath());
        if ($rows === []) {
            return response()->json(['message' => 'File import kosong.'], 422);
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $missingHeaders = $this->missingHeaders($headers, ['name', 'category', 'unit', 'stock', 'price_agent', 'price_sales', 'price_general']);
        if ($missingHeaders !== []) {
            return response()->json([
                'message' => 'Kolom wajib pada file import belum lengkap: '.implode(', ', $missingHeaders).'. Gunakan template import terbaru.',
            ], 422);
        }

        $userId = (int) ($request->user()?->id ?? 0);
        $this->pruneImportTempFiles($userId);
        $token = (string) Str::uuid();
        $relativePath = $this->importTempRelativePath($userId, $token);
        Storage::disk('local')->putFileAs(
            dirname($relativePath),
            $request->file('import_file'),
            basename($relativePath)
        );

        $analysis = $this->buildProductImportAnalysis($rows, $headers);

        return response()->json([
            'token' => $token,
            'summary' => $analysis['summary'],
            'new' => $analysis['new'],
            'matched' => $analysis['matched'],
            'problems' => $analysis['problems'],
        ]);
    }

    /**
     * Phase 2: re-read the stashed file (source of truth for values) and apply the
     * per-row actions chosen by the user inside one database transaction.
     */
    public function applyProducts(Request $request): JsonResponse
    {
        $token = (string) $request->string('token', '');
        $userId = (int) ($request->user()?->id ?? 0);
        $relativePath = $this->importTempRelativePath($userId, $token);
        if ($token === '' || ! Storage::disk('local')->exists($relativePath)) {
            return response()->json(['message' => 'Sesi import sudah kedaluwarsa. Silakan upload ulang file.'], 422);
        }

        $updatePrices = $request->boolean('update_prices', true);
        /** @var array<int, array<string, mixed>> $decisionInput */
        $decisionInput = (array) $request->input('decisions', []);
        $decisions = [];
        foreach ($decisionInput as $decision) {
            $rowNumber = (int) ($decision['row'] ?? 0);
            if ($rowNumber <= 0) {
                continue;
            }
            $decisions[$rowNumber] = [
                'action' => (string) ($decision['action'] ?? 'skip'),
                'target_product_id' => isset($decision['target_product_id']) ? (int) $decision['target_product_id'] : null,
            ];
        }

        $rows = $this->readSpreadsheetRows(Storage::disk('local')->path($relativePath));
        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $headers, $decisions, $updatePrices, $request, &$created, &$updated, &$skipped, &$errors): void {
            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2;
                $decision = $decisions[$rowNumber] ?? null;
                if ($decision === null || $decision['action'] === 'skip') {
                    continue;
                }

                $data = $this->normalizeProductImportNumbers($this->mapRow($headers, $row));
                if ($this->isEmptyRow($data)) {
                    continue;
                }

                $validator = Validator::make($data, $this->productImportRules());
                if ($validator->fails()) {
                    $errors[] = $this->formatImportRowError($rowNumber, implode('; ', $validator->errors()->all()));

                    continue;
                }

                $categoryId = $this->resolveCategoryId((string) $data['category']);
                if ($categoryId === null) {
                    $errors[] = $this->formatImportRowError($rowNumber, 'Kategori tidak terdaftar. Samakan nama kategori dengan data master.');

                    continue;
                }

                $fileStock = (int) $data['stock'];
                $pricePayload = [
                    'price_agent' => (int) round((float) $data['price_agent']),
                    'price_sales' => (int) round((float) $data['price_sales']),
                    'price_general' => (int) round((float) $data['price_general']),
                ];

                if ($decision['action'] === 'new') {
                    $code = $this->productCodeGenerator->resolve(
                        $this->productCodeGenerator->normalizeInput((string) ($data['code'] ?? '')),
                        (string) $data['name'],
                        null,
                        (string) $data['category']
                    );
                    $product = Product::create(array_merge([
                        'item_category_id' => $categoryId,
                        'name' => (string) $data['name'],
                        'code' => $code,
                        'unit' => ProductUnit::ensureExists((string) $data['unit'])->code,
                        'stock' => $fileStock,
                        'is_active' => true,
                    ], $pricePayload));
                    if ($fileStock > 0) {
                        $this->recordImportStockMutation($product->id, $fileStock, 'in', __('ui.stock_mutation_import_initial_note'), $request);
                    }
                    $created++;

                    continue;
                }

                if (! in_array($decision['action'], ['update', 'add', 'subtract'], true)) {
                    continue;
                }

                $target = Product::query()
                    ->whereKey((int) $decision['target_product_id'])
                    ->lockForUpdate()
                    ->first();
                if ($target === null) {
                    $errors[] = $this->formatImportRowError($rowNumber, 'Barang tujuan tidak ditemukan.');

                    continue;
                }

                $oldStock = (int) $target->stock;
                $newStock = match ($decision['action']) {
                    'add' => $oldStock + $fileStock,
                    'subtract' => $oldStock - $fileStock,
                    default => $fileStock,
                };

                if ($newStock < 0) {
                    $errors[] = $this->formatImportRowError($rowNumber, 'Stok tidak cukup untuk dikurangi (stok saat ini '.$oldStock.', diminta kurang '.$fileStock.').');

                    continue;
                }

                $payload = ['stock' => $newStock];
                if ($updatePrices) {
                    $payload = array_merge($payload, $pricePayload);
                }
                $target->update($payload);

                $delta = $newStock - $oldStock;
                if ($delta !== 0) {
                    $this->recordImportStockMutation(
                        (int) $target->id,
                        abs($delta),
                        $delta > 0 ? 'in' : 'out',
                        $delta > 0 ? __('ui.stock_mutation_import_add_note') : __('ui.stock_mutation_import_reduce_note'),
                        $request
                    );
                }
                $updated++;
            }
        });

        Storage::disk('local')->delete($relativePath);
        $this->auditLogService->log(
            'master.product.import',
            null,
            "Import barang (rekonsiliasi): baru={$created}, update={$updated}, lewati={$skipped}, error=".count($errors),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return response()->json([
            'message' => "Import selesai. Baru: {$created}, Update: {$updated}, Error: ".count($errors),
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    /**
     * Download an .xlsx containing only the problematic rows (with a reason column)
     * so the user can fix and re-import them. Clean rows are excluded.
     */
    public function downloadProductImportProblems(Request $request, string $token): StreamedResponse|JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);
        $relativePath = $this->importTempRelativePath($userId, $token);
        if (! Storage::disk('local')->exists($relativePath)) {
            return response()->json(['message' => 'Sesi import sudah kedaluwarsa.'], 422);
        }

        $rows = $this->readSpreadsheetRows(Storage::disk('local')->path($relativePath));
        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $analysis = $this->buildProductImportAnalysis($rows, $headers);

        $exportRows = [['kode', 'nama', 'kategori', 'satuan', 'stok', 'harga_agen', 'harga_sales', 'harga_umum', 'masalah']];
        foreach ($analysis['problems'] as $problem) {
            $data = (array) $problem['data'];
            $exportRows[] = [
                (string) ($data['code'] ?? ''),
                (string) ($data['name'] ?? ''),
                (string) ($data['category'] ?? ''),
                (string) ($data['unit'] ?? ''),
                (string) ($data['stock'] ?? ''),
                (string) ($data['price_agent'] ?? ''),
                (string) ($data['price_sales'] ?? ''),
                (string) ($data['price_general'] ?? ''),
                (string) $problem['reason'],
            ];
        }

        return $this->downloadTemplate('import-barang-bermasalah.xlsx', $exportRows, 'Bermasalah');
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $headers
     * @return array{summary: array<string, int>, new: array<int, array<string, mixed>>, matched: array<int, array<string, mixed>>, problems: array<int, array<string, mixed>>}
     */
    private function buildProductImportAnalysis(array $rows, array $headers): array
    {
        $existingByKey = [];
        $existing = Product::query()
            ->with('category:id,name')
            ->get(['id', 'item_category_id', 'code', 'name', 'stock', 'price_agent', 'price_sales', 'price_general']);
        foreach ($existing as $product) {
            $key = $this->normalizeProductName((string) $product->name).'|'.(int) $product->item_category_id;
            $existingByKey[$key][] = $product;
        }

        // Resolve each distinct category once, then prepare every non-empty row.
        // Product identity here is name + category (the code is derived from both),
        // so matching and duplicate detection must key on both, not name alone.
        $categoryCache = [];
        $resolveCategory = function (string $category) use (&$categoryCache): ?int {
            $cacheKey = strtolower(trim($category));
            if (! array_key_exists($cacheKey, $categoryCache)) {
                $categoryCache[$cacheKey] = $this->resolveCategoryId($category);
            }

            return $categoryCache[$cacheKey];
        };

        $prepared = [];
        $keyCounts = [];
        foreach ($rows as $rowIndex => $row) {
            $data = $this->normalizeProductImportNumbers($this->mapRow($headers, $row));
            if ($this->isEmptyRow($data)) {
                continue;
            }
            $categoryId = $resolveCategory((string) ($data['category'] ?? ''));
            $name = (string) ($data['name'] ?? '');
            $matchKey = ($name !== '' && $categoryId !== null)
                ? $this->normalizeProductName($name).'|'.$categoryId
                : null;
            if ($matchKey !== null) {
                $keyCounts[$matchKey] = ($keyCounts[$matchKey] ?? 0) + 1;
            }
            $prepared[] = [
                'row' => $rowIndex + 2,
                'data' => $data,
                'category_id' => $categoryId,
                'match_key' => $matchKey,
            ];
        }

        $new = [];
        $matched = [];
        $problems = [];

        foreach ($prepared as $entry) {
            $rowNumber = $entry['row'];
            $data = $entry['data'];

            $validator = Validator::make($data, $this->productImportRules());
            if ($validator->fails()) {
                $problems[] = $this->makeProblem($rowNumber, $data, implode('; ', $validator->errors()->all()));

                continue;
            }

            if ($entry['category_id'] === null) {
                $problems[] = $this->makeProblem($rowNumber, $data, 'Kategori tidak terdaftar. Samakan nama kategori dengan data master.');

                continue;
            }

            $matchKey = (string) $entry['match_key'];
            if (($keyCounts[$matchKey] ?? 0) > 1) {
                $problems[] = $this->makeProblem($rowNumber, $data, 'Nama + kategori yang sama muncul lebih dari sekali di file ini.');

                continue;
            }

            $candidates = $existingByKey[$matchKey] ?? [];
            if (count($candidates) === 0) {
                $new[] = [
                    'row' => $rowNumber,
                    'name' => (string) $data['name'],
                    'category' => (string) $data['category'],
                    'stock_file' => (int) $data['stock'],
                    'price_general_file' => (int) round((float) $data['price_general']),
                ];

                continue;
            }

            if (count($candidates) > 1) {
                $problems[] = $this->makeProblem(
                    $rowNumber,
                    $data,
                    'Ada '.count($candidates).' barang dengan nama + kategori sama di database — pilih manual.',
                    array_map(fn ($product): array => [
                        'id' => (int) $product->id,
                        'code' => (string) $product->code,
                        'category' => (string) ($product->category->name ?? '-'),
                        'stock' => (int) $product->stock,
                    ], $candidates)
                );

                continue;
            }

            $product = $candidates[0];
            $matched[] = [
                'row' => $rowNumber,
                'product_id' => (int) $product->id,
                'code' => (string) $product->code,
                'name_db' => (string) $product->name,
                'name_file' => (string) $data['name'],
                'category' => (string) $data['category'],
                'stock_db' => (int) $product->stock,
                'stock_file' => (int) $data['stock'],
                'price_db' => [
                    'agent' => (int) $product->price_agent,
                    'sales' => (int) $product->price_sales,
                    'general' => (int) $product->price_general,
                ],
                'price_file' => [
                    'agent' => (int) round((float) $data['price_agent']),
                    'sales' => (int) round((float) $data['price_sales']),
                    'general' => (int) round((float) $data['price_general']),
                ],
            ];
        }

        return [
            'summary' => [
                'new' => count($new),
                'matched' => count($matched),
                'problems' => count($problems),
            ],
            'new' => $new,
            'matched' => $matched,
            'problems' => $problems,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    private function makeProblem(int $rowNumber, array $data, string $reason, array $candidates = []): array
    {
        return [
            'row' => $rowNumber,
            'name_file' => (string) ($data['name'] ?? ''),
            'reason' => $reason,
            'candidates' => $candidates,
            'data' => $data,
        ];
    }

    private function normalizeProductName(string $name): string
    {
        $normalized = strtolower(trim($name));

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    private function importTempRelativePath(int $userId, string $token): string
    {
        $safeToken = preg_replace('/[^a-zA-Z0-9\-]/', '', $token) ?? '';

        return 'import_tmp/products/'.$userId.'/'.$safeToken.'.xlsx';
    }

    private function pruneImportTempFiles(int $userId): void
    {
        $dir = 'import_tmp/products/'.$userId;
        $threshold = now()->subHours(6)->getTimestamp();
        foreach (Storage::disk('local')->files($dir) as $file) {
            if (Storage::disk('local')->lastModified($file) < $threshold) {
                Storage::disk('local')->delete($file);
            }
        }
    }

    private function recordImportStockMutation(int $productId, int $quantity, string $type, string $notes, Request $request): void
    {
        StockMutation::query()->create([
            'product_id' => $productId,
            'reference_type' => Product::class,
            'reference_id' => $productId,
            'mutation_type' => $type,
            'quantity' => $quantity,
            'notes' => $notes,
            'created_by_user_id' => $request->user()?->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productImportRules(): array
    {
        return [
            'name' => FluentRule::string()->required()->max(200),
            'category' => FluentRule::string()->required()->max(200),
            'unit' => FluentRule::string()->required()->max(30),
            'stock' => FluentRule::integer()->required()->min(0),
            'price_agent' => FluentRule::numeric()->required()->min(0),
            'price_sales' => FluentRule::numeric()->required()->min(0),
            'price_general' => FluentRule::numeric()->required()->min(0),
        ];
    }

    public function importCustomers(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => FluentRule::file()->required()->rule('mimes:xlsx,xls,csv,txt'),
        ]);

        $rows = $this->readSpreadsheetRows($request->file('import_file')->getRealPath());
        if ($rows === []) {
            return back()->with('error', 'File import kosong.');
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $missingHeaders = $this->missingHeaders($headers, ['name', 'phone', 'city', 'address']);
        if ($missingHeaders !== []) {
            return back()->with('error', 'Kolom wajib pada file import belum lengkap: '.implode(', ', $missingHeaders).'. Gunakan template import terbaru.');
        }

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
                    'name' => FluentRule::string()->required()->max(150),
                    'level' => FluentRule::string()->nullable()->max(120),
                    'phone' => FluentRule::string()->nullable()->max(30),
                    'city' => FluentRule::string()->nullable()->max(100),
                    'address' => FluentRule::string()->nullable(),
                    'notes' => FluentRule::string()->nullable(),
                ]);

                if ($validator->fails()) {
                    $errors[] = $this->formatImportRowError($rowIndex + 2, implode('; ', $validator->errors()->all()));

                    continue;
                }

                $levelId = $this->resolveCustomerLevelId((string) ($data['level'] ?? ''));
                $payload = [
                    'customer_level_id' => $levelId,
                    'name' => (string) $data['name'],
                    'phone' => (string) ($data['phone'] ?? ''),
                    'phone_secondary' => (string) ($data['phone_secondary'] ?? ''),
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

    public function importCategories(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => FluentRule::file()->required()->rule('mimes:xlsx,xls,csv,txt'),
        ]);

        $rows = $this->readSpreadsheetRows($request->file('import_file')->getRealPath());
        if ($rows === []) {
            return back()->with('error', 'File import kosong.');
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $missingHeaders = $this->missingHeaders($headers, ['name']);
        if ($missingHeaders !== []) {
            return back()->with('error', 'Kolom wajib pada file import belum lengkap: '.implode(', ', $missingHeaders).'. Gunakan template import terbaru.');
        }

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
                    'name' => FluentRule::string()->required()->max(150),
                    'description' => FluentRule::string()->nullable(),
                    'code' => FluentRule::string()->nullable()->max(50),
                ]);

                if ($validator->fails()) {
                    $errors[] = $this->formatImportRowError($rowIndex + 2, implode('; ', $validator->errors()->all()));

                    continue;
                }

                $name = trim((string) $data['name']);
                $code = $this->resolveImportCategoryCode((string) ($data['code'] ?? ''), $name);

                $existing = ItemCategory::query()
                    ->where('code', $code)
                    ->orWhere('name', $name)
                    ->first();

                $payload = [
                    'code' => $code,
                    'name' => $name,
                    'description' => (string) ($data['description'] ?? ''),
                ];

                if ($existing !== null) {
                    $existing->update($payload);
                    $updated++;

                    continue;
                }

                ItemCategory::query()->create($payload);
                $created++;
            }
        });

        $this->auditLogService->log(
            'master.category.import',
            null,
            "Import categories: created={$created}, updated={$updated}, errors=".count($errors),
            $request
        );

        return back()->with('success', "Import kategori selesai. Baru: {$created}, Update: {$updated}, Error: ".count($errors))
            ->with('import_errors', $errors);
    }

    public function importCustomerShipLocations(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => FluentRule::file()->required()->rule('mimes:xlsx,xls,csv,txt'),
        ]);

        $rows = $this->readSpreadsheetRows($request->file('import_file')->getRealPath());
        if ($rows === []) {
            return back()->with('error', 'File import kosong.');
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $missingHeaders = $this->missingHeaders($headers, ['customer', 'school_name']);
        if ($missingHeaders !== []) {
            return back()->with('error', 'Kolom wajib pada file import belum lengkap: '.implode(', ', $missingHeaders).'. Gunakan template import terbaru.');
        }

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
                    'customer' => FluentRule::string()->required()->max(150),
                    'school_name' => FluentRule::string()->required()->max(150),
                    'phone' => FluentRule::string()->nullable()->max(30),
                    'city' => FluentRule::string()->nullable()->max(100),
                    'address' => FluentRule::string()->nullable(),
                    'notes' => FluentRule::string()->nullable(),
                    'is_active' => FluentRule::field()->nullable(),
                ]);

                if ($validator->fails()) {
                    $errors[] = $this->formatImportRowError($rowIndex + 2, implode('; ', $validator->errors()->all()));

                    continue;
                }

                $customerLookup = trim((string) $data['customer']);
                $customer = Customer::query()
                    ->where('name', $customerLookup)
                    ->orWhere('code', $customerLookup)
                    ->first();
                if ($customer === null) {
                    $errors[] = $this->formatImportRowError($rowIndex + 2, 'Customer tidak terdaftar. Buat customer dulu atau samakan nama/kode customer.');

                    continue;
                }

                $payload = [
                    'customer_id' => (int) $customer->id,
                    'school_name' => trim((string) $data['school_name']),
                    'recipient_name' => null,
                    'recipient_phone' => (string) ($data['phone'] ?? ''),
                    'city' => (string) ($data['city'] ?? ''),
                    'address' => (string) ($data['address'] ?? ''),
                    'notes' => (string) ($data['notes'] ?? ''),
                    'is_active' => $this->importTruthy($data['is_active'] ?? '1'),
                ];

                $existing = CustomerShipLocation::query()
                    ->where('customer_id', (int) $customer->id)
                    ->where('school_name', $payload['school_name'])
                    ->first();

                if ($existing !== null) {
                    $existing->update($payload);
                    $updated++;

                    continue;
                }

                CustomerShipLocation::query()->create($payload);
                $created++;
            }
        });

        $this->auditLogService->log(
            'master.customer_ship_location.import',
            null,
            "Import ship locations: created={$created}, updated={$updated}, errors=".count($errors),
            $request
        );

        return back()->with('success', "Import lokasi kirim selesai. Baru: {$created}, Update: {$updated}, Error: ".count($errors))
            ->with('import_errors', $errors);
    }

    public function importSuppliers(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => FluentRule::file()->required()->rule('mimes:xlsx,xls,csv,txt'),
        ]);

        $rows = $this->readSpreadsheetRows($request->file('import_file')->getRealPath());
        if ($rows === []) {
            return back()->with('error', 'File import kosong.');
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $missingHeaders = $this->missingHeaders($headers, ['name']);
        if ($missingHeaders !== []) {
            return back()->with('error', 'Kolom wajib pada file import belum lengkap: '.implode(', ', $missingHeaders).'. Gunakan template import terbaru.');
        }

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
                    'name' => FluentRule::string()->required()->max(150),
                    'company_name' => FluentRule::string()->nullable()->max(200),
                    'phone' => FluentRule::string()->nullable()->max(30),
                    'address' => FluentRule::string()->nullable()->max(255),
                    'notes' => FluentRule::string()->nullable(),
                ]);

                if ($validator->fails()) {
                    $errors[] = $this->formatImportRowError($rowIndex + 2, implode('; ', $validator->errors()->all()));

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

    public function importSalesInvoices(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => FluentRule::file()->required()->rule('mimes:xlsx,xls,csv,txt'),
        ]);

        $rows = $this->readSpreadsheetRows($request->file('import_file')->getRealPath());
        if ($rows === []) {
            return back()->with('error', 'File import kosong.');
        }

        $headers = $this->normalizeHeaders(array_shift($rows) ?? []);
        $missingHeaders = $this->missingHeaders($headers, ['customer', 'invoice_date', 'payment_method', 'product', 'quantity', 'unit_price']);
        if ($missingHeaders !== []) {
            return back()->with('error', 'Kolom wajib pada file import belum lengkap: '.implode(', ', $missingHeaders).'. Gunakan template import terbaru.');
        }

        $errors = [];
        $created = 0;

        DB::transaction(function () use ($rows, $headers, &$errors, &$created): void {
            foreach ($rows as $rowIndex => $row) {
                $line = $rowIndex + 2;
                $data = $this->mapRow($headers, $row);
                if ($this->isEmptyRow($data)) {
                    continue;
                }

                $validator = Validator::make($data, [
                    'customer' => FluentRule::string()->required()->max(150),
                    'invoice_date' => FluentRule::date()->required(),
                    'due_date' => FluentRule::date()->nullable(),
                    'semester_period' => FluentRule::string()->nullable()->max(30),
                    'transaction_type' => FluentRule::field()->nullable()->rule('in:product,printing'),
                    'payment_method' => FluentRule::field()->required()->rule('in:tunai,kredit'),
                    'product' => FluentRule::string()->required()->max(200),
                    'quantity' => FluentRule::integer()->required()->min(1),
                    'unit_price' => FluentRule::numeric()->required()->min(0),
                    'discount' => FluentRule::numeric()->nullable()->min(0)->max(100),
                    'notes' => FluentRule::string()->nullable(),
                ]);

                if ($validator->fails()) {
                    $errors[] = $this->formatImportRowError($line, implode('; ', $validator->errors()->all()));

                    continue;
                }

                $customer = Customer::query()
                    ->where('name', (string) $data['customer'])
                    ->orWhere('code', (string) $data['customer'])
                    ->first();
                if ($customer === null) {
                    $errors[] = $this->formatImportRowError($line, 'Customer tidak terdaftar. Buat customer dulu atau samakan nama/kode customer.');

                    continue;
                }

                $product = Product::query()
                    ->where('code', (string) $data['product'])
                    ->orWhere('name', (string) $data['product'])
                    ->first();
                if ($product === null) {
                    $errors[] = $this->formatImportRowError($line, 'Barang tidak terdaftar. Buat barang dulu atau samakan nama/kode barang.');

                    continue;
                }

                $quantity = (int) $data['quantity'];
                if ((int) $product->stock < $quantity) {
                    $errors[] = $this->formatImportRowError($line, 'Stok barang '.$product->name.' tidak cukup untuk jumlah yang diimport.');

                    continue;
                }

                $invoiceDate = Carbon::parse((string) $data['invoice_date']);
                $invoiceNumber = $this->generateInvoiceNumber($invoiceDate->toDateString());
                $unitPrice = (float) round((float) $data['unit_price']);
                $discountPercent = max(0.0, min(100.0, (float) ($data['discount'] ?? 0)));
                $gross = $quantity * $unitPrice;
                $discount = (float) round($gross * ($discountPercent / 100));
                $lineTotal = max(0, $gross - $discount);

                $invoice = SalesInvoice::create([
                    'invoice_number' => $invoiceNumber,
                    'customer_id' => (int) $customer->id,
                    'invoice_date' => $invoiceDate->toDateString(),
                    'due_date' => $data['due_date'] ?? null,
                    'semester_period' => (string) ($data['semester_period'] ?? ''),
                    'transaction_type' => TransactionType::normalize((string) ($data['transaction_type'] ?? TransactionType::PRODUCT)),
                    'subtotal' => $lineTotal,
                    'total' => $lineTotal,
                    'total_paid' => 0,
                    'balance' => $lineTotal,
                    'payment_status' => 'unpaid',
                    'notes' => (string) ($data['notes'] ?? ''),
                ]);

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ]);

                $product->decrement('stock', $quantity);
                StockMutation::create([
                    'product_id' => $product->id,
                    'reference_type' => SalesInvoice::class,
                    'reference_id' => $invoice->id,
                    'mutation_type' => 'out',
                    'quantity' => $quantity,
                    'notes' => "Import sales invoice {$invoice->invoice_number}",
                    'created_by_user_id' => auth()->id(),
                ]);

                $this->receivableLedgerService->addDebit(
                    customerId: (int) $invoice->customer_id,
                    invoiceId: (int) $invoice->id,
                    entryDate: $invoiceDate,
                    amount: $lineTotal,
                    periodCode: $invoice->semester_period,
                    description: __('receivable.invoice_label').' '.$invoice->invoice_number,
                    transactionType: (string) $invoice->transaction_type
                );

                if ((string) $data['payment_method'] === 'tunai') {
                    InvoicePayment::create([
                        'sales_invoice_id' => $invoice->id,
                        'payment_date' => $invoiceDate->toDateString(),
                        'amount' => $lineTotal,
                        'method' => 'cash',
                        'notes' => 'Import pembayaran tunai',
                    ]);
                    $invoice->update([
                        'total_paid' => $lineTotal,
                        'balance' => 0,
                        'payment_status' => 'paid',
                    ]);
                    $this->receivableLedgerService->addCredit(
                        customerId: (int) $invoice->customer_id,
                        invoiceId: (int) $invoice->id,
                        entryDate: $invoiceDate,
                        amount: $lineTotal,
                        periodCode: $invoice->semester_period,
                        description: __('receivable.payment_for_invoice', ['invoice' => $invoice->invoice_number]),
                        transactionType: (string) $invoice->transaction_type
                    );
                }

                $this->accountingService->postSalesInvoice(
                    invoiceId: (int) $invoice->id,
                    date: $invoiceDate,
                    amount: (int) round($lineTotal),
                    paymentMethod: (string) $data['payment_method']
                );

                $created++;
            }
        });

        $this->auditLogService->log(
            'sales.invoice.import',
            null,
            "Import sales invoices: created={$created}, errors=".count($errors),
            $request
        );
        AppCache::forgetAfterFinancialMutation();

        return back()->with('success', "Import transaksi selesai. Baru: {$created}, Error: ".count($errors))
            ->with('import_errors', $errors);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, string>
     */
    private function normalizeHeaders(array $rows): array
    {
        $aliases = $this->headerAliases();

        return array_map(static function ($header): string {
            $normalized = strtolower(trim((string) $header));
            $normalized = str_replace([' ', '-'], '_', $normalized);

            return preg_replace('/[^a-z0-9_]/', '', $normalized) ?: '';
        }, array_map(static function ($header) use ($aliases): string {
            $normalized = strtolower(trim((string) $header));
            $normalized = str_replace([' ', '-'], '_', $normalized);
            $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized) ?: '';

            return $aliases[$normalized] ?? $normalized;
        }, $rows));
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $requiredHeaders
     * @return array<int, string>
     */
    private function missingHeaders(array $headers, array $requiredHeaders): array
    {
        $presentHeaders = array_values(array_unique(array_filter($headers)));
        $humanLabels = [
            'name' => 'Nama',
            'phone' => 'No HP 1',
            'phone_secondary' => 'No HP 2',
            'category' => 'Kategori',
            'unit' => 'Satuan',
            'stock' => 'Stok',
            'price_agent' => 'Harga Agen',
            'price_sales' => 'Harga Sales',
            'price_general' => 'Harga Umum',
            'customer' => 'Customer',
            'school_name' => 'Nama Sekolah',
            'invoice_date' => 'Tanggal Faktur',
            'payment_method' => 'Metode Pembayaran',
            'product' => 'Barang',
            'quantity' => 'Jumlah',
            'unit_price' => 'Harga Satuan',
        ];

        $missing = [];
        foreach ($requiredHeaders as $requiredHeader) {
            if (! in_array($requiredHeader, $presentHeaders, true)) {
                $missing[] = $humanLabels[$requiredHeader] ?? $requiredHeader;
            }
        }

        return $missing;
    }

    private function formatImportRowError(int $rowNumber, string $message): string
    {
        return 'Baris '.$rowNumber.': '.trim($message);
    }

    /**
     * @return array<string, string>
     */
    private function headerAliases(): array
    {
        return [
            'kode' => 'code',
            'nama' => 'name',
            'kategori' => 'category',
            'satuan' => 'unit',
            'stok' => 'stock',
            'hargaagen' => 'price_agent',
            'harga_agen' => 'price_agent',
            'hargasales' => 'price_sales',
            'harga_sales' => 'price_sales',
            'hargaumum' => 'price_general',
            'harga_umum' => 'price_general',
            'level_customer' => 'level',
            'levelcustomer' => 'level',
            'nohp' => 'phone',
            'no_hp' => 'phone',
            'nohp1' => 'phone',
            'no_hp_1' => 'phone',
            'nomorhp1' => 'phone',
            'telepon' => 'phone',
            'telepon1' => 'phone',
            'nohp2' => 'phone_secondary',
            'no_hp_2' => 'phone_secondary',
            'nomorhp2' => 'phone_secondary',
            'telepon2' => 'phone_secondary',
            'kota' => 'city',
            'alamat' => 'address',
            'catatan' => 'notes',
            'deskripsi' => 'description',
            'perusahaan' => 'company_name',
            'nama_perusahaan' => 'company_name',
            'supplier' => 'supplier',
            'pelanggan' => 'customer',
            'barang' => 'product',
            'namasekolah' => 'school_name',
            'nama_sekolah' => 'school_name',
            'aktif' => 'is_active',
            'jumlah' => 'quantity',
            'kuantitas' => 'quantity',
            'harga_satuan' => 'unit_price',
            'metode_pembayaran' => 'payment_method',
            'tanggal_faktur' => 'invoice_date',
            'tanggal_jatuh_tempo' => 'due_date',
            'semester' => 'semester_period',
            'tipe_transaksi' => 'transaction_type',
            'diskon_persen' => 'discount',
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $row
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
     * @param  array<string, mixed>  $row
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
        $normalized = Str::lower($value);

        return ItemCategory::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->orWhereRaw('LOWER(code) = ?', [$normalized])
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeProductImportNumbers(array $data): array
    {
        foreach (['stock', 'price_agent', 'price_sales', 'price_general'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->normalizeImportNumber($data[$field]);
                if ($field === 'stock' && is_numeric($data[$field]) && floor((float) $data[$field]) === (float) $data[$field]) {
                    $data[$field] = (string) (int) $data[$field];
                }
            }
        }

        return $data;
    }

    private function normalizeImportNumber(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace(["\xc2\xa0", ' ', 'Rp', 'rp', 'IDR', 'idr'], '', $normalized);

        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $normalized) === 1) {
            return str_replace('.', '', preg_replace('/,\d+$/', '', $normalized) ?? $normalized);
        }

        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $normalized) === 1) {
            return str_replace(',', '', preg_replace('/\.\d+$/', '', $normalized) ?? $normalized);
        }

        if (preg_match('/^-?\d+([.,]\d+)?$/', $normalized) === 1) {
            return str_replace(',', '.', $normalized);
        }

        return $normalized;
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
        $prefix = 'CUS-'.now()->format('Ymd');
        $count = Customer::query()
            ->where('code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function generateInvoiceNumber(string $date): string
    {
        $prefix = 'INV-'.date('dmY', strtotime($date));
        $count = SalesInvoice::query()
            ->whereDate('invoice_date', $date)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    private function resolveImportCategoryCode(string $rawCode, string $name): string
    {
        $candidate = trim($rawCode);
        if ($candidate === '') {
            $parts = preg_split('/\s+/', Str::lower(trim($name))) ?: [];
            $candidate = collect($parts)
                ->filter(fn (string $part): bool => $part !== '')
                ->map(fn (string $part): string => Str::ascii($part))
                ->map(fn (string $part): string => strlen($part) <= 3 ? $part : substr($part, 0, 3))
                ->implode('');
            $candidate = preg_replace('/[^a-z0-9]/', '', $candidate) ?: 'cat';
        }

        $base = strtolower(preg_replace('/[^a-z0-9]/', '', Str::ascii($candidate)) ?: 'cat');
        $code = $base;
        $suffix = 1;
        while (ItemCategory::query()->where('code', $code)->exists()) {
            $existingName = (string) (ItemCategory::query()->where('code', $code)->value('name') ?? '');
            if (strcasecmp($existingName, $name) === 0) {
                return $code;
            }
            $code = $base.$suffix;
            $suffix++;
        }

        return $code;
    }

    private function importTruthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'ya', 'aktif'], true);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function downloadTemplate(string $filename, array $rows, string $sheetTitle): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $sheetTitle): void {
            $spreadsheet = new Spreadsheet;
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
