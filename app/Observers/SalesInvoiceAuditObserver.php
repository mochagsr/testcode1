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
            __('ui.audit_desc_invoice_created', [
                'number' => (string) ($invoice->invoice_number ?? '-'),
                'total' => number_format((int) round((float) ($invoice->total ?? 0)), 0, ',', '.'),
            ])
        );
    }

    public function updated(SalesInvoice $invoice): void
    {
        $this->logUpdated($invoice);
    }

    public function deleted(SalesInvoice $invoice): void
    {
        $this->logDeleted($invoice, __('ui.audit_desc_invoice_deleted', [
            'number' => (string) ($invoice->invoice_number ?? '-'),
        ]));
    }
}
