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
            class_basename($model) . ' created',
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
            class_basename($model) . ' updated',
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
            class_basename($model) . ' deleted',
            null,
            $model->getOriginal(),
            null
        );
    }
}
