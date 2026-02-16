<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\ReceivableLedger;
use Carbon\CarbonInterface;

final class ReceivableLedgerService
{
    /**
     * Add a debit entry to the receivable ledger and update customer outstanding balance.
     *
     * @param  int  $customerId The customer ID
     * @param  int|null  $invoiceId The invoice ID (if applicable)
     * @param  CarbonInterface  $entryDate The date of the ledger entry
     * @param  float  $amount The debit amount
     * @param  string|null  $periodCode The period code
     * @param  string|null  $description Additional description
     * @return ReceivableLedger The created ledger entry
     */
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

    /**
     * Add a credit entry to the receivable ledger and update customer outstanding balance.
     *
     * @param  int  $customerId The customer ID
     * @param  int|null  $invoiceId The invoice ID (if applicable)
     * @param  CarbonInterface  $entryDate The date of the ledger entry
     * @param  float  $amount The credit amount
     * @param  string|null  $periodCode The period code
     * @param  string|null  $description Additional description
     * @return ReceivableLedger The created ledger entry
     */
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
