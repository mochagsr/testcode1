<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;
use App\Models\ProductUnit;
use Illuminate\Support\Collection;

/**
 * Centralized configuration service to reduce AppSetting queries
 * and encapsulate settings logic.
 */
final class ConfigurationService
{
    /**
     * Get a setting value with caching.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return AppSetting::getValue($key, $default);
    }

    /**
     * Get multiple setting values with caching.
     *
     * @param  array<string, string|null>  $defaults
     * @return array<string, string|null>
     */
    public static function getMany(array $defaults): array
    {
        return AppSetting::getValues($defaults);
    }

    /**
     * Set a setting value and clear cache.
     *
     * @param  string  $key
     * @param  string|null  $value
     * @return void
     */
    public static function set(string $key, ?string $value): void
    {
        AppSetting::setValue($key, $value);
    }

    /**
     * Get semester period options as collection.
     *
     * @return Collection<int, string>
     */
    public static function semesterPeriodOptions(): Collection
    {
        $raw = (string) self::get('semester_period_options', '');

        return collect(preg_split('/[\r\n,]+/', $raw) ?: [])
            ->map(fn(string $item): string => trim($item))
            ->filter(fn(string $item): bool => $item !== '')
            ->unique();
    }

    /**
     * Get product unit options as collection.
     *
     * @return Collection<int, array{code: string, label: string}>
     */
    public static function productUnitOptions(): Collection
    {
        return collect(ProductUnit::optionRows());
    }

    /**
     * Get outgoing unit options as collection.
     *
     * @return Collection<int, array{code: string, label: string}>
     */
    public static function outgoingUnitOptions(): Collection
    {
        return collect(ProductUnit::optionRows());
    }

    /**
     * Get company information array.
     *
     * @return array<string, string>
     */
    public static function companyInfo(): array
    {
        $settings = self::getMany([
            'company_logo_path' => '',
            'company_name' => 'CV. PUSTAKA GRAFIKA',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'company_notes' => '',
            'company_invoice_notes' => '',
            'company_billing_note' => '',
            'company_transfer_accounts' => '',
        ]);

        return [
            'logo_path' => (string) ($settings['company_logo_path'] ?? ''),
            'name' => (string) ($settings['company_name'] ?? 'CV. PUSTAKA GRAFIKA'),
            'address' => (string) ($settings['company_address'] ?? ''),
            'phone' => (string) ($settings['company_phone'] ?? ''),
            'email' => (string) ($settings['company_email'] ?? ''),
            'notes' => (string) ($settings['company_notes'] ?? ''),
            'invoice_notes' => (string) ($settings['company_invoice_notes'] ?? ''),
            'billing_note' => (string) ($settings['company_billing_note'] ?? ''),
            'transfer_accounts' => (string) ($settings['company_transfer_accounts'] ?? ''),
        ];
    }

    /**
     * Clear all cached settings.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        // AppSetting handles runtime/persistent cache invalidation.
    }
}
