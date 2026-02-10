<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ReceivableLedger;
use Carbon\CarbonInterface;

class ReceivableLedgerService
{
    public function addDebit(
        int $customerId,
        ?int $invoiceId,
        CarbonInterface $entryDate,
        float $amount,
        ?string $periodCode,
        ?string $description
    ): ReceivableLedger {
        $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
        $current = (float) $customer->outstanding_receivable;
        $next = $current + $amount;

        $customer->update(['outstanding_receivable' => $next]);

        return ReceivableLedger::create([
            'customer_id' => $customerId,
            'sales_invoice_id' => $invoiceId,
            'entry_date' => $entryDate->toDateString(),
            'period_code' => $periodCode,
            'description' => $description,
            'debit' => $amount,
            'credit' => 0,
            'balance_after' => $next,
        ]);
    }

    public function addCredit(
        int $customerId,
        ?int $invoiceId,
        CarbonInterface $entryDate,
        float $amount,
        ?string $periodCode,
        ?string $description
    ): ReceivableLedger {
        $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
        $current = (float) $customer->outstanding_receivable;
        $next = max(0, $current - $amount);

        $customer->update(['outstanding_receivable' => $next]);

        return ReceivableLedger::create([
            'customer_id' => $customerId,
            'sales_invoice_id' => $invoiceId,
            'entry_date' => $entryDate->toDateString(),
            'period_code' => $periodCode,
            'description' => $description,
            'debit' => 0,
            'credit' => $amount,
            'balance_after' => $next,
        ]);
    }
}
