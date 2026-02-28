<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Base observer to automatically log model changes to audit log.
 * Reduces boilerplate audit logging in controllers.
 */
abstract class BaseModelAuditObserver
{
    public function __construct(
        protected readonly AuditLogService $auditLogService
    ) {}

    protected function logCreated(Model $model, string $description): void
    {
        $this->auditLogService->log('created', $model, $description);
    }

    protected function logUpdated(Model $model): void
    {
        $changes = $model->getChanges();
        if (!empty($changes)) {
            $this->auditLogService->log(
                'updated',
                $model,
                __('ui.audit_desc_updated_fields', [
                    'fields' => implode(', ', array_keys($changes)),
                ])
            );
        }
    }

    protected function logDeleted(Model $model, string $description): void
    {
        $this->auditLogService->log('deleted', $model, $description);
    }
}
