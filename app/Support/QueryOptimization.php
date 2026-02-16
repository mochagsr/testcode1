<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for common query optimization patterns.
 * Reduces boilerplate query optimization code in controllers.
 */
trait QueryOptimization
{
    /**
     * Apply pagination with safe defaults for VPS.
     *
     * @param  Builder  $query
     * @param  int  $perPage Items per page
     * @return mixed
     */
    protected function safePaginate(Builder $query, int $perPage = 15): mixed
    {
        return $query->paginate(
            min($perPage, 50), // Max 50 per page to protect memory
            ['*'],
            'page',
            request()->integer('page', 1)
        );
    }

    /**
     * Apply search filtering with validation.
     *
     * @param  Builder  $query
     * @param  string  $searchTerm
     * @param  array<int, string>  $searchableFields Fields to search
     * @return Builder
     */
    protected function applySearch(Builder $query, string $searchTerm, array $searchableFields): Builder
    {
        $searchTerm = trim($searchTerm);

        if ($searchTerm === '' || strlen($searchTerm) < 2) {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($searchTerm, $searchableFields): void {
            foreach ($searchableFields as $field) {
                $subQuery->orWhere($field, 'like', "%{$searchTerm}%");
            }
        });
    }

    /**
     * Apply status filtering.
     *
     * @param  Builder  $query
     * @param  string  $status
     * @param  array<string, mixed>  $statusMap Map of status => condition
     * @return Builder
     */
    protected function applyStatusFilter(Builder $query, string $status, array $statusMap): Builder
    {
        $status = trim($status);

        if ($status === '' || !isset($statusMap[$status])) {
            return $query;
        }

        $condition = $statusMap[$status];

        if (is_array($condition)) {
            [$field, $value] = $condition;

            return $query->where($field, $value);
        }

        return $query;
    }

    /**
     * Apply date range filtering.
     *
     * @param  Builder  $query
     * @param  string  $dateString Format: YYYY-MM-DD
     * @param  string  $dateField Field name
     * @return Builder
     */
    protected function applyDateFilter(Builder $query, string $dateString, string $dateField = 'created_at'): Builder
    {
        $dateString = trim($dateString);

        if ($dateString === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
            return $query;
        }

        try {
            $date = \Carbon\Carbon::parse($dateString);

            return $query->whereBetween($dateField, [
                $date->startOfDay(),
                $date->endOfDay(),
            ]);
        } catch (\Exception) {
            return $query;
        }
    }

    /**
     * Get sorting order.
     *
     * @param  string  $sort Column name
     * @param  string  $direction 'asc' or 'desc'
     * @param  array<int, string>  $allowedColumns Allowed sortable columns
     * @return array{column: string, direction: string}
     */
    protected function getSafeSort(string $sort, string $direction, array $allowedColumns): array
    {
        $sort = trim($sort);
        $direction = strtolower(trim($direction));

        // Prevent SQL injection by validating column name
        if (!in_array($sort, $allowedColumns, true)) {
            $sort = 'id';
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return [
            'column' => $sort,
            'direction' => $direction,
        ];
    }
}
