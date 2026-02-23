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
     * @return HasMany<CustomerShipLocation, $this>
     */
    public function shipLocations(): HasMany
    {
        return $this->hasMany(CustomerShipLocation::class)->orderBy('school_name');
    }

    /**
     * @return HasMany<SchoolBulkTransaction, $this>
     */
    public function schoolBulkTransactions(): HasMany
    {
        return $this->hasMany(SchoolBulkTransaction::class);
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
     * Scope: Columns for sales/return form customer picker.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeOnlySalesFormColumns(Builder $query): Builder
    {
        return $query->select(['id', 'name', 'city', 'customer_level_id']);
    }

    /**
     * Scope: Columns for receivable payment customer picker.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeOnlyReceivableFormColumns(Builder $query): Builder
    {
        return $query->select(['id', 'name', 'city', 'address', 'outstanding_receivable', 'credit_balance']);
    }

    /**
     * Scope: Columns for delivery note customer picker.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeOnlyDeliveryFormColumns(Builder $query): Builder
    {
        return $query->select(['id', 'name', 'city', 'phone', 'address']);
    }

    /**
     * Scope: Columns for order note customer picker.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeOnlyOrderFormColumns(Builder $query): Builder
    {
        return $query->select(['id', 'name', 'city', 'phone']);
    }

    /**
     * Scope: Minimal columns for option/dropdown lists.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeOnlyOptionColumns(Builder $query): Builder
    {
        return $query->select(['id', 'name']);
    }

    /**
     * Scope: Minimal columns for outstanding customer list.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeOnlyOutstandingColumns(Builder $query): Builder
    {
        return $query->select(['id', 'name', 'city', 'outstanding_receivable']);
    }

    /**
     * Scope: Eager load customer level.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeWithLevel(Builder $query): Builder
    {
        return $query->with('level:id,code,name');
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
     * Scope: Apply keyword search for name/city/phone.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('level', function (Builder $levelQuery) use ($search): void {
                    $levelQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
        });
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
