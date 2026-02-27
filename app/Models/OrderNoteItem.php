<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderNoteItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_note_id',
        'product_id',
        'product_code',
        'product_name',
        'quantity',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OrderNote, $this>
     */
    public function orderNote(): BelongsTo
    {
        return $this->belongsTo(OrderNote::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<SalesInvoiceItem, $this>
     */
    public function salesInvoiceItems(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class, 'order_note_item_id');
    }
}
