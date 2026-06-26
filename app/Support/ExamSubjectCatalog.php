<?php

namespace App\Support;

class ExamSubjectCatalog
{
    /**
     * @return array<string, array{name: string, default_max: int}>
     */
    public static function subjects(): array
    {
        return [
            'physics' => ['name' => 'Physics', 'default_max' => 350],
            'chemistry' => ['name' => 'Chemistry', 'default_max' => 200],
            'mathematics' => ['name' => 'Mathematics', 'default_max' => 500],
            'maths' => ['name' => 'Mathematics', 'default_max' => 500],
            'biology' => ['name' => 'Biology', 'default_max' => 200],
            'english' => ['name' => 'English', 'default_max' => 100],
            'botany' => ['name' => 'Botany', 'default_max' => 200],
            'zoology' => ['name' => 'Zoology', 'default_max' => 200],
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
            'm' => 'mathematics',
            'math' => 'mathematics',
            'maths' => 'mathematics',
            'mathematics' => 'mathematics',
            'b' => 'biology',
            'bio' => 'biology',
            'biology' => 'biology',
            'e' => 'english',
            'eng' => 'english',
            'english' => 'english',
            'bot' => 'botany',
            'botany' => 'botany',
            'zoo' => 'zoology',
            'zoology' => 'zoology',
        ];
    }

    public static function resolveLabel(?string $header): string
    {
        $header = trim((string) $header);

        if ($header === '') {
            return 'Subject';
        }

        $key = self::aliases()[self::normalizeKey($header)] ?? null;

        if ($key !== null) {
            return self::subjects()[$key]['name'];
        }

        return $header;
    }

    public static function defaultMaxForHeader(?string $header, float $fallback = 100): float
    {
        $key = self::aliases()[self::normalizeKey($header)] ?? null;

        if ($key === null) {
            return $fallback;
        }

        return (float) self::subjects()[$key]['default_max'];
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
