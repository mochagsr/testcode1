<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class FinancialModelAuditObserver
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {}

    public function created(Model $model): void
    {
        $this->auditLogService->log(
            'financial.created',
            $model,
            __('ui.audit_desc_financial_created', [
                'model' => class_basename($model),
            ]),
            null,
            null,
            $model->getAttributes()
        );
    }

    public function updated(Model $model): void
    {
        $before = array_intersect_key($model->getOriginal(), $model->getChanges());
        $after = $model->getChanges();
        if ($after === []) {
            return;
        }

        $this->auditLogService->log(
            'financial.updated',
            $model,
            __('ui.audit_desc_financial_updated', [
                'model' => class_basename($model),
            ]),
            null,
            $before,
            $after
        );
    }

    public function deleted(Model $model): void
    {
        $this->auditLogService->log(
            'financial.deleted',
            $model,
            __('ui.audit_desc_financial_deleted', [
                'model' => class_basename($model),
            ]),
            null,
            $model->getOriginal(),
            null
        );
    }
}
