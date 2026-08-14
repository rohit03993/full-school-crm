<?php

namespace App\Support;

/**
 * Common school/coaching subjects for one-click programme setup.
 * Used as a checklist that fills the course_subjects repeater — not a separate DB table.
 */
final class CommonCourseSubjects
{
    /**
     * @return array<string, array{name: string, code: string, default_max_marks: int}>
     */
    public static function presets(): array
    {
        return [
            'maths' => ['name' => 'Maths', 'code' => 'MATH', 'default_max_marks' => 100],
            'physics' => ['name' => 'Physics', 'code' => 'PHY', 'default_max_marks' => 100],
            'chemistry' => ['name' => 'Chemistry', 'code' => 'CHEM', 'default_max_marks' => 100],
            'biology' => ['name' => 'Biology', 'code' => 'BIO', 'default_max_marks' => 100],
            'english' => ['name' => 'English', 'code' => 'ENG', 'default_max_marks' => 100],
            'hindi' => ['name' => 'Hindi', 'code' => 'HIN', 'default_max_marks' => 100],
            'computer_science' => ['name' => 'Computer Science', 'code' => 'CS', 'default_max_marks' => 100],
            'accountancy' => ['name' => 'Accountancy', 'code' => 'ACC', 'default_max_marks' => 100],
            'economics' => ['name' => 'Economics', 'code' => 'ECO', 'default_max_marks' => 100],
            'social_science' => ['name' => 'Social Science', 'code' => 'SST', 'default_max_marks' => 100],
            'sanskrit' => ['name' => 'Sanskrit', 'code' => 'SAN', 'default_max_marks' => 100],
        ];
    }

    /**
     * @return array<string, string> key => label
     */
    public static function options(): array
    {
        return collect(self::presets())
            ->mapWithKeys(fn (array $preset, string $key): array => [
                $key => $preset['name'],
            ])
            ->all();
    }

    /**
     * Keys that match existing subject rows (by name, case-insensitive).
     * Also treats “Mathematics” as Maths for older programmes.
     *
     * @param  array<int, array{name?: mixed}>  $rows
     * @return list<string>
     */
    public static function keysMatchingRows(array $rows): array
    {
        $names = collect($rows)
            ->map(fn (array $row): string => mb_strtolower(trim((string) ($row['name'] ?? ''))))
            ->filter()
            ->all();

        $matched = [];

        foreach (self::presets() as $key => $preset) {
            $presetName = mb_strtolower($preset['name']);
            $aliases = self::nameAliases($key);

            foreach ($names as $name) {
                if ($name === $presetName || in_array($name, $aliases, true)) {
                    $matched[] = $key;
                    break;
                }
            }
        }

        return $matched;
    }

    /**
     * Merge selected presets into the subject repeater.
     * Keeps custom (non-preset) rows. Selected presets are added/updated; unselected presets are removed.
     *
     * @param  list<string>|null  $selectedKeys
     * @param  array<int, array{name?: mixed, code?: mixed, default_max_marks?: mixed, is_active?: mixed}>  $existingRows
     * @return list<array{name: string, code: ?string, default_max_marks: ?int, is_active: bool}>
     */
    public static function mergeIntoRows(?array $selectedKeys, array $existingRows): array
    {
        $selectedKeys = array_values(array_unique(array_filter(
            array_map(fn ($key): string => (string) $key, $selectedKeys ?? []),
            fn (string $key): bool => isset(self::presets()[$key]),
        )));

        $customRows = [];

        foreach ($existingRows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            if (self::keyForName($name) !== null) {
                continue;
            }

            $customRows[] = [
                'name' => $name,
                'code' => filled($row['code'] ?? null) ? strtoupper(trim((string) $row['code'])) : null,
                'default_max_marks' => filled($row['default_max_marks'] ?? null)
                    ? max(1, (int) $row['default_max_marks'])
                    : null,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];
        }

        $presetRows = [];

        foreach ($selectedKeys as $key) {
            $preset = self::presets()[$key];
            $existing = self::findExistingPresetRow($existingRows, $key);

            $presetRows[] = [
                'name' => $preset['name'],
                'code' => filled($existing['code'] ?? null)
                    ? strtoupper(trim((string) $existing['code']))
                    : $preset['code'],
                'default_max_marks' => filled($existing['default_max_marks'] ?? null)
                    ? max(1, (int) $existing['default_max_marks'])
                    : $preset['default_max_marks'],
                'is_active' => array_key_exists('is_active', $existing)
                    ? (bool) $existing['is_active']
                    : true,
            ];
        }

        return array_values([...$presetRows, ...$customRows]);
    }

    /**
     * Section form variant: preserve the selected teacher and catalogue id while the common
     * checklist rebuilds the visible subject rows.
     *
     * @param  list<string>|null  $selectedKeys
     * @param  array<int, array<string, mixed>>  $existingRows
     * @return list<array<string, mixed>>
     */
    public static function mergeIntoSectionRows(?array $selectedKeys, array $existingRows): array
    {
        $stateByName = [];

        foreach ($existingRows as $row) {
            $name = mb_strtolower(trim((string) ($row['name'] ?? '')));

            if ($name === '') {
                continue;
            }

            $key = self::keyForName($name);
            $stateByName[$key !== null ? 'preset:'.$key : 'name:'.$name] = [
                'course_subject_id' => $row['course_subject_id'] ?? null,
                'user_id' => $row['user_id'] ?? null,
            ];
        }

        return collect(self::mergeIntoRows($selectedKeys, $existingRows))
            ->map(function (array $row) use ($stateByName): array {
                $key = self::keyForName($row['name']);
                $stateKey = $key !== null
                    ? 'preset:'.$key
                    : 'name:'.mb_strtolower(trim($row['name']));
                $state = $stateByName[$stateKey] ?? [];

                return [
                    ...$row,
                    'course_subject_id' => $state['course_subject_id'] ?? null,
                    'user_id' => $state['user_id'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    public static function keyForName(string $name): ?string
    {
        $normalized = mb_strtolower(trim($name));

        if ($normalized === '') {
            return null;
        }

        foreach (self::presets() as $key => $preset) {
            if ($normalized === mb_strtolower($preset['name']) || in_array($normalized, self::nameAliases($key), true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected static function nameAliases(string $key): array
    {
        return match ($key) {
            'maths' => ['mathematics', 'math'],
            'computer_science' => ['computer', 'computers', 'cs'],
            'social_science' => ['sst', 'social studies'],
            default => [],
        };
    }

    /**
     * @param  array<int, array{name?: mixed, code?: mixed, default_max_marks?: mixed, is_active?: mixed}>  $rows
     * @return array{name?: mixed, code?: mixed, default_max_marks?: mixed, is_active?: mixed}
     */
    protected static function findExistingPresetRow(array $rows, string $key): array
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name !== '' && self::keyForName($name) === $key) {
                return $row;
            }
        }

        return [];
    }
}
