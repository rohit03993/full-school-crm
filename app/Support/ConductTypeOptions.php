<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

class ConductTypeOptions
{
    public const SETTING_KEY = 'crm.conduct_types';

    /**
     * @return list<array{value: string, label: string, is_active: bool}>
     */
    public static function defaults(): array
    {
        return [
            ['value' => 'creating_noise', 'label' => 'Creating noise', 'is_active' => true],
            ['value' => 'indiscipline', 'label' => 'Indiscipline', 'is_active' => true],
            ['value' => 'phone_in_classroom', 'label' => 'Using phone in classroom', 'is_active' => true],
            ['value' => 'fighting', 'label' => 'Fighting', 'is_active' => true],
        ];
    }

    /**
     * @return list<array{value: string, label: string, is_active: bool}>
     */
    public static function all(): array
    {
        $stored = Setting::getValue(self::SETTING_KEY);

        if (! is_array($stored) || $stored === []) {
            return self::defaults();
        }

        return self::normalizeList($stored);
    }

    /**
     * @return list<array{value: string, label: string, is_active: bool}>
     */
    public static function active(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (array $item): bool => $item['is_active'],
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function formOptions(): array
    {
        return collect(self::active())
            ->mapWithKeys(fn (array $item): array => [$item['value'] => $item['label']])
            ->all();
    }

    public static function label(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        foreach (self::all() as $item) {
            if ($item['value'] === $value) {
                return $item['label'];
            }
        }

        return Str::headline(str_replace('_', ' ', (string) $value));
    }

    /**
     * @param  list<mixed>  $keys
     */
    public static function labelsFor(array $keys): string
    {
        $labels = [];

        foreach ($keys as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $labels[] = self::label($key);
        }

        return implode(', ', $labels);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function save(array $items): void
    {
        Setting::setValue(self::SETTING_KEY, self::normalizeList($items), 'crm');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{value: string, label: string, is_active: bool}>
     */
    protected static function normalizeList(array $items): array
    {
        $result = [];
        $usedValues = [];

        foreach ($items as $index => $item) {
            $label = trim((string) ($item['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $value = trim((string) ($item['value'] ?? ''));

            if ($value === '') {
                $value = Str::slug($label, '_');
            }

            $value = Str::lower(preg_replace('/[^a-z0-9_]+/', '_', $value) ?? $value);
            $value = trim($value, '_');

            if ($value === '') {
                $value = 'type_'.($index + 1);
            }

            while (in_array($value, $usedValues, true)) {
                $value .= '_'.(count($usedValues) + 1);
            }

            $usedValues[] = $value;

            $result[] = [
                'value' => $value,
                'label' => $label,
                'is_active' => (bool) ($item['is_active'] ?? true),
            ];
        }

        if ($result === []) {
            return self::defaults();
        }

        return $result;
    }
}
