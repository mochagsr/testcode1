<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryTrip extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_number',
        'trip_date',
        'driver_name',
        'vehicle_plate',
        'member_count',
        'fuel_cost',
        'toll_cost',
        'meal_cost',
        'other_cost',
        'total_cost',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'member_count' => 'integer',
            'fuel_cost' => 'integer',
            'toll_cost' => 'integer',
            'meal_cost' => 'integer',
            'other_cost' => 'integer',
            'total_cost' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<DeliveryTripMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(DeliveryTripMember::class)->orderBy('member_name');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select([
            'id',
            'trip_number',
            'trip_date',
            'driver_name',
            'vehicle_plate',
            'member_count',
            'fuel_cost',
            'toll_cost',
            'meal_cost',
            'other_cost',
            'total_cost',
        ]);
    }

    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('trip_number', 'like', "%{$search}%")
                ->orWhere('driver_name', 'like', "%{$search}%")
                ->orWhere('vehicle_plate', 'like', "%{$search}%")
                ->orWhereHas('members', function (Builder $memberQuery) use ($search): void {
                    $memberQuery->where('member_name', 'like', "%{$search}%");
                });
        });
    }
}

