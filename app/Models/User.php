<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'permissions',
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
            'permissions' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function resolvedPermissions(): array
    {
        $userPermissions = collect($this->permissions ?? [])
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        if ($userPermissions !== []) {
            return $this->expandPermissions($userPermissions);
        }

        $role = strtolower(trim((string) $this->role));
        if ($role === '') {
            $role = 'user';
        }
        $rolePermissions = config('rbac.roles.'.$role, []);

        return $this->expandPermissions(collect(is_array($rolePermissions) ? $rolePermissions : [])
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all());
    }

    public function canAccess(string $permission): bool
    {
        $requiredPermission = strtolower(trim($permission));
        if ($requiredPermission === '') {
            return false;
        }

        if (in_array($requiredPermission, (array) config('rbac.always_allowed', []), true)) {
            return true;
        }

        $resolvedPermissions = $this->resolvedPermissions();

        return in_array('*', $resolvedPermissions, true)
            || in_array($requiredPermission, $resolvedPermissions, true);
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    private function expandPermissions(array $permissions): array
    {
        $normalized = collect($permissions)
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => $value !== '')
            ->values();

        $impliedPermissions = (array) config('rbac.implied_permissions', []);
        $expanded = $normalized->all();

        foreach ($normalized as $permission) {
            foreach ((array) ($impliedPermissions[$permission] ?? []) as $impliedPermission) {
                $expanded[] = strtolower(trim((string) $impliedPermission));
            }
        }

        return collect($expanded)
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, string>  $permissions
     */
    public function canAccessAny(iterable $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->canAccess((string) $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope: columns for user list/report screens.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select(['id', 'name', 'username', 'email', 'role', 'locale', 'theme', 'finance_locked', 'created_at']);
    }

    /**
     * Scope: search by name/username/email/role.
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
                ->orWhere('username', 'like', "%{$search}%")
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
