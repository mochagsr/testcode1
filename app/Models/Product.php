<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_category_id',
        'code',
        'name',
        'unit',
        'stock',
        'price_agent',
        'price_sales',
        'price_general',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'price_agent' => 'integer',
            'price_sales' => 'integer',
            'price_general' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ItemCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    /**
     * @return HasMany<StockMutation, $this>
     */
    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }

    /**
     * @return HasMany<SalesReturnItem, $this>
     */
    public function salesReturnItems(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    /**
     * @return HasMany<DeliveryNoteItem, $this>
     */
    public function deliveryNoteItems(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    /**
     * @return HasMany<OrderNoteItem, $this>
     */
    public function orderNoteItems(): HasMany
    {
        return $this->hasMany(OrderNoteItem::class);
    }

    /**
     * @return HasMany<OutgoingTransactionItem, $this>
     */
    public function outgoingTransactionItems(): HasMany
    {
        return $this->hasMany(OutgoingTransactionItem::class);
    }

    /**
     * Scope: Only select columns necessary for list views.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select([
            'id',
            'item_category_id',
            'code',
            'name',
            'unit',
            'stock',
            'price_agent',
            'price_sales',
            'price_general',
            'is_active',
        ]);
    }

    /**
     * Scope: Columns for sales/return product picker.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOnlySalesFormColumns(Builder $query): Builder
    {
        return $query->select([
            'id',
            'code',
            'name',
            'stock',
            'price_agent',
            'price_sales',
            'price_general',
        ]);
    }

    /**
     * Scope: Columns for delivery note product picker.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOnlyDeliveryFormColumns(Builder $query): Builder
    {
        return $query->select(['id', 'code', 'name', 'unit', 'price_general']);
    }

    /**
     * Scope: Columns for order note product picker.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOnlyOrderFormColumns(Builder $query): Builder
    {
        return $query->select(['id', 'code', 'name']);
    }

    /**
     * Scope: Columns for outgoing transaction product picker.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOnlyOutgoingFormColumns(Builder $query): Builder
    {
        return $query->select(['id', 'code', 'name', 'unit', 'stock', 'price_general']);
    }

    /**
     * Scope: Eager load category with minimal columns.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeWithCategoryInfo(Builder $query): Builder
    {
        return $query->with('category:id,code,name');
    }

    /**
     * Scope: Filter active products only.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by category ID.
     *
     * @param  Builder<Product>  $query
     * @param  int  $categoryId
     * @return Builder<Product>
     */
    public function scopeInCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('item_category_id', $categoryId);
    }

    /**
     * Scope: Apply keyword search for code/name/category name.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhereHas('category', function (Builder $categoryQuery) use ($search): void {
                    $categoryQuery->where('name', 'like', "%{$search}%");
                });
        });
    }

    /**
     * Scope: Filter by low stock (stock <= threshold).
     *
     * @param  Builder<Product>  $query
     * @param  int  $threshold
     * @return Builder<Product>
     */
    public function scopeLowStock(Builder $query, int $threshold = 0): Builder
    {
        return $query->where('stock', '<=', max(0, $threshold));
    }
}
