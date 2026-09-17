<?php

namespace App\Support;

/**
 * Canonical Meta Utility template for exam marks WhatsApp to parents.
 *
 * Named placeholders (not {{1}}…{{4}}) match the approved Motion `test_marks` body.
 * Admin picks this once on WhatsApp → Automations → Exam marks. Staff still click
 * Send on the mark sheet — publish / PDF does not message parents.
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
Test result for your ward *{{name}}* (Roll No. *{{roll_number}}*).

*Test:* {{test}}
*Marks:* {{all_subject_marks}}
TXT;

    /**
     * @return array<string, array{label: string, example: string, crm_source: string}>
     */
    public static function variables(): array
    {
        return [
            'name' => [
                'label' => 'Student name',
                'example' => 'Rishit Gupta',
                'crm_source' => 'student.name',
            ],
            'roll_number' => [
                'label' => 'Roll No.',
                'example' => '20175009286',
                'crm_source' => 'student.enrollment_number',
            ],
            'test' => [
                'label' => 'Test / exam name',
                'example' => '12TH JEE BATCH (C) RESULTS (12-09-2026)',
                'crm_source' => 'activity.test_name',
            ],
            'all_subject_marks' => [
                'label' => 'All subject marks (combined)',
                'example' => 'Chemistry: 76/100, Maths: 48/100, Physics: 65/100',
                'crm_source' => 'activity.marks_summary',
            ],
        ];
    }

    public static function looksLikeName(string $name): bool
    {
        if (in_array(strtolower(trim($name)), self::ALIASES, true)) {
            return true;
        }

        return WhatsAppTemplateParamMappingInferrer::looksLikeMarksTemplateName($name);
    }
}
