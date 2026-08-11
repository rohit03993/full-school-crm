<?php

namespace App\Support;

class MetaWhatsAppTemplateVariableHelper
{
    /** @var array<int, string> */
    private const INDEX_LABELS = [
        1 => 'Student name',
        2 => 'Roll number',
        3 => 'Time',
        4 => 'Date',
        5 => 'Batch / class',
        6 => 'Institute name',
    ];

    /** @var array<int, string> */
    private const HOMEWORK_SHARE_LABELS = [
        1 => 'Student name',
        2 => 'Roll number',
        3 => 'Homework title',
        4 => 'Public homework link',
    ];

    /** @var array<int, string> */
    private const INDEX_SAMPLES = [
        1 => 'Rohit Sharma',
        2 => '12-A-042',
        3 => '9:15 AM',
        4 => '20 Jun 2026',
        5 => 'Class 12-A',
        6 => 'Your Institute',
    ];

    /** @var array<int, string> */
    private const HOMEWORK_SHARE_SAMPLES = [
        1 => 'Rohit Sharma',
        2 => '12-A-042',
        3 => 'Chapter 5 exercises',
        4 => 'https://example.com/h/samplePublicHomeworkToken123456',
    ];

    public static function labelForIndex(int $index): string
    {
        return self::INDEX_LABELS[$index] ?? 'Variable {{'.$index.'}}';
    }

    public static function defaultSampleForIndex(int $index): string
    {
        return self::INDEX_SAMPLES[$index] ?? 'Sample '.$index;
    }

    /**
     * @param  list<array<string, mixed>>  $existingRows
     * @return list<array{index: int, label: string, example: string}>
     */
    public static function syncRowsFromBody(string $bodyText, array $existingRows = [], ?string $templateName = null): array
    {
        $order = MetaWhatsAppTemplateBuilder::positionalPlaceholderOrder($bodyText);

        $existingByIndex = collect($existingRows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->keyBy(fn (array $row): int => (int) ($row['index'] ?? 0));

        $presetVariables = [];
        $isHomeworkShare = self::looksLikeHomeworkShareTemplate((string) $templateName, $bodyText);

        if (FeeReminderWhatsAppTemplate::looksLikeName((string) $templateName)
            || str_contains(strtolower($bodyText), 'fee reminder')) {
            $presetVariables = FeeReminderWhatsAppTemplate::variables();
        } elseif (HomeworkNotDoneWhatsAppTemplate::looksLikeName((string) $templateName)
            || str_contains(strtolower($bodyText), 'has not completed the homework')) {
            $presetVariables = HomeworkNotDoneWhatsAppTemplate::variables();
        }

        $rows = [];

        foreach ($order as $index) {
            $previous = $existingByIndex->get($index);
            $preset = $presetVariables[$index] ?? null;
            $defaultLabel = $isHomeworkShare
                ? (self::HOMEWORK_SHARE_LABELS[$index] ?? self::labelForIndex($index))
                : self::labelForIndex($index);
            $defaultExample = $isHomeworkShare
                ? (self::HOMEWORK_SHARE_SAMPLES[$index] ?? self::defaultSampleForIndex($index))
                : self::defaultSampleForIndex($index);

            $rows[] = [
                'index' => $index,
                'label' => $preset['label'] ?? $defaultLabel,
                'example' => filled($previous['example'] ?? null)
                    ? trim((string) $previous['example'])
                    : ($preset['example'] ?? $defaultExample),
            ];
        }

        return $rows;
    }

    public static function looksLikeHomeworkShareTemplate(string $templateName, string $bodyText): bool
    {
        $name = strtolower(trim($templateName));
        $body = strtolower($bodyText);

        if ($name !== '' && (str_starts_with($name, 'homework_') || str_contains($name, 'homework_update') || str_contains($name, 'homework_api'))) {
            return ! str_contains($body, 'has not completed the homework');
        }

        return str_contains($body, 'open homework')
            || (str_contains($body, 'homework for') && str_contains($body, 'title:'));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    public static function rowsToExamplesList(array $rows): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->sortBy(fn (array $row): int => (int) ($row['index'] ?? 0))
            ->pluck('example')
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function rowsToExamplesCsv(array $rows): string
    {
        return collect($rows)
            ->sortBy('index')
            ->pluck('example')
            ->map(fn (mixed $value): string => trim((string) $value))
            ->implode(', ');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function previewBody(string $bodyText, array $rows): string
    {
        $preview = $bodyText;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $index = (int) ($row['index'] ?? 0);
            $example = trim((string) ($row['example'] ?? ''));

            if ($index < 1 || $example === '') {
                continue;
            }

            $preview = preg_replace(
                '/\{\{\s*'.$index.'\s*\}\}/',
                $example,
                $preview,
            ) ?? $preview;
        }

        return $preview;
    }

    public static function variableCount(string $bodyText): int
    {
        return count(MetaWhatsAppTemplateBuilder::positionalPlaceholderOrder($bodyText));
    }
}
