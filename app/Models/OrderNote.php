<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderNote extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'note_number',
        'note_date',
        'customer_id',
        'customer_name',
        'customer_phone',
        'city',
        'created_by_name',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'note_date' => 'date',
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
     * @return HasMany<OrderNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderNoteItem::class);
    }
}
