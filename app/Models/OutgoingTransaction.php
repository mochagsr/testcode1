<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutgoingTransaction extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'transaction_number',
        'transaction_date',
        'supplier_id',
        'semester_period',
        'note_number',
        'supplier_invoice_photo_path',
        'total',
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
            'total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<OutgoingTransactionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OutgoingTransactionItem::class);
    }

    /**
     * Scope: Only select essential columns for list views.
     *
     * @param  Builder<OutgoingTransaction>  $query
     * @return Builder<OutgoingTransaction>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select([
            'id',
            'transaction_number',
            'transaction_date',
            'supplier_id',
            'semester_period',
            'note_number',
            'total',
            'created_by_user_id',
        ]);
    }

    /**
     * Scope: Eager load supplier with minimal columns.
     *
     * @param  Builder<OutgoingTransaction>  $query
     * @return Builder<OutgoingTransaction>
     */
    public function scopeWithSupplierInfo(Builder $query): Builder
    {
        return $query->with('supplier:id,code,name,company_name,phone');
    }

    /**
     * Scope: Eager load creator user info.
     *
     * @param  Builder<OutgoingTransaction>  $query
     * @return Builder<OutgoingTransaction>
     */
    public function scopeWithCreator(Builder $query): Builder
    {
        return $query->with('creator:id,name,email');
    }

    /**
     * Scope: Filter by semester period.
     *
     * @param  Builder<OutgoingTransaction>  $query
     * @param  string  $semester
     * @return Builder<OutgoingTransaction>
     */
    public function scopeForSemester(Builder $query, string $semester): Builder
    {
        return $query->where('semester_period', $semester);
    }

    /**
     * Scope: Filter by supplier.
     *
     * @param  Builder<OutgoingTransaction>  $query
     * @return Builder<OutgoingTransaction>
     */
    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope: Apply keyword search by transaction/note/supplier.
     *
     * @param  Builder<OutgoingTransaction>  $query
     * @return Builder<OutgoingTransaction>
     */
    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('transaction_number', 'like', "%{$search}%")
                ->orWhere('note_number', 'like', "%{$search}%")
                ->orWhereHas('supplier', function (Builder $supplierQuery) use ($search): void {
                    $supplierQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
        });
    }
}
