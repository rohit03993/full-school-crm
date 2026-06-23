<?php

namespace App\Support;

class ActivityTypePresets
{
    /**
     * Default fields for exams, unit tests, and scored assessments.
     *
     * @return list<array{key: string, label: string, type: string}>
     */
    public static function examMarksFields(): array
    {
        return [
            ['key' => 'subject', 'label' => 'Subject', 'type' => 'text'],
            ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $fieldSchema
     * @return list<array<string, mixed>>
     */
    public static function ensureMarksFields(?array $fieldSchema): array
    {
        $fields = collect($fieldSchema ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->values();

        if ($fields->contains(fn (array $field): bool => ($field['key'] ?? null) === 'max_marks')) {
            return $fields->all();
        }

        foreach (self::examMarksFields() as $presetField) {
            if ($fields->contains(fn (array $field): bool => ($field['key'] ?? null) === $presetField['key'])) {
                continue;
            }

            $fields->push($presetField);
        }

        return $fields->all();
    }

    /**
     * @param  list<array<string, mixed>>|null  $fieldSchema
     * @return list<array<string, mixed>>
     */
    public static function stripMarksFields(?array $fieldSchema): array
    {
        return collect($fieldSchema ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->reject(fn (array $field): bool => in_array($field['key'] ?? null, ['subject', 'max_marks', 'paper'], true))
            ->values()
            ->all();
    }
}
