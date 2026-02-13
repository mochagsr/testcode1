<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingTransactionItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'outgoing_transaction_id',
        'product_id',
        'product_code',
        'product_name',
        'unit',
        'quantity',
        'unit_cost',
        'line_total',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OutgoingTransaction, $this>
     */
    public function outgoingTransaction(): BelongsTo
    {
        return $this->belongsTo(OutgoingTransaction::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
