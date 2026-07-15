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

    public const TYPE_GENERAL = 'general';

    public const TYPE_RAW_MATERIAL = 'raw_material';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
    ];

    /**
     * Category types keyed by value.
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_GENERAL => __('ui.product_type_general'),
            self::TYPE_RAW_MATERIAL => __('ui.product_type_raw_material'),
        ];
    }

    /**
     * Scope: only categories of the given product type.
     *
     * @param  Builder<ItemCategory>  $query
     * @return Builder<ItemCategory>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type === self::TYPE_RAW_MATERIAL ? self::TYPE_RAW_MATERIAL : self::TYPE_GENERAL);
    }

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
        return $query->select(['id', 'code', 'name', 'type', 'description']);
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
