<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'return_number',
        'customer_id',
        'sales_invoice_id',
        'return_date',
        'semester_period',
        'total',
        'reason',
        'is_canceled',
        'canceled_at',
        'canceled_by_user_id',
        'cancel_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'total' => 'integer',
            'is_canceled' => 'boolean',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    /**
     * @return HasMany<SalesReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    /**
     * Scope: Only select essential columns for list views.
     *
     * @param  Builder<SalesReturn>  $query
     * @return Builder<SalesReturn>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select([
            'id',
            'return_number',
            'customer_id',
            'sales_invoice_id',
            'return_date',
            'semester_period',
            'total',
            'is_canceled',
        ]);
    }

    /**
     * Scope to filter active sales returns (not canceled).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_canceled', false);
    }

    /**
     * Scope to eager load the associated customer.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithCustomerInfo(Builder $query): Builder
    {
        return $query->with('customer:id,code,name,city');
    }

    /**
     * Scope to eager load the associated sales invoice.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithInvoiceInfo(Builder $query): Builder
    {
        return $query->with('invoice:id,invoice_number,customer_id,total,balance');
    }

    /**
     * Scope to apply keyword search.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('return_number', 'like', "%{$search}%")
                ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                    $customerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
        });
    }

    /**
     * Scope to filter canceled sales returns only.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeCanceled(Builder $query): Builder
    {
        return $query->where('is_canceled', true);
    }

    /**
     * Scope to order sales returns by return date descending.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrderByDate(Builder $query): Builder
    {
        return $query->orderBy('return_date', 'desc');
    }
}
