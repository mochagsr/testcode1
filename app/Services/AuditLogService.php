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
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?Request $request = null
    ): void {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
