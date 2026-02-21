<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\DeliveryTrip;
use App\Models\OutgoingTransaction;
use App\Models\OrderNote;
use App\Models\Product;
use App\Models\ReportExportTask;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Jobs\GenerateReportExportTaskJob;
use App\Support\AppCache;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    private const MAX_PENDING_EXPORT_TASKS_PER_USER = 5;

    public function __construct(
        private readonly SemesterBookService $semesterBookService
    ) {}

    public function index(Request $request): View
    {
        $now = now();
        $selectedSemester = $this->selectedSemester($request);
        $selectedCustomerId = $this->selectedCustomerId($request);
        $selectedUserRole = $this->selectedUserRole($request);
        $selectedFinanceLock = $this->selectedFinanceLock($request);
        $selectedOutgoingSupplierId = $this->selectedOutgoingSupplierId($request);
        $selectedTransactionType = $this->selectedTransactionType($request);
        $receivableCustomers = Cache::remember(AppCache::lookupCacheKey('reports.receivable_customers.options'), $now->copy()->addSeconds(60), function () {
            return Customer::query()
                ->onlyOptionColumns()
                ->orderBy('name')
                ->get();
        });
        $outgoingSuppliers = Cache::remember(AppCache::lookupCacheKey('reports.outgoing_suppliers.options'), $now->copy()->addSeconds(60), function () {
            return Supplier::query()
                ->onlyOptionColumns()
                ->orderBy('name')
                ->get();
        });

        return view('reports.index', [
            'datasets' => $this->datasets(),
            'selectedSemester' => $selectedSemester,
            'selectedCustomerId' => $selectedCustomerId,
            'selectedUserRole' => $selectedUserRole,
            'selectedFinanceLock' => $selectedFinanceLock,
            'selectedOutgoingSupplierId' => $selectedOutgoingSupplierId,
            'selectedTransactionType' => $selectedTransactionType,
            'semesterOptions' => $this->semesterOptions(),
            'semesterEnabledDatasets' => ['sales_invoices', 'sales_returns', 'delivery_notes', 'delivery_trips', 'order_notes', 'receivables', 'outgoing_transactions', 'balance_sheet', 'income_statement', 'semester_transactions'],
            'receivableCustomers' => $receivableCustomers,
            'outgoingSuppliers' => $outgoingSuppliers,
            'exportTasks' => ReportExportTask::query()
                ->where('user_id', (int) $request->user()->id)
                ->latest('id')
                ->limit(20)
                ->get(['id', 'dataset', 'format', 'status', 'file_name', 'error_message', 'created_at', 'generated_at']),
        ]);
    }

    public function queueExport(Request $request, string $dataset, string $format)
    {
        if (! array_key_exists($dataset, $this->datasets())) {
            abort(404);
        }

        $normalizedFormat = strtolower(trim($format));
        if (! in_array($normalizedFormat, ['pdf', 'excel'], true)) {
            abort(404);
        }

        $pendingTaskCount = ReportExportTask::query()
            ->where('user_id', (int) $request->user()->id)
            ->whereIn('status', ['queued', 'processing'])
            ->count();
        if ($pendingTaskCount >= self::MAX_PENDING_EXPORT_TASKS_PER_USER) {
            return redirect()
                ->route('reports.index', $request->query())
                ->with('error', __('report.export_queue_limit_reached'));
        }

        $task = ReportExportTask::create([
            'user_id' => (int) $request->user()->id,
            'dataset' => $dataset,
            'format' => $normalizedFormat,
            'status' => 'queued',
            'filters' => [
                'semester' => $this->selectedSemester($request),
                'customer_id' => $this->selectedCustomerId($request),
                'customer_ids' => $this->selectedCustomerIds($request),
                'user_role' => $this->selectedUserRole($request),
                'finance_lock' => $this->selectedFinanceLock($request),
                'outgoing_supplier_id' => $this->selectedOutgoingSupplierId($request),
                'transaction_type' => $this->selectedTransactionType($request),
            ],
        ]);

        GenerateReportExportTaskJob::dispatch((int) $task->id)->onQueue('exports');

        return redirect()
            ->route('reports.index', $request->query())
            ->with('success', __('report.export_queued_success'));
    }

    public function downloadQueuedExport(Request $request, ReportExportTask $task)
    {
        $this->authorizeTaskAccess($request, $task);
        if ((string) $task->status !== 'ready' || ! $task->file_path || ! Storage::disk('local')->exists((string) $task->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download((string) $task->file_path, (string) ($task->file_name ?: basename((string) $task->file_path)));
    }

    public function queuedExportsStatus(Request $request): JsonResponse
    {
        $tasks = ReportExportTask::query()
            ->where('user_id', (int) $request->user()->id)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'dataset', 'format', 'status', 'file_name', 'error_message', 'created_at'])
            ->map(function (ReportExportTask $task): array {
                $datasetKey = (string) $task->dataset;
                return [
                    'id' => (int) $task->id,
                    'dataset' => $datasetKey,
                    'dataset_label' => $this->datasets()[$datasetKey] ?? $datasetKey,
                    'format' => strtoupper((string) $task->format),
                    'status' => strtoupper((string) $task->status),
                    'created_at' => $task->created_at?->format('d-m-Y H:i'),
                    'download_url' => (string) route('reports.queue.download', $task),
                    'cancel_url' => (string) route('reports.queue.cancel', $task),
                    'error_message' => (string) ($task->error_message ?: ''),
                ];
            })
            ->values();

        return response()->json([
            'data' => $tasks,
        ]);
    }

    public function retryQueuedExport(Request $request, ReportExportTask $task)
    {
        $this->authorizeTaskAccess($request, $task);
        if ((string) $task->status !== 'failed') {
            return back()->with('error', 'Task belum berstatus failed.');
        }

        $task->update([
            'status' => 'queued',
            'error_message' => null,
        ]);
        GenerateReportExportTaskJob::dispatch((int) $task->id)->onQueue('exports');

        return back()->with('success', 'Retry export dimasukkan ke antrian.');
    }

    public function cancelQueuedExport(Request $request, ReportExportTask $task)
    {
        $this->authorizeTaskAccess($request, $task);
        if (! in_array((string) $task->status, ['queued', 'processing'], true)) {
            return back()->with('error', 'Task tidak bisa dibatalkan.');
        }

        $task->update([
            'status' => 'canceled',
            'error_message' => null,
        ]);

        return back()->with('success', 'Task export dibatalkan.');
    }

    public function exportCsv(Request $request, string $dataset): StreamedResponse
    {
        $selectedSemester = $this->selectedSemester($request);
        $selectedCustomerId = $this->selectedCustomerId($request);
        $selectedCustomerIds = $this->selectedCustomerIds($request);
        $selectedUserRole = $this->selectedUserRole($request);
        $selectedFinanceLock = $this->selectedFinanceLock($request);
        $selectedOutgoingSupplierId = $this->selectedOutgoingSupplierId($request);
        $report = $this->reportData($dataset, $selectedSemester, $selectedCustomerId, $selectedUserRole, $selectedFinanceLock, $selectedCustomerIds, $selectedOutgoingSupplierId, $this->selectedTransactionType($request));
        $printedAt = $this->nowWib();
        $filename = $dataset . '-' . $printedAt->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($report, $printedAt): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Report');
            $phoneHeaderKey = strtolower(__('report.columns.phone'));
            $rowCursor = 1;
            $sheet->setCellValue('A' . $rowCursor, (string) ($report['title'] ?? ''));
            $rowCursor++;
            $sheet->setCellValue('A' . $rowCursor, __('report.printed'));
            $sheet->setCellValue('B' . $rowCursor, $printedAt->format('d-m-Y H:i:s') . ' WIB');
            $rowCursor++;

            if (! empty($report['filters'])) {
                foreach ($report['filters'] as $filter) {
                    $sheet->setCellValue('A' . $rowCursor, (string) ($filter['label'] ?? ''));
                    $sheet->setCellValue('B' . $rowCursor, (string) ($filter['value'] ?? ''));
                    $rowCursor++;
                }
            }

            if (! empty($report['summary'])) {
                foreach ($report['summary'] as $item) {
                    $value = ($item['type'] ?? 'number') === 'currency'
                        ? 'Rp ' . number_format((int) round((float) ($item['value'] ?? 0)), 0, ',', '.')
                        : (int) round((float) ($item['value'] ?? 0));
                    $sheet->setCellValue('A' . $rowCursor, (string) ($item['label'] ?? ''));
                    $sheet->setCellValue('B' . $rowCursor, (string) $value);
                    $rowCursor++;
                }
            }

            $rowCursor++;
            $headerRowIndex = $rowCursor;
            $sheet->fromArray([$report['headers']], null, 'A' . $rowCursor);
            $rowCursor++;
            $isReceivableRecap = ($report['layout'] ?? null) === 'receivable_recap';
            foreach ($report['rows'] as $row) {
                $formatted = [];
                foreach ($report['headers'] as $index => $header) {
                    $value = $row[$index] ?? null;
                    $text = $value === null ? '' : (string) $value;
                    $headerKey = strtolower(trim($header));

                    // Keep phone values as text so Excel does not strip leading zeros.
                    if ($headerKey === $phoneHeaderKey && $text !== '') {
                        $text = "'" . $text;
                    }

                    if ($text !== '' && is_numeric($text) && $isReceivableRecap && $index >= 4) {
                        $text = number_format((int) round((float) $text), 0, ',', '.');
                    } elseif ($text !== '' && is_numeric($text) && $this->isNumericReportHeader($headerKey)) {
                        $text = number_format((int) round((float) $text), 0, ',', '.');
                    }

                    $formatted[] = $text;
                }
                $sheet->fromArray([$formatted], null, 'A' . $rowCursor);
                $rowCursor++;
            }

            $columnCount = count($report['headers']);
            $dataRowCount = max(0, $rowCursor - $headerRowIndex - 1);
            if ($columnCount > 0) {
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
                $lastDataRow = max($headerRowIndex, $headerRowIndex + $dataRowCount);

                $headerRange = 'A' . $headerRowIndex . ':' . $lastColumn . $headerRowIndex;
                $tableRange = 'A' . $headerRowIndex . ':' . $lastColumn . $lastDataRow;

                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F2937'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'BFC3C8'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_TOP,
                    ],
                ]);

                $sheet->freezePane('A' . ($headerRowIndex + 1));

                for ($col = 1; $col <= $columnCount; $col++) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
                }
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function print(Request $request, string $dataset): View
    {
        $printedAt = $this->nowWib();
        $report = $this->reportData(
            $dataset,
            $this->selectedSemester($request),
            $this->selectedCustomerId($request),
            $this->selectedUserRole($request),
            $this->selectedFinanceLock($request),
            $this->selectedCustomerIds($request),
            $this->selectedOutgoingSupplierId($request),
            $this->selectedTransactionType($request)
        );

        return view('reports.print', [
            'title' => $report['title'],
            'headers' => $report['headers'],
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'filters' => $report['filters'],
            'layout' => $report['layout'] ?? null,
            'receivableSemesterHeaders' => $report['receivable_semester_headers'] ?? [],
            'printedAt' => $printedAt,
        ]);
    }

    public function exportPdf(Request $request, string $dataset)
    {
        $printedAt = $this->nowWib();
        $report = $this->reportData(
            $dataset,
            $this->selectedSemester($request),
            $this->selectedCustomerId($request),
            $this->selectedUserRole($request),
            $this->selectedFinanceLock($request),
            $this->selectedCustomerIds($request),
            $this->selectedOutgoingSupplierId($request),
            $this->selectedTransactionType($request)
        );
        $filename = $dataset . '-' . $printedAt->format('Ymd-His') . '.pdf';

        $pdf = Pdf::loadView('reports.print', [
            'title' => $report['title'],
            'headers' => $report['headers'],
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'filters' => $report['filters'],
            'layout' => $report['layout'] ?? null,
            'receivableSemesterHeaders' => $report['receivable_semester_headers'] ?? [],
            'printedAt' => $printedAt,
            'isPdf' => true,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * @return array<string, string>
     */
    private function datasets(): array
    {
        return [
            'products' => __('report.datasets.products'),
            'customers' => __('report.datasets.customers'),
            'users' => __('report.datasets.users'),
            'sales_invoices' => __('report.datasets.sales_invoices'),
            'receivables' => __('report.datasets.receivables'),
            'sales_returns' => __('report.datasets.sales_returns'),
            'delivery_notes' => __('report.datasets.delivery_notes'),
            'delivery_trips' => __('report.datasets.delivery_trips'),
            'order_notes' => __('report.datasets.order_notes'),
            'outgoing_transactions' => __('report.datasets.outgoing_transactions'),
            'income_statement' => __('report.datasets.income_statement'),
            'balance_sheet' => __('report.datasets.balance_sheet'),
            'semester_transactions' => __('report.datasets.semester_transactions'),
        ];
    }

    private function nowWib(): Carbon
    {
        return now('Asia/Jakarta');
    }

    /**
     * @return array<int, string>
     */
    private function numericReportHeaders(): array
    {
        static $headers = null;

        if (is_array($headers)) {
            return $headers;
        }

        $headers = [
            strtolower(__('report.columns.stock')),
            'qty',
            strtolower(__('report.columns.total')),
            strtolower(__('report.columns.paid')),
            strtolower(__('report.columns.paid_cash')),
            strtolower(__('report.columns.paid_customer_balance')),
            strtolower(__('report.columns.balance')),
            strtolower(__('report.columns.customer_balance')),
            strtolower(__('report.columns.outstanding_receivable')),
            strtolower(__('report.columns.price_agent')),
            strtolower(__('report.columns.price_sales')),
            strtolower(__('report.columns.price_general')),
            strtolower(__('report.columns.debit')),
            strtolower(__('report.columns.credit')),
        ];

        return $headers;
    }

    private function isNumericReportHeader(string $header): bool
    {
        static $numericHeaderMap = null;

        if (! is_array($numericHeaderMap)) {
            $numericHeaderMap = array_fill_keys($this->numericReportHeaders(), true);
        }

        return isset($numericHeaderMap[$header]);
    }

    /**
     * @return array{
     *     title:string,
     *     headers:array<int,string>,
     *     rows:callable():array<int,array<int,string|int|float|null>>,
     *     layout?:string,
     *     receivable_semester_headers?:array<int,string>
     * }
     */
    private function datasetConfig(
        string $dataset,
        ?string $selectedSemester = null,
        ?int $selectedCustomerId = null,
        ?string $selectedUserRole = null,
        ?int $selectedFinanceLock = null,
        array $selectedCustomerIds = [],
        ?int $selectedOutgoingSupplierId = null,
        ?string $selectedTransactionType = null
    ): array {
        $semesterRange = $this->semesterDateRange($selectedSemester);
        $receivableSemesterCodes = $dataset === 'receivables'
            ? $this->receivableSemesterColumns($selectedSemester)->values()
            : collect();

        return match ($dataset) {
            'products' => [
                'title' => __('report.titles.products'),
                'headers' => [
                    __('report.columns.name'),
                    __('report.columns.category'),
                    __('report.columns.stock'),
                    __('report.columns.price_agent'),
                    __('report.columns.price_sales'),
                    __('report.columns.price_general'),
                ],
                'rows' => function (): array {
                    return Product::query()
                        ->onlyListColumns()
                        ->withCategoryInfo()
                        ->orderBy('name')
                        ->get()
                        ->map(fn(Product $row): array => [
                            $row->name,
                            $row->category?->name,
                            $row->stock,
                            (int) round((float) $row->price_agent),
                            (int) round((float) $row->price_sales),
                            (int) round((float) $row->price_general),
                        ])
                        ->all();
                },
            ],
            'customers' => [
                'title' => __('report.titles.customers'),
                'headers' => [
                    __('report.columns.name'),
                    __('report.columns.level'),
                    __('report.columns.phone'),
                    __('report.columns.city'),
                    __('report.columns.outstanding_receivable'),
                    __('report.columns.customer_balance'),
                ],
                'rows' => function () use ($selectedCustomerIds): array {
                    return Customer::query()
                        ->onlyListColumns()
                        ->withLevel()
                        ->when(count($selectedCustomerIds) > 0, function ($query) use ($selectedCustomerIds): void {
                            $query->whereIn('id', $selectedCustomerIds);
                        })
                        ->orderBy('name')
                        ->get()
                        ->map(fn(Customer $row): array => [
                            $row->name,
                            $row->level?->name,
                            $row->phone,
                            $row->city,
                            (int) round((float) $row->outstanding_receivable),
                            (int) round((float) $row->credit_balance),
                        ])
                        ->all();
                },
            ],
            'users' => [
                'title' => __('report.titles.users'),
                'headers' => [
                    __('report.columns.name'),
                    __('report.columns.email'),
                    __('report.columns.role'),
                    __('report.columns.locale'),
                    __('report.columns.theme'),
                    __('report.columns.finance_lock'),
                    __('report.columns.created_at'),
                ],
                'rows' => function () use ($selectedUserRole, $selectedFinanceLock): array {
                    return User::query()
                        ->onlyListColumns()
                        ->when($selectedUserRole !== null, function ($query) use ($selectedUserRole): void {
                            $query->inRole($selectedUserRole);
                        })
                        ->when($selectedFinanceLock !== null, function ($query) use ($selectedFinanceLock): void {
                            $query->financeLock((int) $selectedFinanceLock === 1);
                        })
                        ->orderBy('name')
                        ->get()
                        ->map(function (User $row): array {
                            $roleLabel = strtolower((string) $row->role) === 'admin'
                                ? __('report.values.role_admin')
                                : __('report.values.role_user');
                            $financeLockLabel = (bool) $row->finance_locked
                                ? __('ui.yes')
                                : __('ui.no');

                            return [
                                $row->name,
                                $row->email,
                                $roleLabel,
                                strtoupper((string) $row->locale),
                                ucfirst((string) $row->theme),
                                $financeLockLabel,
                                $row->created_at?->format('d-m-Y H:i'),
                            ];
                        })
                        ->all();
                },
            ],
            'sales_invoices' => [
                'title' => __('report.titles.sales_invoices'),
                'headers' => [
                    __('report.columns.invoice_no'),
                    __('report.columns.date'),
                    __('report.columns.customer'),
                    __('report.columns.phone'),
                    __('report.columns.city'),
                    __('report.columns.total'),
                    __('report.columns.paid'),
                    __('report.columns.payment_method'),
                    __('report.columns.status'),
                    __('report.columns.semester'),
                ],
                'rows' => function () use ($selectedSemester): array {
                    $invoices = SalesInvoice::query()
                        ->onlyListColumns()
                        ->withCustomerInfo()
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->forSemester($selectedSemester);
                        })
                        ->orderByDate()
                        ->get();

                    $invoiceIds = $invoices
                        ->pluck('id')
                        ->map(fn($id): int => (int) $id)
                        ->filter(fn(int $id): bool => $id > 0)
                        ->values()
                        ->all();

                    $paymentMethodMap = [];
                    if ($invoiceIds !== []) {
                        $paymentMethodMap = DB::table('invoice_payments')
                            ->select(['sales_invoice_id', 'method'])
                            ->whereIn('sales_invoice_id', $invoiceIds)
                            ->get()
                            ->groupBy('sales_invoice_id')
                            ->map(function ($rows): array {
                                return collect($rows)
                                    ->pluck('method')
                                    ->map(fn($method): string => strtolower((string) $method))
                                    ->filter(fn(string $method): bool => $method !== '')
                                    ->unique()
                                    ->values()
                                    ->all();
                            })
                            ->all();
                    }

                    return $invoices
                        ->map(function (SalesInvoice $row) use ($paymentMethodMap): array {
                            $methodCodes = (array) ($paymentMethodMap[(int) $row->id] ?? []);

                            return [
                                $row->invoice_number,
                                $row->invoice_date?->format('d-m-Y'),
                                $row->customer?->name,
                                $row->customer?->phone,
                                $row->customer?->city,
                                (int) round((float) $row->total),
                                (int) round((float) $row->total_paid),
                                $this->invoicePaymentMethodLabelFromCodes($methodCodes),
                                match ((string) $row->payment_status) {
                                    'paid' => __('txn.status_paid'),
                                    default => __('txn.status_unpaid'),
                                },
                                $row->semester_period,
                            ];
                        })
                        ->all();
                },
            ],
            'receivables' => [
                'title' => 'REKAP PIUTANG (GLOBAL)',
                'headers' => [],
                'layout' => 'receivable_recap',
                'receivable_semester_headers' => $receivableSemesterCodes
                    ->map(fn(string $code): string => $this->semesterDisplayLabel($code))
                    ->all(),
                'rows' => function () use ($selectedSemester, $selectedCustomerId, $receivableSemesterCodes): array {
                    $semesterCodes = $receivableSemesterCodes;
                    $header = [
                        'NO',
                        'NAMA KONSUMEN',
                        'ALAMAT',
                        'STATUS BUKU',
                        ...$semesterCodes->map(fn(string $code): string => $this->semesterDisplayLabel($code))->all(),
                        'TOTAL PIUTANG',
                    ];

                    $semesterAggregates = SalesInvoice::query()
                        ->selectRaw(
                            'customer_id, semester_period, COUNT(*) as invoice_count, ' .
                                'COALESCE(SUM(CASE WHEN is_canceled = 0 THEN balance ELSE 0 END), 0) as total_balance'
                        )
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->forSemester($selectedSemester);
                        })
                        ->when($selectedCustomerId !== null, function ($query) use ($selectedCustomerId): void {
                            $query->forCustomer($selectedCustomerId);
                        })
                        ->groupBy('customer_id', 'semester_period')
                        ->get();

                    $invoiceCountMap = [];
                    $balanceMap = [];
                    foreach ($semesterAggregates as $row) {
                        $customerKey = (int) $row->customer_id;
                        $period = (string) ($row->semester_period ?? '');
                        if ($period === '') {
                            continue;
                        }
                        $invoiceCountMap[$customerKey][$period] = (int) ($row->invoice_count ?? 0);
                        $balanceMap[$customerKey][$period] = (int) round((float) $row->total_balance);
                    }

                    $customers = Customer::query()
                        ->select(['id', 'name', 'address', 'city'])
                        ->when($selectedCustomerId !== null, function ($query) use ($selectedCustomerId): void {
                            $query->whereKey($selectedCustomerId);
                        })
                        ->orderBy('name')
                        ->get();
                    $selectedSemesterLockStates = $selectedSemester !== null
                        ? $this->semesterBookService()->customerSemesterLockStates(
                            $customers->pluck('id')->all(),
                            $selectedSemester
                        )
                        : [];

                    $rows = [];
                    $grandPerSemester = array_fill(0, $semesterCodes->count(), 0);
                    $grandTotal = 0;

                    foreach ($customers as $index => $customer) {
                        $rowTotal = 0;
                        $semesterValues = [];
                        foreach ($semesterCodes as $semesterIndex => $periodCode) {
                            $value = (int) ($balanceMap[(int) $customer->id][$periodCode] ?? 0);
                            $invoiceCount = (int) ($invoiceCountMap[(int) $customer->id][$periodCode] ?? 0);
                            $semesterValues[] = $value > 0 ? $value : ($invoiceCount > 0 ? 'LUNAS' : 0);
                            $rowTotal += $value;
                            $grandPerSemester[$semesterIndex] += $value;
                        }
                        $grandTotal += $rowTotal;
                        $bookStatus = '-';
                        if ($selectedSemester !== null) {
                            $state = $selectedSemesterLockStates[(int) $customer->id] ?? null;
                            if ($state !== null && (bool) ($state['locked'] ?? false)) {
                                $bookStatus = (bool) ($state['auto'] ?? false)
                                    ? __('receivable.customer_semester_locked_auto')
                                    : __('receivable.customer_semester_locked_manual');
                            } else {
                                $bookStatus = __('receivable.customer_semester_unlocked');
                            }
                        }

                        $rows[] = [
                            $index + 1,
                            (string) $customer->name,
                            (string) ($customer->address ?: '-'),
                            $bookStatus,
                            ...$semesterValues,
                            $rowTotal,
                        ];
                    }

                    $rows[] = [
                        'GRAND TOTAL PIUTANG',
                        '',
                        '',
                        '',
                        ...$grandPerSemester,
                        $grandTotal,
                    ];

                    return [
                        $header,
                        ...$rows,
                    ];
                },
            ],
            'sales_returns' => [
                'title' => __('report.titles.sales_returns'),
                'headers' => [
                    __('report.columns.return_no'),
                    __('report.columns.date'),
                    __('report.columns.customer'),
                    __('report.columns.phone'),
                    __('report.columns.city'),
                    __('report.columns.total'),
                    __('report.columns.semester'),
                ],
                'rows' => function () use ($selectedSemester): array {
                    return SalesReturn::query()
                        ->onlyListColumns()
                        ->withCustomerInfo()
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->forSemester($selectedSemester);
                        })
                        ->orderByDate()
                        ->get()
                        ->map(fn(SalesReturn $row): array => [
                            $row->return_number,
                            $row->return_date?->format('d-m-Y'),
                            $row->customer?->name,
                            $row->customer?->phone,
                            $row->customer?->city,
                            (int) round((float) $row->total),
                            $row->semester_period,
                        ])
                        ->all();
                },
            ],
            'delivery_notes' => [
                'title' => __('report.titles.delivery_notes'),
                'headers' => [
                    __('report.columns.note_no'),
                    __('report.columns.date'),
                    __('report.columns.recipient'),
                    __('report.columns.phone'),
                    __('report.columns.city'),
                    __('report.columns.created_by'),
                ],
                'rows' => function () use ($semesterRange): array {
                    return DeliveryNote::query()
                        ->onlyListColumns()
                        ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                            $query->betweenDates($semesterRange['start'], $semesterRange['end']);
                        })
                        ->orderByDate()
                        ->get()
                        ->map(fn(DeliveryNote $row): array => [
                            $row->note_number,
                            $row->note_date?->format('d-m-Y'),
                            $row->recipient_name,
                            $row->recipient_phone,
                            $row->city,
                            $row->created_by_name,
                        ])
                        ->all();
                },
            ],
            'delivery_trips' => [
                'title' => __('report.titles.delivery_trips'),
                'headers' => [
                    __('delivery_trip.trip_number'),
                    __('report.columns.date'),
                    __('delivery_trip.driver_name'),
                    __('delivery_trip.vehicle_plate'),
                    __('delivery_trip.member_count'),
                    __('delivery_trip.total_cost'),
                    __('report.columns.created_by'),
                ],
                'rows' => function () use ($semesterRange): array {
                    return DeliveryTrip::query()
                        ->with('creator:id,name')
                        ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                            $query->whereBetween('trip_date', [$semesterRange['start'], $semesterRange['end']]);
                        })
                        ->orderByDesc('trip_date')
                        ->orderByDesc('id')
                        ->get()
                        ->map(fn(DeliveryTrip $row): array => [
                            $row->trip_number,
                            $row->trip_date?->format('d-m-Y'),
                            $row->driver_name,
                            $row->vehicle_plate ?: '-',
                            (int) $row->member_count,
                            (int) $row->total_cost,
                            $row->creator?->name ?: '-',
                        ])
                        ->all();
                },
            ],
            'order_notes' => [
                'title' => __('report.titles.order_notes'),
                'headers' => [
                    __('report.columns.note_no'),
                    __('report.columns.date'),
                    __('report.columns.customer'),
                    __('report.columns.phone'),
                    __('report.columns.city'),
                    __('report.columns.created_by'),
                ],
                'rows' => function () use ($semesterRange): array {
                    return OrderNote::query()
                        ->onlyListColumns()
                        ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                            $query->betweenDates($semesterRange['start'], $semesterRange['end']);
                        })
                        ->orderByDate()
                        ->get()
                        ->map(fn(OrderNote $row): array => [
                            $row->note_number,
                            $row->note_date?->format('d-m-Y'),
                            $row->customer_name,
                            $row->customer_phone,
                            $row->city,
                            $row->created_by_name,
                        ])
                        ->all();
                },
            ],
            'outgoing_transactions' => [
                'title' => __('report.titles.outgoing_transactions'),
                'headers' => [
                    __('report.columns.transaction_no'),
                    __('report.columns.date'),
                    __('report.columns.note_no'),
                    __('report.columns.supplier'),
                    __('report.columns.phone'),
                    __('report.columns.total'),
                    __('report.columns.semester'),
                    __('report.columns.created_by'),
                ],
                'rows' => function () use ($selectedSemester, $selectedOutgoingSupplierId): array {
                    return OutgoingTransaction::query()
                        ->onlyListColumns()
                        ->withSupplierInfo()
                        ->withCreator()
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->forSemester($selectedSemester);
                        })
                        ->when($selectedOutgoingSupplierId !== null, function ($query) use ($selectedOutgoingSupplierId): void {
                            $query->forSupplier($selectedOutgoingSupplierId);
                        })
                        ->latest('transaction_date')
                        ->get()
                        ->map(fn(OutgoingTransaction $row): array => [
                            $row->transaction_number,
                            $row->transaction_date?->format('d-m-Y'),
                            $row->note_number,
                            $row->supplier?->name,
                            $row->supplier?->phone,
                            (int) round((float) $row->total),
                            $row->semester_period,
                            $row->creator?->name,
                        ])
                        ->all();
                },
            ],
            'income_statement' => [
                'title' => __('report.titles.income_statement'),
                'headers' => [
                    __('report.columns.account'),
                    __('report.columns.type'),
                    __('report.columns.debit'),
                    __('report.columns.credit'),
                    __('report.columns.balance'),
                ],
                'rows' => function () use ($semesterRange): array {
                    $query = DB::table('journal_entry_lines as jl')
                        ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
                        ->join('accounts as a', 'a.id', '=', 'jl.account_id')
                        ->whereIn('a.type', ['revenue', 'expense'])
                        ->selectRaw('a.code, a.name, a.type, COALESCE(SUM(jl.debit),0) as debit_total, COALESCE(SUM(jl.credit),0) as credit_total')
                        ->groupBy('a.code', 'a.name', 'a.type')
                        ->orderBy('a.code');

                    if ($semesterRange !== null) {
                        $query->whereBetween('je.entry_date', [$semesterRange['start'], $semesterRange['end']]);
                    }

                    return $query->get()->map(function ($row): array {
                        $debit = (int) round((float) $row->debit_total);
                        $credit = (int) round((float) $row->credit_total);
                        $balance = $credit - $debit;
                        return [
                            "{$row->code} - {$row->name}",
                            ucfirst((string) $row->type),
                            $debit,
                            $credit,
                            $balance,
                        ];
                    })->all();
                },
            ],
            'balance_sheet' => [
                'title' => __('report.titles.balance_sheet'),
                'headers' => [
                    __('report.columns.account'),
                    __('report.columns.type'),
                    __('report.columns.balance'),
                ],
                'rows' => function () use ($semesterRange): array {
                    $query = DB::table('journal_entry_lines as jl')
                        ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
                        ->join('accounts as a', 'a.id', '=', 'jl.account_id')
                        ->whereIn('a.type', ['asset', 'liability', 'equity'])
                        ->selectRaw('a.code, a.name, a.type, COALESCE(SUM(jl.debit),0) as debit_total, COALESCE(SUM(jl.credit),0) as credit_total')
                        ->groupBy('a.code', 'a.name', 'a.type')
                        ->orderBy('a.type')
                        ->orderBy('a.code');

                    if ($semesterRange !== null) {
                        $query->whereBetween('je.entry_date', [$semesterRange['start'], $semesterRange['end']]);
                    }

                    return $query->get()->map(function ($row): array {
                        $debit = (int) round((float) $row->debit_total);
                        $credit = (int) round((float) $row->credit_total);
                        $balance = in_array((string) $row->type, ['liability', 'equity'], true)
                            ? ($credit - $debit)
                            : ($debit - $credit);
                        return [
                            "{$row->code} - {$row->name}",
                            ucfirst((string) $row->type),
                            $balance,
                        ];
                    })->all();
                },
            ],
            'semester_transactions' => [
                'title' => __('report.titles.semester_transactions'),
                'headers' => [
                    __('report.columns.date'),
                    __('report.columns.type'),
                    __('report.columns.note_no'),
                    __('report.columns.customer'),
                    __('report.columns.city'),
                    __('report.columns.total'),
                    __('report.columns.status'),
                ],
                'rows' => function () use ($selectedSemester, $selectedTransactionType): array {
                    $semester = $selectedSemester ?? $this->semesterBookService()->currentSemester();
                    $semesterRange = $this->semesterDateRange($semester);
                    if ($semesterRange === null) {
                        return [];
                    }
                    $start = Carbon::parse($semesterRange['start']);
                    $end = Carbon::parse($semesterRange['end']);
                    $type = $selectedTransactionType ?? 'all';

                    $invoiceQuery = DB::table('sales_invoices as si')
                        ->leftJoin('customers as c', 'c.id', '=', 'si.customer_id')
                        ->selectRaw("'sales_invoice' as tx_type, si.id as tx_id, si.invoice_date as tx_date, si.invoice_number as tx_number, COALESCE(c.name, '-') as party_name, COALESCE(c.city, '-') as city, si.total as amount, si.is_canceled as is_canceled")
                        ->where('si.semester_period', $semester);
                    $returnQuery = DB::table('sales_returns as sr')
                        ->leftJoin('customers as c', 'c.id', '=', 'sr.customer_id')
                        ->selectRaw("'sales_return' as tx_type, sr.id as tx_id, sr.return_date as tx_date, sr.return_number as tx_number, COALESCE(c.name, '-') as party_name, COALESCE(c.city, '-') as city, sr.total as amount, sr.is_canceled as is_canceled")
                        ->where('sr.semester_period', $semester);
                    $deliveryQuery = DB::table('delivery_notes as dn')
                        ->selectRaw("'delivery_note' as tx_type, dn.id as tx_id, dn.note_date as tx_date, dn.note_number as tx_number, COALESCE(dn.recipient_name, '-') as party_name, COALESCE(dn.city, '-') as city, NULL as amount, dn.is_canceled as is_canceled")
                        ->whereBetween('dn.note_date', [$start, $end]);
                    $deliveryTripQuery = DB::table('delivery_trips as dt')
                        ->selectRaw("'delivery_trip' as tx_type, dt.id as tx_id, dt.trip_date as tx_date, dt.trip_number as tx_number, COALESCE(dt.driver_name, '-') as party_name, '-' as city, dt.total_cost as amount, 0 as is_canceled")
                        ->whereNull('dt.deleted_at')
                        ->whereBetween('dt.trip_date', [$start, $end]);
                    $orderQuery = DB::table('order_notes as onote')
                        ->selectRaw("'order_note' as tx_type, onote.id as tx_id, onote.note_date as tx_date, onote.note_number as tx_number, COALESCE(onote.customer_name, '-') as party_name, COALESCE(onote.city, '-') as city, NULL as amount, onote.is_canceled as is_canceled")
                        ->whereBetween('onote.note_date', [$start, $end]);
                    $outgoingQuery = DB::table('outgoing_transactions as ot')
                        ->leftJoin('suppliers as s', 's.id', '=', 'ot.supplier_id')
                        ->selectRaw("'outgoing_transaction' as tx_type, ot.id as tx_id, ot.transaction_date as tx_date, ot.transaction_number as tx_number, COALESCE(s.name, s.company_name, '-') as party_name, '-' as city, ot.total as amount, 0 as is_canceled")
                        ->where('ot.semester_period', $semester);
                    $receivablePaymentQuery = DB::table('receivable_payments as rp')
                        ->leftJoin('customers as c', 'c.id', '=', 'rp.customer_id')
                        ->selectRaw("'receivable_payment' as tx_type, rp.id as tx_id, rp.payment_date as tx_date, rp.payment_number as tx_number, COALESCE(c.name, '-') as party_name, COALESCE(c.city, '-') as city, rp.amount as amount, rp.is_canceled as is_canceled")
                        ->whereBetween('rp.payment_date', [$start, $end]);

                    $union = $invoiceQuery
                        ->unionAll($returnQuery)
                        ->unionAll($deliveryQuery)
                        ->unionAll($deliveryTripQuery)
                        ->unionAll($orderQuery)
                        ->unionAll($outgoingQuery)
                        ->unionAll($receivablePaymentQuery);
                    $query = DB::query()->fromSub($union, 'semester_transactions');
                    if ($type !== 'all' && in_array($type, ['sales_invoice', 'sales_return', 'delivery_note', 'delivery_trip', 'order_note', 'outgoing_transaction', 'receivable_payment'], true)) {
                        $query->where('tx_type', $type);
                    }

                    return $query->orderByDesc('tx_date')
                        ->orderByDesc('tx_id')
                        ->get()
                        ->map(function ($row): array {
                            return [
                                $row->tx_date ? Carbon::parse((string) $row->tx_date)->format('d-m-Y') : '-',
                                (string) $row->tx_type,
                                (string) $row->tx_number,
                                (string) $row->party_name,
                                (string) $row->city,
                                $row->amount === null ? 0 : (int) round((float) $row->amount),
                                ((bool) $row->is_canceled) ? __('txn.status_canceled') : __('txn.status_active'),
                            ];
                        })
                        ->all();
                },
            ],
            default => abort(404),
        };
    }

    /**
     * @return array{title:string,headers:array<int,string>,rows:array<int,array<int,string|int|float|null>>,summary:array<int,array{label:string,value:int|float,type:string}>|null,filters:array<int,array{label:string,value:string}>|null}
     */
    public function reportData(
        string $dataset,
        ?string $selectedSemester = null,
        ?int $selectedCustomerId = null,
        ?string $selectedUserRole = null,
        ?int $selectedFinanceLock = null,
        array $selectedCustomerIds = [],
        ?int $selectedOutgoingSupplierId = null,
        ?string $selectedTransactionType = null
    ): array {
        $config = $this->datasetConfig($dataset, $selectedSemester, $selectedCustomerId, $selectedUserRole, $selectedFinanceLock, $selectedCustomerIds, $selectedOutgoingSupplierId, $selectedTransactionType);
        $rows = $config['rows']();
        $headers = $config['headers'];
        $layout = $config['layout'] ?? null;
        $receivableSemesterHeaders = $config['receivable_semester_headers'] ?? [];

        if ($layout === 'receivable_recap' && count($rows) > 0) {
            /** @var array<int, string> $computedHeaders */
            $computedHeaders = array_map(fn($value): string => (string) $value, $rows[0]);
            $headers = $computedHeaders;
            $rows = array_values(array_slice($rows, 1));
        }

        $summary = null;
        $filters = null;
        if ($dataset === 'customers') {
            $summary = $this->customerSummary($selectedCustomerIds);
            $filters = $this->customerFilters($selectedCustomerIds);
        }
        if ($dataset === 'users') {
            $summary = $this->userSummary($selectedUserRole, $selectedFinanceLock);
            $filters = $this->userFilters($selectedUserRole, $selectedFinanceLock);
        }
        if ($dataset === 'sales_invoices') {
            $summary = $this->salesInvoiceSummary($selectedSemester);
            $filters = $this->salesInvoiceFilters($selectedSemester);
        }
        if ($dataset === 'outgoing_transactions') {
            $summary = $this->outgoingTransactionSummary($selectedSemester, $selectedOutgoingSupplierId);
            $filters = $this->outgoingTransactionFilters($selectedSemester, $selectedOutgoingSupplierId);
        }
        if ($dataset === 'receivables') {
            $filters = $this->receivableFilters($selectedSemester, $selectedCustomerId);
        }
        if ($dataset === 'income_statement' || $dataset === 'balance_sheet') {
            $filters = [[
                'label' => __('report.filters.semester'),
                'value' => $selectedSemester ?? __('report.all_semesters'),
            ]];
        }
        if ($dataset === 'semester_transactions') {
            $filters = [
                [
                    'label' => __('report.filters.semester'),
                    'value' => $selectedSemester ?? __('report.all_semesters'),
                ],
                [
                    'label' => __('report.filters.type'),
                    'value' => $selectedTransactionType ?? 'all',
                ],
            ];
        }
        if ($dataset === 'income_statement') {
            $summary = $this->incomeStatementSummary($selectedSemester);
        }
        if ($dataset === 'balance_sheet') {
            $summary = $this->balanceSheetSummary($selectedSemester);
        }

        return [
            'title' => $config['title'],
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters,
            'layout' => $layout,
            'receivable_semester_headers' => $receivableSemesterHeaders,
        ];
    }

    private function selectedSemester(Request $request): ?string
    {
        $semester = trim((string) $request->string('semester', ''));
        if ($semester === '') {
            return null;
        }
        $normalized = $this->semesterBookService()->normalizeSemester($semester);
        if ($normalized === null) {
            return null;
        }

        return $this->semesterBookService()->isActive($normalized) ? $normalized : null;
    }

    private function selectedCustomerId(Request $request): ?int
    {
        $customerId = $request->integer('customer_id');

        return $customerId > 0 ? $customerId : null;
    }

    /**
     * @return array<int, int>
     */
    private function selectedCustomerIds(Request $request): array
    {
        $input = $request->input('customer_ids', []);
        $ids = is_array($input) ? $input : [$input];

        return collect($ids)
            ->map(fn($id): int => (int) $id)
            ->filter(fn(int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function selectedOutgoingSupplierId(Request $request): ?int
    {
        $supplierId = $request->integer('outgoing_supplier_id');
        if ($supplierId <= 0) {
            return null;
        }

        return Supplier::query()->whereKey($supplierId)->exists() ? $supplierId : null;
    }

    private function selectedUserRole(Request $request): ?string
    {
        $role = strtolower(trim((string) $request->string('user_role', '')));

        return in_array($role, ['admin', 'user'], true) ? $role : null;
    }

    private function selectedFinanceLock(Request $request): ?int
    {
        $raw = trim((string) $request->string('finance_lock', ''));
        if ($raw === '') {
            return null;
        }

        return in_array($raw, ['0', '1'], true) ? (int) $raw : null;
    }

    /**
     * @return array<int, array{label:string,value:int|float,type:string}>
     */
    private function incomeStatementSummary(?string $selectedSemester): array
    {
        $range = $this->semesterDateRange($selectedSemester);
        $query = DB::table('journal_entry_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('a.type', ['revenue', 'expense'])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN a.type='revenue' THEN jl.credit - jl.debit ELSE 0 END),0) as revenue_total,
                COALESCE(SUM(CASE WHEN a.type='expense' THEN jl.debit - jl.credit ELSE 0 END),0) as expense_total
            ");
        if ($range !== null) {
            $query->whereBetween('je.entry_date', [$range['start'], $range['end']]);
        }
        $row = $query->first();
        $revenue = (int) round((float) ($row->revenue_total ?? 0));
        $expense = (int) round((float) ($row->expense_total ?? 0));
        $net = $revenue - $expense;

        return [
            ['label' => 'Total Pendapatan', 'value' => $revenue, 'type' => 'currency'],
            ['label' => 'Total Beban', 'value' => $expense, 'type' => 'currency'],
            ['label' => 'Laba / Rugi Bersih', 'value' => $net, 'type' => 'currency'],
        ];
    }

    /**
     * @return array<int, array{label:string,value:int|float,type:string}>
     */
    private function balanceSheetSummary(?string $selectedSemester): array
    {
        $range = $this->semesterDateRange($selectedSemester);
        $query = DB::table('journal_entry_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('a.type', ['asset', 'liability', 'equity'])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN a.type='asset' THEN jl.debit - jl.credit ELSE 0 END),0) as asset_total,
                COALESCE(SUM(CASE WHEN a.type='liability' THEN jl.credit - jl.debit ELSE 0 END),0) as liability_total,
                COALESCE(SUM(CASE WHEN a.type='equity' THEN jl.credit - jl.debit ELSE 0 END),0) as equity_total
            ");
        if ($range !== null) {
            $query->whereBetween('je.entry_date', [$range['start'], $range['end']]);
        }
        $row = $query->first();
        $asset = (int) round((float) ($row->asset_total ?? 0));
        $liability = (int) round((float) ($row->liability_total ?? 0));
        $equity = (int) round((float) ($row->equity_total ?? 0));

        return [
            ['label' => 'Total Aset', 'value' => $asset, 'type' => 'currency'],
            ['label' => 'Total Liabilitas', 'value' => $liability, 'type' => 'currency'],
            ['label' => 'Total Ekuitas', 'value' => $equity, 'type' => 'currency'],
            ['label' => 'Liabilitas + Ekuitas', 'value' => $liability + $equity, 'type' => 'currency'],
        ];
    }

    private function selectedTransactionType(Request $request): ?string
    {
        $type = strtolower(trim((string) $request->string('transaction_type', '')));
        if ($type === '') {
            return null;
        }

        return in_array($type, ['all', 'sales_invoice', 'sales_return', 'delivery_note', 'delivery_trip', 'order_note', 'outgoing_transaction', 'receivable_payment'], true)
            ? $type
            : null;
    }

    /**
     * @return array<int, array{label:string,value:int|float,type:string}>
     */
    private function receivableSummary(?string $selectedSemester, ?int $selectedCustomerId): array
    {
        $aggregate = SalesInvoice::query()
            ->where('balance', '>', 0)
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->forSemester($selectedSemester);
            })
            ->when($selectedCustomerId !== null, function ($query) use ($selectedCustomerId): void {
                $query->forCustomer($selectedCustomerId);
            })
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(balance), 0) as total_balance')
            ->first();
        $ledgerAggregate = DB::table('receivable_ledgers')
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->where('period_code', $selectedSemester);
            })
            ->when($selectedCustomerId !== null, function ($query) use ($selectedCustomerId): void {
                $query->where('customer_id', $selectedCustomerId);
            })
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as ledger_balance')
            ->first();

        $totalOutstanding = (float) ($aggregate?->total_balance ?? 0);
        $ledgerBalance = (float) ($ledgerAggregate?->ledger_balance ?? 0);
        $integrationDifference = $totalOutstanding - $ledgerBalance;

        return [
            [
                'label' => __('report.receivable_summary.total_unpaid_invoices'),
                'value' => (int) ($aggregate?->invoice_count ?? 0),
                'type' => 'number',
            ],
            [
                'label' => __('report.receivable_summary.total_outstanding'),
                'value' => $totalOutstanding,
                'type' => 'currency',
            ],
            [
                'label' => __('report.receivable_summary.ledger_mutation_balance'),
                'value' => $ledgerBalance,
                'type' => 'currency',
            ],
            [
                'label' => __('report.receivable_summary.integration_difference'),
                'value' => $integrationDifference,
                'type' => 'currency',
            ],
        ];
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    private function receivableFilters(?string $selectedSemester, ?int $selectedCustomerId): array
    {
        $customerName = __('report.all_customers');
        if ($selectedCustomerId !== null) {
            $customerName = Customer::query()
                ->whereKey($selectedCustomerId)
                ->value('name') ?? __('report.all_customers');
        }

        return [
            [
                'label' => __('report.filters.semester'),
                'value' => $selectedSemester ?? __('report.all_semesters'),
            ],
            [
                'label' => __('report.filters.customer'),
                'value' => $customerName,
            ],
        ];
    }

    /**
     * @return array<int, array{label:string,value:int|float,type:string}>
     */
    private function salesInvoiceSummary(?string $selectedSemester): array
    {
        $invoiceAggregate = SalesInvoice::query()
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->forSemester($selectedSemester);
            })
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(total), 0) as grand_total, COALESCE(SUM(total_paid), 0) as paid_total')
            ->first();
        $paidTotal = (float) ($invoiceAggregate?->paid_total ?? 0);

        return [
            [
                'label' => __('report.sales_invoice_summary.total_invoices'),
                'value' => (int) ($invoiceAggregate?->invoice_count ?? 0),
                'type' => 'number',
            ],
            [
                'label' => __('report.sales_invoice_summary.grand_total'),
                'value' => (float) ($invoiceAggregate?->grand_total ?? 0),
                'type' => 'currency',
            ],
            [
                'label' => __('report.sales_invoice_summary.total_paid'),
                'value' => $paidTotal,
                'type' => 'currency',
            ],
        ];
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    private function salesInvoiceFilters(?string $selectedSemester): array
    {
        return [
            [
                'label' => __('report.filters.semester'),
                'value' => $selectedSemester ?? __('report.all_semesters'),
            ],
        ];
    }

    /**
     * @return array<int, array{label:string,value:int|float,type:string}>
     */
    private function outgoingTransactionSummary(?string $selectedSemester, ?int $selectedOutgoingSupplierId = null): array
    {
        $aggregate = OutgoingTransaction::query()
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->forSemester($selectedSemester);
            })
            ->when($selectedOutgoingSupplierId !== null, function ($query) use ($selectedOutgoingSupplierId): void {
                $query->forSupplier($selectedOutgoingSupplierId);
            })
            ->selectRaw('COUNT(*) as transaction_count, COALESCE(SUM(total), 0) as grand_total')
            ->first();

        return [
            [
                'label' => __('report.outgoing_summary.total_transactions'),
                'value' => (int) ($aggregate?->transaction_count ?? 0),
                'type' => 'number',
            ],
            [
                'label' => __('report.outgoing_summary.grand_total'),
                'value' => (float) ($aggregate?->grand_total ?? 0),
                'type' => 'currency',
            ],
        ];
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    private function outgoingTransactionFilters(?string $selectedSemester, ?int $selectedOutgoingSupplierId = null): array
    {
        $supplierName = __('report.all_suppliers');
        if ($selectedOutgoingSupplierId !== null) {
            $supplierName = Supplier::query()
                ->whereKey($selectedOutgoingSupplierId)
                ->value('name') ?? __('report.all_suppliers');
        }

        return [
            [
                'label' => __('report.filters.semester'),
                'value' => $selectedSemester ?? __('report.all_semesters'),
            ],
            [
                'label' => __('report.filters.supplier'),
                'value' => $supplierName,
            ],
        ];
    }

    /**
     * @return array<int, array{label:string,value:int|float,type:string}>
     */
    private function customerSummary(array $selectedCustomerIds = []): array
    {
        $aggregate = Customer::query()
            ->when(count($selectedCustomerIds) > 0, function ($query) use ($selectedCustomerIds): void {
                $query->whereIn('id', $selectedCustomerIds);
            })
            ->selectRaw('COUNT(*) as customer_count, COALESCE(SUM(outstanding_receivable), 0) as total_outstanding, COALESCE(SUM(credit_balance), 0) as total_customer_balance')
            ->first();

        return [
            [
                'label' => __('report.customer_summary.total_customers'),
                'value' => (int) ($aggregate?->customer_count ?? 0),
                'type' => 'number',
            ],
            [
                'label' => __('report.customer_summary.total_outstanding'),
                'value' => (float) ($aggregate?->total_outstanding ?? 0),
                'type' => 'currency',
            ],
            [
                'label' => __('report.customer_summary.total_customer_balance'),
                'value' => (float) ($aggregate?->total_customer_balance ?? 0),
                'type' => 'currency',
            ],
        ];
    }

    /**
     * @param array<int, int> $selectedCustomerIds
     * @return array<int, array{label:string,value:string}>
     */
    private function customerFilters(array $selectedCustomerIds = []): array
    {
        if (count($selectedCustomerIds) === 0) {
            return [[
                'label' => __('report.customer_filter'),
                'value' => __('report.all_customers'),
            ]];
        }

        $selectedCount = count($selectedCustomerIds);
        $names = Customer::query()
            ->whereIn('id', $selectedCustomerIds)
            ->orderBy('name')
            ->limit(3)
            ->pluck('name')
            ->all();

        $display = implode(', ', $names);
        if ($selectedCount > 3) {
            $display .= ' +' . ($selectedCount - 3);
        }

        return [[
            'label' => __('report.customer_filter'),
            'value' => $display,
        ]];
    }

    /**
     * @return array<int, array{label:string,value:int|float,type:string}>
     */
    private function userSummary(?string $selectedUserRole = null, ?int $selectedFinanceLock = null): array
    {
        $aggregate = User::query()
            ->when($selectedUserRole !== null, function ($query) use ($selectedUserRole): void {
                $query->inRole($selectedUserRole);
            })
            ->when($selectedFinanceLock !== null, function ($query) use ($selectedFinanceLock): void {
                $query->financeLock((int) $selectedFinanceLock === 1);
            })
            ->selectRaw(
                "COUNT(*) as total_users,
                COALESCE(SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END), 0) as total_admins,
                COALESCE(SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END), 0) as total_standard_users,
                COALESCE(SUM(CASE WHEN finance_locked = 1 THEN 1 ELSE 0 END), 0) as total_finance_locked"
            )
            ->first();

        return [
            [
                'label' => __('report.user_summary.total_users'),
                'value' => (int) ($aggregate?->total_users ?? 0),
                'type' => 'number',
            ],
            [
                'label' => __('report.user_summary.total_admins'),
                'value' => (int) ($aggregate?->total_admins ?? 0),
                'type' => 'number',
            ],
            [
                'label' => __('report.user_summary.total_standard_users'),
                'value' => (int) ($aggregate?->total_standard_users ?? 0),
                'type' => 'number',
            ],
            [
                'label' => __('report.user_summary.total_finance_locked'),
                'value' => (int) ($aggregate?->total_finance_locked ?? 0),
                'type' => 'number',
            ],
        ];
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    private function userFilters(?string $selectedUserRole = null, ?int $selectedFinanceLock = null): array
    {
        $roleValue = match ($selectedUserRole) {
            'admin' => __('report.values.role_admin'),
            'user' => __('report.values.role_user'),
            default => __('report.all_roles'),
        };

        $financeLockValue = match ($selectedFinanceLock) {
            1 => __('report.finance_lock_yes'),
            0 => __('report.finance_lock_no'),
            default => __('report.all_finance_lock'),
        };

        return [
            [
                'label' => __('report.user_role_filter'),
                'value' => $roleValue,
            ],
            [
                'label' => __('report.finance_lock_filter'),
                'value' => $financeLockValue,
            ],
        ];
    }

    private function semesterOptions(): array
    {
        $now = now();
        return Cache::remember(AppCache::lookupCacheKey('reports.semester_options'), $now->copy()->addSeconds(60), function (): array {
            return $this->semesterBookService()->buildSemesterOptionCollection(
                SalesInvoice::query()
                    ->whereNotNull('semester_period')
                    ->where('semester_period', '!=', '')
                    ->distinct()
                    ->pluck('semester_period')
                    ->merge(
                        SalesReturn::query()
                            ->whereNotNull('semester_period')
                            ->where('semester_period', '!=', '')
                            ->distinct()
                            ->pluck('semester_period')
                    )
                    ->merge(
                        OutgoingTransaction::query()
                            ->whereNotNull('semester_period')
                            ->where('semester_period', '!=', '')
                            ->distinct()
                            ->pluck('semester_period')
                    )
                    ->merge($this->semesterBookService()->configuredSemesterOptions()),
                true,
                true
            )->all();
        });
    }

    /**
     * @return array{start:string,end:string}|null
     */
    private function semesterDateRange(?string $period): ?array
    {
        return $this->semesterBookService()->semesterDateRange($period);
    }

    private function receivableSemesterColumns(?string $selectedSemester)
    {
        if ($selectedSemester !== null) {
            return collect([$selectedSemester]);
        }

        $columns = SalesInvoice::query()
            ->whereNotNull('semester_period')
            ->where('semester_period', '!=', '')
            ->distinct()
            ->pluck('semester_period')
            ->merge($this->semesterBookService()->configuredSemesterOptions())
            ->values();
        $normalized = $this->semesterBookService()->buildSemesterOptionCollection(
            $columns,
            true,
            false
        );

        return $normalized
            ->sortBy(fn(string $item): int => $this->semesterSortValue($item))
            ->values();
    }

    private function semesterSortValue(string $period): int
    {
        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $period, $matches) === 1) {
            $semester = (int) $matches[1];
            $startYear = 2000 + (int) $matches[2];
            return ($startYear * 10) + $semester;
        }
        return PHP_INT_MAX;
    }

    private function semesterDisplayLabel(string $period): string
    {
        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $period, $matches) === 1) {
            $semester = (int) $matches[1];
            $startYear = 2000 + (int) $matches[2];
            $endYear = 2000 + (int) $matches[3];
            return "Smt {$semester} ({$startYear}-{$endYear})";
        }
        return $period;
    }

    /**
     * @param array<int, string> $paymentMethodCodes
     */
    private function invoicePaymentMethodLabelFromCodes(array $paymentMethodCodes): string
    {
        return match (true) {
            count($paymentMethodCodes) === 0 => __('txn.credit'),
            count($paymentMethodCodes) > 1 => __('txn.cash_plus_customer_balance'),
            in_array('customer_balance', $paymentMethodCodes, true) => __('txn.customer_balance'),
            in_array('writeoff', $paymentMethodCodes, true) => __('txn.writeoff'),
            in_array('discount', $paymentMethodCodes, true) => __('receivable.method_discount'),
            default => __('txn.cash'),
        };
    }

    private function semesterBookService(): SemesterBookService
    {
        return $this->semesterBookService;
    }

    private function authorizeTaskAccess(Request $request, ReportExportTask $task): void
    {
        $isOwner = (int) $task->user_id === (int) $request->user()->id;
        $isAdmin = strtolower((string) ($request->user()->role ?? '')) === 'admin';
        if (! $isOwner && ! $isAdmin) {
            abort(403);
        }
    }
}
