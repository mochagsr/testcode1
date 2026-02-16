<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Support\AppCache;
use App\Support\SemesterBookService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait ResolvesSemesterOptions
{
    /**
     * @param  class-string  $modelClass
     */
    protected function cachedSemesterOptionsFromPeriodColumn(
        string $cachePrefix,
        string $modelClass,
        string $column = 'semester_period'
    ): Collection {
        return Cache::remember(
            AppCache::lookupCacheKey($cachePrefix),
            now()->addSeconds(60),
            function () use ($modelClass, $column): Collection {
                return $this->resolveSemesterBookService()->buildSemesterOptionCollection(
                    $modelClass::query()
                        ->whereNotNull($column)
                        ->where($column, '!=', '')
                        ->distinct()
                        ->pluck($column)
                        ->merge($this->resolveSemesterBookService()->configuredSemesterOptions()),
                    false,
                    true
                );
            }
        );
    }

    /**
     * @param  class-string  $modelClass
     */
    protected function cachedSemesterOptionsFromDateColumn(
        string $cachePrefix,
        string $modelClass,
        string $dateColumn
    ): Collection {
        return Cache::remember(
            AppCache::lookupCacheKey($cachePrefix),
            now()->addSeconds(60),
            function () use ($modelClass, $dateColumn): Collection {
                return $this->resolveSemesterBookService()->buildSemesterOptionCollection(
                    $modelClass::query()
                        ->whereNotNull($dateColumn)
                        ->orderByDesc($dateColumn)
                        ->pluck($dateColumn)
                        ->map(fn($date): string => $this->semesterPeriodFromDate($date))
                        ->merge($this->resolveSemesterBookService()->configuredSemesterOptions()),
                    false,
                    true
                );
            }
        );
    }

    protected function semesterOptionsForIndex(Collection $baseOptions, bool $isAdminUser): Collection
    {
        return $isAdminUser
            ? $baseOptions->values()
            : $this->resolveSemesterBookService()->buildSemesterOptionCollection($baseOptions->all(), true, false);
    }

    protected function semesterOptionsForForm(Collection $baseOptions): Collection
    {
        return $this->resolveSemesterBookService()->buildSemesterOptionCollection($baseOptions->all(), true, true);
    }

    protected function selectedSemesterIfAvailable(?string $selectedSemester, Collection $semesterOptions): ?string
    {
        if ($selectedSemester === null || $selectedSemester === '') {
            return null;
        }

        return $semesterOptions->contains($selectedSemester) ? $selectedSemester : null;
    }

    protected function normalizedSemesterInput(string $semester): ?string
    {
        $value = trim($semester);
        if ($value === '') {
            return null;
        }

        return $this->resolveSemesterBookService()->normalizeSemester($value);
    }

    private function resolveSemesterBookService(): SemesterBookService
    {
        return app(SemesterBookService::class);
    }
}
