<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuditLogService
{
    /**
     * Log an audit event to the database.
     *
     * @param  string  $action The action type being audited
     * @param  Model|null  $subject The model instance being acted upon
     * @param  string|null  $description Additional details about the action
     * @param  Request|null  $request The HTTP request that triggered the action
     * @param  array<string, mixed>|null  $beforeData Data snapshot before change
     * @param  array<string, mixed>|null  $afterData Data snapshot after change
     * @param  array<string, mixed>|null  $metaData Extra metadata for investigation
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?Request $request = null,
        ?array $beforeData = null,
        ?array $afterData = null,
        ?array $metaData = null
    ): void {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'meta_data' => $metaData,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
