<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerLevel extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Scope: Minimal columns for customer level list.
     *
     * @param  Builder<CustomerLevel>  $query
     * @return Builder<CustomerLevel>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select(['id', 'code', 'name', 'description']);
    }

    /**
     * Scope: Apply keyword search for customer level code/name.
     *
     * @param  Builder<CustomerLevel>  $query
     * @return Builder<CustomerLevel>
     */
    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%");
        });
    }
}
