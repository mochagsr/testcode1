<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableLedger extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'sales_invoice_id',
        'entry_date',
        'period_code',
        'description',
        'debit',
        'credit',
        'balance_after',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'debit' => 'integer',
            'credit' => 'integer',
            'balance_after' => 'integer',
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
     * Scope: Order by date (newest first by default).
     *
     * @param  Builder  $query
     * @param  string  $direction
     * @return Builder
     */
    public function scopeOrderByDate(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('entry_date', $direction)->orderBy('id', $direction);
    }

    /**
     * Scope: Eager load customer information.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeWithCustomerInfo(Builder $query): Builder
    {
        return $query->with('customer:id,name,city,code');
    }

    /**
     * Scope: Eager load invoice information.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeWithInvoiceInfo(Builder $query): Builder
    {
        return $query->with('invoice:id,invoice_number,customer_id,balance,total');
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
     * Scope: Filter by semester/period code.
     *
     * @param  Builder  $query
     * @param  string  $semester
     * @return Builder
     */
    public function scopeForSemester(Builder $query, string $semester): Builder
    {
        return $query->where('period_code', $semester);
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
        return $query->whereBetween('entry_date', [$startDate, $endDate]);
    }
}
