<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierLedger;
use Carbon\CarbonInterface;

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

        return SupplierLedger::create([
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

        return SupplierLedger::create([
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
    }
}
