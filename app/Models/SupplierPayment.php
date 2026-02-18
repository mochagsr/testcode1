<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_number',
        'supplier_id',
        'payment_date',
        'proof_number',
        'payment_proof_photo_path',
        'amount',
        'amount_in_words',
        'supplier_signature',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select([
            'id',
            'payment_number',
            'supplier_id',
            'payment_date',
            'proof_number',
            'amount',
            'is_canceled',
        ]);
    }

    public function scopeWithSupplierInfo(Builder $query): Builder
    {
        return $query->with('supplier:id,name,company_name,phone');
    }

    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('payment_number', 'like', "%{$search}%")
                ->orWhere('proof_number', 'like', "%{$search}%")
                ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search): void {
                    $supplierQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
        });
    }
}
