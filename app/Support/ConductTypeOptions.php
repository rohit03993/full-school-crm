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
            ['value' => 'phone_in_classroom', 'label' => 'Using phone in class', 'is_active' => true],
            ['value' => 'fighting', 'label' => 'Fighting', 'is_active' => true],
            ['value' => 'late_arrival', 'label' => 'Late arrival', 'is_active' => true],
            ['value' => 'left_without_permission', 'label' => 'Left class without permission', 'is_active' => true],
            ['value' => 'bunking', 'label' => 'Bunking class', 'is_active' => true],
            ['value' => 'uniform', 'label' => 'Uniform or dress code', 'is_active' => true],
            ['value' => 'homework_not_done', 'label' => 'Homework or assignment not done', 'is_active' => true],
            ['value' => 'test_not_given', 'label' => 'Missed a test', 'is_active' => true],
            ['value' => 'cheating', 'label' => 'Cheating in a test', 'is_active' => true],
            ['value' => 'misbehaviour_with_staff', 'label' => 'Misbehaviour with teacher or staff', 'is_active' => true],
            ['value' => 'abusive_language', 'label' => 'Abusive language', 'is_active' => true],
            ['value' => 'bullying', 'label' => 'Bullying or ragging', 'is_active' => true],
            ['value' => 'damaging_property', 'label' => 'Damaging property', 'is_active' => true],
            ['value' => 'no_study_material', 'label' => 'Books or material not brought', 'is_active' => true],
            ['value' => 'disturbing_class', 'label' => 'Disturbing the class', 'is_active' => true],
            ['value' => 'other', 'label' => 'Other', 'is_active' => true],
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

        $saved = self::normalizeList($stored);
        $have = array_column($saved, 'value');

        foreach (self::defaults() as $default) {
            if (! in_array($default['value'], $have, true)) {
                $saved[] = $default;
            }
        }

        return $saved;
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
