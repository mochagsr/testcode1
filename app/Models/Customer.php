<?php

namespace App\Models;

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
}

