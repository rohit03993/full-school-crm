<?php

namespace App\Services;

use App\Models\CustomFieldDefinition;
use Illuminate\Support\Str;

class CustomFieldService
{
    public const ENTITY_STUDENT = 'student';

    public const ENTITY_ENQUIRY = 'enquiry';

    /**
     * @return list<CustomFieldDefinition>
     */
    public function activeDefinitions(string $entity): array
    {
        return CustomFieldDefinition::query()
            ->where('entity', $entity)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncDefinitions(string $entity, array $rows): void
    {
        $keptIds = [];

        foreach ($rows as $index => $row) {
            $label = trim((string) ($row['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $fieldKey = filled($row['field_key'] ?? null)
                ? Str::slug((string) $row['field_key'], '_')
                : Str::slug($label, '_');

            if ($fieldKey === '') {
                continue;
            }

            $definition = CustomFieldDefinition::query()->updateOrCreate(
                [
                    'entity' => $entity,
                    'field_key' => $fieldKey,
                ],
                [
                    'label' => $label,
                    'field_type' => (string) ($row['field_type'] ?? 'text'),
                    'options' => $this->normalizeOptions($row['options'] ?? []),
                    'is_required' => (bool) ($row['is_required'] ?? false),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'sort_order' => (int) $index,
                ],
            );

            $keptIds[] = $definition->id;
        }

        CustomFieldDefinition::query()
            ->where('entity', $entity)
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->delete();
    }

    /**
     * @param  array<string, mixed>|null  $customData
     * @return array<string, mixed>
     */
    public function validateForEntity(string $entity, ?array $customData): array
    {
        $customData ??= [];
        $validated = [];

        foreach ($this->activeDefinitions($entity) as $definition) {
            $value = $customData[$definition->field_key] ?? null;

            if ($definition->is_required && blank($value)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'custom_data.'.$definition->field_key => $definition->label.' is required.',
                ]);
            }

            if (filled($value)) {
                $validated[$definition->field_key] = is_string($value) ? trim($value) : $value;
            }
        }

        return $validated;
    }

    /**
     * @param  mixed  $options
     * @return list<string>|null
     */
    protected function normalizeOptions(mixed $options): ?array
    {
        if (! is_array($options)) {
            return null;
        }

        $values = collect($options)
            ->map(fn (mixed $option): string => trim(is_array($option) ? (string) ($option['value'] ?? $option['label'] ?? '') : (string) $option))
            ->filter()
            ->values()
            ->all();

        return $values === [] ? null : $values;
    }
}
