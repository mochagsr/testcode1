<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\SalesInvoicePageController;
use App\Models\SalesInvoice;
use Illuminate\Http\Request;

final class InvoiceCorrectionExecutor
{
    /**
     * @param array<string, mixed> $patch
     */
    public function applySalesInvoiceCorrection(int $salesInvoiceId, array $patch, ?Request $request = null): void
    {
        $invoice = SalesInvoice::query()->findOrFail($salesInvoiceId);
        $baseRequest = $request ?? request();

        $payload = [
            'invoice_date' => (string) ($patch['invoice_date'] ?? optional($invoice->invoice_date)->format('Y-m-d')),
            'due_date' => $patch['due_date'] ?? optional($invoice->due_date)->format('Y-m-d'),
            'semester_period' => (string) ($patch['semester_period'] ?? ($invoice->semester_period ?? '')),
            'notes' => (string) ($patch['notes'] ?? ($invoice->notes ?? '')),
            'items' => $patch['items'] ?? [],
        ];

        $correctionRequest = Request::create('/sales-invoices/'.$salesInvoiceId.'/admin-update', 'PUT', $payload);
        $correctionRequest->setUserResolver($baseRequest->getUserResolver());
        $correctionRequest->setRouteResolver($baseRequest->getRouteResolver());
        $correctionRequest->headers->replace($baseRequest->headers->all());
        $correctionRequest->server->set('REMOTE_ADDR', (string) $baseRequest->ip());
        $correctionRequest->server->set('HTTP_USER_AGENT', (string) $baseRequest->userAgent());

        app(SalesInvoicePageController::class)->adminUpdate($correctionRequest, $invoice);
    }
}

