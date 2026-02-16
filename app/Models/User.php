<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'locale',
        'theme',
        'finance_locked',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'finance_locked' => 'boolean',
        ];
    }

    /**
     * Scope: columns for user list/report screens.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select(['id', 'name', 'email', 'role', 'locale', 'theme', 'finance_locked', 'created_at']);
    }

    /**
     * Scope: search by name/email/role.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('role', 'like', "%{$search}%");
        });
    }

    /**
     * Scope: filter by role.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeInRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    /**
     * Scope: filter by finance lock flag.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeFinanceLock(Builder $query, bool $locked): Builder
    {
        return $query->where('finance_locked', $locked);
    }
}
