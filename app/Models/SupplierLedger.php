<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierLedger extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_id',
        'outgoing_transaction_id',
        'supplier_payment_id',
        'entry_date',
        'period_code',
        'description',
        'debit',
        'credit',
        'balance_after',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'debit' => 'integer',
            'credit' => 'integer',
            'balance_after' => 'integer',
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
     * @return BelongsTo<OutgoingTransaction, $this>
     */
    public function outgoingTransaction(): BelongsTo
    {
        return $this->belongsTo(OutgoingTransaction::class);
    }

    /**
     * @return BelongsTo<SupplierPayment, $this>
     */
    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }

    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeForSemester(Builder $query, string $semester): Builder
    {
        return $query->where('period_code', $semester);
    }

    public function scopeOrderByDate(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('entry_date', $direction)->orderBy('id', $direction);
    }
}
