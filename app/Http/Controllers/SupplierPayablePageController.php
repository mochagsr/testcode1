<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\SupplierPayment;
use App\Models\OutgoingTransaction;
use App\Services\AuditLogService;
use App\Services\AccountingService;
use App\Services\SupplierLedgerService;
use App\Support\AppCache;
use App\Support\AppSetting;
use App\Support\ExcelExportStyler;
use App\Support\PrintTextFormatter;
use App\Support\SemesterBookService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierPayablePageController extends Controller
{
    public function __construct(
        private readonly SupplierLedgerService $supplierLedgerService,
        private readonly AuditLogService $auditLogService,
        private readonly SemesterBookService $semesterBookService,
        private readonly AccountingService $accountingService
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
        $supplierPaymentAdminEditedMap = [];
        $outgoingTransactionAdminEditedMap = [];
        $totalDebit = 0;
        $totalCredit = 0;
        $mutationBalance = 0;
        $finalOutstanding = 0;
        if ($selectedSupplier !== null) {
            $ledgerRows = SupplierLedger::query()
                ->forSupplier((int) $selectedSupplier->id)
                ->when($semester !== null, fn($query) => $query->forSemester($semester))
                ->with(['outgoingTransaction:id,transaction_number', 'supplierPayment:id,payment_number'])
                ->orderByDate()
                ->limit(500)
                ->get();

            $totalDebit = (int) round((float) $ledgerRows->sum('debit'));
            $totalCredit = (int) round((float) $ledgerRows->sum('credit'));
            $mutationBalance = $totalDebit - $totalCredit;
            $finalOutstanding = (int) ($selectedSupplier->outstanding_payable ?? 0);

            $supplierPaymentIds = $ledgerRows
                ->pluck('supplier_payment_id')
                ->map(fn($id): int => (int) $id)
                ->filter(fn(int $id): bool => $id > 0)
                ->unique()
                ->values();
            if ($supplierPaymentIds->isNotEmpty()) {
                $paymentRows = AuditLog::query()
                    ->selectRaw("subject_id, MAX(CASE WHEN action = 'supplier.payment.admin_update' THEN 1 ELSE 0 END) as edited")
                    ->where('subject_type', SupplierPayment::class)
                    ->whereIn('subject_id', $supplierPaymentIds->all())
                    ->where('action', 'supplier.payment.admin_update')
                    ->groupBy('subject_id')
                    ->get();
                foreach ($paymentRows as $row) {
                    $paymentId = (int) ($row->subject_id ?? 0);
                    if ($paymentId <= 0) {
                        continue;
                    }
                    $supplierPaymentAdminEditedMap[$paymentId] = (int) ($row->edited ?? 0) === 1;
                }
            }

            $outgoingTransactionIds = $ledgerRows
                ->pluck('outgoing_transaction_id')
                ->map(fn($id): int => (int) $id)
                ->filter(fn(int $id): bool => $id > 0)
                ->unique()
                ->values();
            if ($outgoingTransactionIds->isNotEmpty()) {
                $outgoingRows = AuditLog::query()
                    ->selectRaw("subject_id, MAX(CASE WHEN action = 'outgoing.transaction.admin_update' THEN 1 ELSE 0 END) as edited")
                    ->where('subject_type', OutgoingTransaction::class)
                    ->whereIn('subject_id', $outgoingTransactionIds->all())
                    ->where('action', 'outgoing.transaction.admin_update')
                    ->groupBy('subject_id')
                    ->get();
                foreach ($outgoingRows as $row) {
                    $outgoingId = (int) ($row->subject_id ?? 0);
                    if ($outgoingId <= 0) {
                        continue;
                    }
                    $outgoingTransactionAdminEditedMap[$outgoingId] = (int) ($row->edited ?? 0) === 1;
                }
            }
        }

        return view('supplier_payables.index', [
            'suppliers' => $suppliers,
            'selectedSupplier' => $selectedSupplier,
            'selectedSupplierId' => $selectedSupplierId,
            'ledgerRows' => $ledgerRows,
            'selectedSemester' => $semester,
            'search' => $search,
            'semesterOptions' => $this->semesterBookService->configuredSemesterOptions(),
            'supplierPaymentAdminEditedMap' => $supplierPaymentAdminEditedMap,
            'outgoingTransactionAdminEditedMap' => $outgoingTransactionAdminEditedMap,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'mutationBalance' => $mutationBalance,
            'finalOutstanding' => $finalOutstanding,
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

            $this->accountingService->postSupplierPayment(
                paymentId: (int) $payment->id,
                date: $paymentDate,
                amount: (int) round($amount)
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

    public function adminUpdate(Request $request, SupplierPayment $supplierPayment): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'proof_number' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'integer', 'min:1'],
            'supplier_signature' => ['nullable', 'string', 'max:120'],
            'user_signature' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'payment_proof_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        DB::transaction(function () use ($supplierPayment, $data, $request): void {
            $payment = SupplierPayment::query()
                ->whereKey($supplierPayment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $supplier = Supplier::query()
                ->whereKey((int) $payment->supplier_id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldAmount = (int) $payment->amount;
            $newAmount = (int) $data['amount'];
            $maxPayable = max(0, (int) $supplier->outstanding_payable + $oldAmount);
            if ($newAmount > $maxPayable && $maxPayable > 0) {
                throw ValidationException::withMessages([
                    'amount' => __('supplier_payable.amount_exceeds_outstanding'),
                ]);
            }

            $paymentDate = Carbon::parse((string) $data['payment_date']);
            $semester = $this->semesterBookService->semesterFromDate((string) $data['payment_date']);
            $difference = $newAmount - $oldAmount;

            $proofPhotoPath = $payment->payment_proof_photo_path;
            if ($request->hasFile('payment_proof_photo')) {
                $proofPhotoPath = $request->file('payment_proof_photo')->store('supplier_payment_proofs', 'public');
            }

            $payment->update([
                'payment_date' => $paymentDate->toDateString(),
                'proof_number' => $data['proof_number'] ?? null,
                'payment_proof_photo_path' => $proofPhotoPath,
                'amount' => $newAmount,
                'amount_in_words' => $this->toIndonesianWords($newAmount),
                'supplier_signature' => $data['supplier_signature'] ?? null,
                'user_signature' => $data['user_signature'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($difference > 0) {
                $this->supplierLedgerService->addCredit(
                    supplierId: (int) $supplier->id,
                    supplierPaymentId: (int) $payment->id,
                    entryDate: $paymentDate,
                    amount: (float) $difference,
                    periodCode: $semester,
                    description: "[ADMIN EDIT +] {$payment->payment_number}"
                );
            } elseif ($difference < 0) {
                $this->supplierLedgerService->addDebit(
                    supplierId: (int) $supplier->id,
                    outgoingTransactionId: null,
                    entryDate: $paymentDate,
                    amount: (float) abs($difference),
                    periodCode: $semester,
                    description: "[ADMIN EDIT -] {$payment->payment_number}"
                );
            }
        });

        $supplierPayment->refresh();
        $this->auditLogService->log(
            'supplier.payment.admin_update',
            $supplierPayment,
            "Admin update supplier payment {$supplierPayment->payment_number}",
            $request
        );
        AppCache::forgetAfterFinancialMutation([(string) $supplierPayment->payment_date]);

        return redirect()
            ->route('supplier-payables.show-payment', $supplierPayment)
            ->with('success', __('txn.admin_update_saved'));
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

    public function exportPaymentExcel(SupplierPayment $supplierPayment): StreamedResponse
    {
        $supplierPayment->load('supplier:id,name,company_name,address,phone');
        $filename = $supplierPayment->payment_number . '.xlsx';

        return response()->streamDownload(function () use ($supplierPayment): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Kwitansi');

            $settings = AppSetting::getValues([
                'company_name' => 'CV. PUSTAKA GRAFIKA',
                'company_address' => '',
                'company_phone' => '',
                'company_email' => '',
                'company_notes' => '',
            ]);
            $companyAddress = PrintTextFormatter::wrapWords(trim((string) ($settings['company_address'] ?? '')), 5);
            $companyDetail = collect([
                $companyAddress,
                trim((string) ($settings['company_phone'] ?? '')),
                trim((string) ($settings['company_email'] ?? '')),
                trim((string) ($settings['company_notes'] ?? '')),
            ])->filter(fn (string $line): bool => $line !== '')->implode("\n");
            $supplierAddress = PrintTextFormatter::wrapWords(trim((string) ($supplierPayment->supplier?->address ?? '')), 5);
            $paymentNotes = PrintTextFormatter::wrapWords(trim((string) ($supplierPayment->notes ?? '')), 4);

            $sheet->mergeCells('A1:F1');
            $sheet->setCellValue('A1', __('supplier_payable.receipt_title'));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells('A2:B2');
            $sheet->setCellValue('A2', trim((string) ($settings['company_name'] ?? 'CV. PUSTAKA GRAFIKA')));
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);
            $sheet->mergeCells('A3:B5');
            $sheet->setCellValue('A3', $companyDetail !== '' ? $companyDetail : '-');
            $sheet->getStyle('A3:B5')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

            $sheet->mergeCells('C2:D2');
            $sheet->setCellValue('C2', __('txn.no') . ': ' . $supplierPayment->payment_number);
            $sheet->getStyle('C2')->getFont()->setBold(true);
            $sheet->getStyle('C2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $metaRows = [
                [__('txn.date'), $supplierPayment->payment_date?->format('d-m-Y') ?: '-'],
                [__('txn.supplier'), (string) ($supplierPayment->supplier?->name ?? '-')],
                [__('txn.phone'), (string) ($supplierPayment->supplier?->phone ?? '-')],
                [__('txn.address'), $supplierAddress !== '' ? $supplierAddress : '-'],
            ];
            $metaRowIndex = 2;
            foreach ($metaRows as [$label, $value]) {
                $sheet->setCellValue('E' . $metaRowIndex, $label);
                $sheet->setCellValue('F' . $metaRowIndex, $value);
                $metaRowIndex++;
            }
            $sheet->getStyle('E2:E5')->getFont()->setBold(true);
            $sheet->getStyle('F2:F5')->getAlignment()->setWrapText(true);

            $detailHeaderRow = 8;
            $detailRows = [
                [__('txn.supplier'), (string) ($supplierPayment->supplier?->name ?? '-')],
                [__('supplier_payable.proof_number'), (string) ($supplierPayment->proof_number ?: '-')],
                [__('txn.amount'), 'Rp ' . number_format((int) round((float) $supplierPayment->amount), 0, ',', '.')],
                [__('supplier_payable.amount_in_words'), (string) ($supplierPayment->amount_in_words ?: '-')],
                [__('txn.notes'), $paymentNotes !== '' ? $paymentNotes : '-'],
            ];
            $sheet->fromArray([['Keterangan', 'Nilai']], null, 'A' . $detailHeaderRow);
            $sheet->fromArray($detailRows, null, 'A' . ($detailHeaderRow + 1));
            ExcelExportStyler::styleTable($sheet, $detailHeaderRow, 2, count($detailRows), true);
            $sheet->getStyle('A' . $detailHeaderRow . ':B' . ($detailHeaderRow + count($detailRows)))
                ->getAlignment()
                ->setWrapText(true);

            $signatureRow = $detailHeaderRow + count($detailRows) + 3;
            $sheet->mergeCells('A' . $signatureRow . ':B' . $signatureRow);
            $sheet->mergeCells('E' . $signatureRow . ':F' . $signatureRow);
            $sheet->setCellValue('A' . $signatureRow, __('supplier_payable.supplier_signature'));
            $sheet->setCellValue('E' . $signatureRow, __('supplier_payable.user_signature'));
            $sheet->getStyle('A' . $signatureRow . ':F' . $signatureRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->mergeCells('A' . ($signatureRow + 2) . ':B' . ($signatureRow + 2));
            $sheet->mergeCells('E' . ($signatureRow + 2) . ':F' . ($signatureRow + 2));
            $sheet->setCellValue('A' . ($signatureRow + 2), (string) ($supplierPayment->supplier_signature ?: '-'));
            $sheet->setCellValue('E' . ($signatureRow + 2), (string) ($supplierPayment->user_signature ?: '-'));
            $sheet->getStyle('A' . ($signatureRow + 2) . ':F' . ($signatureRow + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A' . ($signatureRow + 1) . ':B' . ($signatureRow + 1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle('E' . ($signatureRow + 1) . ':F' . ($signatureRow + 1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            foreach (range('A', 'F') as $column) {
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

    private function generatePaymentNumber(string $date): string
    {
        $prefix = 'KWTS-' . date('dmY', strtotime($date));
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
