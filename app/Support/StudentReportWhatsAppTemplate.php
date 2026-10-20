<?php

namespace App\Support;

/**
 * Meta Utility template for a student report parent WhatsApp.
 * Sent only after Super Admin or Academic coordinator approves the report.
 */
final class StudentReportWhatsAppTemplate
{
    public const NAME = 'student_report';

    public const CATEGORY = 'UTILITY';

    public const BODY = <<<'TXT'
Dear Parent,

This is to inform you that a report has been recorded for your ward {{1}}, Class {{2}}.

Date: {{3}}
Type: {{4}}
Fine: {{5}}
Remark: {{6}}

Please discuss this with your ward. You may contact the school for any clarification.

{{7}}
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
                'example' => 'Riya Sharma',
                'crm_source' => 'student.name',
            ],
            2 => [
                'label' => 'Class',
                'example' => '12-A',
                'crm_source' => 'report.class',
            ],
            3 => [
                'label' => 'Report date',
                'example' => '20 Oct 2026',
                'crm_source' => 'report.date',
            ],
            4 => [
                'label' => 'Report type',
                'example' => 'Creating noise',
                'crm_source' => 'report.type',
            ],
            5 => [
                'label' => 'Fine',
                'example' => 'No fine',
                'crm_source' => 'report.fine',
            ],
            6 => [
                'label' => 'Remark',
                'example' => 'Was talking loudly during class.',
                'crm_source' => 'report.remark',
            ],
            7 => [
                'label' => 'Institute name',
                'example' => 'Horizon School',
                'crm_source' => 'institute.name',
            ],
        ];
    }

    /**
     * @return list<array{index: int, label: string, example: string, source: string}>
     */
    public static function sampleRows(): array
    {
        $rows = [];

        foreach (self::variables() as $index => $variable) {
            $rows[] = [
                'index' => $index,
                'label' => $variable['label'],
                'example' => $variable['example'],
                'source' => $variable['crm_source'],
            ];
        }

        return $rows;
    }

    public static function looksLikeName(string $name): bool
    {
        return $name === self::NAME;
    }
}
