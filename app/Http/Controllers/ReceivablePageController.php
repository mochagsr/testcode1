<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\InvoicePayment;
use App\Models\ReceivableLedger;
use App\Models\SalesInvoice;
use App\Services\ReceivableLedgerService;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivablePageController extends Controller
{
    public function __construct(
        private readonly ReceivableLedgerService $receivableLedgerService,
        private readonly SemesterBookService $semesterBookService
    ) {
    }

    public function index(Request $request): View
    {
        $isAdminUser = (string) ($request->user()?->role ?? '') === 'admin';
        $search = trim((string) $request->string('search', ''));
        $customerId = $request->integer('customer_id');
        $semester = trim((string) $request->string('semester', ''));
        $selectedSemester = $semester !== '' ? $this->semesterBookService->normalizeSemester($semester) : null;
        $currentSemester = $this->currentSemesterPeriod();
        $previousSemester = $this->previousSemesterPeriod($currentSemester);

        $semesterOptions = ReceivableLedger::query()
            ->whereNotNull('period_code')
            ->where('period_code', '!=', '')
            ->distinct()
            ->orderByDesc('period_code')
            ->pluck('period_code')
            ->merge($this->configuredSemesterOptions())
            ->push($currentSemester)
            ->push($previousSemester)
            ->unique()
            ->sortDesc()
            ->values();
        $semesterOptions = $isAdminUser
            ? $semesterOptions->values()
            : collect($this->semesterBookService()->filterToActiveSemesters($semesterOptions->all()));
        if ($selectedSemester !== null && ! $semesterOptions->contains($selectedSemester)) {
            $selectedSemester = null;
        }

        $customersQuery = Customer::query()
            ->select(['customers.id', 'customers.name', 'customers.city', 'customers.credit_balance'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            });

        if ($selectedSemester !== null) {
            $semesterLedger = ReceivableLedger::query()
                ->selectRaw('customer_id, SUM(debit - credit) as semester_outstanding')
                ->where('period_code', $selectedSemester)
                ->groupBy('customer_id');

            $customersQuery
                ->joinSub($semesterLedger, 'semester_ledger', function ($join): void {
                    $join->on('customers.id', '=', 'semester_ledger.customer_id');
                })
                ->addSelect(DB::raw('semester_ledger.semester_outstanding as outstanding_receivable'))
                ->orderByDesc('semester_ledger.semester_outstanding')
                ->orderBy('customers.name');
        } else {
            $customersQuery
                ->addSelect('customers.outstanding_receivable')
                ->orderByDesc('customers.outstanding_receivable')
                ->orderBy('customers.name');
        }

        $customers = $customersQuery
            ->paginate(25)
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
        $ledgerOutstandingTotal = null;
        $customerOutstandingTotal = null;
        $selectedCustomerSemesterClosed = false;
        if ($customerId > 0) {
            $selectedCustomer = Customer::query()->find($customerId);
            $selectedCustomerName = $selectedCustomer?->name;
            if ($selectedSemester !== null) {
                $selectedCustomerSemesterClosed = $this->semesterBookService->isCustomerLocked($customerId, $selectedSemester);
            }

            $customerOutstandingTotal = (float) SalesInvoice::query()
                ->where('customer_id', $customerId)
                ->where('is_canceled', false)
                ->where('balance', '>', 0)
                ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                    $query->where('semester_period', $selectedSemester);
                })
                ->sum('balance');

            $outstandingInvoices = SalesInvoice::query()
                ->select(['id', 'invoice_number', 'invoice_date', 'semester_period', 'total', 'total_paid', 'balance'])
                ->where('customer_id', $customerId)
                ->where('is_canceled', false)
                ->where('balance', '>', 0)
                ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                    $query->where('semester_period', $selectedSemester);
                })
                ->orderBy('invoice_date')
                ->orderBy('id')
                ->get();

            $ledgerOutstandingTotal = (float) ReceivableLedger::query()
                ->where('customer_id', $customerId)
                ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                    $query->where('period_code', $selectedSemester);
                })
                ->sum(DB::raw('debit - credit'));
            $ledgerRows = ReceivableLedger::query()
                ->select([
                    'id',
                    'customer_id',
                    'sales_invoice_id',
                    'entry_date',
                    'description',
                    'debit',
                    'credit',
                    'balance_after',
                    'period_code',
                ])
                ->with('invoice:id,invoice_number,balance,semester_period,customer_id,is_canceled')
                ->where('customer_id', $customerId)
                ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                    $query->where('period_code', $selectedSemester);
                })
                ->latest('entry_date')
                ->latest('id')
                ->limit(50)
                ->get();
            $ledgerRows = $this->filterRedundantPaymentSummaryRows($ledgerRows);

            if ($selectedCustomer) {
                $statementData = $this->buildCustomerBillStatement($selectedCustomer, $selectedSemester);
                $billStatementRows = $statementData['rows'];
                $billStatementTotals = $statementData['totals'];
            }
        }

        return view('receivables.index', [
            'customers' => $customers,
            'ledgerRows' => $ledgerRows,
            'search' => $search,
            'selectedCustomerId' => $customerId,
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
            'selectedSemesterGlobalClosed' => $selectedSemesterGlobalClosed,
            'selectedSemesterActive' => $selectedSemesterActive,
            'customerSemesterClosedMap' => $customerSemesterClosedMap,
            'customerSemesterAutoClosedMap' => $customerSemesterAutoClosedMap,
            'customerSemesterManualClosedMap' => $customerSemesterManualClosedMap,
        ]);
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

    public function customerWriteoff(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'payment_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string'],
            'semester' => ['nullable', 'string', 'max:30'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $this->processCustomerAdjustment($customer, $data, 'writeoff');

        return redirect()
            ->route('receivables.index', [
                'search' => $data['search'] ?? null,
                'semester' => $data['semester'] ?? null,
                'customer_id' => $customer->id,
            ])
            ->with('success', __('receivable.payment_saved'));
    }

    public function customerDiscount(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'payment_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string'],
            'semester' => ['nullable', 'string', 'max:30'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $this->processCustomerAdjustment($customer, $data, 'discount');

        return redirect()
            ->route('receivables.index', [
                'search' => $data['search'] ?? null,
                'semester' => $data['semester'] ?? null,
                'customer_id' => $customer->id,
            ])
            ->with('success', __('receivable.payment_saved'));
    }

    public function printCustomerBill(Request $request, Customer $customer): View
    {
        return view('receivables.print_customer_bill', $this->customerBillViewData($request, $customer));
    }

    public function exportCustomerBillPdf(Request $request, Customer $customer)
    {
        $data = $this->customerBillViewData($request, $customer);
        $data['isPdf'] = true;
        $filename = 'tagihan-'.$customer->id.'-'.$this->nowWib()->format('Ymd-His').'.pdf';

        return Pdf::loadView('receivables.print_customer_bill', $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function exportCustomerBillExcel(Request $request, Customer $customer): StreamedResponse
    {
        $data = $this->customerBillViewData($request, $customer);
        $rows = collect($data['rows'] ?? []);
        $filename = 'tagihan-'.$customer->id.'-'.$this->nowWib()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($rows, $data): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tagihan');
            $rowsOut = [];
            $rowsOut[] = [__('receivable.customer_bill_title')];
            $rowsOut[] = [__('receivable.customer'), $data['customer']->name ?? ''];
            $rowsOut[] = [__('txn.address'), $data['customer']->address ?: ($data['customer']->city ?: '-')];
            if (!empty($data['selectedSemester'])) {
                $rowsOut[] = [__('txn.semester_period'), (string) $data['selectedSemester']];
            }
            $rowsOut[] = [];
            $rowsOut[] = [
                __('receivable.bill_date'),
                __('receivable.bill_proof_number'),
                __('receivable.bill_credit_sales'),
                __('receivable.bill_installment_payment'),
                __('receivable.bill_sales_return'),
                __('receivable.bill_running_balance'),
            ];

            foreach ($rows as $row) {
                $rowsOut[] = [
                    (string) ($row['date_label'] ?? ''),
                    (string) ($row['proof_number'] ?? ''),
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
                __('receivable.bill_total_receivable'),
                number_format((int) round((float) ($totals['running_balance'] ?? 0)), 0, ',', '.'),
            ];

            $sheet->fromArray($rowsOut, null, 'A1');
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
                ->where('customer_id', $customerId)
                ->where('is_canceled', false)
                ->where('balance', '>', 0)
                ->when($selectedSemester !== '', function ($query) use ($selectedSemester): void {
                    $query->where('semester_period', $selectedSemester);
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
                    }
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

    private function configuredSemesterOptions()
    {
        return collect(preg_split('/[\r\n,]+/', (string) AppSetting::getValue('semester_period_options', '')) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '');
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

    private function semesterSortValue(string $periodCode): int
    {
        if (preg_match('/^S([12])-(\d{2})(\d{2})$/', $periodCode, $matches) === 1) {
            $semester = (int) $matches[1];
            $startYear = 2000 + (int) $matches[2];

            return ($startYear * 10) + $semester;
        }
        return PHP_INT_MAX;
    }

    /**
     * @return array<string, mixed>
     */
    private function customerBillViewData(Request $request, Customer $customer): array
    {
        $selectedSemester = $this->normalizeSemester((string) $request->string('semester', ''));
        $statementData = $this->buildCustomerBillStatement($customer, $selectedSemester !== '' ? $selectedSemester : null);
        $statementRows = $statementData['rows'];
        $totals = $statementData['totals'];

        return [
            'customer' => $customer,
            'selectedSemester' => $selectedSemester !== '' ? $selectedSemester : null,
            'selectedSemesterLabel' => $selectedSemester !== '' ? $this->semesterDescriptionLabel($selectedSemester) : null,
            'rows' => $statementRows,
            'totalOutstanding' => (int) round((float) $totals['running_balance']),
            'totals' => $totals,
            'companyLogoPath' => AppSetting::getValue('company_logo_path'),
            'companyName' => trim((string) AppSetting::getValue('company_name', 'CV. PUSTAKA GRAFIKA')),
            'companyAddress' => trim((string) AppSetting::getValue('company_address', '')),
            'companyPhone' => trim((string) AppSetting::getValue('company_phone', '')),
            'companyEmail' => trim((string) AppSetting::getValue('company_email', '')),
            'companyInvoiceNotes' => trim((string) AppSetting::getValue('company_invoice_notes', '')),
        ];
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

    private function semesterBookService(): SemesterBookService
    {
        return app(SemesterBookService::class);
    }

    /**
     * @return array{rows:Collection<int, array<string, int|string|null>>, totals:array<string, int>}
     */
    private function buildCustomerBillStatement(Customer $customer, ?string $selectedSemester): array
    {
        $semester = $selectedSemester ?? '';
        $ledgerRows = ReceivableLedger::query()
            ->with('invoice:id,invoice_number,invoice_date')
            ->where('customer_id', $customer->id)
            ->when($semester !== '', function ($query) use ($semester): void {
                $query->where('period_code', $semester);
            })
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        if ($ledgerRows->isNotEmpty()) {
            $first = $ledgerRows->first();
            $openingBalance = (int) round((float) $first->balance_after - (float) $first->debit + (float) $first->credit);
        } else {
            $openingBalance = (int) round((float) SalesInvoice::query()
                ->where('customer_id', $customer->id)
                ->where('is_canceled', false)
                ->where('balance', '>', 0)
                ->when($semester !== '', function ($query) use ($semester): void {
                    $query->where('semester_period', $semester);
                })
                ->sum('balance'));
        }

        $statementRows = collect([
            [
                'date_label' => __('receivable.bill_opening_balance'),
                'invoice_id' => null,
                'proof_number' => '',
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
            'running_balance' => $openingBalance,
        ];

        $groupedRows = [];
        foreach ($ledgerRows as $ledgerRow) {
            $debit = (int) round((float) $ledgerRow->debit);
            $credit = (int) round((float) $ledgerRow->credit);
            $description = strtolower((string) ($ledgerRow->description ?? ''));
            $isReturn = str_contains($description, 'retur') || str_contains($description, 'return');
            $salesReturn = $isReturn ? $credit : 0;
            $installment = $isReturn ? 0 : $credit;
            $proofNumber = $ledgerRow->invoice?->invoice_number ?: (trim((string) ($ledgerRow->description ?? '')) ?: '-');
            $invoiceId = $ledgerRow->invoice?->id;
            $groupKey = $invoiceId !== null
                ? 'invoice:'.$invoiceId
                : 'text:'.$proofNumber;

            if (!isset($groupedRows[$groupKey])) {
                $groupedRows[$groupKey] = [
                    'date_value' => $ledgerRow->invoice?->invoice_date ?: $ledgerRow->entry_date,
                    'invoice_id' => $invoiceId,
                    'proof_number' => $proofNumber,
                    'credit_sales' => 0,
                    'installment_payment' => 0,
                    'sales_return' => 0,
                ];
            }

            $groupedRows[$groupKey]['credit_sales'] += $debit;
            $groupedRows[$groupKey]['installment_payment'] += $installment;
            $groupedRows[$groupKey]['sales_return'] += $salesReturn;
        }

        uasort($groupedRows, function (array $a, array $b): int {
            $left = $a['date_value'] instanceof Carbon
                ? $a['date_value']->timestamp
                : Carbon::parse((string) $a['date_value'])->timestamp;
            $right = $b['date_value'] instanceof Carbon
                ? $b['date_value']->timestamp
                : Carbon::parse((string) $b['date_value'])->timestamp;
            if ($left === $right) {
                return strcmp((string) ($a['proof_number'] ?? ''), (string) ($b['proof_number'] ?? ''));
            }

            return $left <=> $right;
        });

        $runningBalance = $openingBalance;
        foreach ($groupedRows as $groupedRow) {
            $delta = (int) $groupedRow['credit_sales']
                - (int) $groupedRow['installment_payment']
                - (int) $groupedRow['sales_return'];
            $runningBalance += $delta;

            $statementRows->push([
                'date_label' => $this->formatBillDate($groupedRow['date_value']),
                'invoice_id' => $groupedRow['invoice_id'],
                'proof_number' => $groupedRow['proof_number'],
                'credit_sales' => (int) $groupedRow['credit_sales'],
                'installment_payment' => (int) $groupedRow['installment_payment'],
                'sales_return' => (int) $groupedRow['sales_return'],
                'running_balance' => $runningBalance,
            ]);

            $totals['credit_sales'] += (int) $groupedRow['credit_sales'];
            $totals['installment_payment'] += (int) $groupedRow['installment_payment'];
            $totals['sales_return'] += (int) $groupedRow['sales_return'];
            $totals['running_balance'] = $runningBalance;
        }

        return [
            'rows' => $statementRows,
            'totals' => $totals,
        ];
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
