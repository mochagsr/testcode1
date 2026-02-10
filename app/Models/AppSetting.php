<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        try {
            if (! Schema::hasTable((new static())->getTable())) {
                return $default;
            }

            return static::query()
                ->where('key', $key)
                ->value('value') ?? $default;
        } catch (Throwable) {
            return $default;
        }
    }

    public static function setValue(string $key, ?string $value): void
    {
        try {
            if (! Schema::hasTable((new static())->getTable())) {
                return;
            }

            static::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        } catch (Throwable) {
            // Ignore setting persistence errors so core transactions remain usable.
        }
    }
}
