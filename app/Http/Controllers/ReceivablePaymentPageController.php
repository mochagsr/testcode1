<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InvoicePayment;
use App\Models\ReceivablePayment;
use App\Models\SalesInvoice;
use App\Services\ReceivableLedgerService;
use App\Services\AccountingService;
use App\Support\AppCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReceivablePaymentPageController extends Controller
{
    public function __construct(
        private readonly ReceivableLedgerService $receivableLedgerService,
        private readonly AccountingService $accountingService
    ) {}

    public function index(Request $request): View
    {
        $now = now();
        $search = trim((string) $request->string('search', ''));
        $status = trim((string) $request->string('status', ''));
        $paymentDate = trim((string) $request->string('payment_date', ''));
        $selectedStatus = in_array($status, ['active', 'canceled'], true) ? $status : null;
        $selectedPaymentDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate) === 1 ? $paymentDate : null;
        $selectedPaymentDateRange = $selectedPaymentDate !== null
            ? [
                Carbon::parse($selectedPaymentDate)->startOfDay(),
                Carbon::parse($selectedPaymentDate)->endOfDay(),
            ]
            : null;
        $isDefaultRecentMode = $selectedPaymentDateRange === null && $search === '';
        $recentRangeStart = $now->copy()->subDays(6)->startOfDay();

        $payments = ReceivablePayment::query()
            ->onlyListColumns()
            ->withCustomerInfo()
            ->searchKeyword($search)
            ->when($selectedStatus === 'active', fn($query) => $query->active())
            ->when($selectedStatus === 'canceled', fn($query) => $query->canceled())
            ->when($selectedPaymentDateRange !== null, fn($query) => $query->betweenDates(
                $selectedPaymentDateRange[0],
                $selectedPaymentDateRange[1]
            ))
            ->when($isDefaultRecentMode, function ($query) use ($recentRangeStart): void {
                $query->where('payment_date', '>=', $recentRangeStart);
            })
            ->orderByDate()
            ->paginate(20)
            ->withQueryString();

        return view('receivable_payments.index', [
            'payments' => $payments,
            'search' => $search,
            'selectedStatus' => $selectedStatus,
            'selectedPaymentDate' => $selectedPaymentDate,
            'isDefaultRecentMode' => $isDefaultRecentMode,
        ]);
    }

    public function create(Request $request): View
    {
        $now = now();
        $prefillCustomerId = $request->integer('customer_id');
        $rawPrefillAmount = $request->integer('amount', 0);
        $prefillAmount = $rawPrefillAmount > 0 ? $rawPrefillAmount : null;
        $prefillDate = trim((string) $request->string('payment_date', $now->format('Y-m-d')));
        $preferredInvoiceId = $request->integer('preferred_invoice_id');
        $returnTo = $this->sanitizeReturnPath((string) $request->string('return_to', ''));

        $preferredInvoice = null;
        if ($prefillCustomerId > 0 && $preferredInvoiceId > 0) {
            $preferredInvoice = SalesInvoice::query()
                ->whereKey($preferredInvoiceId)
                ->where('customer_id', $prefillCustomerId)
                ->active()
                ->withOpenBalance()
                ->first(['id', 'invoice_number', 'balance']);
        }
        $oldCustomerId = (int) old('customer_id', $prefillCustomerId > 0 ? $prefillCustomerId : 0);
        $customers = Cache::remember(
            AppCache::lookupCacheKey('forms.receivable_payments.customers', ['limit' => 20]),
            $now->copy()->addSeconds(60),
            fn() => Customer::query()
                ->onlyReceivableFormColumns()
                ->orderBy('name')
                ->limit(20)
                ->get()
        );
        if ($oldCustomerId > 0 && ! $customers->contains('id', $oldCustomerId)) {
            $oldCustomer = Customer::query()
                ->onlyReceivableFormColumns()
                ->whereKey($oldCustomerId)
                ->first();
            if ($oldCustomer !== null) {
                $customers->prepend($oldCustomer);
            }
        }
        $customers = $customers->unique('id')->values();

        return view('receivable_payments.create', [
            'customers' => $customers,
            'prefillCustomerId' => $prefillCustomerId > 0 ? $prefillCustomerId : null,
            'prefillAmount' => $prefillAmount,
            'prefillDate' => $prefillDate !== '' ? $prefillDate : $now->format('Y-m-d'),
            'preferredInvoice' => $preferredInvoice,
            'returnTo' => $returnTo,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'payment_date' => ['required', 'date'],
            'customer_address' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'preferred_invoice_id' => ['nullable', 'integer', 'exists:sales_invoices,id'],
            'return_to' => ['nullable', 'string', 'max:500'],
            'customer_signature' => ['required', 'string', 'max:120'],
            'user_signature' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = DB::transaction(function () use ($data): ReceivablePayment {
            $customer = Customer::query()
                ->lockForUpdate()
                ->findOrFail((int) $data['customer_id']);

            $amount = (float) $data['amount'];

            $paymentDate = Carbon::parse((string) $data['payment_date']);
            $payment = ReceivablePayment::create([
                'payment_number' => $this->generatePaymentNumber($paymentDate->toDateString()),
                'customer_id' => $customer->id,
                'payment_date' => $paymentDate->toDateString(),
                'customer_address' => $data['customer_address'] ?: $customer->address,
                'amount' => $amount,
                'amount_in_words' => $this->toIndonesianWords($amount),
                'customer_signature' => $data['customer_signature'],
                'user_signature' => $data['user_signature'],
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => auth()->id(),
            ]);

            $remaining = $amount;
            $appliedToInvoices = 0.0;
            $invoices = SalesInvoice::query()
                ->where('customer_id', $customer->id)
                ->active()
                ->withOpenBalance()
                ->orderByDate('asc')
                ->lockForUpdate()
                ->get();

            $preferredInvoiceId = (int) ($data['preferred_invoice_id'] ?? 0);
            if ($preferredInvoiceId > 0) {
                $preferred = $invoices->firstWhere('id', $preferredInvoiceId);
                if ($preferred) {
                    $invoices = collect([$preferred])->concat(
                        $invoices->reject(fn(SalesInvoice $invoice): bool => (int) $invoice->id === $preferredInvoiceId)
                    )->values();
                }
            }

            foreach ($invoices as $invoice) {
                if ($remaining <= 0) {
                    break;
                }

                $invoiceBalance = (float) $invoice->balance;
                if ($invoiceBalance <= 0) {
                    continue;
                }

                $payAmount = min($remaining, $invoiceBalance);

                InvoicePayment::create([
                    'sales_invoice_id' => $invoice->id,
                    'payment_date' => $paymentDate->toDateString(),
                    'amount' => $payAmount,
                    'method' => 'cash',
                    'notes' => __('receivable.receivable_payment', ['payment' => $payment->payment_number]),
                ]);

                $newTotalPaid = (float) $invoice->total_paid + $payAmount;
                $newBalance = max(0, (float) $invoice->total - $newTotalPaid);
                $invoice->update([
                    'total_paid' => $newTotalPaid,
                    'balance' => $newBalance,
                    'payment_status' => $newBalance <= 0 ? 'paid' : 'unpaid',
                ]);

                $this->receivableLedgerService->addCredit(
                    customerId: (int) $invoice->customer_id,
                    invoiceId: (int) $invoice->id,
                    entryDate: $paymentDate,
                    amount: $payAmount,
                    periodCode: $invoice->semester_period,
                    description: __('receivable.receivable_payment_for_invoice', [
                        'payment' => $payment->payment_number,
                        'invoice' => $invoice->invoice_number,
                    ])
                );

                $remaining -= $payAmount;
                $appliedToInvoices += $payAmount;
            }

            if ($remaining > 0) {
                $newCreditBalance = (float) $customer->credit_balance + $remaining;
                $customer->update([
                    'credit_balance' => $newCreditBalance,
                ]);

                $this->receivableLedgerService->addCredit(
                    customerId: (int) $customer->id,
                    invoiceId: null,
                    entryDate: $paymentDate,
                    amount: $remaining,
                    periodCode: null,
                    description: __('receivable.receivable_payment', ['payment' => $payment->payment_number])
                );
            }

            $this->accountingService->postReceivablePayment(
                paymentId: (int) $payment->id,
                date: $paymentDate,
                appliedAmount: (int) round($appliedToInvoices),
                overPayment: (int) round(max(0, $remaining))
            );

            return $payment;
        });

        $redirect = redirect()->route('receivable-payments.show', $payment);
        $returnTo = $this->sanitizeReturnPath((string) ($data['return_to'] ?? ''));
        if ($returnTo !== null) {
            $redirect->with('receivable_payment_return_to', $returnTo);
        }
        AppCache::forgetAfterFinancialMutation([(string) $payment->payment_date]);

        return $redirect
            ->with('success', __('receivable.receivable_payment_saved'));
    }

    public function show(ReceivablePayment $receivablePayment): View
    {
        $receivablePayment->load('customer:id,name,city,address,phone');
        $returnTo = $this->sanitizeReturnPath((string) session('receivable_payment_return_to', ''));

        return view('receivable_payments.show', [
            'payment' => $receivablePayment,
            'returnTo' => $returnTo,
        ]);
    }

    public function adminUpdate(Request $request, ReceivablePayment $receivablePayment): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'customer_address' => ['nullable', 'string', 'max:255'],
            'customer_signature' => ['required', 'string', 'max:120'],
            'user_signature' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $receivablePayment->update([
            'payment_date' => $data['payment_date'],
            'customer_address' => $data['customer_address'] ?? null,
            'customer_signature' => $data['customer_signature'],
            'user_signature' => $data['user_signature'],
            'notes' => $data['notes'] ?? null,
        ]);
        AppCache::forgetAfterFinancialMutation([(string) $receivablePayment->payment_date]);

        return redirect()
            ->route('receivable-payments.show', $receivablePayment)
            ->with('success', __('txn.admin_update_saved'));
    }

    public function cancel(Request $request, ReceivablePayment $receivablePayment): RedirectResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($receivablePayment, $data): void {
            $payment = ReceivablePayment::query()
                ->whereKey($receivablePayment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->is_canceled) {
                return;
            }

            $customer = Customer::query()
                ->whereKey($payment->customer_id)
                ->lockForUpdate()
                ->firstOrFail();

            $paymentRef = __('receivable.receivable_payment', ['payment' => $payment->payment_number]);

            $invoicePayments = InvoicePayment::query()
                ->with('invoice:id,customer_id,total,total_paid,balance,payment_status,invoice_number,semester_period')
                ->where('method', 'cash')
                ->where('notes', $paymentRef)
                ->whereDate('payment_date', $payment->payment_date?->toDateString())
                ->lockForUpdate()
                ->get();

            $appliedToInvoices = 0.0;
            foreach ($invoicePayments as $invoicePayment) {
                $invoice = $invoicePayment->invoice;
                if (! $invoice || (int) $invoice->customer_id !== (int) $customer->id) {
                    continue;
                }

                $amount = (float) $invoicePayment->amount;
                if ($amount <= 0) {
                    continue;
                }

                $appliedToInvoices += $amount;

                $newTotalPaid = max(0, (float) $invoice->total_paid - $amount);
                $newBalance = max(0, (float) $invoice->total - $newTotalPaid);
                $invoice->update([
                    'total_paid' => $newTotalPaid,
                    'balance' => $newBalance,
                    'payment_status' => $newBalance <= 0 ? 'paid' : 'unpaid',
                ]);

                $this->receivableLedgerService->addDebit(
                    customerId: (int) $customer->id,
                    invoiceId: (int) $invoice->id,
                    entryDate: now(),
                    amount: $amount,
                    periodCode: $invoice->semester_period,
                    description: __('txn.cancel_receivable_payment_invoice_ledger_note', [
                        'payment' => $payment->payment_number,
                        'invoice' => $invoice->invoice_number,
                    ]),
                );

                $invoicePayment->delete();
            }

            $overPayment = max(0, (float) $payment->amount - $appliedToInvoices);
            if ($overPayment > 0) {
                $customer->update([
                    'credit_balance' => max(0, (float) $customer->credit_balance - $overPayment),
                ]);

                $this->receivableLedgerService->addDebit(
                    customerId: (int) $customer->id,
                    invoiceId: null,
                    entryDate: now(),
                    amount: $overPayment,
                    periodCode: null,
                    description: __('txn.cancel_receivable_payment_balance_ledger_note', [
                        'payment' => $payment->payment_number,
                    ]),
                );
            }

            $payment->update([
                'is_canceled' => true,
                'canceled_at' => now(),
                'canceled_by_user_id' => auth()->id(),
                'cancel_reason' => $data['cancel_reason'],
            ]);
        });
        AppCache::forgetAfterFinancialMutation([(string) $receivablePayment->payment_date]);

        return redirect()
            ->route('receivable-payments.show', $receivablePayment)
            ->with('success', __('txn.transaction_canceled_success'));
    }

    public function print(ReceivablePayment $receivablePayment): View
    {
        $receivablePayment->load('customer:id,name,city,address,phone');

        return view('receivable_payments.print', [
            'payment' => $receivablePayment,
        ]);
    }

    public function exportPdf(ReceivablePayment $receivablePayment)
    {
        $receivablePayment->load('customer:id,name,city,address,phone');

        $filename = $receivablePayment->payment_number . '.pdf';
        $pdf = Pdf::loadView('receivable_payments.print', [
            'payment' => $receivablePayment,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    private function generatePaymentNumber(string $date): string
    {
        $prefix = 'KWT-' . date('Ymd', strtotime($date));
        $count = ReceivablePayment::query()
            ->whereDate('payment_date', $date)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    private function toIndonesianWords(float $amount): string
    {
        $integerPart = (int) round($amount);
        $result = trim($this->spellNumber($integerPart)) . ' rupiah';

        return ucfirst(trim($result));
    }

    private function spellNumber(int $number): string
    {
        $number = abs($number);
        $words = [
            '',
            'satu',
            'dua',
            'tiga',
            'empat',
            'lima',
            'enam',
            'tujuh',
            'delapan',
            'sembilan',
            'sepuluh',
            'sebelas',
        ];

        if ($number < 12) {
            return $words[$number];
        }
        if ($number < 20) {
            return $this->spellNumber($number - 10) . ' belas';
        }
        if ($number < 100) {
            return $this->spellNumber((int) floor($number / 10)) . ' puluh ' . $this->spellNumber($number % 10);
        }
        if ($number < 200) {
            return 'seratus ' . $this->spellNumber($number - 100);
        }
        if ($number < 1000) {
            return $this->spellNumber((int) floor($number / 100)) . ' ratus ' . $this->spellNumber($number % 100);
        }
        if ($number < 2000) {
            return 'seribu ' . $this->spellNumber($number - 1000);
        }
        if ($number < 1000000) {
            return $this->spellNumber((int) floor($number / 1000)) . ' ribu ' . $this->spellNumber($number % 1000);
        }
        if ($number < 1000000000) {
            return $this->spellNumber((int) floor($number / 1000000)) . ' juta ' . $this->spellNumber($number % 1000000);
        }
        if ($number < 1000000000000) {
            return $this->spellNumber((int) floor($number / 1000000000)) . ' miliar ' . $this->spellNumber($number % 1000000000);
        }

        return $this->spellNumber((int) floor($number / 1000000000000)) . ' triliun ' . $this->spellNumber($number % 1000000000000);
    }

    private function sanitizeReturnPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        // Allow only local relative paths to avoid open redirects.
        if (str_starts_with($path, '/') && ! str_starts_with($path, '//')) {
            return $path;
        }

        return null;
    }
}
