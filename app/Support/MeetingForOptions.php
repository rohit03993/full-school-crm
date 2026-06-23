<?php

namespace App\Support;

use App\Enums\MeetingFor;
use App\Models\Setting;
use Illuminate\Support\Str;

class MeetingForOptions
{
    public const SETTING_KEY = 'crm.meeting_for_options';

    /**
     * @return list<array{value: string, label: string, is_active: bool, is_default: bool}>
     */
    public static function defaults(): array
    {
        $items = [];
        $first = true;

        foreach (MeetingFor::formCases() as $case) {
            $items[] = [
                'value' => $case->value,
                'label' => $case->label(),
                'is_active' => true,
                'is_default' => $first,
            ];
            $first = false;
        }

        return $items;
    }

    /**
     * @return list<array{value: string, label: string, is_active: bool, is_default: bool}>
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
     * @return list<array{value: string, label: string, is_active: bool, is_default: bool}>
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

    public static function defaultValue(): string
    {
        foreach (self::all() as $item) {
            if ($item['is_default'] && $item['is_active']) {
                return $item['value'];
            }
        }

        $active = self::active();

        return $active[0]['value'] ?? MeetingFor::Enquiry->value;
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

        return MeetingFor::tryFrom($value)?->label()
            ?? Str::headline(str_replace('_', ' ', (string) $value));
    }

    public static function resolve(?string $value): string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value !== '' && self::isValid($value)) {
            return $value;
        }

        return self::defaultValue();
    }

    public static function isValid(?string $value): bool
    {
        if (blank($value)) {
            return false;
        }

        foreach (self::active() as $item) {
            if ($item['value'] === $value) {
                return true;
            }
        }

        return MeetingFor::tryFrom((string) $value) !== null;
    }

    /**
     * @return array{bg: string, text: string, ring: string, icon: string}
     */
    public static function badgeStyle(?string $value): array
    {
        $enum = MeetingFor::tryFrom((string) $value);

        if ($enum) {
            return array_merge($enum->badgeColors(), ['icon' => $enum->icon()]);
        }

        return [
            'bg' => 'bg-gray-500/20',
            'text' => 'text-gray-900 dark:text-gray-300',
            'ring' => 'ring-gray-500/35',
            'icon' => 'heroicon-m-tag',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function save(array $items): void
    {
        $normalized = self::normalizeList($items);

        if ($normalized === []) {
            Setting::query()->where('key', self::SETTING_KEY)->delete();

            return;
        }

        Setting::setValue(self::SETTING_KEY, $normalized, 'crm');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{value: string, label: string, is_active: bool, is_default: bool}>
     */
    protected static function normalizeList(array $items): array
    {
        $result = [];
        $usedValues = [];
        $defaultIndex = null;

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
                $value = 'option_'.($index + 1);
            }

            while (in_array($value, $usedValues, true)) {
                $value .= '_'.(count($usedValues) + 1);
            }

            $usedValues[] = $value;

            if ((bool) ($item['is_default'] ?? false)) {
                $defaultIndex = count($result);
            }

            $result[] = [
                'value' => $value,
                'label' => $label,
                'is_active' => (bool) ($item['is_active'] ?? true),
                'is_default' => false,
            ];
        }

        if ($result === []) {
            return self::defaults();
        }

        if ($defaultIndex === null) {
            $defaultIndex = 0;
        }

        foreach ($result as $i => &$row) {
            $row['is_default'] = $i === $defaultIndex;
        }

        unset($row);

        return $result;
    }
}
