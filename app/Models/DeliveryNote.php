<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryNote extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'note_number',
        'note_date',
        'customer_id',
        'recipient_name',
        'recipient_phone',
        'city',
        'address',
        'notes',
        'created_by_name',
        'is_canceled',
        'canceled_at',
        'canceled_by_user_id',
        'cancel_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'note_date' => 'date',
            'is_canceled' => 'boolean',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<DeliveryNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }
}
