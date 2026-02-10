<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\InvoicePayment;
use App\Models\ReceivableLedger;
use App\Models\SalesInvoice;
use App\Services\ReceivableLedgerService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivablePageController extends Controller
{
    public function __construct(
        private readonly ReceivableLedgerService $receivableLedgerService
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $customerId = $request->integer('customer_id');
        $semester = trim((string) $request->string('semester', ''));
        $selectedSemester = $semester !== '' ? $semester : null;
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

        $customersQuery = Customer::query()
            ->select(['customers.id', 'customers.name', 'customers.city'])
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

        $ledgerRows = collect();
        $selectedCustomerName = null;
        if ($customerId > 0) {
            $selectedCustomerName = Customer::query()
                ->whereKey($customerId)
                ->value('name');

            $ledgerRows = ReceivableLedger::query()
                ->with('invoice:id,invoice_number,balance,semester_period,customer_id')
                ->where('customer_id', $customerId)
                ->when($selectedSemester !== null, function ($query) use ($selectedSemester): void {
                    $query->where('period_code', $selectedSemester);
                })
                ->latest('entry_date')
                ->latest('id')
                ->limit(50)
                ->get();
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
        ]);
    }

    public function pay(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank_transfer'],
            'payment_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string'],
            'semester' => ['nullable', 'string', 'max:30'],
            'customer_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($salesInvoice, $data): void {
            $invoice = SalesInvoice::query()
                ->whereKey($salesInvoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $selectedCustomerId = (int) ($data['customer_id'] ?? 0);
            if ($selectedCustomerId > 0 && (int) $invoice->customer_id !== $selectedCustomerId) {
                throw ValidationException::withMessages([
                    'amount' => __('receivable.invalid_invoice_customer'),
                ]);
            }

            $amount = (float) $data['amount'];
            $invoiceBalance = (float) $invoice->balance;
            if ($invoiceBalance <= 0) {
                throw ValidationException::withMessages([
                    'amount' => __('receivable.invoice_already_paid'),
                ]);
            }

            if ($amount > $invoiceBalance) {
                throw ValidationException::withMessages([
                    'amount' => __('receivable.payment_exceeds_balance'),
                ]);
            }

            $paymentDate = isset($data['payment_date']) && $data['payment_date'] !== ''
                ? Carbon::parse($data['payment_date'])
                : now();

            InvoicePayment::create([
                'sales_invoice_id' => $invoice->id,
                'payment_date' => $paymentDate->toDateString(),
                'amount' => $amount,
                'method' => $data['method'],
                'notes' => 'Payment from receivable ledger',
            ]);

            $newTotalPaid = (float) $invoice->total_paid + $amount;
            $newBalance = max(0, (float) $invoice->total - $newTotalPaid);

            $invoice->update([
                'total_paid' => $newTotalPaid,
                'balance' => $newBalance,
                'payment_status' => $newBalance <= 0 ? 'paid' : 'partial',
            ]);

            $this->receivableLedgerService->addCredit(
                customerId: (int) $invoice->customer_id,
                invoiceId: (int) $invoice->id,
                entryDate: $paymentDate,
                amount: $amount,
                periodCode: $invoice->semester_period,
                description: "Payment for {$invoice->invoice_number}"
            );
        });

        return redirect()
            ->route('receivables.index', [
                'search' => $data['search'] ?? null,
                'semester' => $data['semester'] ?? null,
                'customer_id' => $data['customer_id'] ?? $salesInvoice->customer_id,
            ])
            ->with('success', __('receivable.payment_saved'));
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
        $year = $previous->year;

        return "S{$semester}-{$year}";
    }

    private function configuredSemesterOptions()
    {
        return collect(explode(',', (string) AppSetting::getValue('semester_period_options', '')))
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '');
    }
}
