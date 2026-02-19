<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

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
}

