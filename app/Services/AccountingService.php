<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AccountingService
{
    /**
     * @param array<int, array{code:string,debit?:int|float,credit?:int|float,memo?:string|null}> $lines
     */
    public function postEntry(
        string $entryType,
        CarbonInterface $entryDate,
        ?string $referenceType,
        ?int $referenceId,
        ?string $description,
        array $lines
    ): JournalEntry {
        $normalizedLines = collect($lines)
            ->map(function (array $line): array {
                return [
                    'code' => trim((string) ($line['code'] ?? '')),
                    'debit' => (int) round((float) ($line['debit'] ?? 0)),
                    'credit' => (int) round((float) ($line['credit'] ?? 0)),
                    'memo' => isset($line['memo']) ? trim((string) $line['memo']) : null,
                ];
            })
            ->filter(fn (array $line): bool => $line['code'] !== '' && ($line['debit'] > 0 || $line['credit'] > 0))
            ->values();

        if ($normalizedLines->isEmpty()) {
            throw ValidationException::withMessages([
                'journal' => 'Jurnal gagal diposting: baris jurnal kosong.',
            ]);
        }

        $debitTotal = (int) $normalizedLines->sum('debit');
        $creditTotal = (int) $normalizedLines->sum('credit');
        if ($debitTotal !== $creditTotal) {
            throw ValidationException::withMessages([
                'journal' => 'Jurnal tidak seimbang (debit dan kredit harus sama).',
            ]);
        }

        $accountCodes = $normalizedLines->pluck('code')->unique()->values()->all();
        $accountMap = Account::query()
            ->whereIn('code', $accountCodes)
            ->get(['id', 'code'])
            ->keyBy('code');

        $missing = collect($accountCodes)
            ->reject(fn (string $code): bool => $accountMap->has($code))
            ->values();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'journal' => 'Akun tidak ditemukan: '.$missing->implode(', '),
            ]);
        }

        $entry = JournalEntry::create([
            'entry_number' => $this->generateEntryNumber($entryDate->toDateString()),
            'entry_date' => $entryDate->toDateString(),
            'entry_type' => $entryType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'created_by_user_id' => auth()->id(),
        ]);

        $entry->lines()->createMany(
            $normalizedLines
                ->map(function (array $line) use ($accountMap): array {
                    return [
                        'account_id' => (int) $accountMap->get($line['code'])->id,
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'memo' => $line['memo'],
                    ];
                })
                ->all()
        );

        return $entry;
    }

    public function postSalesInvoice(int $invoiceId, CarbonInterface $date, int $amount, string $paymentMethod): void
    {
        if ($amount <= 0) {
            return;
        }

        $debitCode = $paymentMethod === 'tunai' ? '1101' : '1102';
        $this->postEntry(
            entryType: 'sales_invoice_create',
            entryDate: $date,
            referenceType: \App\Models\SalesInvoice::class,
            referenceId: $invoiceId,
            description: "Posting invoice #{$invoiceId}",
            lines: [
                ['code' => $debitCode, 'debit' => $amount, 'memo' => 'Penjualan'],
                ['code' => '4101', 'credit' => $amount, 'memo' => 'Pendapatan penjualan'],
            ]
        );
    }

    public function postSalesReturn(int $returnId, CarbonInterface $date, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->postEntry(
            entryType: 'sales_return_create',
            entryDate: $date,
            referenceType: \App\Models\SalesReturn::class,
            referenceId: $returnId,
            description: "Posting retur #{$returnId}",
            lines: [
                ['code' => '5101', 'debit' => $amount, 'memo' => 'Retur penjualan'],
                ['code' => '1102', 'credit' => $amount, 'memo' => 'Pengurang piutang'],
            ]
        );
    }

    public function postReceivablePayment(int $paymentId, CarbonInterface $date, int $appliedAmount, int $overPayment = 0): void
    {
        $total = max(0, $appliedAmount + $overPayment);
        if ($total <= 0) {
            return;
        }

        $lines = [
            ['code' => '1101', 'debit' => $total, 'memo' => 'Kas diterima'],
            ['code' => '1102', 'credit' => max(0, $appliedAmount), 'memo' => 'Pelunasan piutang'],
        ];
        if ($overPayment > 0) {
            $lines[] = ['code' => '2101', 'credit' => $overPayment, 'memo' => 'Uang muka customer'];
        }

        $this->postEntry(
            entryType: 'receivable_payment_create',
            entryDate: $date,
            referenceType: \App\Models\ReceivablePayment::class,
            referenceId: $paymentId,
            description: "Posting pembayaran piutang #{$paymentId}",
            lines: $lines
        );
    }

    public function postOutgoingTransaction(int $transactionId, CarbonInterface $date, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->postEntry(
            entryType: 'outgoing_transaction_create',
            entryDate: $date,
            referenceType: \App\Models\OutgoingTransaction::class,
            referenceId: $transactionId,
            description: "Posting transaksi keluar #{$transactionId}",
            lines: [
                ['code' => '1201', 'debit' => $amount, 'memo' => 'Persediaan masuk'],
                ['code' => '2101', 'credit' => $amount, 'memo' => 'Hutang supplier'],
            ]
        );
    }

    public function postSupplierPayment(int $paymentId, CarbonInterface $date, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->postEntry(
            entryType: 'supplier_payment_create',
            entryDate: $date,
            referenceType: \App\Models\SupplierPayment::class,
            referenceId: $paymentId,
            description: "Posting pembayaran supplier #{$paymentId}",
            lines: [
                ['code' => '2101', 'debit' => $amount, 'memo' => 'Pelunasan hutang supplier'],
                ['code' => '1101', 'credit' => $amount, 'memo' => 'Kas keluar'],
            ]
        );
    }

    public function postDeliveryTripExpense(int $tripId, CarbonInterface $date, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->postEntry(
            entryType: 'delivery_trip_create',
            entryDate: $date,
            referenceType: \App\Models\DeliveryTrip::class,
            referenceId: $tripId,
            description: "Posting biaya perjalanan #{$tripId}",
            lines: [
                ['code' => '5102', 'debit' => $amount, 'memo' => 'Biaya operasional pengiriman'],
                ['code' => '1101', 'credit' => $amount, 'memo' => 'Kas keluar'],
            ]
        );
    }

    public function postDeliveryTripAdjustment(int $tripId, CarbonInterface $date, int $difference): void
    {
        if ($difference === 0) {
            return;
        }

        if ($difference > 0) {
            $this->postEntry(
                entryType: 'delivery_trip_adjustment',
                entryDate: $date,
                referenceType: \App\Models\DeliveryTrip::class,
                referenceId: $tripId,
                description: "Penyesuaian naik biaya perjalanan #{$tripId}",
                lines: [
                    ['code' => '5102', 'debit' => $difference, 'memo' => 'Koreksi biaya pengiriman (+)'],
                    ['code' => '1101', 'credit' => $difference, 'memo' => 'Kas keluar koreksi'],
                ]
            );

            return;
        }

        $abs = abs($difference);
        $this->postEntry(
            entryType: 'delivery_trip_adjustment',
            entryDate: $date,
            referenceType: \App\Models\DeliveryTrip::class,
            referenceId: $tripId,
            description: "Penyesuaian turun biaya perjalanan #{$tripId}",
            lines: [
                ['code' => '1101', 'debit' => $abs, 'memo' => 'Kas kembali koreksi'],
                ['code' => '5102', 'credit' => $abs, 'memo' => 'Koreksi biaya pengiriman (-)'],
            ]
        );
    }

    private function generateEntryNumber(string $date): string
    {
        $prefix = 'JR-'.date('Ymd', strtotime($date)).'-';
        $last = JournalEntry::query()
            ->where('entry_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->max('entry_number');
        $next = 1;
        if (is_string($last) && $last !== '') {
            $next = ((int) substr($last, -4)) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
