<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InvoicePayment;
use App\Models\ReceivablePayment;
use App\Models\SalesInvoice;
use App\Services\ReceivableLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivablePaymentPageController extends Controller
{
    public function __construct(
        private readonly ReceivableLedgerService $receivableLedgerService
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));

        $payments = ReceivablePayment::query()
            ->with('customer:id,name,city')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('payment_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('city', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('payment_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('receivable_payments.index', [
            'payments' => $payments,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('receivable_payments.create', [
            'customers' => Customer::query()
                ->orderBy('name')
                ->get(['id', 'name', 'city', 'address', 'outstanding_receivable']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'payment_date' => ['required', 'date'],
            'customer_address' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'customer_signature' => ['required', 'string', 'max:120'],
            'user_signature' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = DB::transaction(function () use ($data): ReceivablePayment {
            $customer = Customer::query()
                ->lockForUpdate()
                ->findOrFail((int) $data['customer_id']);

            $amount = (float) $data['amount'];
            $outstanding = (float) $customer->outstanding_receivable;
            if ($outstanding <= 0) {
                throw ValidationException::withMessages([
                    'amount' => __('receivable.customer_has_no_outstanding'),
                ]);
            }

            if ($amount > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => __('receivable.payment_exceeds_customer_outstanding'),
                ]);
            }

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
            $invoices = SalesInvoice::query()
                ->where('customer_id', $customer->id)
                ->where('balance', '>', 0)
                ->orderBy('invoice_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

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
                    'notes' => "Receivable payment {$payment->payment_number}",
                ]);

                $newTotalPaid = (float) $invoice->total_paid + $payAmount;
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
                    amount: $payAmount,
                    periodCode: $invoice->semester_period,
                    description: "Payment {$payment->payment_number} for {$invoice->invoice_number}"
                );

                $remaining -= $payAmount;
            }

            if ($remaining > 0) {
                $this->receivableLedgerService->addCredit(
                    customerId: (int) $customer->id,
                    invoiceId: null,
                    entryDate: $paymentDate,
                    amount: $remaining,
                    periodCode: null,
                    description: "Payment {$payment->payment_number}"
                );
            }

            return $payment;
        });

        return redirect()
            ->route('receivable-payments.show', $payment)
            ->with('success', __('receivable.receivable_payment_saved'));
    }

    public function show(ReceivablePayment $receivablePayment): View
    {
        $receivablePayment->load('customer:id,name,city,address,phone');

        return view('receivable_payments.show', [
            'payment' => $receivablePayment,
        ]);
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

        $filename = $receivablePayment->payment_number.'.pdf';
        $pdf = Pdf::loadView('receivable_payments.print', [
            'payment' => $receivablePayment,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    private function generatePaymentNumber(string $date): string
    {
        $prefix = 'PYT-'.date('Ymd', strtotime($date));
        $count = ReceivablePayment::query()
            ->whereDate('payment_date', $date)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    private function toIndonesianWords(float $amount): string
    {
        $integerPart = (int) floor($amount);
        $decimalPart = (int) round(($amount - $integerPart) * 100);
        $result = trim($this->spellNumber($integerPart)).' rupiah';

        if ($decimalPart > 0) {
            $digits = str_split((string) $decimalPart);
            $decimalWords = collect($digits)
                ->map(fn (string $digit): string => $this->digitWord((int) $digit))
                ->implode(' ');
            $result .= ' koma '.$decimalWords;
        }

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
            return $this->spellNumber($number - 10).' belas';
        }
        if ($number < 100) {
            return $this->spellNumber((int) floor($number / 10)).' puluh '.$this->spellNumber($number % 10);
        }
        if ($number < 200) {
            return 'seratus '.$this->spellNumber($number - 100);
        }
        if ($number < 1000) {
            return $this->spellNumber((int) floor($number / 100)).' ratus '.$this->spellNumber($number % 100);
        }
        if ($number < 2000) {
            return 'seribu '.$this->spellNumber($number - 1000);
        }
        if ($number < 1000000) {
            return $this->spellNumber((int) floor($number / 1000)).' ribu '.$this->spellNumber($number % 1000);
        }
        if ($number < 1000000000) {
            return $this->spellNumber((int) floor($number / 1000000)).' juta '.$this->spellNumber($number % 1000000);
        }
        if ($number < 1000000000000) {
            return $this->spellNumber((int) floor($number / 1000000000)).' miliar '.$this->spellNumber($number % 1000000000);
        }

        return $this->spellNumber((int) floor($number / 1000000000000)).' triliun '.$this->spellNumber($number % 1000000000000);
    }

    private function digitWord(int $digit): string
    {
        $words = [
            'nol',
            'satu',
            'dua',
            'tiga',
            'empat',
            'lima',
            'enam',
            'tujuh',
            'delapan',
            'sembilan',
        ];

        return $words[$digit] ?? 'nol';
    }
}
