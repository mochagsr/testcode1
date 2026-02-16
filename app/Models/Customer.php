<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_level_id',
        'code',
        'name',
        'phone',
        'city',
        'address',
        'id_card_photo_path',
        'outstanding_receivable',
        'credit_balance',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outstanding_receivable' => 'integer',
            'credit_balance' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CustomerLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(CustomerLevel::class, 'customer_level_id');
    }

    /**
     * @return HasMany<SalesInvoice, $this>
     */
    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    /**
     * @return HasMany<ReceivableLedger, $this>
     */
    public function receivableLedgers(): HasMany
    {
        return $this->hasMany(ReceivableLedger::class);
    }

    /**
     * @return HasMany<SalesReturn, $this>
     */
    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    /**
     * @return HasMany<DeliveryNote, $this>
     */
    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    /**
     * @return HasMany<OrderNote, $this>
     */
    public function orderNotes(): HasMany
    {
        return $this->hasMany(OrderNote::class);
    }

    /**
     * Scope: Only select essential columns for list views.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select([
            'id',
            'customer_level_id',
            'code',
            'name',
            'phone',
            'city',
            'outstanding_receivable',
            'credit_balance',
        ]);
    }

    /**
     * Scope: Eager load customer level.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeWithLevel(Builder $query): Builder
    {
        return $query->with('level:id,name,discount_percentage');
    }

    /**
     * Scope: Filter customers with outstanding receivables.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeWithOutstanding(Builder $query): Builder
    {
        return $query->where('outstanding_receivable', '>', 0);
    }

    /**
     * Scope: Filter customers by city.
     *
     * @param  Builder<Customer>  $query
     * @param  string  $city
     * @return Builder<Customer>
     */
    public function scopeInCity(Builder $query, string $city): Builder
    {
        return $query->where('city', $city);
    }
}
