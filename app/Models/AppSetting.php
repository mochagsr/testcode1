<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AppSetting extends Model
{
    private const CACHE_KEY = 'app_settings.key_value_map';

    private static ?bool $tableExists = null;

    /**
     * @var array<string, string|null>|null
     */
    private static ?array $runtimeKeyValueMap = null;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        try {
            if (! static::tableExists()) {
                return $default;
            }

            $value = static::normalizedValue($key, static::keyValueMap()[$key] ?? null);

            return $value ?? $default;
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * @param  array<string, string|null>  $defaults
     * @return array<string, string|null>
     */
    public static function getValues(array $defaults): array
    {
        try {
            if (! static::tableExists()) {
                return $defaults;
            }

            $map = static::keyValueMap();
            $result = [];
            foreach ($defaults as $key => $default) {
                $result[$key] = static::normalizedValue((string) $key, $map[$key] ?? null) ?? $default;
            }

            return $result;
        } catch (Throwable) {
            return $defaults;
        }
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::setValues([$key => $value]);
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function setValues(array $values): void
    {
        try {
            if (! static::tableExists() || $values === []) {
                return;
            }

            $timestamp = now();
            $rows = [];
            foreach ($values as $key => $value) {
                $normalizedKey = (string) $key;
                if ($normalizedKey === '') {
                    continue;
                }

                $rows[] = [
                    'key' => $normalizedKey,
                    'value' => $value,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            if ($rows === []) {
                return;
            }

            DB::table((new static)->getTable())
                ->upsert($rows, ['key'], ['value', 'updated_at']);

            Cache::forget(static::CACHE_KEY);
            static::$runtimeKeyValueMap = null;
        } catch (Throwable) {
            // Ignore setting persistence errors so core transactions remain usable.
        }
    }

    private static function tableExists(): bool
    {
        if (static::$tableExists !== null) {
            return static::$tableExists;
        }

        static::$tableExists = Schema::hasTable((new static)->getTable());

        return static::$tableExists;
    }

    /**
     * @return array<string, string|null>
     */
    private static function keyValueMap(): array
    {
        if (static::$runtimeKeyValueMap !== null) {
            return static::$runtimeKeyValueMap;
        }

        /** @var array<string, string|null> $raw */
        $raw = Cache::rememberForever(static::CACHE_KEY, static function (): array {
            return static::query()
                ->select(['key', 'value'])
                ->get()
                ->pluck('value', 'key')
                ->all();
        });

        static::$runtimeKeyValueMap = $raw;

        return static::$runtimeKeyValueMap;
    }

    private static function normalizedValue(string $key, ?string $value): ?string
    {
        if ($key !== 'company_logo_path') {
            return $value;
        }

        $normalized = trim((string) $value);

        if ($normalized !== '' && static::logoPathExists($normalized)) {
            return $normalized;
        }

        return static::discoverCompanyLogoPath();
    }

    private static function logoPathExists(string $path): bool
    {
        try {
            return Storage::disk('public')->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    private static function discoverCompanyLogoPath(): ?string
    {
        try {
            $files = collect(Storage::disk('public')->files('company'))
                ->filter(function (string $path): bool {
                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                    return in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true);
                })
                ->sortByDesc(function (string $path): int {
                    try {
                        return (int) Storage::disk('public')->lastModified($path);
                    } catch (Throwable) {
                        return 0;
                    }
                })
                ->values();

            $candidate = $files->first();

            return is_string($candidate) && $candidate !== '' ? $candidate : null;
        } catch (Throwable) {
            return null;
        }
    }
}
