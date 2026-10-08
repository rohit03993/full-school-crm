<?php

namespace App\Support;

/**
 * Meta Utility template for the daily combined homework message.
 *
 * One message per student. {{1}} {{2}} {{3}} are the student, roll number, and class date.
 * {{4}} onwards is one subject per line. The new line is in this template text, between the boxes.
 * A day with fewer subjects leaves the spare boxes as a short dash.
 */
final class CombinedHomeworkWhatsAppTemplate
{
    public const NAME = 'homework_combined';

    public const SUBJECT_SLOTS = 6;

    /** @var list<string> */
    public const ALIASES = [
        'homework_combined',
        'homework_daily',
        'homework_today',
    ];

    public const CATEGORY = 'UTILITY';

    public const BODY = <<<'TXT'
Dear Parent,
Today's homework for your ward {{1}} (Roll No: {{2}}) has been assigned for {{3}}.

Please open each subject below to view or download. No login is required:
{{4}}
{{5}}
{{6}}
{{7}}
{{8}}
{{9}}

Kindly ensure it is completed on time. Thank you.
TXT;

    /**
     * @return array<int, array{label: string, example: string}>
     */
    public static function variables(): array
    {
        return [
            1 => [
                'label' => 'Student name',
                'example' => 'Rohit Sharma',
            ],
            2 => [
                'label' => 'Roll number',
                'example' => '11-JEE-042',
            ],
            3 => [
                'label' => 'Class / date',
                'example' => 'Class 11 JEE · 14 Aug 2026',
            ],
            4 => [
                'label' => 'Subject 1',
                'example' => 'Physics: https://example.com/h/samplePhysicsToken',
            ],
            5 => [
                'label' => 'Subject 2',
                'example' => 'Chemistry: https://example.com/h/sampleChemistryToken',
            ],
            6 => [
                'label' => 'Subject 3',
                'example' => 'Biology: https://example.com/h/sampleBiologyToken',
            ],
            7 => [
                'label' => 'Subject 4',
                'example' => '—',
            ],
            8 => [
                'label' => 'Subject 5',
                'example' => '—',
            ],
            9 => [
                'label' => 'Subject 6',
                'example' => '—',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function exampleValues(): array
    {
        return array_values(array_map(
            static fn (array $variable): string => (string) $variable['example'],
            self::variables(),
        ));
    }

    /**
     * @return list<array{index: int, label: string, example: string}>
     */
    public static function sampleRows(): array
    {
        $rows = [];

        foreach (self::variables() as $index => $variable) {
            $rows[] = [
                'index' => $index,
                'label' => $variable['label'],
                'example' => $variable['example'],
                'source' => (string) ($variable['crm_source'] ?? ''),
            ];
        }

        return $rows;
    }

    public static function looksLikeName(string $name): bool
    {
        $normalized = MetaWhatsAppTemplateBuilder::normalizeName($name);

        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, self::ALIASES, true)) {
            return true;
        }

        return str_starts_with($normalized, 'homework_combined')
            || str_starts_with($normalized, 'homework_daily')
            || str_starts_with($normalized, 'homework_today');
    }
}
