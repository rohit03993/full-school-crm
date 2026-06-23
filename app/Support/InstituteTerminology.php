<?php

namespace App\Support;

use App\Models\Setting;

class InstituteTerminology
{
    /** @var array<string, string> */
    public const KEYS = [
        'course' => 'Course / programme label',
        'batch' => 'Batch / section label',
        'roll_number' => 'Student ID / roll number label',
        'programmes_heading' => 'Public site programmes section title',
    ];

    public static function label(string $key): string
    {
        $stored = Setting::getValue('crm.label.'.$key);

        if (is_string($stored) && filled(trim($stored))) {
            return trim($stored);
        }

        return self::defaultLabel($key);
    }

    public static function defaultLabel(string $key): string
    {
        return match ($key) {
            'course' => 'Programme',
            'batch' => 'Batch / Section',
            'roll_number' => 'Roll No.',
            'programmes_heading' => 'Our Programmes',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return collect(array_keys(self::KEYS))
            ->mapWithKeys(fn (string $key): array => [$key => self::defaultLabel($key)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $labels = [];

        foreach (array_keys(self::KEYS) as $key) {
            $labels[$key] = self::label($key);
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $labels
     */
    public static function save(array $labels): void
    {
        foreach (array_keys(self::KEYS) as $key) {
            $value = trim((string) ($labels[$key] ?? ''));

            if ($value === '') {
                Setting::query()->where('key', 'crm.label.'.$key)->delete();

                continue;
            }

            Setting::setValue('crm.label.'.$key, $value, 'crm');
        }
    }
}
