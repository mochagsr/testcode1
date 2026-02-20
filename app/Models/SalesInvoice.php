<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoice extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_number',
        'customer_id',
        'customer_ship_location_id',
        'invoice_date',
        'due_date',
        'semester_period',
        'subtotal',
        'total',
        'total_paid',
        'balance',
        'payment_status',
        'ship_to_name',
        'ship_to_phone',
        'ship_to_city',
        'ship_to_address',
        'notes',
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
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'integer',
            'total' => 'integer',
            'total_paid' => 'integer',
            'balance' => 'integer',
            'is_canceled' => 'boolean',
            'canceled_at' => 'datetime',
            'deleted_at' => 'datetime',
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
     * @return BelongsTo<CustomerShipLocation, $this>
     */
    public function shipLocation(): BelongsTo
    {
        return $this->belongsTo(CustomerShipLocation::class, 'customer_ship_location_id');
    }

    /**
     * @return HasMany<SalesInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    /**
     * @return HasMany<InvoicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * Scope: Only select essential columns for list views.
     *
     * @param  Builder<SalesInvoice>  $query
     * @return Builder<SalesInvoice>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select([
            'id',
            'invoice_number',
            'customer_id',
            'invoice_date',
            'semester_period',
            'total',
            'total_paid',
            'balance',
            'payment_status',
            'is_canceled',
        ]);
    }

    /**
     * Scope: Eager load customer with minimal columns.
     *
     * @param  Builder<SalesInvoice>  $query
     * @return Builder<SalesInvoice>
     */
    public function scopeWithCustomerInfo(Builder $query): Builder
    {
        return $query->with('customer:id,code,name,phone,city');
    }

    /**
     * Scope: Filter active invoices only.
     *
     * @param  Builder<SalesInvoice>  $query
     * @return Builder<SalesInvoice>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_canceled', false);
    }

    /**
     * Scope: Filter canceled invoices only.
     *
     * @param  Builder<SalesInvoice>  $query
     * @return Builder<SalesInvoice>
     */
    public function scopeCanceled(Builder $query): Builder
    {
        return $query->where('is_canceled', true);
    }

    /**
     * Scope: Filter by semester period.
     *
     * @param  Builder<SalesInvoice>  $query
     * @param  string  $semester
     * @return Builder<SalesInvoice>
     */
    public function scopeForSemester(Builder $query, string $semester): Builder
    {
        return $query->where('semester_period', $semester);
    }

    /**
     * Scope: Filter by customer.
     *
     * @param  Builder<SalesInvoice>  $query
     * @return Builder<SalesInvoice>
     */
    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope: Filter invoices with open balance.
     *
     * @param  Builder<SalesInvoice>  $query
     * @return Builder<SalesInvoice>
     */
    public function scopeWithOpenBalance(Builder $query): Builder
    {
        return $query->where('balance', '>', 0);
    }

    /**
     * Scope: Apply keyword search by invoice number or customer.
     *
     * @param  Builder<SalesInvoice>  $query
     * @return Builder<SalesInvoice>
     */
    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('invoice_number', 'like', "%{$search}%")
                ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                    $customerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
        });
    }

    /**
     * Scope: Order by invoice date then id.
     *
     * @param  Builder<SalesInvoice>  $query
     * @param  string  $direction
     * @return Builder<SalesInvoice>
     */
    public function scopeOrderByDate(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('invoice_date', $direction)
            ->orderBy('id', $direction);
    }
}
