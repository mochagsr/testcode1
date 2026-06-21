<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    protected $fillable = [
        'type',
        'level',
        'title',
        'message',
        'context',
        'dedupe_key',
        'resolved_at',
        'resolved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<SystemAlert>  $query
     * @return Builder<SystemAlert>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Record a system alert. If a dedupe key is given and an unresolved alert
     * with that key already exists, refresh it instead of creating a duplicate.
     *
     * @param  array<string, mixed>  $context
     */
    public static function raise(
        string $type,
        string $title,
        string $message = '',
        string $level = 'critical',
        array $context = [],
        ?string $dedupeKey = null
    ): self {
        if ($dedupeKey !== null) {
            $existing = static::query()->unresolved()->where('dedupe_key', $dedupeKey)->first();
            if ($existing !== null) {
                $existing->forceFill([
                    'title' => $title,
                    'message' => $message,
                    'level' => $level,
                    'context' => $context,
                    'updated_at' => now(),
                ])->save();

                return $existing;
            }
        }

        return static::query()->create([
            'type' => $type,
            'level' => $level,
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'dedupe_key' => $dedupeKey,
        ]);
    }
}
