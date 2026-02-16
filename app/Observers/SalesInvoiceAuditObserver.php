<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SalesInvoice;

/**
 * Observer for SalesInvoice model.
 */
final class SalesInvoiceAuditObserver extends BaseModelAuditObserver
{
    public function created(SalesInvoice $invoice): void
    {
        $this->logCreated(
            $invoice,
            "Invoice '{$invoice->invoice_number}' created with total {$invoice->total}"
        );
    }

    public function updated(SalesInvoice $invoice): void
    {
        $this->logUpdated($invoice);
    }

    public function deleted(SalesInvoice $invoice): void
    {
        $this->logDeleted($invoice, "Invoice '{$invoice->invoice_number}' deleted");
    }
}
