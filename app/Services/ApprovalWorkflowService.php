<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\DeliveryNote;
use App\Models\OrderNote;
use App\Models\ReceivablePayment;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

final class ApprovalWorkflowService
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function create(
        string $module,
        string $action,
        ?Model $subject,
        ?array $payload,
        ?string $reason,
        ?Request $request = null
    ): ApprovalRequest {
        $approval = ApprovalRequest::create([
            'module' => $module,
            'action' => $action,
            'status' => 'pending',
            'subject_id' => $subject?->getKey(),
            'subject_type' => $subject?->getMorphClass() ?? ($subject ? $subject::class : null),
            'payload' => $payload,
            'reason' => $reason,
            'requested_by_user_id' => (int) auth()->id(),
        ]);

        app(AuditLogService::class)->log(
            'approval.request.create',
            $approval,
            "Approval requested: {$module}.{$action} #{$approval->id}",
            $request
        );

        return $approval;
    }

    public function approve(ApprovalRequest $approvalRequest, ?string $note, ?Request $request = null): ApprovalRequest
    {
        $approvalRequest->update([
            'status' => 'approved',
            'approved_by_user_id' => (int) auth()->id(),
            'approved_at' => now(),
            'approval_note' => $note,
        ]);

        app(AuditLogService::class)->log(
            'approval.request.approve',
            $approvalRequest,
            "Approval approved #{$approvalRequest->id}",
            $request
        );

        $this->executeApprovedRequest($approvalRequest, $request);

        return $approvalRequest->refresh();
    }

    public function reject(ApprovalRequest $approvalRequest, ?string $note, ?Request $request = null): ApprovalRequest
    {
        $approvalRequest->update([
            'status' => 'rejected',
            'approved_by_user_id' => (int) auth()->id(),
            'rejected_at' => now(),
            'approval_note' => $note,
        ]);

        app(AuditLogService::class)->log(
            'approval.request.reject',
            $approvalRequest,
            "Approval rejected #{$approvalRequest->id}",
            $request
        );

        return $approvalRequest->refresh();
    }

    private function executeApprovedRequest(ApprovalRequest $approvalRequest, ?Request $request = null): void
    {
        $payload = is_array($approvalRequest->payload) ? $approvalRequest->payload : [];
        $isTransactionCorrection = (string) $approvalRequest->module === 'transaction'
            && (string) $approvalRequest->action === 'correction';
        if (! $isTransactionCorrection) {
            return;
        }

        $type = (string) ($payload['type'] ?? '');
        $supportedTypes = ['sales_invoice', 'sales_return', 'delivery_note', 'order_note', 'receivable_payment'];
        if (! in_array($type, $supportedTypes, true)) {
            $payload['execution'] = [
                'status' => 'skipped',
                'message' => 'Auto execute belum tersedia untuk tipe ini.',
                'executed_at' => now()->toDateTimeString(),
            ];
            $approvalRequest->update(['payload' => $payload]);

            return;
        }

        $subjectId = (int) ($approvalRequest->subject_id ?? 0);
        $expectedSubjectType = match ($type) {
            'sales_invoice' => SalesInvoice::class,
            'sales_return' => SalesReturn::class,
            'delivery_note' => DeliveryNote::class,
            'order_note' => OrderNote::class,
            'receivable_payment' => ReceivablePayment::class,
            default => '',
        };
        if ($subjectId <= 0 || (string) $approvalRequest->subject_type !== $expectedSubjectType) {
            $payload['execution'] = [
                'status' => 'failed',
                'message' => 'Subject dokumen tidak valid.',
                'executed_at' => now()->toDateTimeString(),
            ];
            $approvalRequest->update(['payload' => $payload]);

            return;
        }

        $patch = is_array($payload['patch'] ?? null) ? $payload['patch'] : [];
        if ($patch === []) {
            $payload['execution'] = [
                'status' => 'failed',
                'message' => 'Data koreksi (patch) belum diisi.',
                'executed_at' => now()->toDateTimeString(),
            ];
            $approvalRequest->update(['payload' => $payload]);

            return;
        }

        try {
            $executor = app(InvoiceCorrectionExecutor::class);
            match ($type) {
                'sales_invoice' => $executor->applySalesInvoiceCorrection($subjectId, $patch, $request),
                'sales_return' => $executor->applySalesReturnCorrection($subjectId, $patch, $request),
                'delivery_note' => $executor->applyDeliveryNoteCorrection($subjectId, $patch, $request),
                'order_note' => $executor->applyOrderNoteCorrection($subjectId, $patch, $request),
                'receivable_payment' => $executor->applyReceivablePaymentCorrection($subjectId, $patch, $request),
                default => null,
            };
            $payload['execution'] = [
                'status' => 'success',
                'message' => 'Koreksi dokumen dieksekusi otomatis.',
                'executed_at' => now()->toDateTimeString(),
            ];
            $approvalRequest->update(['payload' => $payload]);
            app(AuditLogService::class)->log(
                'approval.request.execute',
                $approvalRequest,
                "Approval execution success #{$approvalRequest->id}",
                $request
            );
        } catch (Throwable $exception) {
            $payload['execution'] = [
                'status' => 'failed',
                'message' => mb_substr($exception->getMessage(), 0, 500),
                'executed_at' => now()->toDateTimeString(),
            ];
            $approvalRequest->update(['payload' => $payload]);
            app(AuditLogService::class)->log(
                'approval.request.execute_failed',
                $approvalRequest,
                "Approval execution failed #{$approvalRequest->id}: ".$exception->getMessage(),
                $request
            );
        }
    }
}
