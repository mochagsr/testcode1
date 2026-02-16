<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\AppSetting;

trait ResolvesProductUnits
{
    /**
     * @var array{product_unit_options:string|null,product_default_unit:string|null}|null
     */
    private ?array $productUnitSettingsCache = null;

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

        $settings = $this->productUnitSettings();
        $raw = (string) ($settings['product_unit_options'] ?? 'exp|Exemplar');
        $options = collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn(string $item): string => trim($item))
            ->filter(fn(string $item): bool => $item !== '')
            ->map(function (string $item): array {
                [$code, $label] = array_pad(array_map('trim', explode('|', $item, 2)), 2, '');
                $normalizedCode = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', $code));
                $normalizedLabel = $label !== '' ? $label : ucfirst($normalizedCode);

                return [
                    'code' => $normalizedCode,
                    'label' => $normalizedLabel,
                ];
            })
            ->filter(fn(array $item): bool => $item['code'] !== '')
            ->unique('code')
            ->values();

        $withoutExp = $options->filter(fn(array $item): bool => $item['code'] !== 'exp')->values();
        $this->productUnitOptionsCache = collect([[
            'code' => 'exp',
            'label' => 'Exemplar',
        ]])->merge($withoutExp)->values()->all();

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
        $settings = $this->productUnitSettings();
        $default = strtolower((string) ($settings['product_default_unit'] ?? 'exp'));

        return $default !== '' ? $default : 'exp';
    }

    protected function normalizeProductUnitInput(mixed $unit): string
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9\-]/', '', (string) $unit));

        return $normalized !== '' ? $normalized : $this->defaultProductUnitCode();
    }

    /**
     * @return array{product_unit_options:string|null,product_default_unit:string|null}
     */
    private function productUnitSettings(): array
    {
        if (is_array($this->productUnitSettingsCache)) {
            return $this->productUnitSettingsCache;
        }

        $this->productUnitSettingsCache = AppSetting::getValues([
            'product_unit_options' => 'exp|Exemplar',
            'product_default_unit' => 'exp',
        ]);

        return $this->productUnitSettingsCache;
    }
}

