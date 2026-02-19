<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\SalesInvoice;
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
        if ($type !== 'sales_invoice') {
            $payload['execution'] = [
                'status' => 'skipped',
                'message' => 'Auto execute belum tersedia untuk tipe ini.',
                'executed_at' => now()->toDateTimeString(),
            ];
            $approvalRequest->update(['payload' => $payload]);

            return;
        }

        $subjectId = (int) ($approvalRequest->subject_id ?? 0);
        if ($subjectId <= 0 || (string) $approvalRequest->subject_type !== SalesInvoice::class) {
            $payload['execution'] = [
                'status' => 'failed',
                'message' => 'Subject invoice tidak valid.',
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
            app(InvoiceCorrectionExecutor::class)->applySalesInvoiceCorrection($subjectId, $patch, $request);
            $payload['execution'] = [
                'status' => 'success',
                'message' => 'Koreksi invoice dieksekusi otomatis.',
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
