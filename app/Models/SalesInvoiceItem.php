<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sales_invoice_id',
        'product_id',
        'product_code',
        'product_name',
        'quantity',
        'unit_price',
        'discount',
        'line_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'discount' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope to filter active items (not canceled).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_canceled', false);
    }

    /**
     * Scope to eager load the associated sales invoice.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithInvoiceInfo(Builder $query): Builder
    {
        return $query->with('invoice');
    }

    /**
     * Scope to eager load the associated product.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithProductInfo(Builder $query): Builder
    {
        return $query->with('product');
    }

    /**
     * Scope to order items by position ascending.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrderByPosition(Builder $query): Builder
    {
        return $query->orderBy('position', 'asc');
    }
}
