<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerLevel;
use App\Models\InvoicePayment;
use App\Models\ReceivableLedger;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Services\ReceivableLedgerService;
use App\Support\AppCache;
use App\Support\ExcelExportStyler;
use App\Support\PrintTextFormatter;
use App\Support\SemesterBookService;
use App\Support\TransactionType;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivablePageController extends Controller
{
    public function __construct(
        private readonly ReceivableLedgerService $receivableLedgerService,
        private readonly SemesterBookService $semesterBookService
    ) {}

    public function index(Request $request): View
    {
        $isAdminUser = (string) ($request->user()?->role ?? '') === 'admin';
        $search = trim((string) $request->string('search', ''));
        $customerId = $request->integer('customer_id');
        $selectedTransactionType = trim((string) $request->string('transaction_type', ''));
        if ($selectedTransactionType !== '' && ! in_array($selectedTransactionType, TransactionType::values(), true)) {
            $selectedTransactionType = '';
        }
        $semester = trim((string) $request->string('semester', ''));
        $selectedSemester = $semester !== '' ? $this->semesterBookService->normalizeSemester($semester) : null;
        $currentSemester = $this->currentSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);

        $baseSemesterOptions = Cache::remember(
            AppCache::lookupCacheKey('receivables.index.semester_options.base'),
            now()->addSeconds(60),
            fn() => $this->semesterBookService->buildSemesterOptionCollection(
                ReceivableLedger::query()
                    ->whereNotNull('period_code')
                    ->where('period_code', '!=', '')
                    ->distinct()
                    ->orderByDesc('period_code')
                    ->pluck('period_code')
                    ->merge($this->semesterBookService->configuredSemesterOptions()),
                false,
                true
            )
        );
        $semesterOptions = collect(
            $this->semesterBookService->filterToOpenSemesters(
                $baseSemesterOptions->all(),
                ! $isAdminUser
            )
        )->values();
        if ($selectedSemester !== null && ! $semesterOptions->contains($selectedSemester)) {
            $selectedSemester = null;
        }

        $customersQuery = Customer::query()
            ->select(['customers.id', 'customers.name', 'customers.city', 'customers.credit_balance'])
            ->searchKeyword($search);

        if ($selectedSemester !== null) {
            $semesterLedger = ReceivableLedger::query()
                ->selectRaw('customer_id, SUM(debit - credit) as semester_outstanding')
                ->where('period_code', $selectedSemester)
                ->groupBy('customer_id');

            $customersQuery
                ->leftJoinSub($semesterLedger, 'semester_ledger', function ($join): void {
                    $join->on('customers.id', '=', 'semester_ledger.customer_id');
                })
                ->addSelect(DB::raw('COALESCE(semester_ledger.semester_outstanding, 0) as outstanding_receivable'))
                ->orderByDesc(DB::raw('COALESCE(semester_ledger.semester_outstanding, 0)'))
                ->orderBy('customers.name');
        } else {
            $totalLedger = ReceivableLedger::query()
                ->selectRaw('customer_id, SUM(debit - credit) as total_outstanding')
                ->groupBy('customer_id');

            $customersQuery
                ->leftJoinSub($totalLedger, 'total_ledger', function ($join): void {
                    $join->on('customers.id', '=', 'total_ledger.customer_id');
                })
                ->addSelect(DB::raw('COALESCE(total_ledger.total_outstanding, 0) as outstanding_receivable'))
                ->orderByDesc(DB::raw('COALESCE(total_ledger.total_outstanding, 0)'))
                ->orderBy('customers.name');
        }

        $customers = $customersQuery
            ->paginate((int) config('pagination.default_per_page', 20))
            ->withQueryString();
        $selectedSemesterGlobalClosed = $selectedSemester !== null
            ? $this->semesterBookService->isClosed($selectedSemester)
            : false;
        $selectedSemesterActive = $selectedSemester !== null
            ? $this->semesterBookService->isActive($selectedSemester)
            : true;
        $customerSemesterClosedMap = [];
        $customerSemesterAutoClosedMap = [];
        $customerSemesterManualClosedMap = [];
        if ($selectedSemester !== null) {
            $lockStates = $this->semesterBookService->customerSemesterLockStates(
                $customers->pluck('id')->all(),
                $selectedSemester
            );
            foreach ($customers as $customerRow) {
                $state = $lockStates[(int) $customerRow->id] ?? null;
                $customerSemesterClosedMap[(int) $customerRow->id] = (bool) ($state['locked'] ?? false);
                $customerSemesterAutoClosedMap[(int) $customerRow->id] = (bool) ($state['auto'] ?? false);
                $customerSemesterManualClosedMap[(int) $customerRow->id] = (bool) ($state['manual'] ?? false);
            }
        }

        $ledgerRows = collect();
        $outstandingInvoices = collect();
        $billStatementRows = collect();
        $billStatementTotals = null;
        $selectedCustomerName = null;
        $selectedCustomer = null;
        $selectedCustomerOption = null;
        $ledgerOutstandingTotal = null;
        $customerOutstandingTotal = null;
        $selectedCustomerSemesterClosed = false;
        $semesterClosingState = null;
        $paymentRefsWithAlloc = [];
        $salesReturnLinkMap = [];
        if ($isAdminUser && $selectedSemester !== null) {
            $semesterClosingState = $this->semesterBookService->receivableSemesterClosingState($selectedSemester);
        }
        if ($customerId > 0) {
            $selectedCustomer = Customer::query()
                ->select(['id', 'name', 'city'])
                ->find($customerId);
            $selectedCustomerName = $selectedCustomer?->name;
            if ($selectedCustomer !== null) {
                $selectedCustomerOption = [
                    'id' => (int) $selectedCustomer->id,
                    'name' => (string) $selectedCustomer->name,
                    'city' => (string) ($selectedCustomer->city ?? ''),
                ];
            }
            if ($selectedSemester !== null) {
                $selectedCustomerSemesterClosed = $this->semesterBookService->isCustomerLocked($customerId, $selectedSemester);
            }

            $outstandingInvoiceQuery = SalesInvoice::query()
                ->forCustomer($customerId)
                ->active()
                ->withOpenBalance()
                ->when($selectedTransactionType !== '', function ($query) use ($selectedTransactionType): void {
                    $query->where('transaction_type', $selectedTransactionType);
                })
                ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                    $query->forSemester($selectedSemester);
                });

            $outstandingInvoices = (clone $outstandingInvoiceQuery)
                ->select(['id', 'invoice_number', 'invoice_date', 'semester_period', 'total', 'total_paid', 'balance'])
                ->orderByDate('asc')
                ->get();

            $ledgerBaseQuery = ReceivableLedger::query()
                ->forCustomer($customerId)
                ->when($selectedTransactionType !== '', function ($query) use ($selectedTransactionType): void {
                    $query->where('transaction_type', $selectedTransactionType);
                })
                ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                    $query->forSemester($selectedSemester);
                });

            $ledgerOutstandingTotal = (float) (clone $ledgerBaseQuery)->sum(DB::raw('debit - credit'));
            $ledgerRows = (clone $ledgerBaseQuery)
                ->select([
                    'id',
                    'customer_id',
                    'sales_invoice_id',
                    'entry_date',
                    'transaction_type',
                    'description',
                    'debit',
                    'credit',
                    'balance_after',
                    'period_code',
                ])
                ->withCustomerInfo()
                ->withInvoiceInfo()
                ->orderByDate()
                ->limit(50)
                ->get();
            $ledgerRows = $this->filterRedundantPaymentSummaryRows($ledgerRows);
            $paymentRefsWithAlloc = $this->paymentRefsWithAlloc($ledgerRows);
            $customerOutstandingTotal = max(0, (float) $ledgerOutstandingTotal);
            $returnNumbers = $ledgerRows
                ->map(function (ReceivableLedger $row): ?string {
                    $description = (string) ($row->description ?? '');
                    if (preg_match('/\bRTR-\d{8}-\d{4}\b/i', $description, $match) !== 1) {
                        return null;
                    }

                    return strtoupper((string) $match[0]);
                })
                ->filter(fn (?string $number): bool => $number !== null && $number !== '')
                ->unique()
                ->values();
            if ($returnNumbers->isNotEmpty()) {
                $salesReturnLinkMap = SalesReturn::query()
                    ->select(['id', 'return_number'])
                    ->where('customer_id', $customerId)
                    ->whereIn('return_number', $returnNumbers->all())
                    ->get()
                    ->mapWithKeys(fn (SalesReturn $salesReturn): array => [
                        strtoupper((string) $salesReturn->return_number) => (int) $salesReturn->id,
                    ])
                    ->all();
            }

            if ($selectedCustomer) {
                $statementData = $this->cachedCustomerBillStatement(
                    (int) $selectedCustomer->id,
                    $selectedSemester,
                    $selectedTransactionType !== '' ? $selectedTransactionType : null
                );
                $billStatementRows = $statementData['rows'];
                $billStatementTotals = $statementData['totals'];
            }
        }

        return view('receivables.index', [
            'customers' => $customers,
            'ledgerRows' => $ledgerRows,
            'search' => $search,
            'selectedCustomerId' => $customerId,
            'selectedTransactionType' => $selectedTransactionType,
            'semesterOptions' => $semesterOptions,
            'selectedSemester' => $selectedSemester,
            'currentSemester' => $currentSemester,
            'previousSemester' => $previousSemester,
            'selectedCustomerName' => $selectedCustomerName,
            'ledgerOutstandingTotal' => $ledgerOutstandingTotal,
            'customerOutstandingTotal' => $customerOutstandingTotal,
            'outstandingInvoices' => $outstandingInvoices,
            'billStatementRows' => $billStatementRows,
            'billStatementTotals' => $billStatementTotals,
            'selectedCustomerSemesterClosed' => $selectedCustomerSemesterClosed,
            'semesterClosingState' => $semesterClosingState,
            'selectedSemesterGlobalClosed' => $selectedSemesterGlobalClosed,
            'selectedSemesterActive' => $selectedSemesterActive,
            'customerSemesterClosedMap' => $customerSemesterClosedMap,
            'customerSemesterAutoClosedMap' => $customerSemesterAutoClosedMap,
            'customerSemesterManualClosedMap' => $customerSemesterManualClosedMap,
            'paymentRefsWithAlloc' => $paymentRefsWithAlloc,
            'salesReturnLinkMap' => $salesReturnLinkMap,
            'selectedCustomerOption' => $selectedCustomerOption,
        ]);
    }

    public function semesterIndex(Request $request): View
    {
        return view('receivables.semester_index', $this->semesterReceivableData($request, true));
    }

    public function globalIndex(Request $request): View
    {
        return view('receivables.global_index', $this->globalReceivableData($request, true));
    }

    public function semesterPrint(Request $request): View
    {
        return view('receivables.semester_print', array_merge(
            $this->semesterReceivableData($request, false),
            ['isPdf' => false]
        ));
    }

    public function globalPrint(Request $request): View
    {
        return view('receivables.global_print', array_merge(
            $this->globalReceivableData($request, false),
            ['isPdf' => false]
        ));
    }

    public function semesterExportPdf(Request $request)
    {
        $data = array_merge($this->semesterReceivableData($request, false), ['isPdf' => true]);
        $html = view('receivables.semester_print', $data)->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');

        return $pdf->download($this->semesterReportFilename('pdf', (string) ($data['selectedSemester'] ?? '')));
    }

    public function globalExportPdf(Request $request)
    {
        $data = array_merge($this->globalReceivableData($request, false), ['isPdf' => true]);
        $html = view('receivables.global_print', $data)->render();
        $selectedCustomer = $data['selectedCustomer'] ?? null;
        $pdf = Pdf::loadHTML($html)->setPaper('a4', $selectedCustomer instanceof Customer ? 'portrait' : 'landscape');

        $filename = $selectedCustomer instanceof Customer
            ? 'invoice-piutang-' . Str::slug((string) $selectedCustomer->name) . '-' . $this->nowWib()->format('Ymd-His') . '.pdf'
            : 'piutang-global-' . $this->nowWib()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }

    public function semesterExportExcel(Request $request): StreamedResponse
    {
        $data = $this->semesterReceivableData($request, false);
        $rows = collect($data['rows'] ?? []);
        $filename = $this->semesterReportFilename('xlsx', (string) ($data['selectedSemester'] ?? ''));

        return response()->streamDownload(function () use ($rows, $data): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Piutang Semester');

            $sheet->mergeCells('A1:I1');
            $sheet->setCellValue('A1', (string) ($data['printTitle'] ?? $data['title'] ?? __('receivable.semester_page_title')));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

            $sheet->mergeCells('H2:I2');
            $sheet->setCellValue('H2', 'Update : ' . now()->translatedFormat('j F Y'));
            $sheet->getStyle('H2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('H2')->getFont()->setItalic(true)->setBold(true);

            $tableStart = 4;
            $rowsOut = [[
                'No.',
                'NAMA',
                'ALAMAT',
                'Ket.',
                'REKAP PENJUALAN',
                'ANGSURAN',
                'RETUR PENJUALAN',
                'PIUTANG',
                'STATUS',
            ]];

            foreach ($rows as $idx => $row) {
                $outstanding = (int) ($row['outstanding_total'] ?? 0);
                $rowsOut[] = [
                    (int) $idx + 1,
                    strtoupper((string) ($row['name'] ?? '')),
                    (string) ($row['address'] ?? '-'),
                    strtoupper((string) (($row['level_label'] ?? '') !== '' ? $row['level_label'] : '-')),
                    'Rp ' . number_format((int) ($row['sales_total'] ?? 0), 0, ',', '.'),
                    'Rp ' . number_format((int) ($row['payment_total'] ?? 0), 0, ',', '.'),
                    'Rp ' . number_format((int) ($row['return_total'] ?? 0), 0, ',', '.'),
                    ($outstanding < 0 ? '-Rp ' : 'Rp ') . number_format(abs($outstanding), 0, ',', '.'),
                    $outstanding <= 0 ? 'LUNAS' : '-',
                ];
            }

            $totals = (array) ($data['totals'] ?? []);
            $rowsOut[] = [
                '',
                __('receivable.semester_total'),
                '',
                '',
                'Rp ' . number_format((int) ($totals['sales_total'] ?? 0), 0, ',', '.'),
                'Rp ' . number_format((int) ($totals['payment_total'] ?? 0), 0, ',', '.'),
                'Rp ' . number_format((int) ($totals['return_total'] ?? 0), 0, ',', '.'),
                ((int) ($totals['outstanding_total'] ?? 0) < 0 ? '-Rp ' : 'Rp ') . number_format(abs((int) ($totals['outstanding_total'] ?? 0)), 0, ',', '.'),
                '',
            ];

            $sheet->fromArray($rowsOut, null, 'A' . $tableStart);
            $lastRow = $tableStart + count($rowsOut) - 1;
            $sheet->getStyle('A' . $tableStart . ':I' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle('A' . $tableStart . ':I' . $lastRow)->getAlignment()->setWrapText(true);
            $sheet->getStyle('A' . $tableStart . ':I' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A' . $tableStart . ':I' . $tableStart)->getFont()->setBold(true);
            $sheet->getStyle('A' . $tableStart . ':I' . $tableStart)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A' . $tableStart . ':I' . $tableStart)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A' . $tableStart . ':I' . $tableStart)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            $sheet->getStyle('A' . ($tableStart + 1) . ':A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . ($tableStart + 1) . ':D' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . ($tableStart + 1) . ':H' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('A' . ($tableStart + 1) . ':C' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $totalRow = $tableStart + count($rowsOut) - 1;
            $sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F74C8');
            $sheet->getStyle('E' . $totalRow . ':H' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('B' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            for ($rowIndex = $tableStart + 1; $rowIndex < $totalRow; $rowIndex++) {
                if (strtoupper((string) $sheet->getCell('I' . $rowIndex)->getValue()) === 'LUNAS') {
                    $sheet->getStyle('I' . $rowIndex)->getFont()->getColor()->setARGB('FFD60000');
                    $sheet->getStyle('I' . $rowIndex)->getFont()->setBold(true);
                    $sheet->getStyle('I' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $sheet->getStyle('I' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }

            foreach (['A' => 5, 'B' => 20, 'C' => 24, 'D' => 8, 'E' => 18, 'F' => 18, 'G' => 18, 'H' => 18, 'I' => 10] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            $sheet->getRowDimension(1)->setRowHeight(24);
            $sheet->getRowDimension($tableStart)->setRowHeight(24);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function globalExportExcel(Request $request): StreamedResponse
    {
        $data = $this->globalReceivableData($request, false);
        $rows = collect($data['rows'] ?? []);
        $semesterHeaders = collect($data['semesterHeaders'] ?? []);
        $semesterCodes = collect($data['semesterCodes'] ?? []);
        $selectedCustomer = $data['selectedCustomer'] ?? null;
        $filename = $selectedCustomer instanceof Customer
            ? 'invoice-piutang-' . Str::slug((string) $selectedCustomer->name) . '-' . $this->nowWib()->format('Ymd-His') . '.xlsx'
            : 'piutang-global-' . $this->nowWib()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($rows, $data, $semesterHeaders, $semesterCodes, $selectedCustomer): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($selectedCustomer instanceof Customer ? 'Invoice Piutang' : 'Piutang Global');

            if ($selectedCustomer instanceof Customer) {
                $companyName = trim((string) ($data['companyName'] ?? 'CV. PUSTAKA GRAFIKA'));
                $companyAddress = PrintTextFormatter::wrapWords(trim((string) ($data['companyAddress'] ?? '')), 5);
                $companyPhone = trim((string) ($data['companyPhone'] ?? ''));
                $companyEmail = trim((string) ($data['companyEmail'] ?? ''));
                $customerAddress = PrintTextFormatter::wrapWords(trim((string) ($selectedCustomer->address ?? '')), 4);
                $notesText = PrintTextFormatter::wrapWords(trim((string) ($data['companyInvoiceNotes'] ?? '')), 4);
                $transferText = trim((string) ($data['companyTransferAccounts'] ?? ''));
                $invoiceRows = collect($data['customerInvoiceRows'] ?? []);
                $invoiceTotal = (int) ($data['customerInvoiceTotal'] ?? 0);
                $companyLogoPath = trim((string) ($data['companyLogoPath'] ?? ''));

                if ($companyLogoPath !== '') {
                    $absoluteLogoPath = public_path('storage/' . $companyLogoPath);
                    if (is_file($absoluteLogoPath)) {
                        $drawing = new Drawing();
                        $drawing->setPath($absoluteLogoPath);
                        $drawing->setHeight(74);
                        $drawing->setCoordinates('A1');
                        $drawing->setWorksheet($sheet);
                    }
                }

                $sheet->mergeCells('B1:D1');
                $sheet->setCellValue('B1', $companyName);
                $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(18);
                $sheet->mergeCells('B2:D4');
                $sheet->setCellValue('B2', collect([$companyAddress, $companyPhone, $companyEmail])->filter()->implode("\n"));
                $sheet->getStyle('B2:D4')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

                $sheet->mergeCells('E1:G2');
                $sheet->setCellValue('E1', 'Invoice');
                $sheet->getStyle('E1:G2')->getFont()->setBold(true)->setSize(24);
                $sheet->getStyle('E1:G2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->mergeCells('I1:J1');
                $sheet->setCellValue('I1', 'Update : ' . now()->translatedFormat('j F Y'));
                $sheet->getStyle('I1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('I1:J1')->getFont()->setItalic(true)->setBold(true);

                $sheet->setCellValue('A6', 'Konsumen');
                $sheet->setCellValue('B6', ':');
                $sheet->setCellValue('C6', strtoupper((string) $selectedCustomer->name));
                $sheet->setCellValue('A7', 'Alamat');
                $sheet->setCellValue('B7', ':');
                $sheet->setCellValue('C7', strtoupper($customerAddress !== '' ? $customerAddress : '-'));
                $sheet->getStyle('A6:C7')->getAlignment()->setWrapText(true);

                $tableStart = 9;
                $sheet->fromArray([['No.', 'Deskripsi', 'Nominal']], null, 'A' . $tableStart);
                $sheet->getStyle('A' . $tableStart . ':C' . $tableStart)->getFont()->setBold(true);
                $sheet->getStyle('A' . $tableStart . ':C' . $tableStart)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A' . $tableStart . ':C' . $tableStart)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE5E7EB');
                $sheet->getStyle('A' . $tableStart . ':C' . $tableStart)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                $cursor = $tableStart + 1;
                foreach ($invoiceRows as $index => $invoiceRow) {
                    $sheet->setCellValue('A' . $cursor, (int) $index + 1);
                    $sheet->setCellValue('B' . $cursor, strtoupper((string) ($invoiceRow['description'] ?? '')));
                    $sheet->setCellValue('C' . $cursor, 'Rp ' . number_format((int) ($invoiceRow['nominal'] ?? 0), 0, ',', '.'));
                    $cursor++;
                }
                for ($i = $invoiceRows->count(); $i < 7; $i++) {
                    $sheet->setCellValue('A' . $cursor, $i + 1);
                    $cursor++;
                }
                $dataEnd = $cursor - 1;
                $sheet->getStyle('A' . ($tableStart + 1) . ':C' . $dataEnd)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('A' . ($tableStart + 1) . ':A' . $dataEnd)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C' . ($tableStart + 1) . ':C' . $dataEnd)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->mergeCells('A' . ($cursor + 1) . ':B' . ($cursor + 1));
                $sheet->setCellValue('A' . ($cursor + 1), 'TOTAL');
                $sheet->setCellValue('C' . ($cursor + 1), 'Rp ' . number_format($invoiceTotal, 0, ',', '.'));
                $sheet->getStyle('A' . ($cursor + 1) . ':C' . ($cursor + 1))->getFont()->setBold(true);
                $sheet->getStyle('A' . ($cursor + 1) . ':C' . ($cursor + 1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('A' . ($cursor + 1) . ':B' . ($cursor + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C' . ($cursor + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $cursor += 4;
                if ($notesText !== '') {
                    $sheet->setCellValue('A' . $cursor, 'Note :');
                    $sheet->getStyle('A' . $cursor)->getFont()->setBold(true);
                    foreach (preg_split('/\r\n|\r|\n/', $notesText) ?: [] as $line) {
                        if (trim($line) === '') {
                            continue;
                        }
                        $cursor++;
                        $sheet->mergeCells('A' . $cursor . ':F' . $cursor);
                        $sheet->setCellValue('A' . $cursor, $line);
                    }
                    $cursor += 2;
                }

                if ($transferText !== '') {
                    $sheet->setCellValue('A' . $cursor, 'Transfer via :');
                    $sheet->getStyle('A' . $cursor)->getFont()->setBold(true);
                    foreach (preg_split('/\r\n|\r|\n/', $transferText) ?: [] as $line) {
                        if (trim($line) === '') {
                            continue;
                        }
                        $cursor++;
                        $sheet->mergeCells('A' . $cursor . ':F' . $cursor);
                        $sheet->setCellValue('A' . $cursor, $line);
                    }
                }

                foreach (['A' => 6, 'B' => 18, 'C' => 24, 'D' => 4, 'E' => 14, 'F' => 16, 'G' => 16, 'H' => 4, 'I' => 16, 'J' => 16] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                return;
            }

            $columnCount = 4 + $semesterHeaders->count() + 1;
            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);

            $sheet->mergeCells('A1:' . $lastColumn . '1');
            $sheet->setCellValue('A1', (string) ($data['printTitle'] ?? $data['title']));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells(($columnCount > 1 ? \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, $columnCount - 1)) : 'A') . '2:' . $lastColumn . '2');
            $sheet->setCellValue(($columnCount > 1 ? \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, $columnCount - 1)) : 'A') . '2', 'Update : ' . now()->translatedFormat('j F Y'));
            $sheet->getStyle('A2:' . $lastColumn . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('A2:' . $lastColumn . '2')->getFont()->setItalic(true)->setBold(true);

            $tableStart = 4;
            $sheet->setCellValue('A' . $tableStart, 'NO');
            $sheet->setCellValue('B' . $tableStart, 'NAMA PELANGGAN');
            $sheet->setCellValue('C' . $tableStart, 'KOTA');
            $sheet->setCellValue('D' . $tableStart, 'ALAMAT');

            if ($semesterHeaders->isNotEmpty()) {
                $semesterStartColumn = 5;
                $semesterEndColumn = $semesterStartColumn + $semesterHeaders->count() - 1;
                $semesterStartLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($semesterStartColumn);
                $semesterEndLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($semesterEndColumn);
                $sheet->mergeCells($semesterStartLetter . $tableStart . ':' . $semesterEndLetter . $tableStart);
                $sheet->setCellValue($semesterStartLetter . $tableStart, 'PIUTANG');
                foreach ($semesterHeaders->values() as $index => $header) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($semesterStartColumn + $index);
                    $sheet->setCellValue($columnLetter . ($tableStart + 1), (string) $header);
                }
            }

            $totalColumnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
            $sheet->mergeCells($totalColumnLetter . $tableStart . ':' . $totalColumnLetter . ($tableStart + 1));
            $sheet->setCellValue($totalColumnLetter . $tableStart, 'TOTAL PIUTANG');

            $sheet->mergeCells('A' . $tableStart . ':A' . ($tableStart + 1));
            $sheet->mergeCells('B' . $tableStart . ':B' . ($tableStart + 1));
            $sheet->mergeCells('C' . $tableStart . ':C' . ($tableStart + 1));
            $sheet->mergeCells('D' . $tableStart . ':D' . ($tableStart + 1));

            $dataStartRow = $tableStart + 2;
            $rowCursor = $dataStartRow;

            foreach ($rows as $index => $row) {
                $sheet->setCellValue('A' . $rowCursor, (int) $index + 1);
                $sheet->setCellValue('B' . $rowCursor, strtoupper((string) ($row['name'] ?? '')));
                $sheet->setCellValue('C' . $rowCursor, (string) ($row['city'] ?? '-'));
                $sheet->setCellValue('D' . $rowCursor, (string) ($row['address'] ?? '-'));

                foreach ($semesterCodes->values() as $semesterIndex => $semesterCode) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $semesterIndex);
                    $value = (int) ($row['semester_totals'][$semesterCode] ?? 0);
                    $sheet->setCellValue($columnLetter . $rowCursor, 'Rp ' . number_format($value, 0, ',', '.'));
                    $sheet->getStyle($columnLetter . $rowCursor)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $sheet->setCellValue($totalColumnLetter . $rowCursor, 'Rp ' . number_format((int) ($row['total_outstanding'] ?? 0), 0, ',', '.'));
                $sheet->getStyle($totalColumnLetter . $rowCursor)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $rowCursor++;
            }

            $sheet->mergeCells('A' . $rowCursor . ':D' . $rowCursor);
            $sheet->setCellValue('A' . $rowCursor, __('receivable.semester_total'));
            foreach ($semesterCodes->values() as $semesterIndex => $semesterCode) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $semesterIndex);
                $sheet->setCellValue($columnLetter . $rowCursor, 'Rp ' . number_format((int) ($data['totals']['per_semester'][$semesterCode] ?? 0), 0, ',', '.'));
                $sheet->getStyle($columnLetter . $rowCursor)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $sheet->setCellValue($totalColumnLetter . $rowCursor, 'Rp ' . number_format((int) ($data['totals']['grand_total'] ?? 0), 0, ',', '.'));
            $sheet->getStyle($totalColumnLetter . $rowCursor)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle('A' . $tableStart . ':' . $lastColumn . ($tableStart + 1))->getFont()->setBold(true);
            $sheet->getStyle('A' . $tableStart . ':' . $lastColumn . ($tableStart + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A' . $tableStart . ':' . $lastColumn . ($tableStart + 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A' . $tableStart . ':' . $lastColumn . ($tableStart + 1))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF3F8');
            $sheet->getStyle('A' . $tableStart . ':' . $lastColumn . $rowCursor)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle('A' . $tableStart . ':' . $lastColumn . $rowCursor)->getAlignment()->setWrapText(true);
            $sheet->getStyle('A' . $rowCursor . ':' . $lastColumn . $rowCursor)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle('A' . $rowCursor . ':' . $lastColumn . $rowCursor)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF2F74C8');
            $sheet->getStyle('A' . $dataStartRow . ':A' . ($rowCursor - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            foreach (['A' => 5, 'B' => 20, 'C' => 14, 'D' => 34] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            foreach ($semesterCodes->values() as $semesterIndex => $semesterCode) {
                $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $semesterIndex))->setWidth(16);
            }
            $sheet->getColumnDimension($totalColumnLetter)->setWidth(16);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function semesterReportFilename(string $extension, string $semester): string
    {
        $normalizedSemester = trim($semester) !== '' ? strtolower(trim($semester)) : 'semua-customer';

        return 'daftar-piutang-' . $normalizedSemester . '-' . $this->nowWib()->format('Ymd-His') . '.' . $extension;
    }

    /**
     * @return array<string, mixed>
     */
    private function globalReceivableData(Request $request, bool $paginate = true): array
    {
        $search = trim((string) $request->string('search', ''));
        $status = strtolower(trim((string) $request->string('status', 'all')));
        $selectedCustomerId = max(0, (int) $request->integer('customer_id'));
        $selectedTransactionType = trim((string) $request->string('transaction_type', ''));
        if ($selectedTransactionType !== '' && ! in_array($selectedTransactionType, TransactionType::values(), true)) {
            $selectedTransactionType = '';
        }
        if (! in_array($status, ['all', 'outstanding', 'paid', 'credit'], true)) {
            $status = 'all';
        }

        $baseSemesterOptions = Cache::remember(
            AppCache::lookupCacheKey('receivables.global_page.options'),
            now()->addSeconds(60),
            fn() => $this->semesterBookService->buildSemesterOptionCollection(
                ReceivableLedger::query()
                    ->whereNotNull('period_code')
                    ->where('period_code', '!=', '')
                    ->distinct()
                    ->pluck('period_code')
                    ->merge($this->semesterBookService->configuredSemesterOptions()),
                false,
                false
            )->values()
        );

        $activeSemesterCodes = collect($this->semesterBookService->filterToActiveSemesters($baseSemesterOptions->all()))
            ->reject(fn (string $semester): bool => $this->semesterBookService->isClosed($semester))
            ->values();

        if ($activeSemesterCodes->isEmpty()) {
            $activeSemesterCodes = $baseSemesterOptions
                ->reject(fn (string $semester): bool => $this->semesterBookService->isClosed($semester))
                ->values();
        }

        $customerOptions = Cache::remember(
            AppCache::lookupCacheKey('receivables.global_page.customer_options'),
            now()->addSeconds(60),
            fn() => Customer::query()
                ->select(['id', 'name', 'city'])
                ->orderBy('name')
                ->get()
                ->map(fn (Customer $customer): array => [
                    'id' => (int) $customer->id,
                    'label' => trim((string) $customer->name) . ((string) ($customer->city ?? '') !== '' ? ' (' . trim((string) $customer->city) . ')' : ''),
                ])
                ->all()
        );

        $selectedCustomer = $selectedCustomerId > 0
            ? Customer::query()->find($selectedCustomerId)
            : null;
        if ($selectedCustomer === null) {
            $selectedCustomerId = 0;
        }

        $ledgerRows = ReceivableLedger::query()
            ->selectRaw('customer_id, period_code, COALESCE(SUM(debit - credit), 0) as outstanding_total')
            ->whereIn('period_code', $activeSemesterCodes->all())
            ->when($selectedTransactionType !== '', function ($query) use ($selectedTransactionType): void {
                $query->where('transaction_type', $selectedTransactionType);
            })
            ->groupBy('customer_id', 'period_code')
            ->get();

        $balanceMap = [];
        foreach ($ledgerRows as $ledgerRow) {
            $periodCode = (string) ($ledgerRow->period_code ?? '');
            if ($periodCode === '') {
                continue;
            }
            $balanceMap[(int) $ledgerRow->customer_id][$periodCode] = (int) round((float) ($ledgerRow->outstanding_total ?? 0));
        }

        $totalAggregate = ReceivableLedger::query()
            ->selectRaw('customer_id, COALESCE(SUM(debit - credit), 0) as total_outstanding')
            ->whereIn('period_code', $activeSemesterCodes->all())
            ->when($selectedTransactionType !== '', function ($query) use ($selectedTransactionType): void {
                $query->where('transaction_type', $selectedTransactionType);
            })
            ->groupBy('customer_id');

        $customerQuery = Customer::query()
            ->leftJoinSub($totalAggregate, 'global_ledger', function ($join): void {
                $join->on('customers.id', '=', 'global_ledger.customer_id');
            })
            ->select([
                'customers.id',
                'customers.name',
                'customers.city',
                'customers.address',
                DB::raw('COALESCE(global_ledger.total_outstanding, 0) as total_outstanding'),
            ])
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($subQuery) use ($search): void {
                    $subQuery->where('customers.name', 'like', '%' . $search . '%')
                        ->orWhere('customers.city', 'like', '%' . $search . '%')
                        ->orWhere('customers.address', 'like', '%' . $search . '%');
                });
            })
            ->when($selectedCustomerId > 0, function ($builder) use ($selectedCustomerId): void {
                $builder->where('customers.id', $selectedCustomerId);
            })
            ->when($status === 'outstanding', function ($builder): void {
                $builder->whereRaw('COALESCE(global_ledger.total_outstanding, 0) > 0');
            })
            ->when($status === 'paid', function ($builder): void {
                $builder->whereRaw('COALESCE(global_ledger.total_outstanding, 0) = 0');
            })
            ->when($status === 'credit', function ($builder): void {
                $builder->whereRaw('COALESCE(global_ledger.total_outstanding, 0) < 0');
            })
            ->orderBy('name');

        $customers = $paginate
            ? $customerQuery->paginate(10)->withQueryString()
            : $customerQuery->get();

        $customerCollection = $paginate ? collect($customers->items()) : collect($customers);
        $rows = $customerCollection->map(function (Customer $customer) use ($balanceMap, $activeSemesterCodes): array {
            $semesterTotals = [];
            $grandTotal = 0;
            foreach ($activeSemesterCodes as $semesterCode) {
                $value = (int) ($balanceMap[(int) $customer->id][$semesterCode] ?? 0);
                $semesterTotals[$semesterCode] = $value;
                $grandTotal += $value;
            }

            return [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'city' => trim((string) ($customer->city ?? '')) !== '' ? (string) $customer->city : '-',
                'address' => trim((string) ($customer->address ?? '')) !== '' ? (string) $customer->address : '-',
                'semester_totals' => $semesterTotals,
                'total_outstanding' => $grandTotal,
            ];
        });

        $totalsPerSemester = [];
        foreach ($activeSemesterCodes as $semesterCode) {
            $totalsPerSemester[$semesterCode] = (int) $rows->sum(fn (array $row): int => (int) ($row['semester_totals'][$semesterCode] ?? 0));
        }

        $grandTotal = (int) $rows->sum(fn (array $row): int => (int) ($row['total_outstanding'] ?? 0));

        $customerInvoiceRows = collect();
        $customerInvoiceTotal = 0;
        $companySettings = [];

        if ($selectedCustomer instanceof Customer) {
            $customerInvoiceRows = ReceivableLedger::query()
                ->selectRaw('period_code, transaction_type, COALESCE(SUM(debit - credit), 0) as outstanding_total')
                ->where('customer_id', (int) $selectedCustomer->id)
                ->whereIn('period_code', $activeSemesterCodes->all())
                ->when($selectedTransactionType !== '', function ($query) use ($selectedTransactionType): void {
                    $query->where('transaction_type', $selectedTransactionType);
                })
                ->groupBy('period_code', 'transaction_type')
                ->orderBy('period_code')
                ->get()
                ->map(function ($row): array {
                    $transactionType = trim((string) ($row->transaction_type ?? '')) !== ''
                        ? TransactionType::normalize((string) $row->transaction_type)
                        : '';

                    $typeLabel = match ($transactionType) {
                        TransactionType::PRINTING => __('receivable.transaction_type_printing'),
                        TransactionType::PRODUCT => __('receivable.transaction_type_product'),
                        default => __('receivable.transaction_type_none'),
                    };

                    return [
                        'semester_code' => (string) ($row->period_code ?? ''),
                        'transaction_type' => $transactionType !== '' ? $transactionType : null,
                        'description' => $this->semesterDescriptionLabel((string) ($row->period_code ?? '')) . ($transactionType !== '' ? ' - ' . strtoupper($typeLabel) : ''),
                        'nominal' => (int) round((float) ($row->outstanding_total ?? 0)),
                    ];
                })
                ->filter(fn (array $row): bool => (int) $row['nominal'] !== 0)
                ->values();

            $customerInvoiceTotal = (int) $customerInvoiceRows->sum(fn (array $row): int => (int) $row['nominal']);
            $companySettings = $this->companyPrintSettings();
        }

        return [
            'title' => __('receivable.global_page_title'),
            'printTitle' => $selectedCustomer instanceof Customer
                ? 'Invoice'
                : strtoupper(__('receivable.global_page_title')) . ($selectedTransactionType !== '' ? ' - ' . strtoupper($this->transactionTypeLabel($selectedTransactionType)) : ''),
            'rows' => $rows,
            'paginator' => $paginate ? $customers : null,
            'totalItems' => $paginate ? $customers->total() : $rows->count(),
            'search' => $search,
            'selectedStatus' => $status,
            'selectedCustomerId' => $selectedCustomerId,
            'selectedTransactionType' => $selectedTransactionType,
            'selectedCustomer' => $selectedCustomer,
            'customerOptions' => $customerOptions,
            'semesterHeaders' => $activeSemesterCodes->map(fn (string $code): string => $this->semesterDescriptionLabel($code))->all(),
            'semesterCodes' => $activeSemesterCodes->all(),
            'totals' => [
                'per_semester' => $totalsPerSemester,
                'grand_total' => $grandTotal,
            ],
            'customerInvoiceRows' => $customerInvoiceRows,
            'customerInvoiceTotal' => $customerInvoiceTotal,
            'companyLogoPath' => $companySettings['companyLogoPath'] ?? null,
            'companyName' => $companySettings['companyName'] ?? 'CV. PUSTAKA GRAFIKA',
            'companyAddress' => $companySettings['companyAddress'] ?? '',
            'companyPhone' => $companySettings['companyPhone'] ?? '',
            'companyEmail' => $companySettings['companyEmail'] ?? '',
            'companyInvoiceNotes' => $companySettings['companyInvoiceNotes'] ?? '',
            'companyTransferAccounts' => $companySettings['companyTransferAccounts'] ?? '',
        ];
    }

    public function closeCustomerSemester(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'semester' => ['required', 'string', 'max:30'],
            'search' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $semester = $this->semesterBookService->normalizeSemester((string) ($data['semester'] ?? ''));
        if ($semester === null) {
            return redirect()
                ->route('receivables.index')
                ->withErrors(['semester' => __('ui.invalid_semester_format')]);
        }

        $this->semesterBookService->closeCustomerSemester((int) $customer->id, $semester);

        return redirect()
            ->route('receivables.index', [
                'search' => $data['search'] ?? null,
                'semester' => $semester,
                'customer_id' => (int) $customer->id,
            ])
            ->with('success', __('receivable.customer_semester_closed_success', [
                'semester' => $semester,
                'customer' => $customer->name,
            ]));
    }

    public function openCustomerSemester(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'semester' => ['required', 'string', 'max:30'],
            'search' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $semester = $this->semesterBookService->normalizeSemester((string) ($data['semester'] ?? ''));
        if ($semester === null) {
            return redirect()
                ->route('receivables.index')
                ->withErrors(['semester' => __('ui.invalid_semester_format')]);
        }

        $this->semesterBookService->openCustomerSemester((int) $customer->id, $semester);

        return redirect()
            ->route('receivables.index', [
                'search' => $data['search'] ?? null,
                'semester' => $semester,
                'customer_id' => (int) $customer->id,
            ])
            ->with('success', __('receivable.customer_semester_opened_success', [
                'semester' => $semester,
                'customer' => $customer->name,
            ]));
    }

    public function customerWriteoff(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'payment_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string'],
            'semester' => ['nullable', 'string', 'max:30'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $this->processCustomerAdjustment($customer, $data, 'writeoff');
        AppCache::forgetAfterFinancialMutation([(string) ($data['payment_date'] ?? now()->toDateString())]);

        $redirect = redirect()
            ->route('receivables.index', [
                'search' => $data['search'] ?? null,
                'semester' => $data['semester'] ?? null,
                'customer_id' => $customer->id,
            ])
            ->with('success', __('receivable.payment_saved'));

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => __('receivable.payment_saved'),
                'method' => 'writeoff',
                'customer_id' => (int) $customer->id,
            ]);
        }

        return $redirect;
    }

    public function customerDiscount(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'payment_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string'],
            'semester' => ['nullable', 'string', 'max:30'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $this->processCustomerAdjustment($customer, $data, 'discount');
        AppCache::forgetAfterFinancialMutation([(string) ($data['payment_date'] ?? now()->toDateString())]);

        $redirect = redirect()
            ->route('receivables.index', [
                'search' => $data['search'] ?? null,
                'semester' => $data['semester'] ?? null,
                'customer_id' => $customer->id,
            ])
            ->with('success', __('receivable.payment_saved'));

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => __('receivable.payment_saved'),
                'method' => 'discount',
                'customer_id' => (int) $customer->id,
            ]);
        }

        return $redirect;
    }

    public function printCustomerBill(Request $request, Customer $customer): View
    {
        return view('receivables.print_customer_bill', $this->customerBillViewData($request, $customer));
    }

    public function exportCustomerBillPdf(Request $request, Customer $customer)
    {
        $data = $this->customerBillViewData($request, $customer);
        $data['isPdf'] = true;
        $filename = 'tagihan-' . $customer->id . '-' . $this->nowWib()->format('Ymd-His') . '.pdf';

        return Pdf::loadView('receivables.print_customer_bill', $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function exportCustomerBillExcel(Request $request, Customer $customer): StreamedResponse
    {
        $data = $this->customerBillViewData($request, $customer);
        $rows = collect($data['rows'] ?? []);
        $schoolBreakdown = collect($data['schoolBreakdown'] ?? []);
        $filename = 'tagihan-' . $customer->id . '-' . $this->nowWib()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($rows, $data, $schoolBreakdown): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tagihan');
            $companyName = trim((string) ($data['companyName'] ?? 'CV. PUSTAKA GRAFIKA'));
            $companyAddress = PrintTextFormatter::wrapWords(trim((string) ($data['companyAddress'] ?? '')), 5);
            $companyPhone = trim((string) ($data['companyPhone'] ?? ''));
            $companyEmail = trim((string) ($data['companyEmail'] ?? ''));
            $customerAddress = PrintTextFormatter::wrapWords(trim((string) ($data['customer']->address ?? '')), 5);
            $notesText = PrintTextFormatter::wrapWords(trim((string) ($data['companyInvoiceNotes'] ?? '')), 4);

            $sheet->mergeCells('A1:G1');
            $sheet->setCellValue('A1', (string) ($data['reportTitle'] ?? __('receivable.customer_bill_title')));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->mergeCells('A2:B2');
            $sheet->setCellValue('A2', $companyName);
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);
            $sheet->mergeCells('A3:B5');
            $sheet->setCellValue('A3', collect([$companyAddress, $companyPhone, $companyEmail])->filter()->implode("\n"));
            $sheet->getStyle('A3:B5')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

            $metaRows = [
                [__('receivable.print_date'), now()->format('d-m-Y')],
                ['Semester', (string) ($data['selectedSemester'] ?? '-')],
                [__('receivable.customer'), (string) ($data['customer']->name ?? '-')],
                [__('txn.phone'), (string) ($data['customer']->phone ?? '-')],
                [__('txn.city'), (string) ($data['customer']->city ?? '-')],
                [__('txn.address'), $customerAddress !== '' ? $customerAddress : '-'],
            ];
            $metaRowIndex = 2;
            foreach ($metaRows as [$label, $value]) {
                $sheet->setCellValue('E' . $metaRowIndex, $label);
                $sheet->setCellValue('F' . $metaRowIndex, $value);
                $metaRowIndex++;
            }
            $sheet->getStyle('E2:E7')->getFont()->setBold(true);
            $sheet->getStyle('F2:F7')->getAlignment()->setWrapText(true);

        $rowsOut = [[
                __('receivable.bill_date'),
                __('receivable.bill_proof_number'),
                __('receivable.transaction_type'),
                __('receivable.bill_credit_sales'),
                __('receivable.bill_installment_payment'),
                __('receivable.bill_sales_return'),
                __('receivable.bill_running_balance'),
            ]];

            foreach ($rows as $row) {
                $adjustmentAmount = (int) round((float) ($row['adjustment_amount'] ?? 0));
                $proofNumber = (string) ($row['proof_number'] ?? '');
                if ($adjustmentAmount !== 0) {
                    $proofNumber .= sprintf(
                        ' (%sRp %s)',
                        $adjustmentAmount > 0 ? '+' : '-',
                        number_format(abs($adjustmentAmount), 0, ',', '.')
                    );
                }
                $rowsOut[] = [
                    (string) ($row['date_label'] ?? ''),
                    $proofNumber,
                    (string) ($row['transaction_type_label'] ?? __('receivable.transaction_type_none')),
                    number_format((int) round((float) ($row['credit_sales'] ?? 0)), 0, ',', '.'),
                    number_format((int) round((float) ($row['installment_payment'] ?? 0)), 0, ',', '.'),
                    number_format((int) round((float) ($row['sales_return'] ?? 0)), 0, ',', '.'),
                    number_format((int) round((float) ($row['running_balance'] ?? 0)), 0, ',', '.'),
                ];
            }

            $totals = (array) ($data['totals'] ?? []);
            $rowsOut[] = [
                '',
                __('receivable.bill_total'),
                '',
                number_format((int) round((float) ($totals['credit_sales'] ?? 0)), 0, ',', '.'),
                number_format((int) round((float) ($totals['installment_payment'] ?? 0)), 0, ',', '.'),
                number_format((int) round((float) ($totals['sales_return'] ?? 0)), 0, ',', '.'),
                number_format((int) round((float) ($totals['running_balance'] ?? 0)), 0, ',', '.'),
            ];
            $rowsOut[] = [
                '',
                '',
                '',
                '',
                '',
                __('receivable.bill_total_receivable'),
                number_format((int) round((float) ($totals['running_balance'] ?? 0)), 0, ',', '.'),
            ];

            $tableStartRow = 10;
            $sheet->fromArray($rowsOut, null, 'A' . $tableStartRow);
            ExcelExportStyler::styleTable($sheet, $tableStartRow, 7, count($rowsOut) - 1, true);
            $sheet->getStyle('A' . $tableStartRow . ':G' . ($tableStartRow + count($rowsOut)))->getAlignment()->setWrapText(true);
            $sheet->getStyle('D' . ($tableStartRow + 1) . ':G' . ($tableStartRow + count($rowsOut)))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $rowCursor = $tableStartRow + count($rowsOut) + 2;

            if ($schoolBreakdown->isNotEmpty()) {
                $sheet->mergeCells('A' . $rowCursor . ':G' . $rowCursor);
                $sheet->setCellValue('A' . $rowCursor, __('receivable.school_breakdown_title'));
                $sheet->getStyle('A' . $rowCursor)->getFont()->setBold(true);
                $rowCursor++;
                $schoolHeader = [
                    __('receivable.school_name'),
                    __('receivable.school_city'),
                    __('receivable.bill_date'),
                    __('receivable.bill_proof_number'),
                    __('receivable.school_invoice_total'),
                    __('receivable.school_paid_total'),
                    __('receivable.school_balance_total'),
                ];
                $sectionStart = $rowCursor;
                $sheet->fromArray([$schoolHeader], null, 'A' . $rowCursor);
                $rowCursor++;
                $schoolDataCount = 0;

                foreach ($schoolBreakdown as $group) {
                    $groupRows = collect($group['rows'] ?? []);
                    if ($groupRows->isEmpty()) {
                        continue;
                    }
                    foreach ($groupRows as $groupRow) {
                        $sheet->fromArray([[
                            (string) ($group['school_name'] ?? '-'),
                            (string) ($group['school_city'] ?? '-'),
                            (string) ($groupRow['date_label'] ?? ''),
                            (string) ($groupRow['invoice_number'] ?? ''),
                            number_format((int) round((float) ($groupRow['invoice_total'] ?? 0)), 0, ',', '.'),
                            number_format((int) round((float) ($groupRow['paid_total'] ?? 0)), 0, ',', '.'),
                            number_format((int) round((float) ($groupRow['balance_total'] ?? 0)), 0, ',', '.'),
                        ]], null, 'A' . $rowCursor);
                        $rowCursor++;
                        $schoolDataCount++;
                    }

                    $totalsPerSchool = (array) ($group['totals'] ?? []);
                    $sheet->fromArray([[
                        '',
                        '',
                        '',
                        __('receivable.bill_total'),
                        number_format((int) round((float) ($totalsPerSchool['invoice_total'] ?? 0)), 0, ',', '.'),
                        number_format((int) round((float) ($totalsPerSchool['paid_total'] ?? 0)), 0, ',', '.'),
                        number_format((int) round((float) ($totalsPerSchool['balance_total'] ?? 0)), 0, ',', '.'),
                    ]], null, 'A' . $rowCursor);
                    $rowCursor++;
                    $schoolDataCount++;
                }

                if ($schoolDataCount > 0) {
                    ExcelExportStyler::styleTable($sheet, $sectionStart, 7, $schoolDataCount, false);
                    $sheet->getStyle('A' . $sectionStart . ':G' . ($rowCursor - 1))->getAlignment()->setWrapText(true);
                }
                $rowCursor++;
            }

            if ($notesText !== '') {
                $sheet->setCellValue('A' . $rowCursor, __('receivable.note_label'));
                $sheet->getStyle('A' . $rowCursor)->getFont()->setBold(true);
                $sheet->mergeCells('B' . $rowCursor . ':G' . ($rowCursor + 2));
                $sheet->setCellValue('B' . $rowCursor, $notesText);
                $sheet->getStyle('B' . $rowCursor . ':G' . ($rowCursor + 2))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle('A' . $rowCursor . ':G' . ($rowCursor + 2))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $rowCursor += 4;
            }

            foreach (range('A', 'G') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function processCustomerAdjustment(Customer $customer, array $data, string $method): void
    {
        DB::transaction(function () use ($customer, $data, $method): void {
            $customerId = (int) $customer->id;
            $selectedCustomerId = (int) ($data['customer_id'] ?? 0);
            if ($selectedCustomerId > 0 && $selectedCustomerId !== $customerId) {
                throw ValidationException::withMessages([
                    'amount' => __('receivable.invalid_invoice_customer'),
                ]);
            }

            $selectedSemester = trim((string) ($data['semester'] ?? ''));
            $selectedSemester = $selectedSemester !== ''
                ? ($this->semesterBookService->normalizeSemester($selectedSemester) ?? '')
                : '';
            $amount = (float) $data['amount'];
            $paymentDate = isset($data['payment_date']) && $data['payment_date'] !== ''
                ? Carbon::parse($data['payment_date'])
                : now();

            $invoices = SalesInvoice::query()
                ->forCustomer($customerId)
                ->active()
                ->withOpenBalance()
                ->when($selectedSemester !== '', function ($query) use ($selectedSemester): void {
                    $query->forSemester($selectedSemester);
                })
                ->orderBy('invoice_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $outstandingTotal = (float) $invoices->sum('balance');
            if ($outstandingTotal <= 0) {
                throw ValidationException::withMessages([
                    'amount' => __('receivable.customer_has_no_outstanding'),
                ]);
            }

            if ($amount > $outstandingTotal) {
                throw ValidationException::withMessages([
                    'amount' => __('receivable.payment_exceeds_balance'),
                ]);
            }

            $remaining = $amount;
            foreach ($invoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $invoiceBalance = (float) $invoice->balance;
                if ($invoiceBalance <= 0) {
                    continue;
                }

                $applied = min($remaining, $invoiceBalance);
                if ($applied <= 0) {
                    continue;
                }

                InvoicePayment::create([
                    'sales_invoice_id' => $invoice->id,
                    'payment_date' => $paymentDate->toDateString(),
                    'amount' => $applied,
                    'method' => $method,
                    'notes' => match ($method) {
                        'writeoff' => __('receivable.writeoff_from_ledger_note'),
                        default => __('receivable.discount_from_ledger_note'),
                    },
                ]);

                $newTotalPaid = (float) $invoice->total_paid + $applied;
                $newBalance = max(0, (float) $invoice->total - $newTotalPaid);

                $invoice->update([
                    'total_paid' => $newTotalPaid,
                    'balance' => $newBalance,
                    'payment_status' => $newBalance <= 0 ? 'paid' : 'unpaid',
                ]);

                $this->receivableLedgerService->addCredit(
                    customerId: $customerId,
                    invoiceId: (int) $invoice->id,
                    entryDate: $paymentDate,
                    amount: $applied,
                    periodCode: $invoice->semester_period,
                    description: match ($method) {
                        'writeoff' => __('receivable.writeoff_for_invoice', ['invoice' => $invoice->invoice_number]),
                        default => __('receivable.discount_for_invoice', ['invoice' => $invoice->invoice_number]),
                    },
                    transactionType: (string) $invoice->transaction_type
                );

                $remaining -= $applied;
            }
        });
    }

    private function currentSemesterPeriod(): string
    {
        return $this->semesterBookService->currentSemester();
    }

    private function previousSemesterPeriod(string $period): string
    {
        return $this->semesterBookService->previousSemester($period);
    }

    /**
     * @return array<string, mixed>
     */
    private function semesterReceivableData(Request $request, bool $paginate = true): array
    {
        $search = trim((string) $request->string('search', ''));
        $status = strtolower(trim((string) $request->string('status', 'all')));
        $selectedTransactionType = trim((string) $request->string('transaction_type', ''));
        if ($selectedTransactionType !== '' && ! in_array($selectedTransactionType, TransactionType::values(), true)) {
            $selectedTransactionType = '';
        }
        if (! in_array($status, ['all', 'outstanding', 'paid', 'credit'], true)) {
            $status = 'all';
        }

        $semesterOptions = Cache::remember(
            AppCache::lookupCacheKey('receivables.semester_page.options'),
            now()->addSeconds(60),
            function () {
                $baseOptions = $this->semesterBookService->buildSemesterOptionCollection(
                    ReceivableLedger::query()
                        ->whereNotNull('period_code')
                        ->where('period_code', '!=', '')
                        ->distinct()
                        ->pluck('period_code')
                        ->merge($this->semesterBookService->configuredSemesterOptions()),
                    false,
                    true
                );

                return collect($this->semesterBookService->filterToOpenSemesters($baseOptions->all(), false))
                    ->values();
            }
        );

        $selectedSemester = trim((string) $request->string('semester', ''));
        $selectedSemester = $selectedSemester !== ''
            ? ($this->semesterBookService->normalizeSemester($selectedSemester) ?? '')
            : '';
        if ($selectedSemester === '' || ! $semesterOptions->contains($selectedSemester)) {
            $selectedSemester = (string) ($semesterOptions->first() ?: $this->currentSemesterPeriod());
        }

        $ledgerAggregate = ReceivableLedger::query()
            ->selectRaw("
                customer_id,
                COALESCE(SUM(debit), 0) as sales_total,
                COALESCE(SUM(CASE WHEN lower(description) like '%retur%' OR lower(description) like '%return%' THEN credit ELSE 0 END), 0) as return_total,
                COALESCE(SUM(CASE WHEN lower(description) like '%retur%' OR lower(description) like '%return%' THEN 0 ELSE credit END), 0) as payment_total,
                COALESCE(SUM(debit - credit), 0) as outstanding_total
            ")
            ->where('period_code', $selectedSemester)
            ->when($selectedTransactionType !== '', function ($query) use ($selectedTransactionType): void {
                $query->where('transaction_type', $selectedTransactionType);
            })
            ->groupBy('customer_id');

        $query = Customer::query()
            ->leftJoinSub($ledgerAggregate, 'semester_ledger', function ($join): void {
                $join->on('customers.id', '=', 'semester_ledger.customer_id');
            })
            ->leftJoin('customer_levels', 'customer_levels.id', '=', 'customers.customer_level_id')
            ->select([
                'customers.id',
                'customers.name',
                'customers.city',
                'customers.address',
                DB::raw('COALESCE(customer_levels.code, \'\') as level_code'),
                DB::raw('COALESCE(customer_levels.name, \'\') as level_name'),
                DB::raw('COALESCE(semester_ledger.sales_total, 0) as sales_total'),
                DB::raw('COALESCE(semester_ledger.payment_total, 0) as payment_total'),
                DB::raw('COALESCE(semester_ledger.return_total, 0) as return_total'),
                DB::raw('COALESCE(semester_ledger.outstanding_total, 0) as outstanding_total'),
            ])
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($subQuery) use ($search): void {
                    $subQuery->where('customers.name', 'like', '%' . $search . '%')
                        ->orWhere('customers.city', 'like', '%' . $search . '%')
                        ->orWhere('customers.address', 'like', '%' . $search . '%');
                });
            })
            ->when($status === 'outstanding', function ($builder): void {
                $builder->whereRaw('COALESCE(semester_ledger.outstanding_total, 0) > 0');
            })
            ->when($status === 'paid', function ($builder): void {
                $builder->whereRaw('COALESCE(semester_ledger.outstanding_total, 0) = 0');
            })
            ->when($status === 'credit', function ($builder): void {
                $builder->whereRaw('COALESCE(semester_ledger.outstanding_total, 0) < 0');
            })
            ->orderBy('customers.name');

        $totalQuery = clone $query;
        $totalsRow = $totalQuery
            ->selectRaw('
                COALESCE(SUM(COALESCE(semester_ledger.sales_total, 0)), 0) as sales_total,
                COALESCE(SUM(COALESCE(semester_ledger.payment_total, 0)), 0) as payment_total,
                COALESCE(SUM(COALESCE(semester_ledger.return_total, 0)), 0) as return_total,
                COALESCE(SUM(COALESCE(semester_ledger.outstanding_total, 0)), 0) as outstanding_total
            ')
            ->first();

        $rows = $paginate
            ? $query->paginate(10)->withQueryString()
            : $query->get();

        $rowCollection = $paginate ? collect($rows->items()) : collect($rows);

        return [
            'title' => __('receivable.semester_page_title'),
            'semesterOptions' => $semesterOptions,
            'selectedSemester' => $selectedSemester,
            'selectedSemesterLabel' => $this->semesterDescriptionLabel($selectedSemester),
            'selectedStatus' => $status,
            'selectedTransactionType' => $selectedTransactionType,
            'selectedStatusLabel' => match ($status) {
                'outstanding' => __('receivable.semester_status_outstanding'),
                'paid' => __('receivable.semester_status_paid'),
                'credit' => __('receivable.semester_status_credit'),
                default => __('receivable.semester_status_all'),
            },
            'search' => $search,
            'rows' => $rowCollection->map(function ($row): array {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) ($row->name ?? ''),
                    'city' => trim((string) ($row->city ?? '')) !== '' ? (string) $row->city : '-',
                    'address' => trim((string) ($row->address ?? '')) !== '' ? (string) $row->address : '-',
                    'level_label' => trim((string) ($row->level_code ?? '')) !== ''
                        ? strtoupper(trim((string) $row->level_code))
                        : strtoupper(trim((string) ($row->level_name ?? ''))),
                    'sales_total' => (int) round((float) ($row->sales_total ?? 0)),
                    'payment_total' => (int) round((float) ($row->payment_total ?? 0)),
                    'return_total' => (int) round((float) ($row->return_total ?? 0)),
                    'outstanding_total' => (int) round((float) ($row->outstanding_total ?? 0)),
                ];
            }),
            'paginator' => $paginate ? $rows : null,
            'totals' => [
                'sales_total' => (int) round((float) ($totalsRow->sales_total ?? 0)),
                'payment_total' => (int) round((float) ($totalsRow->payment_total ?? 0)),
                'return_total' => (int) round((float) ($totalsRow->return_total ?? 0)),
                'outstanding_total' => (int) round((float) ($totalsRow->outstanding_total ?? 0)),
            ],
            'printTitle' => 'DAFTAR PIUTANG ' . strtoupper($this->semesterDescriptionLabel($selectedSemester)) . ($selectedTransactionType !== '' ? ' - ' . strtoupper($this->transactionTypeLabel($selectedTransactionType)) : ''),
            'totalItems' => $paginate ? (int) $rows->total() : (int) $rowCollection->count(),
        ];
    }

    private function semesterDescriptionLabel(string $periodCode): string
    {
        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $periodCode, $matches) === 1) {
            $semester = (int) $matches[1];
            $startYear = 2000 + (int) $matches[2];
            $endYear = 2000 + (int) $matches[3];

            return "SMT {$semester} ({$startYear}-{$endYear})";
        }
        return $periodCode !== '' ? $periodCode : __('txn.semester_period');
    }

    /**
     * @return array<string, mixed>
     */
    private function customerBillViewData(Request $request, Customer $customer): array
    {
        $selectedSemester = $this->normalizeSemester((string) $request->string('semester', ''));
        $selectedTransactionType = trim((string) $request->string('transaction_type', ''));
        if ($selectedTransactionType !== '' && ! in_array($selectedTransactionType, TransactionType::values(), true)) {
            $selectedTransactionType = '';
        }
        $statementData = $this->cachedCustomerBillStatement(
            (int) $customer->id,
            $selectedSemester !== '' ? $selectedSemester : null,
            $selectedTransactionType !== '' ? $selectedTransactionType : null
        );
        $statementRows = $statementData['rows'];
        $totals = $statementData['totals'];
        $schoolBreakdown = $this->buildCustomerBillSchoolBreakdown(
            (int) $customer->id,
            $selectedSemester !== '' ? $selectedSemester : null,
            $selectedTransactionType !== '' ? $selectedTransactionType : null
        );
        $settings = $this->companyPrintSettings();

        return [
            'customer' => $customer,
            'selectedSemester' => $selectedSemester !== '' ? $selectedSemester : null,
            'selectedTransactionType' => $selectedTransactionType !== '' ? $selectedTransactionType : null,
            'selectedSemesterLabel' => $selectedSemester !== '' ? $this->semesterDescriptionLabel($selectedSemester) : null,
            'reportTitle' => $this->customerBillReportTitle(
                $customer,
                $selectedSemester !== '' ? $selectedSemester : null,
                $selectedTransactionType !== '' ? $selectedTransactionType : null
            ),
            'rows' => $statementRows,
            'totalOutstanding' => (int) round((float) $totals['running_balance']),
            'totals' => $totals,
            'schoolBreakdown' => $schoolBreakdown,
            'companyLogoPath' => $settings['companyLogoPath'] ?? null,
            'companyName' => $settings['companyName'] ?? 'CV. PUSTAKA GRAFIKA',
            'companyAddress' => $settings['companyAddress'] ?? '',
            'companyPhone' => $settings['companyPhone'] ?? '',
            'companyEmail' => $settings['companyEmail'] ?? '',
            'companyInvoiceNotes' => $settings['companyInvoiceNotes'] ?? '',
            'companyTransferAccounts' => $settings['companyTransferAccounts'] ?? '',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function companyPrintSettings(): array
    {
        $settings = AppSetting::getValues([
            'company_logo_path' => null,
            'company_name' => 'CV. PUSTAKA GRAFIKA',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'company_invoice_notes' => '',
            'company_transfer_accounts' => '',
        ]);

        return [
            'companyLogoPath' => $settings['company_logo_path'] ?? null,
            'companyName' => trim((string) ($settings['company_name'] ?? 'CV. PUSTAKA GRAFIKA')),
            'companyAddress' => trim((string) ($settings['company_address'] ?? '')),
            'companyPhone' => trim((string) ($settings['company_phone'] ?? '')),
            'companyEmail' => trim((string) ($settings['company_email'] ?? '')),
            'companyInvoiceNotes' => trim((string) ($settings['company_invoice_notes'] ?? '')),
            'companyTransferAccounts' => trim((string) ($settings['company_transfer_accounts'] ?? __('receivable.default_transfer_accounts'))),
        ];
    }

    private function customerBillReportTitle(Customer $customer, ?string $selectedSemester, ?string $selectedTransactionType = null): string
    {
        $customerName = trim((string) $customer->name);
        $namePart = $customerName !== '' ? $customerName : 'Customer';
        $typeSuffix = $selectedTransactionType !== null && trim($selectedTransactionType) !== ''
            ? ' - ' . strtoupper($this->transactionTypeLabel($selectedTransactionType))
            : '';

        if ($selectedSemester !== null) {
            return sprintf('Rekap Piutang %s %s%s', $namePart, strtoupper($selectedSemester), $typeSuffix);
        }

        return sprintf('Rekap Piutang %s%s', $namePart, $typeSuffix);
    }

    private function normalizeSemester(string $semester): string
    {
        $value = trim($semester);
        if ($value === '') {
            return '';
        }

        $normalized = $this->semesterBookService->normalizeSemester($value);
        if ($normalized === null) {
            return '';
        }

        return $normalized;
    }

    /**
     * @return array{rows:Collection<int, array<string, int|string|null>>, totals:array<string, int>}
     */
    private function cachedCustomerBillStatement(int $customerId, ?string $selectedSemester, ?string $selectedTransactionType = null): array
    {
        $normalizedSemester = $selectedSemester !== null
            ? $this->semesterBookService->normalizeSemester($selectedSemester)
            : null;
        $normalizedTransactionType = $selectedTransactionType !== null && trim($selectedTransactionType) !== ''
            ? TransactionType::normalize($selectedTransactionType)
            : null;

        return Cache::remember(
            AppCache::lookupCacheKey('receivables.bill_statement', [
                'customer_id' => $customerId,
                'semester' => (string) ($normalizedSemester ?? ''),
                'transaction_type' => (string) ($normalizedTransactionType ?? ''),
            ]),
            now()->addSeconds(45),
            fn() => $this->buildCustomerBillStatement($customerId, $normalizedSemester, $normalizedTransactionType)
        );
    }

    /**
     * @return array{rows:Collection<int, array<string, int|string|null>>, totals:array<string, int>}
     */
    private function buildCustomerBillStatement(int $customerId, ?string $selectedSemester, ?string $selectedTransactionType = null): array
    {
        $semester = $selectedSemester ?? '';
        $transactionType = $selectedTransactionType ?? '';
        $ledgerRows = ReceivableLedger::query()
            ->with('invoice:id,invoice_number,invoice_date')
            ->forCustomer($customerId)
            ->when($transactionType !== '', function ($query) use ($transactionType): void {
                $query->where('transaction_type', $transactionType);
            })
            ->when($semester !== '', function ($query) use ($semester): void {
                $query->forSemester($semester);
            })
            ->orderByDate('asc')
            ->get();

        if ($ledgerRows->isNotEmpty()) {
            $first = $ledgerRows->first();
            $openingBalance = (int) round((float) $first->balance_after - (float) $first->debit + (float) $first->credit);
        } else {
            $openingBalance = (int) round((float) SalesInvoice::query()
                ->forCustomer($customerId)
                ->active()
                ->withOpenBalance()
                ->when($transactionType !== '', function ($query) use ($transactionType): void {
                    $query->where('transaction_type', $transactionType);
                })
                ->when($semester !== '', function ($query) use ($semester): void {
                    $query->forSemester($semester);
                })
                ->sum('balance'));
        }

        $statementRows = collect([
            [
                'date_label' => __('receivable.bill_opening_balance'),
                'invoice_id' => null,
                'proof_number' => '',
                'entry_type' => 'opening',
                'transaction_type' => null,
                'transaction_type_label' => __('receivable.transaction_type_none'),
                'adjustment_amount' => 0,
                'credit_sales' => 0,
                'installment_payment' => 0,
                'sales_return' => 0,
                'running_balance' => $openingBalance,
            ],
        ]);

        $totals = [
            'credit_sales' => 0,
            'installment_payment' => 0,
            'sales_return' => 0,
            'adjustment_amount' => 0,
            'running_balance' => $openingBalance,
        ];

        $groupedRows = [];
        foreach ($ledgerRows as $ledgerRow) {
            $debit = (int) round((float) $ledgerRow->debit);
            $credit = (int) round((float) $ledgerRow->credit);
            $transactionType = $ledgerRow->transaction_type !== null && trim((string) $ledgerRow->transaction_type) !== ''
                ? TransactionType::normalize((string) $ledgerRow->transaction_type)
                : ($ledgerRow->invoice?->transaction_type
                    ? TransactionType::normalize((string) $ledgerRow->invoice->transaction_type)
                    : null);
            $description = strtolower((string) ($ledgerRow->description ?? ''));
            $isReturn = str_contains($description, 'retur') || str_contains($description, 'return');
            $isWriteoff = str_contains($description, 'write-off') || str_contains($description, 'writeoff');
            $isDiscount = str_contains($description, 'diskon') || str_contains($description, 'discount');
            $isAdminInvoiceAdjustment = str_contains($description, 'admin edit faktur')
                || str_contains($description, 'admin invoice edit')
                || str_contains($description, 'penyesuaian nilai faktur')
                || str_contains($description, 'invoice adjustment');
            $salesReturn = $isReturn ? $credit : 0;
            $installment = $isReturn ? 0 : $credit;
            $baseProofNumber = $ledgerRow->invoice?->invoice_number ?: (trim((string) ($ledgerRow->description ?? '')) ?: '-');
            $entryType = 'payment';
            if ($debit > 0) {
                $entryType = 'debit';
            } elseif ($salesReturn > 0) {
                $entryType = 'return';
            } elseif ($isWriteoff) {
                $entryType = 'writeoff';
            } elseif ($isDiscount) {
                $entryType = 'discount';
            }
            if ($isAdminInvoiceAdjustment) {
                $entryType = 'adjustment';
            }

            $proofNumber = match ($entryType) {
                'writeoff' => $baseProofNumber . ' - ' . __('receivable.method_writeoff'),
                'discount' => $baseProofNumber . ' - ' . __('receivable.method_discount'),
                'adjustment' => (trim((string) ($ledgerRow->description ?? '')) !== '')
                    ? trim((string) $ledgerRow->description)
                    : $baseProofNumber,
                default => $baseProofNumber,
            };
            $invoiceId = $ledgerRow->invoice?->id;
            $typeKey = $transactionType ?? 'none';
            $groupKey = $isAdminInvoiceAdjustment
                ? 'ledger:' . (int) $ledgerRow->id . ':adjustment:' . $typeKey
                : ($invoiceId !== null
                ? 'invoice:' . $invoiceId . ':' . $entryType . ':' . $typeKey
                : 'text:' . $baseProofNumber . ':' . $entryType . ':' . $typeKey);
            $dateValue = $debit > 0
                ? ($ledgerRow->invoice?->invoice_date ?: $ledgerRow->entry_date)
                : $ledgerRow->entry_date;

            if (!isset($groupedRows[$groupKey])) {
                $groupedRows[$groupKey] = [
                    'date_value' => $dateValue,
                    'date_ts' => $this->toTimestamp($dateValue),
                    'invoice_id' => $invoiceId,
                    'proof_number' => $proofNumber,
                    'entry_type' => $entryType,
                    'transaction_type' => $transactionType,
                    'adjustment_amount' => 0,
                    'credit_sales' => 0,
                    'installment_payment' => 0,
                    'sales_return' => 0,
                ];
            }

            if ($entryType === 'adjustment') {
                $groupedRows[$groupKey]['adjustment_amount'] += ($debit - $credit);
            } else {
                $groupedRows[$groupKey]['credit_sales'] += $debit;
                $groupedRows[$groupKey]['installment_payment'] += $installment;
                $groupedRows[$groupKey]['sales_return'] += $salesReturn;
            }
        }

        uasort($groupedRows, function (array $a, array $b): int {
            $left = (int) ($a['date_ts'] ?? 0);
            $right = (int) ($b['date_ts'] ?? 0);
            if ($left === $right) {
                return strcmp((string) ($a['proof_number'] ?? ''), (string) ($b['proof_number'] ?? ''));
            }

            return $left <=> $right;
        });

        $runningBalance = $openingBalance;
        foreach ($groupedRows as $groupedRow) {
            $delta = (int) $groupedRow['credit_sales']
                - (int) $groupedRow['installment_payment']
                - (int) $groupedRow['sales_return']
                + (int) ($groupedRow['adjustment_amount'] ?? 0);
            $runningBalance += $delta;

            $statementRows->push([
                'date_label' => $this->formatBillDate($groupedRow['date_value']),
                'invoice_id' => $groupedRow['invoice_id'],
                'proof_number' => $groupedRow['proof_number'],
                'entry_type' => (string) ($groupedRow['entry_type'] ?? 'payment'),
                'transaction_type' => $groupedRow['transaction_type'] ?? null,
                'transaction_type_label' => $this->transactionTypeLabel($groupedRow['transaction_type'] ?? null),
                'adjustment_amount' => (int) ($groupedRow['adjustment_amount'] ?? 0),
                'credit_sales' => (int) $groupedRow['credit_sales'],
                'installment_payment' => (int) $groupedRow['installment_payment'],
                'sales_return' => (int) $groupedRow['sales_return'],
                'running_balance' => $runningBalance,
            ]);

            $totals['credit_sales'] += (int) $groupedRow['credit_sales'];
            $totals['installment_payment'] += (int) $groupedRow['installment_payment'];
            $totals['sales_return'] += (int) $groupedRow['sales_return'];
            $totals['adjustment_amount'] += (int) ($groupedRow['adjustment_amount'] ?? 0);
            $totals['running_balance'] = $runningBalance;
        }

        return [
            'rows' => $statementRows,
            'totals' => $totals,
        ];
    }

    private function transactionTypeLabel(?string $transactionType): string
    {
        $normalized = $transactionType !== null && trim($transactionType) !== ''
            ? TransactionType::normalize($transactionType)
            : '';

        return match ($normalized) {
            TransactionType::PRINTING => __('receivable.transaction_type_printing'),
            TransactionType::PRODUCT => __('receivable.transaction_type_product'),
            default => __('receivable.transaction_type_none'),
        };
    }

    /**
     * @return Collection<int, array{
     *     school_name:string,
     *     school_city:string,
     *     rows:Collection<int, array{
     *         invoice_id:int|null,
     *         invoice_number:string,
     *         date_label:string,
     *         invoice_total:int,
     *         paid_total:int,
     *         balance_total:int
     *     }>,
     *     totals:array{invoice_total:int,paid_total:int,balance_total:int}
     * }>
     */
    private function buildCustomerBillSchoolBreakdown(int $customerId, ?string $selectedSemester, ?string $selectedTransactionType = null): Collection
    {
        $semester = $selectedSemester ?? '';
        $transactionType = $selectedTransactionType ?? '';
        $rows = SalesInvoice::query()
            ->select([
                'id',
                'invoice_number',
                'invoice_date',
                'semester_period',
                'total',
                'total_paid',
                'balance',
                'ship_to_name',
                'ship_to_city',
                'customer_ship_location_id',
            ])
            ->with(['shipLocation:id,school_name,city'])
            ->forCustomer($customerId)
            ->active()
            ->when($transactionType !== '', function ($query) use ($transactionType): void {
                $query->where('transaction_type', $transactionType);
            })
            ->when($semester !== '', function ($query) use ($semester): void {
                $query->forSemester($semester);
            })
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->map(function (SalesInvoice $invoice): array {
                $schoolName = trim((string) ($invoice->ship_to_name ?: ($invoice->shipLocation?->school_name ?: '')));
                $schoolCity = trim((string) ($invoice->ship_to_city ?: ($invoice->shipLocation?->city ?: '')));
                if ($schoolName === '') {
                    $schoolName = (string) __('receivable.unknown_school');
                }

                return [
                    'school_name' => $schoolName,
                    'school_city' => $schoolCity !== '' ? $schoolCity : '-',
                    'invoice_id' => $invoice->id ? (int) $invoice->id : null,
                    'invoice_number' => (string) ($invoice->invoice_number ?? '-'),
                    'date_label' => $this->formatBillDate($invoice->invoice_date),
                    'invoice_total' => (int) round((float) ($invoice->total ?? 0)),
                    'paid_total' => (int) round((float) ($invoice->total_paid ?? 0)),
                    'balance_total' => (int) round((float) ($invoice->balance ?? 0)),
                ];
            });

        if ($rows->isEmpty()) {
            return collect();
        }

        return $rows
            ->groupBy(fn(array $row): string => mb_strtolower(($row['school_name'] ?? '-') . '|' . ($row['school_city'] ?? '-')))
            ->map(function (Collection $group): array {
                $first = (array) $group->first();

                return [
                    'school_name' => (string) ($first['school_name'] ?? '-'),
                    'school_city' => (string) ($first['school_city'] ?? '-'),
                    'rows' => $group->values(),
                    'totals' => [
                        'invoice_total' => (int) $group->sum(fn(array $row): int => (int) ($row['invoice_total'] ?? 0)),
                        'paid_total' => (int) $group->sum(fn(array $row): int => (int) ($row['paid_total'] ?? 0)),
                        'balance_total' => (int) $group->sum(fn(array $row): int => (int) ($row['balance_total'] ?? 0)),
                    ],
                ];
            })
            ->sortBy(fn(array $group): string => mb_strtolower((string) ($group['school_name'] ?? '-')))
            ->values();
    }

    private function formatBillDate(mixed $value): string
    {
        if (!$value) {
            return '-';
        }

        try {
            $date = $value instanceof Carbon ? $value : Carbon::parse((string) $value);
        } catch (\Throwable) {
            return '-';
        }

        return $date->format('d-m-Y');
    }

    private function toTimestamp(mixed $value): int
    {
        if (!$value) {
            return 0;
        }

        try {
            $date = $value instanceof Carbon ? $value : Carbon::parse((string) $value);
        } catch (\Throwable) {
            return 0;
        }

        return (int) $date->timestamp;
    }

    /**
     * Collapse payment mutation rows so each payment ref (KWT/PYT) appears once.
     * Priority: keep summary row; if only allocation rows exist, keep one row
     * and normalize its description to a summary label.
     *
     * @param Collection<int, ReceivableLedger> $rows
     * @return Collection<int, ReceivableLedger>
     */
    private function filterRedundantPaymentSummaryRows(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $firstIndexByPaymentRef = [];
        $summaryByPaymentRef = [];
        foreach ($rows as $index => $row) {
            $description = (string) ($row->description ?? '');
            $paymentRef = $this->extractPaymentRef($description);
            if ($paymentRef === null) {
                continue;
            }

            if (!isset($firstIndexByPaymentRef[$paymentRef])) {
                $firstIndexByPaymentRef[$paymentRef] = $index;
            }

            if (! $this->isAllocationPaymentRow($row, $description) && !isset($summaryByPaymentRef[$paymentRef])) {
                $summaryByPaymentRef[$paymentRef] = $row;
            }
        }

        if ($firstIndexByPaymentRef === []) {
            return $rows;
        }

        $result = collect();
        $pushedPaymentRef = [];
        foreach ($rows as $index => $row) {
            $description = (string) ($row->description ?? '');
            $paymentRef = $this->extractPaymentRef($description);
            if ($paymentRef === null) {
                $result->push($row);
                continue;
            }

            if (isset($pushedPaymentRef[$paymentRef])) {
                continue;
            }

            $canonicalRow = $summaryByPaymentRef[$paymentRef] ?? null;
            if ($canonicalRow instanceof ReceivableLedger) {
                $result->push($canonicalRow);
                $pushedPaymentRef[$paymentRef] = true;
                continue;
            }

            if (($firstIndexByPaymentRef[$paymentRef] ?? null) === $index) {
                $row->setAttribute('description', (string) __('receivable.receivable_payment', ['payment' => $paymentRef]));
                $row->setAttribute('invoice_id', null);
                $row->setRelation('invoice', null);
                $result->push($row);
                $pushedPaymentRef[$paymentRef] = true;
            }
        }

        return $result->values();
    }

    /**
     * @param Collection<int, ReceivableLedger> $rows
     * @return array<string, bool>
     */
    private function paymentRefsWithAlloc(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $refs = [];
        foreach ($rows as $row) {
            $description = (string) ($row->description ?? '');
            $paymentRef = $this->extractPaymentRef($description);
            if ($paymentRef === null) {
                continue;
            }
            if ($this->isAllocationPaymentRow($row, $description)) {
                $refs[$paymentRef] = true;
            }
        }

        return $refs;
    }

    private function isAllocationPaymentRow(ReceivableLedger $row, string $description): bool
    {
        return $row->invoice_id !== null
            || str_contains(strtolower($description), ' untuk ')
            || str_contains(strtolower($description), ' for ');
    }

    private function extractPaymentRef(string $description): ?string
    {
        if (preg_match('/\b(?:KWT|PYT)-\d{8}-\d{4}\b/i', $description, $matches) !== 1) {
            return null;
        }

        return strtoupper((string) $matches[0]);
    }

    private function nowWib(): Carbon
    {
        return now('Asia/Jakarta');
    }
}
