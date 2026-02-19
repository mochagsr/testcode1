<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierLedger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class SupplierLedgerService
{
    public function addDebit(
        int $supplierId,
        ?int $outgoingTransactionId,
        CarbonInterface $entryDate,
        float $amount,
        ?string $periodCode,
        ?string $description
    ): SupplierLedger {
        $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
        $current = (int) $supplier->outstanding_payable;
        $next = max(0, $current + (int) round($amount));

        $supplier->update(['outstanding_payable' => $next]);

        $ledger = SupplierLedger::create([
            'supplier_id' => $supplierId,
            'outgoing_transaction_id' => $outgoingTransactionId,
            'supplier_payment_id' => null,
            'entry_date' => $entryDate->toDateString(),
            'period_code' => $periodCode,
            'description' => $description,
            'debit' => (int) round($amount),
            'credit' => 0,
            'balance_after' => $next,
        ]);

        $this->syncOutstandingFromLedger($supplierId);

        return $ledger;
    }

    public function addCredit(
        int $supplierId,
        ?int $supplierPaymentId,
        CarbonInterface $entryDate,
        float $amount,
        ?string $periodCode,
        ?string $description
    ): SupplierLedger {
        $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplierId);
        $current = (int) $supplier->outstanding_payable;
        $next = max(0, $current - (int) round($amount));

        $supplier->update(['outstanding_payable' => $next]);

        $ledger = SupplierLedger::create([
            'supplier_id' => $supplierId,
            'outgoing_transaction_id' => null,
            'supplier_payment_id' => $supplierPaymentId,
            'entry_date' => $entryDate->toDateString(),
            'period_code' => $periodCode,
            'description' => $description,
            'debit' => 0,
            'credit' => (int) round($amount),
            'balance_after' => $next,
        ]);

        $this->syncOutstandingFromLedger($supplierId);

        return $ledger;
    }

    public function syncOutstandingFromLedger(int $supplierId): void
    {
        $supplier = Supplier::query()->lockForUpdate()->find($supplierId);
        if ($supplier === null) {
            return;
        }

        $ledgerBalance = (int) round((float) SupplierLedger::query()
            ->where('supplier_id', $supplierId)
            ->sum(DB::raw('debit - credit')));

        if ((int) $supplier->outstanding_payable !== $ledgerBalance) {
            $supplier->update(['outstanding_payable' => max(0, $ledgerBalance)]);
        }
    }
}
