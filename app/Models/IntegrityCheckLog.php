<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrityCheckLog extends Model
{
    protected $fillable = [
        'customer_mismatch_count',
        'supplier_mismatch_count',
        'invalid_receivable_links',
        'invalid_supplier_links',
        'details',
        'is_ok',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'customer_mismatch_count' => 'integer',
            'supplier_mismatch_count' => 'integer',
            'invalid_receivable_links' => 'integer',
            'invalid_supplier_links' => 'integer',
            'details' => 'array',
            'is_ok' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }
}
