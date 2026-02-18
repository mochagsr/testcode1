<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\SupplierPayment;
use App\Services\AuditLogService;
use App\Services\SupplierLedgerService;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPayablePageController extends Controller
{
    public function __construct(
        private readonly SupplierLedgerService $supplierLedgerService,
        private readonly AuditLogService $auditLogService,
        private readonly SemesterBookService $semesterBookService
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search', ''));
        $semester = $this->semesterBookService->normalizeSemester((string) $request->string('semester', ''));
        $supplierId = $request->integer('supplier_id');
        $selectedSupplierId = $supplierId > 0 ? $supplierId : null;

        $suppliers = Supplier::query()
            ->onlyListColumns()
            ->searchKeyword($search)
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $selectedSupplier = $selectedSupplierId
            ? Supplier::query()->onlyListColumns()->find($selectedSupplierId)
            : null;

        $ledgerRows = collect();
        if ($selectedSupplier !== null) {
            $ledgerRows = SupplierLedger::query()
                ->forSupplier((int) $selectedSupplier->id)
                ->when($semester !== null, fn($query) => $query->forSemester($semester))
                ->with(['outgoingTransaction:id,transaction_number', 'supplierPayment:id,payment_number'])
                ->orderByDate()
                ->limit(500)
                ->get();
        }

        return view('supplier_payables.index', [
            'suppliers' => $suppliers,
            'selectedSupplier' => $selectedSupplier,
            'selectedSupplierId' => $selectedSupplierId,
            'ledgerRows' => $ledgerRows,
            'selectedSemester' => $semester,
            'search' => $search,
            'semesterOptions' => $this->semesterBookService->configuredSemesterOptions(),
        ]);
    }

    public function create(Request $request): View
    {
        $supplierId = $request->integer('supplier_id');

        return view('supplier_payables.create', [
            'suppliers' => Supplier::query()->onlyLookupColumns()->orderBy('name')->limit(50)->get(),
            'prefillSupplierId' => $supplierId > 0 ? $supplierId : null,
            'prefillDate' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'payment_date' => ['required', 'date'],
            'proof_number' => ['nullable', 'string', 'max:80'],
            'payment_proof_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'amount' => ['required', 'integer', 'min:1'],
            'supplier_signature' => ['nullable', 'string', 'max:120'],
            'user_signature' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $semester = $this->semesterBookService->semesterFromDate((string) $data['payment_date']);
        $isAdmin = ((string) ($request->user()?->role ?? '') === 'admin');
        if (! $isAdmin && $this->semesterBookService->isSupplierClosed((int) $data['supplier_id'], $semester)) {
            return back()
                ->withErrors(['payment_date' => __('txn.supplier_semester_closed_error', ['semester' => $semester ?? '-'])])
                ->withInput()
                ->with('error_popup', __('ui.contact_admin_for_locked_customer_semester'));
        }

        $paymentProofPhotoPath = $request->hasFile('payment_proof_photo')
            ? $request->file('payment_proof_photo')->store('supplier_payment_proofs', 'public')
            : null;

        $payment = DB::transaction(function () use ($data, $semester, $request, $paymentProofPhotoPath): SupplierPayment {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail((int) $data['supplier_id']);
            $amount = (int) $data['amount'];
            $beforeOutstanding = (int) $supplier->outstanding_payable;
            if ($amount > $beforeOutstanding && $beforeOutstanding > 0) {
                throw ValidationException::withMessages([
                    'amount' => __('supplier_payable.amount_exceeds_outstanding'),
                ]);
            }

            $paymentDate = Carbon::parse((string) $data['payment_date']);
            $payment = SupplierPayment::create([
                'payment_number' => $this->generatePaymentNumber($paymentDate->toDateString()),
                'supplier_id' => (int) $supplier->id,
                'payment_date' => $paymentDate->toDateString(),
                'proof_number' => $data['proof_number'] ?? null,
                'payment_proof_photo_path' => $paymentProofPhotoPath,
                'amount' => $amount,
                'amount_in_words' => $this->toIndonesianWords($amount),
                'supplier_signature' => $data['supplier_signature'] ?? null,
                'user_signature' => $data['user_signature'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $request->user()?->id,
            ]);

            $ledger = $this->supplierLedgerService->addCredit(
                supplierId: (int) $supplier->id,
                supplierPaymentId: (int) $payment->id,
                entryDate: $paymentDate,
                amount: (float) $amount,
                periodCode: $semester,
                description: __('supplier_payable.payment_ledger_note', ['payment' => $payment->payment_number])
            );

            $afterOutstanding = (int) $ledger->balance_after;
            $this->auditLogService->log(
                'supplier.payment.create',
                $payment,
                __('supplier_payable.payment_saved'),
                $request,
                ['outstanding_payable' => $beforeOutstanding],
                ['outstanding_payable' => $afterOutstanding],
                ['supplier_id' => (int) $supplier->id, 'payment_number' => $payment->payment_number]
            );

            return $payment;
        });

        return redirect()
            ->route('supplier-payables.show-payment', $payment)
            ->with('success', __('supplier_payable.payment_saved'));
    }

    public function showPayment(SupplierPayment $supplierPayment): View
    {
        $supplierPayment->load('supplier:id,name,company_name,address,phone');

        return view('supplier_payables.show', [
            'payment' => $supplierPayment,
        ]);
    }

    public function printPayment(SupplierPayment $supplierPayment): View
    {
        $supplierPayment->load('supplier:id,name,company_name,address,phone');

        return view('supplier_payables.print', ['payment' => $supplierPayment]);
    }

    public function exportPaymentPdf(SupplierPayment $supplierPayment)
    {
        $supplierPayment->load('supplier:id,name,company_name,address,phone');

        $pdf = Pdf::loadView('supplier_payables.print', [
            'payment' => $supplierPayment,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($supplierPayment->payment_number . '.pdf');
    }

    private function generatePaymentNumber(string $date): string
    {
        $prefix = 'KWTS-' . date('Ymd', strtotime($date));
        $count = SupplierPayment::query()
            ->whereDate('payment_date', $date)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    private function toIndonesianWords(int $amount): string
    {
        return ucfirst(trim($this->spellNumber($amount))) . ' rupiah';
    }

    private function spellNumber(int $number): string
    {
        $number = abs($number);
        $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
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

        return $this->spellNumber((int) floor($number / 1000000000)) . ' miliar ' . $this->spellNumber($number % 1000000000);
    }
}
