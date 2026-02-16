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
        return $query->with('supplier:id,code,name,company_name');
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
}
