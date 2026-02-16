<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

            $value = static::keyValueMap()[$key] ?? null;

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
                $result[$key] = $map[$key] ?? $default;
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
            $rows = collect($values)
                ->map(function ($value, $key) use ($timestamp): array {
                    return [
                        'key' => (string) $key,
                        'value' => $value,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                })
                ->filter(fn(array $row): bool => $row['key'] !== '')
                ->values()
                ->all();

            if ($rows === []) {
                return;
            }

            DB::table((new static())->getTable())
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

        static::$tableExists = Schema::hasTable((new static())->getTable());

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
}
