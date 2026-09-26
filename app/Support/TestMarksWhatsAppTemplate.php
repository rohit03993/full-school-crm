<?php

namespace App\Support;

/**
 * Canonical Meta Utility template for exam marks WhatsApp to parents.
 *
 * Admin types `test_marks` on Templates → New, leaves the body blank, blurs the name —
 * body + samples auto-fill (same as homework / fees). Then pick it once on
 * WhatsApp → Automations → Exam marks. Staff still click Send on the mark sheet.
 */
final class TestMarksWhatsAppTemplate
{
    public const NAME = 'test_marks';

    /** @var list<string> */
    public const ALIASES = [
        'test_marks',
        'exam_marks',
        'activity_marks',
        'marks_update',
        'test_marks_api',
    ];

    public const CATEGORY = 'UTILITY';

    public const BODY = <<<'TXT'
Dear Parent,
Test result for your ward {{1}} (Roll No. {{2}}).

Test: {{3}}
Marks: {{4}}

Thank you.
TXT;

    /**
     * @return array<int, array{label: string, example: string, crm_source: string}>
     */
    public static function variables(): array
    {
        return [
            1 => [
                'label' => 'Student name',
                'example' => 'Rishit Gupta',
                'crm_source' => 'student.name',
            ],
            2 => [
                'label' => 'Roll No.',
                'example' => '20175009286',
                'crm_source' => 'student.enrollment_number',
            ],
            3 => [
                'label' => 'Test / exam name',
                'example' => '12TH JEE BATCH (C) RESULTS (12-09-2026)',
                'crm_source' => 'activity.test_name',
            ],
            4 => [
                'label' => 'All subject marks (combined)',
                'example' => 'Chemistry: 76/100, Maths: 48/100, Physics: 65/100',
                'crm_source' => 'activity.marks_summary',
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

        return str_starts_with($normalized, 'test_marks')
            || str_starts_with($normalized, 'exam_marks')
            || str_starts_with($normalized, 'activity_marks');
    }
}
