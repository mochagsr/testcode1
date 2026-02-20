<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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
        $payload = [
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'meta_data' => $metaData,
            'request_id' => $this->resolveRequestId($request),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];

        // Backward compatibility for databases that have not run all audit log migrations yet.
        $columns = $this->availableAuditLogColumns();
        $safePayload = array_intersect_key($payload, array_flip($columns));
        if (! array_key_exists('action', $safePayload)) {
            return;
        }

        try {
            AuditLog::query()->create($safePayload);
        } catch (QueryException $exception) {
            // Some environments may lag behind migrations. Retry with legacy-safe fields only.
            if (! $this->isMissingColumnError($exception)) {
                throw $exception;
            }

            $legacyPayload = $this->filterLegacyPayload($safePayload);
            if (! array_key_exists('action', $legacyPayload)) {
                return;
            }

            AuditLog::query()->create($legacyPayload);
        }
    }

    /**
     * @return array<int, string>
     */
    private function availableAuditLogColumns(): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return ['action'];
        }

        return array_map(
            static fn (string $column): string => strtolower(trim($column)),
            Schema::getColumnListing('audit_logs')
        );
    }

    private function resolveRequestId(?Request $request): string
    {
        $activeRequest = $request ?? request();
        if (! $activeRequest instanceof Request) {
            return (string) \Illuminate\Support\Str::uuid();
        }

        $headerRequestId = trim((string) $activeRequest->headers->get('X-Request-Id', ''));
        if ($headerRequestId !== '') {
            return substr($headerRequestId, 0, 100);
        }

        $generated = (string) $activeRequest->attributes->get('_request_id', '');
        if ($generated !== '') {
            return substr($generated, 0, 100);
        }

        $generated = (string) \Illuminate\Support\Str::uuid();
        $activeRequest->attributes->set('_request_id', $generated);

        return $generated;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterLegacyPayload(array $payload): array
    {
        return array_intersect_key($payload, array_flip([
            'user_id',
            'action',
            'subject_type',
            'subject_id',
            'description',
            'ip_address',
            'user_agent',
        ]));
    }

    private function isMissingColumnError(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'no column named')
            || str_contains($message, 'unknown column');
    }
}
