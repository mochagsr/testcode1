<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;

trait ResolvesDateFilters
{
    protected function selectedDateFilter(string $date): ?string
    {
        $value = trim($date);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $parsed !== false && $parsed->format('Y-m-d') === $value
            ? $value
            : null;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    protected function selectedDateRange(?string $date): ?array
    {
        if ($date === null) {
            return null;
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $date);
        if ($parsed === false) {
            return null;
        }

        return [
            $parsed->copy()->startOfDay(),
            $parsed->copy()->endOfDay(),
        ];
    }
}
