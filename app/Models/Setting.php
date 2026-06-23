<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** @var array<string, mixed> */
    protected static array $valueCache = [];

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$valueCache)) {
            return static::$valueCache[$key];
        }

        $setting = static::query()->where('key', $key)->first();

        if (! $setting || $setting->value === null || $setting->value === '') {
            return static::$valueCache[$key] = $default;
        }

        $decoded = json_decode($setting->value, true);

        return static::$valueCache[$key] = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? $decoded
            : $setting->value;
    }

    public static function setValue(string $key, mixed $value, string $group = 'general'): void
    {
        unset(static::$valueCache[$key]);

        $stored = is_array($value) ? json_encode($value) : (string) $value;

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'group' => $group],
        );
    }

    public static function flushValueCache(): void
    {
        static::$valueCache = [];
    }
}
