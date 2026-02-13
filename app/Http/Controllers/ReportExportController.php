<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\OutgoingTransaction;
use App\Models\OrderNote;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function index(Request $request): View
    {
        $selectedSemester = $this->selectedSemester($request);
        $selectedCustomerId = $this->selectedCustomerId($request);
        $selectedUserRole = $this->selectedUserRole($request);
        $selectedFinanceLock = $this->selectedFinanceLock($request);
        $selectedOutgoingSupplierId = $this->selectedOutgoingSupplierId($request);
        $receivableCustomers = Cache::remember('reports.receivable_customers.options', now()->addSeconds(60), function () {
            return Customer::query()
                ->orderBy('name')
                ->get(['id', 'name']);
        });
        $outgoingSuppliers = Cache::remember('reports.outgoing_suppliers.options', now()->addSeconds(60), function () {
            return Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name']);
        });

        return view('reports.index', [
            'datasets' => $this->datasets(),
            'selectedSemester' => $selectedSemester,
            'selectedCustomerId' => $selectedCustomerId,
            'selectedUserRole' => $selectedUserRole,
            'selectedFinanceLock' => $selectedFinanceLock,
            'selectedOutgoingSupplierId' => $selectedOutgoingSupplierId,
            'semesterOptions' => $this->semesterOptions(),
            'semesterEnabledDatasets' => ['sales_invoices', 'sales_returns', 'delivery_notes', 'order_notes', 'receivables', 'outgoing_transactions'],
            'receivableCustomers' => $receivableCustomers,
            'outgoingSuppliers' => $outgoingSuppliers,
        ]);
    }

    public function exportCsv(Request $request, string $dataset): StreamedResponse
    {
        $selectedSemester = $this->selectedSemester($request);
        $selectedCustomerId = $this->selectedCustomerId($request);
        $selectedCustomerIds = $this->selectedCustomerIds($request);
        $selectedUserRole = $this->selectedUserRole($request);
        $selectedFinanceLock = $this->selectedFinanceLock($request);
        $selectedOutgoingSupplierId = $this->selectedOutgoingSupplierId($request);
        $report = $this->reportData($dataset, $selectedSemester, $selectedCustomerId, $selectedUserRole, $selectedFinanceLock, $selectedCustomerIds, $selectedOutgoingSupplierId);
        $printedAt = $this->nowWib();
        $filename = $dataset.'-'.$printedAt->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($report, $printedAt): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Report');
            $rowsOut = [];
            $rowsOut[] = [$report['title']];
            $rowsOut[] = [__('report.printed'), $printedAt->format('d-m-Y H:i:s').' WIB'];

            if (! empty($report['filters'])) {
                foreach ($report['filters'] as $filter) {
                    $rowsOut[] = [$filter['label'], $filter['value']];
                }
            }

            if (! empty($report['summary'])) {
                foreach ($report['summary'] as $item) {
                    $value = ($item['type'] ?? 'number') === 'currency'
                        ? 'Rp '.number_format((int) round((float) ($item['value'] ?? 0)), 0, ',', '.')
                        : (int) round((float) ($item['value'] ?? 0));

                    $rowsOut[] = [$item['label'], $value];
                }
            }

            $rowsOut[] = [];
            $headerRowIndex = count($rowsOut) + 1;
            $rowsOut[] = $report['headers'];
            foreach ($report['rows'] as $row) {
                $formatted = [];
                $isReceivableRecap = ($report['layout'] ?? null) === 'receivable_recap';
                foreach ($report['headers'] as $index => $header) {
                    $value = $row[$index] ?? null;
                    $text = $value === null ? '' : (string) $value;
                    $headerKey = strtolower(trim($header));
                    $phoneHeaderKey = strtolower(__('report.columns.phone'));

                    // Keep phone values as text so Excel does not strip leading zeros.
                    if ($headerKey === $phoneHeaderKey && $text !== '') {
                        $text = "'".$text;
                    }

                    if ($text !== '' && is_numeric($text) && $isReceivableRecap && $index >= 4) {
                        $text = number_format((int) round((float) $text), 0, ',', '.');
                    } elseif ($text !== '' && is_numeric($text) && $this->isNumericReportHeader($headerKey)) {
                        $text = number_format((int) round((float) $text), 0, ',', '.');
                    }

                    $formatted[] = $text;
                }
                $rowsOut[] = $formatted;
            }

            $sheet->fromArray($rowsOut, null, 'A1');

            $columnCount = count($report['headers']);
            $dataRowCount = count($report['rows']);
            if ($columnCount > 0) {
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
                $lastDataRow = max($headerRowIndex, $headerRowIndex + $dataRowCount);

                $headerRange = 'A'.$headerRowIndex.':'.$lastColumn.$headerRowIndex;
                $tableRange = 'A'.$headerRowIndex.':'.$lastColumn.$lastDataRow;

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

                $sheet->freezePane('A'.($headerRowIndex + 1));

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
            $this->selectedOutgoingSupplierId($request)
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
            $this->selectedOutgoingSupplierId($request)
        );
        $filename = $dataset.'-'.$printedAt->format('Ymd-His').'.pdf';

        $pdf = Pdf::loadView('reports.pdf', [
            'title' => $report['title'],
            'headers' => $report['headers'],
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'filters' => $report['filters'],
            'layout' => $report['layout'] ?? null,
            'receivableSemesterHeaders' => $report['receivable_semester_headers'] ?? [],
            'printedAt' => $printedAt,
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
            'order_notes' => __('report.datasets.order_notes'),
            'outgoing_transactions' => __('report.datasets.outgoing_transactions'),
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
        return [
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
        ];
    }

    private function isNumericReportHeader(string $header): bool
    {
        return in_array($header, $this->numericReportHeaders(), true);
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
        ?int $selectedOutgoingSupplierId = null
    ): array
    {
        $semesterRange = $this->semesterDateRange($selectedSemester);

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
                        ->select([
                            'id',
                            'item_category_id',
                            'name',
                            'stock',
                            'price_agent',
                            'price_sales',
                            'price_general',
                        ])
                        ->with('category:id,name')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Product $row): array => [
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
                        ->select([
                            'id',
                            'customer_level_id',
                            'name',
                            'phone',
                            'city',
                            'outstanding_receivable',
                            'credit_balance',
                        ])
                        ->with('level:id,name')
                        ->when(count($selectedCustomerIds) > 0, function ($query) use ($selectedCustomerIds): void {
                            $query->whereIn('id', $selectedCustomerIds);
                        })
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Customer $row): array => [
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
                        ->select([
                            'id',
                            'name',
                            'email',
                            'role',
                            'locale',
                            'theme',
                            'finance_locked',
                            'created_at',
                        ])
                        ->when($selectedUserRole !== null, function ($query) use ($selectedUserRole): void {
                            $query->where('role', $selectedUserRole);
                        })
                        ->when($selectedFinanceLock !== null, function ($query) use ($selectedFinanceLock): void {
                            $query->where('finance_locked', $selectedFinanceLock);
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
                    return SalesInvoice::query()
                        ->select([
                            'id',
                            'invoice_number',
                            'invoice_date',
                            'customer_id',
                            'total',
                            'total_paid',
                            'payment_status',
                            'semester_period',
                        ])
                        ->with([
                            'customer:id,name,phone,city',
                            'payments:id,sales_invoice_id,method',
                        ])
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->where('semester_period', $selectedSemester);
                        })
                        ->latest('invoice_date')
                        ->get()
                        ->map(function (SalesInvoice $row): array {
                            return [
                                $row->invoice_number,
                                $row->invoice_date?->format('d-m-Y'),
                                $row->customer?->name,
                                $row->customer?->phone,
                                $row->customer?->city,
                                (int) round((float) $row->total),
                                (int) round((float) $row->total_paid),
                                $this->invoicePaymentMethodLabel($row),
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
                'receivable_semester_headers' => $this->receivableSemesterColumns($selectedSemester)
                    ->map(fn (string $code): string => $this->semesterDisplayLabel($code))
                    ->all(),
                'rows' => function () use ($selectedSemester, $selectedCustomerId): array {
                    $semesterCodes = $this->receivableSemesterColumns($selectedSemester)->values();
                    $header = [
                        'NO',
                        'NAMA KONSUMEN',
                        'ALAMAT',
                        'STATUS BUKU',
                        ...$semesterCodes->map(fn (string $code): string => $this->semesterDisplayLabel($code))->all(),
                        'TOTAL PIUTANG',
                    ];

                    $semesterAggregates = SalesInvoice::query()
                        ->selectRaw(
                            'customer_id, semester_period, COUNT(*) as invoice_count, '.
                            'COALESCE(SUM(CASE WHEN is_canceled = 0 THEN balance ELSE 0 END), 0) as total_balance'
                        )
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->where('semester_period', $selectedSemester);
                        })
                        ->when($selectedCustomerId !== null, function ($query) use ($selectedCustomerId): void {
                            $query->where('customer_id', $selectedCustomerId);
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
                        ->select([
                            'id',
                            'return_number',
                            'return_date',
                            'customer_id',
                            'total',
                            'semester_period',
                        ])
                        ->with('customer:id,name,phone,city')
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->where('semester_period', $selectedSemester);
                        })
                        ->latest('return_date')
                        ->get()
                        ->map(fn (SalesReturn $row): array => [
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
                        ->select([
                            'id',
                            'note_number',
                            'note_date',
                            'recipient_name',
                            'recipient_phone',
                            'city',
                            'created_by_name',
                        ])
                        ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                            $query->whereBetween('note_date', [$semesterRange['start'], $semesterRange['end']]);
                        })
                        ->latest('note_date')
                        ->get()
                        ->map(fn (DeliveryNote $row): array => [
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
                        ->select([
                            'id',
                            'note_number',
                            'note_date',
                            'customer_name',
                            'customer_phone',
                            'city',
                            'created_by_name',
                        ])
                        ->when($semesterRange !== null, function ($query) use ($semesterRange): void {
                            $query->whereBetween('note_date', [$semesterRange['start'], $semesterRange['end']]);
                        })
                        ->latest('note_date')
                        ->get()
                        ->map(fn (OrderNote $row): array => [
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
                        ->select([
                            'id',
                            'transaction_number',
                            'transaction_date',
                            'note_number',
                            'supplier_id',
                            'total',
                            'semester_period',
                            'created_by_user_id',
                        ])
                        ->with(['supplier:id,name,phone', 'creator:id,name'])
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->where('semester_period', $selectedSemester);
                        })
                        ->when($selectedOutgoingSupplierId !== null, function ($query) use ($selectedOutgoingSupplierId): void {
                            $query->where('supplier_id', $selectedOutgoingSupplierId);
                        })
                        ->latest('transaction_date')
                        ->get()
                        ->map(fn (OutgoingTransaction $row): array => [
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
            default => abort(404),
        };
    }

    /**
     * @return array{title:string,headers:array<int,string>,rows:array<int,array<int,string|int|float|null>>,summary:array<int,array{label:string,value:int|float,type:string}>|null,filters:array<int,array{label:string,value:string}>|null}
     */
    private function reportData(
        string $dataset,
        ?string $selectedSemester = null,
        ?int $selectedCustomerId = null,
        ?string $selectedUserRole = null,
        ?int $selectedFinanceLock = null,
        array $selectedCustomerIds = [],
        ?int $selectedOutgoingSupplierId = null
    ): array
    {
        $config = $this->datasetConfig($dataset, $selectedSemester, $selectedCustomerId, $selectedUserRole, $selectedFinanceLock, $selectedCustomerIds, $selectedOutgoingSupplierId);
        $rows = $config['rows']();
        $headers = $config['headers'];
        $layout = $config['layout'] ?? null;
        $receivableSemesterHeaders = $config['receivable_semester_headers'] ?? [];

        if ($layout === 'receivable_recap' && count($rows) > 0) {
            /** @var array<int, string> $computedHeaders */
            $computedHeaders = array_map(fn ($value): string => (string) $value, $rows[0]);
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
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
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
    private function receivableSummary(?string $selectedSemester, ?int $selectedCustomerId): array
    {
        $aggregate = SalesInvoice::query()
            ->where('balance', '>', 0)
            ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                $query->where('semester_period', $selectedSemester);
            })
            ->when($selectedCustomerId !== null, function ($query) use ($selectedCustomerId): void {
                $query->where('customer_id', $selectedCustomerId);
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
                $query->where('semester_period', $selectedSemester);
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
                $query->where('semester_period', $selectedSemester);
            })
            ->when($selectedOutgoingSupplierId !== null, function ($query) use ($selectedOutgoingSupplierId): void {
                $query->where('supplier_id', $selectedOutgoingSupplierId);
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
            $display .= ' +'.($selectedCount - 3);
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
                $query->where('role', $selectedUserRole);
            })
            ->when($selectedFinanceLock !== null, function ($query) use ($selectedFinanceLock): void {
                $query->where('finance_locked', $selectedFinanceLock);
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
        return Cache::remember('reports.semester_options', now()->addSeconds(60), function (): array {
            $current = $this->currentSemesterPeriod();
            $previous = $this->previousSemesterPeriod($current);

            $options = SalesInvoice::query()
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
                ->merge($this->configuredSemesterOptions())
                ->push($current)
                ->push($previous)
                ->unique()
                ->sortDesc()
                ->values();

            return $this->semesterBookService()->filterToActiveSemesters($options->all());
        });
    }

    private function currentSemesterPeriod(): string
    {
        return $this->semesterBookService()->currentSemester();
    }

    private function previousSemesterPeriod(string $period): string
    {
        return $this->semesterBookService()->previousSemester($period);
    }

    /**
     * @return array{start:string,end:string}|null
     */
    private function semesterDateRange(?string $period): ?array
    {
        if ($period === null) {
            return null;
        }
        $normalized = $this->semesterBookService()->normalizeSemester($period);
        if ($normalized === null) {
            return null;
        }
        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $normalized, $matches) === 1) {
            $half = (int) $matches[1];
            $startYear = 2000 + (int) $matches[2];
            $endYear = 2000 + (int) $matches[3];
            if ($half === 1) {
                return [
                    'start' => Carbon::create($startYear, 5, 1)->startOfDay()->toDateString(),
                    'end' => Carbon::create($startYear, 10, 31)->endOfDay()->toDateString(),
                ];
            }

            return [
                'start' => Carbon::create($startYear, 11, 1)->startOfDay()->toDateString(),
                'end' => Carbon::create($endYear, 4, 30)->endOfDay()->toDateString(),
            ];
        }
        return null;
    }

    private function configuredSemesterOptions()
    {
        return collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('semester_period_options', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '');
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
            ->merge($this->configuredSemesterOptions())
            ->map(fn (string $item): ?string => $this->semesterBookService()->normalizeSemester($item))
            ->filter(fn (?string $item): bool => $item !== null)
            ->unique()
            ->sortBy(fn (string $item): int => $this->semesterSortValue($item))
            ->values();

        return collect($this->semesterBookService()->filterToActiveSemesters($columns->all()))
            ->sortBy(fn (string $item): int => $this->semesterSortValue($item))
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

    private function invoicePaymentMethodLabel(SalesInvoice $invoice): string
    {
        $paymentMethodCodes = $invoice->payments
            ->pluck('method')
            ->map(fn ($method) => strtolower((string) $method))
            ->filter(fn (string $method): bool => $method !== '')
            ->unique()
            ->values();

        return match (true) {
            $paymentMethodCodes->isEmpty() => __('txn.credit'),
            $paymentMethodCodes->count() > 1 => __('txn.cash_plus_customer_balance'),
            $paymentMethodCodes->contains('customer_balance') => __('txn.customer_balance'),
            $paymentMethodCodes->contains('writeoff') => __('txn.writeoff'),
            $paymentMethodCodes->contains('discount') => __('receivable.method_discount'),
            default => __('txn.cash'),
        };
    }

    private function semesterBookService(): SemesterBookService
    {
        return app(SemesterBookService::class);
    }
}

