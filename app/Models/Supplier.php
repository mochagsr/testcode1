<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company_name',
        'phone',
        'address',
        'bank_account_notes',
        'notes',
    ];

    /**
     * @return HasMany<OutgoingTransaction, $this>
     */
    public function outgoingTransactions(): HasMany
    {
        return $this->hasMany(OutgoingTransaction::class);
    }

    /**
     * Scope: Columns for supplier list screens.
     *
     * @param  Builder<Supplier>  $query
     * @return Builder<Supplier>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select(['id', 'name', 'company_name', 'phone', 'address', 'notes']);
    }

    /**
     * Scope: Columns for supplier lookup/dropdown.
     *
     * @param  Builder<Supplier>  $query
     * @return Builder<Supplier>
     */
    public function scopeOnlyLookupColumns(Builder $query): Builder
    {
        return $query->select(['id', 'name', 'company_name', 'phone', 'address']);
    }

    /**
     * Scope: Minimal columns for option/dropdown lists.
     *
     * @param  Builder<Supplier>  $query
     * @return Builder<Supplier>
     */
    public function scopeOnlyOptionColumns(Builder $query): Builder
    {
        return $query->select(['id', 'name']);
    }

    /**
     * Scope: Apply keyword search for supplier list/lookup.
     *
     * @param  Builder<Supplier>  $query
     * @return Builder<Supplier>
     */
    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }
}
