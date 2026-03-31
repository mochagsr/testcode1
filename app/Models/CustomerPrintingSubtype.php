<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPrintingSubtype extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'name',
        'normalized_name',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function normalizeName(?string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        return mb_strtolower((string) $normalized, 'UTF-8');
    }
}
