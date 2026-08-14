<?php

namespace App\Support;

class MetaWhatsAppTemplateVariableHelper
{
    public static function labelForIndex(int $index): string
    {
        return 'Variable '.$index;
    }

    public static function defaultSampleForIndex(int $index): string
    {
        return 'Sample '.$index;
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

        $presetVariables = self::resolvePresetVariables((string) $templateName, $bodyText);

        $rows = [];

        foreach ($order as $index) {
            $previous = $existingByIndex->get($index);
            $preset = $presetVariables[$index] ?? null;

            $rows[] = [
                'index' => $index,
                'label' => $preset['label'] ?? self::labelForIndex($index),
                'example' => filled($previous['example'] ?? null)
                    ? trim((string) $previous['example'])
                    : ($preset['example'] ?? self::defaultSampleForIndex($index)),
            ];
        }

        return $rows;
    }

    /**
     * Known CRM product templates only — custom / AiSensy-style bodies stay generic.
     *
     * @return array<int, array{label: string, example: string}>
     */
    public static function resolvePresetVariables(string $templateName, string $bodyText): array
    {
        $name = MetaWhatsAppTemplateBuilder::normalizeName($templateName);
        $body = strtolower($bodyText);

        if (FeeReminderWhatsAppTemplate::looksLikeName($name)
            || str_contains($body, 'this is a fee reminder')) {
            return FeeReminderWhatsAppTemplate::variables();
        }

        if (HomeworkNotDoneWhatsAppTemplate::looksLikeName($name)
            || str_contains($body, 'has not completed the homework')) {
            return HomeworkNotDoneWhatsAppTemplate::variables();
        }

        if (CombinedHomeworkWhatsAppTemplate::looksLikeName($name)) {
            return CombinedHomeworkWhatsAppTemplate::variables();
        }

        if (HomeworkShareWhatsAppTemplate::looksLikeName($name)) {
            return HomeworkShareWhatsAppTemplate::variables();
        }

        if (StaffPunchWhatsAppTemplate::looksLikeName($name)
            || str_contains($body, 'your staff attendance check-in has been recorded')
            || str_contains($body, 'your staff attendance check-out has been recorded')
            || str_contains($body, 'you have checked in at')
            || str_contains($body, 'you have checked out at')) {
            return StaffPunchWhatsAppTemplate::variables();
        }

        if (LoginOtpWhatsAppTemplate::looksLikeName($name)
            || str_contains($body, 'your login code is')) {
            return LoginOtpWhatsAppTemplate::variables();
        }

        // Do not infer homework-share from body text alone — too many false positives.
        return [];
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
