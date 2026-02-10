<?php

namespace App\Models;

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
            'price_agent' => 'decimal:2',
            'price_sales' => 'decimal:2',
            'price_general' => 'decimal:2',
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
}
