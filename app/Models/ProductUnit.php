<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    /**
     * @param  Builder<ProductUnit>  $query
     * @return Builder<ProductUnit>
     */
    public function scopeOnlyListColumns(Builder $query): Builder
    {
        return $query->select(['id', 'code', 'name', 'description']);
    }

    /**
     * @param  Builder<ProductUnit>  $query
     * @return Builder<ProductUnit>
     */
    public function scopeSearchKeyword(Builder $query, string $keyword): Builder
    {
        $search = trim($keyword);
        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($search): void {
            $subQuery->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%");
        });
    }

    public static function normalizeCode(mixed $code): string
    {
        return substr(strtolower((string) preg_replace('/[^a-z0-9\-]/', '', trim((string) $code))), 0, 30);
    }

    public static function defaultCode(): string
    {
        return (string) (static::query()->orderByRaw("CASE WHEN code = 'exp' THEN 0 ELSE 1 END")->orderBy('code')->value('code') ?? 'exp');
    }

    /**
     * @return array<int, array{code:string,label:string}>
     */
    public static function optionRows(): array
    {
        $rows = static::query()
            ->orderByRaw("CASE WHEN code = 'exp' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn (ProductUnit $unit): array => [
                'code' => (string) $unit->code,
                'label' => (string) $unit->name,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return [[
                'code' => 'exp',
                'label' => 'Exemplar',
            ]];
        }

        return $rows;
    }

    public static function ensureExists(mixed $code, ?string $name = null): self
    {
        $normalizedCode = static::normalizeCode($code);
        if ($normalizedCode === '') {
            $normalizedCode = 'exp';
        }

        $label = trim((string) $name);
        if ($label === '') {
            $label = ucfirst($normalizedCode);
        }

        return static::query()->firstOrCreate(
            ['code' => $normalizedCode],
            ['name' => substr($label, 0, 120)]
        );
    }
}
