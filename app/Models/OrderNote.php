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
        'customer_name',
        'customer_phone',
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

    /**
     * @return HasMany<OrderNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderNoteItem::class);
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
            'customer_name',
            'city',
            'created_by_name',
            'is_canceled',
        ]);
    }

    /**
     * Scope to filter active order notes (not canceled).
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
        return $query->with('customer:id,name,city');
    }

    /**
     * Scope to filter canceled order notes only.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeCanceled(Builder $query): Builder
    {
        return $query->where('is_canceled', true);
    }

    /**
     * Scope to order order notes by order date descending.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrderByDate(Builder $query): Builder
    {
        return $query->orderBy('note_date', 'desc');
    }
}
