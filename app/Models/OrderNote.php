<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderNote extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'note_number',
        'note_date',
        'customer_id',
        'transaction_type',
        'customer_printing_subtype_id',
        'printing_subtype_name',
        'customer_name',
        'customer_phone',
        'address',
        'city',
        'created_by_name',
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
            'note_date' => 'date',
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

    public function printingSubtype(): BelongsTo
    {
        return $this->belongsTo(CustomerPrintingSubtype::class, 'customer_printing_subtype_id');
    }

    /**
     * @return HasMany<OrderNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderNoteItem::class);
    }

    /**
     * @return HasMany<SalesInvoice, $this>
     */
    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'order_note_id');
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class, 'order_note_id');
    }

    /**
     * Scope: Only select essential columns for list views.
     *
     * @param  Builder<OrderNote>  $query
     * @return Builder<OrderNote>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select([
            'id',
            'note_number',
            'note_date',
            'customer_id',
            'transaction_type',
            'printing_subtype_name',
            'customer_name',
            'customer_phone',
            'city',
            'created_by_name',
            'is_canceled',
        ]);
    }

    /**
     * Scope to filter active order notes (not canceled).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_canceled', false);
    }

    /**
     * Scope to eager load the associated customer.
     */
    public function scopeWithCustomerInfo(Builder $query): Builder
    {
        return $query->with('customer:id,name,city');
    }

    /**
     * Scope to apply keyword search.
     */
    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('note_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%");
        });
    }

    /**
     * Scope to filter canceled order notes only.
     */
    public function scopeCanceled(Builder $query): Builder
    {
        return $query->where('is_canceled', true);
    }

    /**
     * Scope to order order notes by order date descending.
     */
    public function scopeOrderByDate(Builder $query): Builder
    {
        return $query->orderBy('note_date', 'desc');
    }

    /**
     * Scope: filter note date by range.
     *
     * @param  Builder<OrderNote>  $query
     * @return Builder<OrderNote>
     */
    public function scopeBetweenDates(Builder $query, mixed $startDate, mixed $endDate): Builder
    {
        return $query->whereBetween('note_date', [$startDate, $endDate]);
    }
}
