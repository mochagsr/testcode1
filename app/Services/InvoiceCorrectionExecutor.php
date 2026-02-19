<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\DeliveryNotePageController;
use App\Http\Controllers\OrderNotePageController;
use App\Http\Controllers\OutgoingTransactionPageController;
use App\Http\Controllers\ReceivablePaymentPageController;
use App\Http\Controllers\SalesInvoicePageController;
use App\Http\Controllers\SalesReturnPageController;
use App\Http\Controllers\SupplierPayablePageController;
use App\Models\DeliveryNote;
use App\Models\OrderNote;
use App\Models\OutgoingTransaction;
use App\Models\ReceivablePayment;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\SupplierPayment;
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

    /**
     * @param array<string, mixed> $patch
     */
    public function applySalesReturnCorrection(int $salesReturnId, array $patch, ?Request $request = null): void
    {
        $salesReturn = SalesReturn::query()->findOrFail($salesReturnId);
        $baseRequest = $request ?? request();

        $payload = [
            'return_date' => (string) ($patch['return_date'] ?? optional($salesReturn->return_date)->format('Y-m-d')),
            'semester_period' => (string) ($patch['semester_period'] ?? ($salesReturn->semester_period ?? '')),
            'reason' => (string) ($patch['reason'] ?? ($salesReturn->reason ?? '')),
            'items' => $patch['items'] ?? [],
        ];

        $correctionRequest = Request::create('/sales-returns/'.$salesReturnId.'/admin-update', 'PUT', $payload);
        $this->hydrateRequestFromBase($correctionRequest, $baseRequest);

        app(SalesReturnPageController::class)->adminUpdate($correctionRequest, $salesReturn);
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function applyDeliveryNoteCorrection(int $deliveryNoteId, array $patch, ?Request $request = null): void
    {
        $note = DeliveryNote::query()->findOrFail($deliveryNoteId);
        $baseRequest = $request ?? request();

        $payload = [
            'note_date' => (string) ($patch['note_date'] ?? optional($note->note_date)->format('Y-m-d')),
            'recipient_name' => (string) ($patch['recipient_name'] ?? ($note->recipient_name ?? '')),
            'recipient_phone' => (string) ($patch['recipient_phone'] ?? ($note->recipient_phone ?? '')),
            'city' => (string) ($patch['city'] ?? ($note->city ?? '')),
            'address' => (string) ($patch['address'] ?? ($note->address ?? '')),
            'notes' => (string) ($patch['notes'] ?? ($note->notes ?? '')),
            'items' => $patch['items'] ?? [],
        ];

        $correctionRequest = Request::create('/delivery-notes/'.$deliveryNoteId.'/admin-update', 'PUT', $payload);
        $this->hydrateRequestFromBase($correctionRequest, $baseRequest);

        app(DeliveryNotePageController::class)->adminUpdate($correctionRequest, $note);
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function applyOrderNoteCorrection(int $orderNoteId, array $patch, ?Request $request = null): void
    {
        $note = OrderNote::query()->findOrFail($orderNoteId);
        $baseRequest = $request ?? request();

        $payload = [
            'note_date' => (string) ($patch['note_date'] ?? optional($note->note_date)->format('Y-m-d')),
            'customer_name' => (string) ($patch['customer_name'] ?? ($note->customer_name ?? '')),
            'customer_phone' => (string) ($patch['customer_phone'] ?? ($note->customer_phone ?? '')),
            'city' => (string) ($patch['city'] ?? ($note->city ?? '')),
            'notes' => (string) ($patch['notes'] ?? ($note->notes ?? '')),
            'items' => $patch['items'] ?? [],
        ];

        $correctionRequest = Request::create('/order-notes/'.$orderNoteId.'/admin-update', 'PUT', $payload);
        $this->hydrateRequestFromBase($correctionRequest, $baseRequest);

        app(OrderNotePageController::class)->adminUpdate($correctionRequest, $note);
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function applyReceivablePaymentCorrection(int $paymentId, array $patch, ?Request $request = null): void
    {
        $payment = ReceivablePayment::query()->findOrFail($paymentId);
        $baseRequest = $request ?? request();

        $payload = [
            'payment_date' => (string) ($patch['payment_date'] ?? optional($payment->payment_date)->format('Y-m-d')),
            'customer_address' => (string) ($patch['customer_address'] ?? ($payment->customer_address ?? '')),
            'customer_signature' => (string) ($patch['customer_signature'] ?? ($payment->customer_signature ?? '')),
            'user_signature' => (string) ($patch['user_signature'] ?? ($payment->user_signature ?? '')),
            'notes' => (string) ($patch['notes'] ?? ($payment->notes ?? '')),
        ];

        $correctionRequest = Request::create('/receivable-payments/'.$paymentId.'/admin-update', 'PUT', $payload);
        $this->hydrateRequestFromBase($correctionRequest, $baseRequest);

        app(ReceivablePaymentPageController::class)->adminUpdate($correctionRequest, $payment);
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function applyOutgoingTransactionCorrection(int $transactionId, array $patch, ?Request $request = null): void
    {
        $transaction = OutgoingTransaction::query()->findOrFail($transactionId);
        $baseRequest = $request ?? request();

        $payload = [
            'transaction_date' => (string) ($patch['transaction_date'] ?? optional($transaction->transaction_date)->format('Y-m-d')),
            'semester_period' => (string) ($patch['semester_period'] ?? ($transaction->semester_period ?? '')),
            'supplier_id' => (int) ($patch['supplier_id'] ?? (int) $transaction->supplier_id),
            'note_number' => (string) ($patch['note_number'] ?? ($transaction->note_number ?? '')),
            'notes' => (string) ($patch['notes'] ?? ($transaction->notes ?? '')),
            'items' => $patch['items'] ?? [],
        ];

        $correctionRequest = Request::create('/outgoing-transactions/'.$transactionId.'/admin-update', 'PUT', $payload);
        $this->hydrateRequestFromBase($correctionRequest, $baseRequest);

        app(OutgoingTransactionPageController::class)->adminUpdate($correctionRequest, $transaction);
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function applySupplierPaymentCorrection(int $paymentId, array $patch, ?Request $request = null): void
    {
        $payment = SupplierPayment::query()->findOrFail($paymentId);
        $baseRequest = $request ?? request();

        $payload = [
            'payment_date' => (string) ($patch['payment_date'] ?? optional($payment->payment_date)->format('Y-m-d')),
            'proof_number' => (string) ($patch['proof_number'] ?? ($payment->proof_number ?? '')),
            'amount' => (int) ($patch['amount'] ?? (int) $payment->amount),
            'supplier_signature' => (string) ($patch['supplier_signature'] ?? ($payment->supplier_signature ?? '')),
            'user_signature' => (string) ($patch['user_signature'] ?? ($payment->user_signature ?? '')),
            'notes' => (string) ($patch['notes'] ?? ($payment->notes ?? '')),
        ];

        $correctionRequest = Request::create('/supplier-payables/payment/'.$paymentId.'/admin-update', 'PUT', $payload);
        $this->hydrateRequestFromBase($correctionRequest, $baseRequest);

        app(SupplierPayablePageController::class)->adminUpdate($correctionRequest, $payment);
    }

    private function hydrateRequestFromBase(Request $targetRequest, Request $baseRequest): void
    {
        $targetRequest->setUserResolver($baseRequest->getUserResolver());
        $targetRequest->setRouteResolver($baseRequest->getRouteResolver());
        $targetRequest->headers->replace($baseRequest->headers->all());
        $targetRequest->server->set('REMOTE_ADDR', (string) $baseRequest->ip());
        $targetRequest->server->set('HTTP_USER_AGENT', (string) $baseRequest->userAgent());
    }
}
