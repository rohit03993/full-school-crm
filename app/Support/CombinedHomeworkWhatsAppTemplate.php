<?php

namespace App\Support;

/**
 * Meta Utility template for the daily COMBINED homework message.
 *
 * One message per student that lists every subject that has homework today, each with its own
 * public /h/ link (no login). Subjects without homework are simply left out of {{4}}.
 *
 * {{4}} is a single dynamic block built at send time, e.g.:
 *   Maths: https://example.com/h/aaa
 *   Physics: https://example.com/h/bbb
 *
 * This avoids Meta's fixed variable count (we never need one variable per subject) and keeps the
 * message readable when only some subjects have homework.
 */
final class CombinedHomeworkWhatsAppTemplate
{
    public const NAME = 'homework_combined';

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
                'label' => 'Subject links (one per line)',
                'example' => "Maths: https://example.com/h/sampleMathsHomeworkToken1234\nPhysics: https://example.com/h/samplePhysicsHomeworkToken12",
            ],
        ];
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
