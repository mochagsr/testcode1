<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolBulkTransactionLocation extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_bulk_transaction_id',
        'customer_ship_location_id',
        'school_name',
        'recipient_name',
        'recipient_phone',
        'city',
        'address',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SchoolBulkTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SchoolBulkTransaction::class, 'school_bulk_transaction_id');
    }

    /**
     * @return BelongsTo<CustomerShipLocation, $this>
     */
    public function shipLocation(): BelongsTo
    {
        return $this->belongsTo(CustomerShipLocation::class, 'customer_ship_location_id');
    }

    /**
     * @return HasMany<SalesInvoice, $this>
     */
    public function generatedInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'school_bulk_location_id');
    }

    /**
     * @return HasMany<SchoolBulkTransactionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SchoolBulkTransactionItem::class, 'school_bulk_transaction_location_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
