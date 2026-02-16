<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivablePayment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_number',
        'customer_id',
        'payment_date',
        'customer_address',
        'amount',
        'amount_in_words',
        'customer_signature',
        'user_signature',
        'notes',
        'created_by_user_id',
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
            'payment_date' => 'date',
            'amount' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope: Only active (not canceled) payments.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_canceled', false);
    }

    /**
     * Scope: Eager load customer with related info.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeWithCustomerInfo(Builder $query): Builder
    {
        return $query->with('customer:id,name,city,code');
    }

    /**
     * Scope: Eager load creator user info.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeWithCreatorInfo(Builder $query): Builder
    {
        return $query->with('createdBy:id,name');
    }

    /**
     * Scope: Filter by customer.
     *
     * @param  Builder  $query
     * @param  int  $customerId
     * @return Builder
     */
    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope: Filter by date range.
     *
     * @param  Builder  $query
     * @param  \Carbon\CarbonInterface  $startDate
     * @param  \Carbon\CarbonInterface  $endDate
     * @return Builder
     */
    public function scopeBetweenDates(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Order by payment date (newest first).
     *
     * @param  Builder  $query
     * @param  string  $direction
     * @return Builder
     */
    public function scopeOrderByDate(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('payment_date', $direction)->orderBy('id', $direction);
    }
}
