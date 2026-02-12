<?php

namespace App\Http\Controllers;

use App\Support\ExcelCsv;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\OrderNote;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\User;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function index(Request $request): View
    {
        $selectedSemester = $this->selectedSemester($request);
        $selectedCustomerId = $this->selectedCustomerId($request);
        $selectedUserRole = $this->selectedUserRole($request);
        $selectedFinanceLock = $this->selectedFinanceLock($request);

        return view('reports.index', [
            'datasets' => $this->datasets(),
            'selectedSemester' => $selectedSemester,
            'selectedCustomerId' => $selectedCustomerId,
            'selectedUserRole' => $selectedUserRole,
            'selectedFinanceLock' => $selectedFinanceLock,
            'semesterOptions' => $this->semesterOptions(),
            'semesterEnabledDatasets' => ['sales_invoices', 'sales_returns', 'delivery_notes', 'order_notes', 'receivables'],
            'receivableCustomers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function exportCsv(Request $request, string $dataset): StreamedResponse
    {
        $selectedSemester = $this->selectedSemester($request);
        $selectedCustomerId = $this->selectedCustomerId($request);
        $selectedUserRole = $this->selectedUserRole($request);
        $selectedFinanceLock = $this->selectedFinanceLock($request);
        $report = $this->reportData($dataset, $selectedSemester, $selectedCustomerId, $selectedUserRole, $selectedFinanceLock);
        $filename = $dataset.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM + separator hint to keep Excel import stable.
            ExcelCsv::start($handle);

            ExcelCsv::row($handle, [$report['title']]);
            ExcelCsv::row($handle, [__('report.printed'), now()->format('d-m-Y H:i:s')]);

            if (! empty($report['filters'])) {
                foreach ($report['filters'] as $filter) {
                    ExcelCsv::row($handle, [$filter['label'], $filter['value']]);
                }
            }

            if (! empty($report['summary'])) {
                foreach ($report['summary'] as $item) {
                    $value = ($item['type'] ?? 'number') === 'currency'
                        ? 'Rp '.number_format((int) round((float) ($item['value'] ?? 0)), 0, ',', '.')
                        : (int) round((float) ($item['value'] ?? 0));

                    ExcelCsv::row($handle, [$item['label'], $value]);
                }
            }

            ExcelCsv::row($handle, []);
            ExcelCsv::row($handle, $report['headers']);
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
                ExcelCsv::row($handle, $formatted);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function print(Request $request, string $dataset): View
    {
        $report = $this->reportData(
            $dataset,
            $this->selectedSemester($request),
            $this->selectedCustomerId($request),
            $this->selectedUserRole($request),
            $this->selectedFinanceLock($request)
        );

        return view('reports.print', [
            'title' => $report['title'],
            'headers' => $report['headers'],
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'filters' => $report['filters'],
            'layout' => $report['layout'] ?? null,
            'receivableSemesterHeaders' => $report['receivable_semester_headers'] ?? [],
            'printedAt' => now(),
        ]);
    }

    public function exportPdf(Request $request, string $dataset)
    {
        $report = $this->reportData(
            $dataset,
            $this->selectedSemester($request),
            $this->selectedCustomerId($request),
            $this->selectedUserRole($request),
            $this->selectedFinanceLock($request)
        );
        $filename = $dataset.'-'.now()->format('Ymd-His').'.pdf';

        $pdf = Pdf::loadView('reports.pdf', [
            'title' => $report['title'],
            'headers' => $report['headers'],
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'filters' => $report['filters'],
            'layout' => $report['layout'] ?? null,
            'receivableSemesterHeaders' => $report['receivable_semester_headers'] ?? [],
            'printedAt' => now(),
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
        ];
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
        ?int $selectedFinanceLock = null
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
                'rows' => function (): array {
                    return Customer::query()
                        ->with('level:id,name')
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
                        ->with([
                            'customer:id,name,phone,city',
                            'payments:id,sales_invoice_id,method,amount',
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

                    $invoiceCounts = SalesInvoice::query()
                        ->selectRaw('customer_id, semester_period, COUNT(*) as invoice_count')
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->where('semester_period', $selectedSemester);
                        })
                        ->when($selectedCustomerId !== null, function ($query) use ($selectedCustomerId): void {
                            $query->where('customer_id', $selectedCustomerId);
                        })
                        ->groupBy('customer_id', 'semester_period')
                        ->get();

                    $balances = SalesInvoice::query()
                        ->selectRaw('customer_id, semester_period, COALESCE(SUM(balance), 0) as total_balance')
                        ->where('is_canceled', false)
                        ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                            $query->where('semester_period', $selectedSemester);
                        })
                        ->when($selectedCustomerId !== null, function ($query) use ($selectedCustomerId): void {
                            $query->where('customer_id', $selectedCustomerId);
                        })
                        ->groupBy('customer_id', 'semester_period')
                        ->get();

                    $invoiceCountMap = [];
                    foreach ($invoiceCounts as $row) {
                        $customerKey = (int) $row->customer_id;
                        $period = (string) ($row->semester_period ?? '');
                        if ($period === '') {
                            continue;
                        }
                        $invoiceCountMap[$customerKey][$period] = (int) $row->invoice_count;
                    }

                    $balanceMap = [];
                    foreach ($balances as $row) {
                        $customerKey = (int) $row->customer_id;
                        $period = (string) ($row->semester_period ?? '');
                        if ($period === '') {
                            continue;
                        }
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
        ?int $selectedFinanceLock = null
    ): array
    {
        $config = $this->datasetConfig($dataset, $selectedSemester, $selectedCustomerId, $selectedUserRole, $selectedFinanceLock);
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
            $summary = $this->customerSummary();
        }
        if ($dataset === 'users') {
            $summary = $this->userSummary($selectedUserRole, $selectedFinanceLock);
            $filters = $this->userFilters($selectedUserRole, $selectedFinanceLock);
        }
        if ($dataset === 'sales_invoices') {
            $summary = $this->salesInvoiceSummary($selectedSemester);
            $filters = $this->salesInvoiceFilters($selectedSemester);
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

        if (preg_match('/^S([12])-(\d{4})$/', $semester) !== 1) {
            return null;
        }

        return $this->semesterBookService()->isActive($semester) ? $semester : null;
    }

    private function selectedCustomerId(Request $request): ?int
    {
        $customerId = $request->integer('customer_id');

        return $customerId > 0 ? $customerId : null;
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
    private function customerSummary(): array
    {
        $aggregate = Customer::query()
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
            ->merge($this->configuredSemesterOptions())
            ->push($current)
            ->push($previous)
            ->unique()
            ->sortDesc()
            ->values();

        return $this->semesterBookService()->filterToActiveSemesters($options->all());
    }

    private function currentSemesterPeriod(): string
    {
        $year = now()->year;
        $month = (int) now()->format('n');
        $semester = $month <= 6 ? 1 : 2;

        return "S{$semester}-{$year}";
    }

    private function previousSemesterPeriod(string $period): string
    {
        if (preg_match('/^S([12])-(\d{4})$/', $period, $matches) === 1) {
            $semester = (int) $matches[1];
            $year = (int) $matches[2];

            if ($semester === 2) {
                return "S1-{$year}";
            }

            return 'S2-'.($year - 1);
        }

        $previous = now()->subMonths(6);
        $semester = (int) $previous->format('n') <= 6 ? 1 : 2;

        return "S{$semester}-{$previous->year}";
    }

    /**
     * @return array{start:string,end:string}|null
     */
    private function semesterDateRange(?string $period): ?array
    {
        if ($period === null || preg_match('/^S([12])-(\d{4})$/', $period, $matches) !== 1) {
            return null;
        }

        $semester = (int) $matches[1];
        $year = (int) $matches[2];
        $start = Carbon::create($year, $semester === 1 ? 1 : 7, 1)->startOfDay();
        $end = (clone $start)->addMonths(6)->subDay()->endOfDay();

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ];
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
            ->filter(fn (string $item): bool => preg_match('/^S([12])-(\d{4})$/', $item) === 1)
            ->unique()
            ->sortBy(fn (string $item): int => $this->semesterSortValue($item))
            ->values();

        return collect($this->semesterBookService()->filterToActiveSemesters($columns->all()))
            ->sortBy(fn (string $item): int => $this->semesterSortValue($item))
            ->values();
    }

    private function semesterSortValue(string $period): int
    {
        if (preg_match('/^S([12])-(\d{4})$/', $period, $matches) !== 1) {
            return PHP_INT_MAX;
        }

        return ((int) $matches[2] * 10) + (int) $matches[1];
    }

    private function semesterDisplayLabel(string $period): string
    {
        if (preg_match('/^S([12])-(\d{4})$/', $period, $matches) !== 1) {
            return $period;
        }

        $semester = (int) $matches[1];
        $year = (int) $matches[2];

        return "Smt {$semester} ({$year}-".($year + 1).')';
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

