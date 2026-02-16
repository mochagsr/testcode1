<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemCategory extends Model
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
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scope: Minimal columns for category list view.
     *
     * @param  Builder<ItemCategory>  $query
     * @return Builder<ItemCategory>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select(['id', 'code', 'name', 'description']);
    }

    /**
     * Scope: Apply keyword search for category code/name.
     *
     * @param  Builder<ItemCategory>  $query
     * @return Builder<ItemCategory>
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
