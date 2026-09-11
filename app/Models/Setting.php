<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Request-scoped cache of key => value pairs. */
    protected static ?array $bag = null;

    protected const CACHE_KEY = 'app_settings_bag';
    protected const CACHE_TTL = 300; // 5 minutes

    protected static function loadBag(): array
    {
        if (static::$bag !== null) {
            return static::$bag;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return static::$bag = [];
            }

            static::$bag = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                return static::query()->pluck('value', 'key')->all();
            });
        } catch (\Throwable) {
            static::$bag = static::query()->pluck('value', 'key')->all();
        }

        return static::$bag ?? [];
    }

    public static function get(string $key, $default = null)
    {
        return static::loadBag()[$key] ?? $default;
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::forgetCache();
    }

    public static function forgetCache(): void
    {
        static::$bag = null;
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
        }
    }
}

