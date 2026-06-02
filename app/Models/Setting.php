<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Cache key holding the full key => value map of settings.
     */
    public const CACHE_KEY = 'app.settings';

    /**
     * All settings as a key => value map, cached until the next write.
     *
     * @return array<string, string|null>
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->pluck('value', 'key')->all();
        });
    }

    /**
     * Read a single setting, falling back to $default when unset or blank.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::map()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    /**
     * Write a single setting and flush the cache so reads stay fresh.
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }
}
