<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolBulkTransaction extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'transaction_number',
        'transaction_date',
        'customer_id',
        'semester_period',
        'total_locations',
        'total_items',
        'notes',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'total_locations' => 'integer',
            'total_items' => 'integer',
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
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<SchoolBulkTransactionLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(SchoolBulkTransactionLocation::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<SchoolBulkTransactionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SchoolBulkTransactionItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<SalesInvoice, $this>
     */
    public function generatedInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'school_bulk_transaction_id')
            ->orderBy('invoice_date')
            ->orderBy('id');
    }

    /**
     * @param  Builder<SchoolBulkTransaction>  $query
     * @return Builder<SchoolBulkTransaction>
     */
    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('transaction_number', 'like', "%{$search}%")
                ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                    $customerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
        });
    }
}
