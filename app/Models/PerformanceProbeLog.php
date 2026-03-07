<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceProbeLog extends Model
{
    protected $fillable = [
        'loops',
        'duration_ms',
        'avg_loop_ms',
        'search_token',
        'metrics',
        'probed_at',
    ];

    protected function casts(): array
    {
        return [
            'loops' => 'integer',
            'duration_ms' => 'integer',
            'avg_loop_ms' => 'integer',
            'search_token' => 'string',
            'metrics' => 'array',
            'probed_at' => 'datetime',
        ];
    }
}

