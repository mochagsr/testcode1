<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sales_invoice_id',
        'payment_date',
        'amount',
        'method',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    /**
     * Scope to filter active payments (not canceled).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_canceled', false);
    }

    /**
     * Scope to eager load the associated sales invoice.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithInvoiceInfo(Builder $query): Builder
    {
        return $query->with('invoice');
    }

    /**
     * Scope to order payments by payment date descending.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrderByDate(Builder $query): Builder
    {
        return $query->orderBy('payment_date', 'desc');
    }
}
