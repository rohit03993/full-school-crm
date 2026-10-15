<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** @var array<string, string|null>|null */
    protected static ?array $rawValues = null;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $value = static::rawValues()[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? $decoded
            : $value;
    }

    public static function hasKey(string $key): bool
    {
        return array_key_exists($key, static::rawValues());
    }

    public static function setValue(string $key, mixed $value, string $group = 'general'): void
    {
        $stored = is_array($value) ? json_encode($value) : (string) $value;

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'group' => $group],
        );

        if (static::$rawValues !== null) {
            static::$rawValues[$key] = $stored;
        }
    }

    public static function forgetValue(string ...$keys): void
    {
        static::query()->whereIn('key', $keys)->delete();

        if (static::$rawValues !== null) {
            foreach ($keys as $key) {
                unset(static::$rawValues[$key]);
            }
        }
    }

    public static function flushValueCache(): void
    {
        static::$rawValues = null;
    }

    /**
     * @return array<string, string|null>
     */
    protected static function rawValues(): array
    {
        return static::$rawValues ??= static::query()->pluck('value', 'key')->all();
    }
}
