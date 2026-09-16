<?php

namespace App\Support;

class ExamSubjectCatalog
{
    /**
     * Suggested names for common Excel headers. Totals are per test, not locked here.
     *
     * @return array<string, array{name: string, default_max: int}>
     */
    public static function subjects(): array
    {
        return [
            'physics' => ['name' => 'Physics', 'default_max' => 100],
            'chemistry' => ['name' => 'Chemistry', 'default_max' => 100],
            'maths' => ['name' => 'Maths', 'default_max' => 100],
            'biology' => ['name' => 'Biology', 'default_max' => 100],
            'english' => ['name' => 'English', 'default_max' => 100],
            'botany' => ['name' => 'Botany', 'default_max' => 100],
            'zoology' => ['name' => 'Zoology', 'default_max' => 100],
            'physical_education' => ['name' => 'Physical Education', 'default_max' => 100],
        ];
    }

    /**
     * @return array<string, string> alias => canonical key
     */
    public static function aliases(): array
    {
        return [
            'p' => 'physics',
            'phy' => 'physics',
            'physics' => 'physics',
            'c' => 'chemistry',
            'chem' => 'chemistry',
            'chemistry' => 'chemistry',
            'm' => 'maths',
            'math' => 'maths',
            'maths' => 'maths',
            'mathematics' => 'maths',
            'b' => 'biology',
            'bio' => 'biology',
            'biology' => 'biology',
            'e' => 'english',
            'eng' => 'english',
            'english' => 'english',
            'bot' => 'botany',
            'botany' => 'botany',
            'z' => 'zoology',
            'zoo' => 'zoology',
            'zoology' => 'zoology',
            'pe' => 'physical_education',
            'physed' => 'physical_education',
            'physicaleducation' => 'physical_education',
        ];
    }

    public static function resolveLabel(?string $header): string
    {
        $header = trim((string) $header);

        if ($header === '') {
            return 'Subject';
        }

        return self::canonicalDisplayName($header);
    }

    public static function canonicalDisplayName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'Subject';
        }

        $key = self::aliases()[self::normalizeKey($name)] ?? null;

        if ($key !== null) {
            return self::subjects()[$key]['name'];
        }

        return $name;
    }

    /**
     * Names that should be treated as the same subject when matching stored sheets.
     *
     * @return list<string>
     */
    public static function matchingStoredNames(string $name): array
    {
        $canonical = self::canonicalDisplayName($name);
        $key = self::aliases()[self::normalizeKey($name)]
            ?? self::aliases()[self::normalizeKey($canonical)]
            ?? null;

        $names = [$name, $canonical];

        if ($key !== null) {
            $names[] = self::subjects()[$key]['name'];

            foreach (self::aliases() as $alias => $aliasKey) {
                if ($aliasKey !== $key) {
                    continue;
                }

                $names[] = $alias;
                $names[] = ucfirst($alias);
            }
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (string $value): string => trim($value), $names),
            static fn (string $value): bool => $value !== '',
        )));
    }

    public static function defaultMaxForHeader(?string $header, float $fallback = 100): float
    {
        return $fallback;
    }

    /**
     * @return array<int, float> column index => default max
     *
     * @param  list<string|null>  $headers
     * @param  list<int>  $subjectColumns
     */
    public static function defaultMaxMarksForColumns(array $headers, array $subjectColumns, float $fallback = 100): array
    {
        $defaults = [];

        foreach ($subjectColumns as $columnIndex) {
            $defaults[$columnIndex] = self::defaultMaxForHeader($headers[$columnIndex] ?? null, $fallback);
        }

        return $defaults;
    }

    protected static function normalizeKey(string $header): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($header)) ?? trim($header));
    }
}
