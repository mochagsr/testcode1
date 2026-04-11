<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\ProductUnit;

trait ResolvesProductUnits
{
    /**
     * @var array<int, array{code:string,label:string}>|null
     */
    private ?array $productUnitOptionsCache = null;

    /**
     * @return array<int, array{code:string,label:string}>
     */
    protected function configuredProductUnitOptions(): array
    {
        if (is_array($this->productUnitOptionsCache)) {
            return $this->productUnitOptionsCache;
        }

        $this->productUnitOptionsCache = ProductUnit::optionRows();

        return $this->productUnitOptionsCache;
    }

    /**
     * @return array<int, string>
     */
    protected function configuredProductUnitCodes(): array
    {
        return collect($this->configuredProductUnitOptions())
            ->pluck('code')
            ->filter(fn(string $code): bool => $code !== '')
            ->values()
            ->all();
    }

    protected function defaultProductUnitCode(): string
    {
        return ProductUnit::defaultCode();
    }

    protected function normalizeProductUnitInput(mixed $unit): string
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', (string) $unit));

        return $normalized !== '' ? $normalized : $this->defaultProductUnitCode();
    }
}
