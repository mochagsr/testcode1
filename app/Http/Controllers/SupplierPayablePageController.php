<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\OutgoingTransaction;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\SupplierPayment;
use App\Services\AccountingService;
use App\Services\AuditLogService;
use App\Services\SupplierLedgerService;
use App\Support\AppCache;
use App\Support\AppSetting;
use App\Support\ExcelExportStyler;
use App\Support\PrintTextFormatter;
use App\Support\SemesterBookService;
use App\Support\UploadedImageCompressor;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SanderMuller\FluentValidation\FluentRule;
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
        $pageData = $this->buildIndexData($request);

        return view('supplier_payables.index', [
            'suppliers' => $pageData['suppliers'],
            'selectedSupplier' => $pageData['selectedSupplier'],
            'selectedSupplierId' => $pageData['selectedSupplierId'],
            'ledgerRows' => $pageData['ledgerRows'],
            'selectedYear' => $pageData['selectedYear'],
            'selectedMonth' => $pageData['selectedMonth'],
            'search' => $pageData['search'],
            'yearOptions' => $this->supplierYearOptions(),
            'monthOptions' => $this->monthOptions(),
            'supplierPaymentAdminEditedMap' => $pageData['supplierPaymentAdminEditedMap'],
            'outgoingTransactionAdminEditedMap' => $pageData['outgoingTransactionAdminEditedMap'],
            'totalDebit' => $pageData['totalDebit'],
            'totalCredit' => $pageData['totalCredit'],
            'mutationBalance' => $pageData['mutationBalance'],
            'finalOutstanding' => $pageData['finalOutstanding'],
            'selectedSupplierMonthClosed' => $pageData['selectedSupplierMonthClosed'],
        ]);
    }

    public function create(Request $request): View
    {
        $supplierId = $request->integer('supplier_id');
        $suppliers = Supplier::query()->onlyLookupColumns()->orderBy('name')->limit(50)->get();
        if ($supplierId > 0 && ! $suppliers->contains('id', $supplierId)) {
            $prefillSupplier = Supplier::query()->onlyLookupColumns()->whereKey($supplierId)->first();
            if ($prefillSupplier !== null) {
                $suppliers->prepend($prefillSupplier);
            }
        }

        return view('supplier_payables.create', [
            'suppliers' => $suppliers->unique('id')->values(),
            'prefillSupplierId' => $supplierId > 0 ? $supplierId : null,
            'prefillDate' => now()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => FluentRule::integer()->required()->exists('suppliers', 'id'),
            'payment_date' => FluentRule::date()->required(),
            'proof_number' => FluentRule::string()->nullable()->max(80),
            'payment_proof_photo' => FluentRule::image()->nullable()->rule('mimes:jpg,jpeg,png,webp')->max(4096),
            'amount' => FluentRule::integer()->required()->min(1),
            'supplier_signature' => FluentRule::string()->nullable()->max(120),
            'user_signature' => FluentRule::string()->nullable()->max(120),
            'notes' => FluentRule::string()->nullable(),
        ]);

        $supplierYear = $this->semesterBookService->yearFromDate((string) $data['payment_date']);
        $supplierMonth = (int) Carbon::parse((string) $data['payment_date'])->format('n');
        $isAdmin = ((string) ($request->user()?->role ?? '') === 'admin');
        if (! $isAdmin && $this->semesterBookService->isSupplierMonthClosed((int) $data['supplier_id'], $supplierYear, $supplierMonth)) {
            return back()
                ->withErrors(['payment_date' => __('txn.supplier_semester_closed_error', ['semester' => sprintf('%s-%02d', $supplierYear ?? '-', $supplierMonth)])])
                ->withInput()
                ->with('error_popup', __('ui.contact_admin_for_locked_customer_semester'));
        }

        $paymentProofPhotoPath = $request->hasFile('payment_proof_photo')
            ? UploadedImageCompressor::storeJpeg($request->file('payment_proof_photo'), 'supplier_payment_proofs')
            : null;

        $payment = DB::transaction(function () use ($data, $request, $paymentProofPhotoPath): SupplierPayment {
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
                periodCode: $this->semesterBookService->semesterFromDate((string) $data['payment_date']),
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

    public function closeYear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => FluentRule::integer()->required()->exists('suppliers', 'id'),
            'year' => FluentRule::field()->required()->rule('digits:4'),
            'month' => FluentRule::integer()->required()->min(1)->max(12),
            'search' => FluentRule::string()->nullable(),
        ]);

        $normalizedYear = $this->semesterBookService->normalizeYear((string) $data['year']);
        if ($normalizedYear === null) {
            return back()->with('error', __('supplier_payable.invalid_year'));
        }

        $month = (int) $data['month'];
        $this->semesterBookService->closeSupplierMonth((int) $data['supplier_id'], $normalizedYear, $month);

        return redirect()
            ->route('supplier-payables.index', [
                'supplier_id' => (int) $data['supplier_id'],
                'year' => $normalizedYear,
                'month' => $month,
                'search' => trim((string) ($data['search'] ?? '')),
            ])
            ->with('success', __('supplier_payable.month_closed_success', [
                'year' => $normalizedYear,
                'month' => $this->monthOptions()[$month] ?? sprintf('%02d', $month),
            ]));
    }

    public function openYear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => FluentRule::integer()->required()->exists('suppliers', 'id'),
            'year' => FluentRule::field()->required()->rule('digits:4'),
            'month' => FluentRule::integer()->required()->min(1)->max(12),
            'search' => FluentRule::string()->nullable(),
        ]);

        $normalizedYear = $this->semesterBookService->normalizeYear((string) $data['year']);
        if ($normalizedYear === null) {
            return back()->with('error', __('supplier_payable.invalid_year'));
        }

        $month = (int) $data['month'];
        $this->semesterBookService->openSupplierMonth((int) $data['supplier_id'], $normalizedYear, $month);

        return redirect()
            ->route('supplier-payables.index', [
                'supplier_id' => (int) $data['supplier_id'],
                'year' => $normalizedYear,
                'month' => $month,
                'search' => trim((string) ($data['search'] ?? '')),
            ])
            ->with('success', __('supplier_payable.month_opened_success', [
                'year' => $normalizedYear,
                'month' => $this->monthOptions()[$month] ?? sprintf('%02d', $month),
            ]));
    }

    public function printReport(Request $request): View
    {
        return view('supplier_payables.report', $this->buildReportData($request) + ['isPdf' => false]);
    }

    public function exportReportPdf(Request $request)
    {
        $data = $this->buildReportData($request);
        $pdf = Pdf::loadView('supplier_payables.report', $data + ['isPdf' => true])->setPaper(\App\Support\PrintPaperSize::continuousForm95x11());

        return $pdf->download($this->buildReportFileName($data).'.pdf');
    }

    public function exportReportExcel(Request $request): StreamedResponse
    {
        $data = $this->buildReportData($request);
        $filename = $this->buildReportFileName($data).'.xlsx';

        return response()->streamDownload(function () use ($data): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Hutang Supplier');

            $sheet->mergeCells('A1:F1');
            $sheet->setCellValue('A1', __('supplier_payable.report_title'));
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $metaRows = [
                [__('txn.supplier'), $data['selectedSupplier']?->name ?: __('supplier_payable.all_suppliers')],
                [__('supplier_payable.year_label'), $data['selectedYear'] ?: __('supplier_payable.all_years')],
                [__('supplier_payable.month_label'), $data['selectedMonthLabel'] ?: __('supplier_payable.all_months')],
                [__('txn.date'), now()->format('d-m-Y H:i:s')],
            ];
            $metaStartRow = 3;
            foreach ($metaRows as $offset => [$label, $value]) {
                $row = $metaStartRow + $offset;
                $sheet->setCellValue('A'.$row, $label);
                $sheet->setCellValue('B'.$row, $value);
            }
            $sheet->getStyle('A'.$metaStartRow.':A'.($metaStartRow + count($metaRows) - 1))->getFont()->setBold(true);

            $summaryHeaderRow = 8;
            $sheet->fromArray([[
                __('txn.supplier'),
                __('supplier_payable.outstanding'),
                __('receivable.total_debit'),
                __('receivable.total_credit'),
                __('supplier_payable.final_outstanding'),
            ]], null, 'A'.$summaryHeaderRow);

            $summaryRows = $data['summarySuppliers']->map(fn (Supplier $supplier): array => [
                (string) $supplier->name,
                'Rp '.number_format((int) ($supplier->outstanding_payable ?? 0), 0, ',', '.'),
                'Rp '.number_format((int) ($data['summaryDebitMap'][(int) $supplier->id] ?? 0), 0, ',', '.'),
                'Rp '.number_format((int) ($data['summaryCreditMap'][(int) $supplier->id] ?? 0), 0, ',', '.'),
                'Rp '.number_format((int) ($data['summaryBalanceMap'][(int) $supplier->id] ?? 0), 0, ',', '.'),
            ])->all();

            if ($summaryRows !== []) {
                $sheet->fromArray($summaryRows, null, 'A'.($summaryHeaderRow + 1));
            }
            ExcelExportStyler::styleTable($sheet, $summaryHeaderRow, 5, count($summaryRows), true);

            if ($data['selectedSupplier'] !== null) {
                $ledgerHeaderRow = $summaryHeaderRow + max(3, count($summaryRows) + 3);
                $sheet->mergeCells('A'.$ledgerHeaderRow.':F'.$ledgerHeaderRow);
                $sheet->setCellValue('A'.$ledgerHeaderRow, __('supplier_payable.mutation'));
                $sheet->getStyle('A'.$ledgerHeaderRow)->getFont()->setBold(true);

                $ledgerTableHeaderRow = $ledgerHeaderRow + 1;
                $sheet->fromArray([[
                    __('txn.date'),
                    __('receivable.description'),
                    __('receivable.debit'),
                    __('receivable.credit'),
                    __('receivable.balance'),
                    __('txn.type'),
                ]], null, 'A'.$ledgerTableHeaderRow);

                $ledgerRows = $data['ledgerRows']->map(function (SupplierLedger $row): array {
                    $type = $row->supplier_payment_id ? __('supplier_payable.pay') : __('txn.outgoing_transactions_title');

                    return [
                        $row->entry_date?->format('d-m-Y') ?: '-',
                        (string) ($row->description ?: '-'),
                        'Rp '.number_format((int) $row->debit, 0, ',', '.'),
                        'Rp '.number_format((int) $row->credit, 0, ',', '.'),
                        'Rp '.number_format((int) $row->balance_after, 0, ',', '.'),
                        $type,
                    ];
                })->all();

                if ($ledgerRows !== []) {
                    $sheet->fromArray($ledgerRows, null, 'A'.($ledgerTableHeaderRow + 1));
                }
                ExcelExportStyler::styleTable($sheet, $ledgerTableHeaderRow, 6, count($ledgerRows), true);
            }

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
            'payment_date' => FluentRule::date()->required(),
            'proof_number' => FluentRule::string()->nullable()->max(80),
            'amount' => FluentRule::integer()->required()->min(1),
            'supplier_signature' => FluentRule::string()->nullable()->max(120),
            'user_signature' => FluentRule::string()->nullable()->max(120),
            'notes' => FluentRule::string()->nullable(),
            'payment_proof_photo' => FluentRule::image()->nullable()->rule('mimes:jpg,jpeg,png,webp')->max(4096),
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
                if ($proofPhotoPath) {
                    Storage::disk('public')->delete($proofPhotoPath);
                }
                $proofPhotoPath = UploadedImageCompressor::storeJpeg($request->file('payment_proof_photo'), 'supplier_payment_proofs');
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
        ])->setPaper(\App\Support\PrintPaperSize::continuousForm95x11());

        return $pdf->download($supplierPayment->payment_number.'.pdf');
    }

    public function exportPaymentExcel(SupplierPayment $supplierPayment): StreamedResponse
    {
        $supplierPayment->load('supplier:id,name,company_name,address,phone');
        $filename = $supplierPayment->payment_number.'.xlsx';

        return response()->streamDownload(function () use ($supplierPayment): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Kwitansi');

            $settings = AppSetting::getValues([
                'company_name' => '',
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
            $sheet->setCellValue('A2', trim((string) ($settings['company_name'] ?? '')));
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);
            $sheet->mergeCells('A3:B5');
            $sheet->setCellValue('A3', $companyDetail !== '' ? $companyDetail : '-');
            $sheet->getStyle('A3:B5')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

            $sheet->mergeCells('C2:D2');
            $sheet->setCellValue('C2', __('txn.no').': '.$supplierPayment->payment_number);
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
                $sheet->setCellValue('E'.$metaRowIndex, $label);
                $sheet->setCellValue('F'.$metaRowIndex, $value);
                $metaRowIndex++;
            }
            $sheet->getStyle('E2:E5')->getFont()->setBold(true);
            $sheet->getStyle('F2:F5')->getAlignment()->setWrapText(true);

            $detailHeaderRow = 8;
            $detailRows = [
                [__('txn.supplier'), (string) ($supplierPayment->supplier?->name ?? '-')],
                [__('supplier_payable.proof_number'), (string) ($supplierPayment->proof_number ?: '-')],
                [__('txn.amount'), 'Rp '.number_format((int) round((float) $supplierPayment->amount), 0, ',', '.')],
                [__('supplier_payable.amount_in_words'), (string) ($supplierPayment->amount_in_words ?: '-')],
                [__('txn.notes'), $paymentNotes !== '' ? $paymentNotes : '-'],
            ];
            $sheet->fromArray([['Keterangan', 'Nilai']], null, 'A'.$detailHeaderRow);
            $sheet->fromArray($detailRows, null, 'A'.($detailHeaderRow + 1));
            ExcelExportStyler::styleTable($sheet, $detailHeaderRow, 2, count($detailRows), true);
            $sheet->getStyle('A'.$detailHeaderRow.':B'.($detailHeaderRow + count($detailRows)))
                ->getAlignment()
                ->setWrapText(true);

            $signatureRow = $detailHeaderRow + count($detailRows) + 3;
            $sheet->mergeCells('A'.$signatureRow.':B'.$signatureRow);
            $sheet->mergeCells('E'.$signatureRow.':F'.$signatureRow);
            $sheet->setCellValue('A'.$signatureRow, __('supplier_payable.supplier_signature'));
            $sheet->setCellValue('E'.$signatureRow, __('supplier_payable.user_signature'));
            $sheet->getStyle('A'.$signatureRow.':F'.$signatureRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->mergeCells('A'.($signatureRow + 2).':B'.($signatureRow + 2));
            $sheet->mergeCells('E'.($signatureRow + 2).':F'.($signatureRow + 2));
            $sheet->setCellValue('A'.($signatureRow + 2), (string) ($supplierPayment->supplier_signature ?: '-'));
            $sheet->setCellValue('E'.($signatureRow + 2), (string) ($supplierPayment->user_signature ?: '-'));
            $sheet->getStyle('A'.($signatureRow + 2).':F'.($signatureRow + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A'.($signatureRow + 1).':B'.($signatureRow + 1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle('E'.($signatureRow + 1).':F'.($signatureRow + 1))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

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
        $prefix = 'KWTS-'.date('dmY', strtotime($date));
        $count = SupplierPayment::query()
            ->whereDate('payment_date', $date)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    private function toIndonesianWords(int $amount): string
    {
        return ucfirst(trim($this->spellNumber($amount))).' rupiah';
    }

    private function spellNumber(int $number): string
    {
        $number = abs($number);
        $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
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

        return $this->spellNumber((int) floor($number / 1000000000)).' miliar '.$this->spellNumber($number % 1000000000);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function supplierYearOptions(): Collection
    {
        return SupplierLedger::query()
            ->whereNotNull('entry_date')
            ->pluck('entry_date')
            ->map(fn ($date): ?string => $this->semesterBookService->yearFromDate((string) $date))
            ->filter()
            ->push((string) now()->format('Y'))
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @return array{
     *   suppliers:\Illuminate\Contracts\Pagination\LengthAwarePaginator,
     *   selectedSupplier:?Supplier,
     *   selectedSupplierId:?int,
     *   ledgerRows:Collection<int, SupplierLedger>,
     *   selectedYear:?string,
     *   selectedMonth:?int,
     *   search:string,
     *   supplierPaymentAdminEditedMap:array<int,bool>,
     *   outgoingTransactionAdminEditedMap:array<int,bool>,
     *   totalDebit:int,
     *   totalCredit:int,
     *   mutationBalance:int,
     *   finalOutstanding:int,
     *   selectedSupplierMonthClosed:bool
     * }
     */
    private function buildIndexData(Request $request): array
    {
        $search = trim((string) $request->string('search', ''));
        $selectedYear = $this->semesterBookService->normalizeYear((string) $request->string('year', ''));
        $selectedMonth = $this->normalizeMonth((string) $request->string('month', ''));
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
        $selectedSupplierMonthClosed = false;
        if ($selectedSupplier !== null) {
            $ledgerRows = SupplierLedger::query()
                ->forSupplier((int) $selectedSupplier->id)
                ->when($selectedYear !== null, fn ($query) => $query->whereYear('entry_date', (int) $selectedYear))
                ->when($selectedMonth !== null, fn ($query) => $query->whereMonth('entry_date', $selectedMonth))
                ->with(['outgoingTransaction:id,transaction_number', 'supplierPayment:id,payment_number'])
                ->orderByDate()
                ->limit(500)
                ->get();

            $totalDebit = (int) round((float) $ledgerRows->sum('debit'));
            $totalCredit = (int) round((float) $ledgerRows->sum('credit'));
            $mutationBalance = $totalDebit - $totalCredit;
            $finalOutstanding = $selectedYear !== null ? $mutationBalance : (int) ($selectedSupplier->outstanding_payable ?? 0);
            $selectedSupplierMonthClosed = $selectedYear !== null
                && $selectedMonth !== null
                && $this->semesterBookService->isSupplierMonthClosed((int) $selectedSupplier->id, $selectedYear, $selectedMonth);

            $supplierPaymentIds = $ledgerRows
                ->pluck('supplier_payment_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
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
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
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

        return compact(
            'suppliers',
            'selectedSupplier',
            'selectedSupplierId',
            'ledgerRows',
            'selectedYear',
            'selectedMonth',
            'search',
            'supplierPaymentAdminEditedMap',
            'outgoingTransactionAdminEditedMap',
            'totalDebit',
            'totalCredit',
            'mutationBalance',
            'finalOutstanding',
            'selectedSupplierMonthClosed',
        );
    }

    /**
     * @return array{
     *   search:string,
     *   selectedYear:?string,
     *   selectedMonth:?int,
     *   selectedMonthLabel:?string,
     *   selectedSupplierId:?int,
     *   selectedSupplier:?Supplier,
     *   summarySuppliers:Collection<int,Supplier>,
     *   summaryDebitMap:array<int,int>,
     *   summaryCreditMap:array<int,int>,
     *   summaryBalanceMap:array<int,int>,
     *   totalOutstanding:int,
     *   ledgerRows:Collection<int,SupplierLedger>
     * }
     */
    private function buildReportData(Request $request): array
    {
        $search = trim((string) $request->string('search', ''));
        $selectedYear = $this->semesterBookService->normalizeYear((string) $request->string('year', ''));
        $selectedMonth = $this->normalizeMonth((string) $request->string('month', ''));
        $selectedSupplierId = $request->integer('supplier_id') ?: null;

        $summarySuppliers = Supplier::query()
            ->onlyListColumns()
            ->searchKeyword($search)
            ->when($selectedSupplierId, fn ($query) => $query->whereKey($selectedSupplierId))
            ->orderBy('name')
            ->get();

        $selectedSupplier = $selectedSupplierId
            ? $summarySuppliers->firstWhere('id', $selectedSupplierId)
            : null;

        $supplierIds = $summarySuppliers->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $ledgerAggregateRows = collect();
        if ($supplierIds !== []) {
            $ledgerAggregateRows = SupplierLedger::query()
                ->whereIn('supplier_id', $supplierIds)
                ->when($selectedYear !== null, fn ($query) => $query->whereYear('entry_date', (int) $selectedYear))
                ->when($selectedMonth !== null, fn ($query) => $query->whereMonth('entry_date', $selectedMonth))
                ->selectRaw('supplier_id, COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
                ->groupBy('supplier_id')
                ->get();
        }

        $summaryDebitMap = [];
        $summaryCreditMap = [];
        $summaryBalanceMap = [];
        foreach ($ledgerAggregateRows as $row) {
            $supplierId = (int) $row->supplier_id;
            $debit = (int) round((float) $row->total_debit);
            $credit = (int) round((float) $row->total_credit);
            $summaryDebitMap[$supplierId] = $debit;
            $summaryCreditMap[$supplierId] = $credit;
            $summaryBalanceMap[$supplierId] = $debit - $credit;
        }

        $totalOutstanding = $selectedYear !== null
            ? (int) array_sum($summaryBalanceMap)
            : (int) $summarySuppliers->sum(fn (Supplier $supplier): int => (int) ($supplier->outstanding_payable ?? 0));

        $ledgerRows = collect();
        if ($selectedSupplier !== null) {
            $ledgerRows = SupplierLedger::query()
                ->forSupplier((int) $selectedSupplier->id)
                ->when($selectedYear !== null, fn ($query) => $query->whereYear('entry_date', (int) $selectedYear))
                ->when($selectedMonth !== null, fn ($query) => $query->whereMonth('entry_date', $selectedMonth))
                ->with(['outgoingTransaction:id,transaction_number', 'supplierPayment:id,payment_number'])
                ->orderByDate()
                ->limit(500)
                ->get();
        }

        $selectedMonthLabel = $selectedMonth !== null ? ($this->monthOptions()[$selectedMonth] ?? null) : null;

        return compact(
            'search',
            'selectedYear',
            'selectedMonth',
            'selectedMonthLabel',
            'selectedSupplierId',
            'selectedSupplier',
            'summarySuppliers',
            'summaryDebitMap',
            'summaryCreditMap',
            'summaryBalanceMap',
            'totalOutstanding',
            'ledgerRows',
        );
    }

    /**
     * @param  array{selectedSupplier:?Supplier,selectedYear:?string}  $data
     */
    private function buildReportFileName(array $data): string
    {
        $supplierPart = $data['selectedSupplier']?->name
            ? preg_replace('/[^A-Za-z0-9\-]+/', '-', strtolower((string) $data['selectedSupplier']->name))
            : 'semua-supplier';
        $yearPart = $data['selectedYear'] ?: 'semua-tahun';
        $monthPart = isset($data['selectedMonth']) && $data['selectedMonth'] !== null
            ? sprintf('bulan-%02d', (int) $data['selectedMonth'])
            : 'semua-bulan';

        return 'hutang-supplier-'.$supplierPart.'-'.$yearPart.'-'.$monthPart;
    }

    /**
     * @return array<int, string>
     */
    private function monthOptions(): array
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
    }

    private function normalizeMonth(string $value): ?int
    {
        $month = (int) trim($value);

        return $month >= 1 && $month <= 12 ? $month : null;
    }
}
