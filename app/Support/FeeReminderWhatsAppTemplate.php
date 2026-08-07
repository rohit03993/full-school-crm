<?php

namespace App\Support;

/**
 * Canonical Meta Utility template for daily fee reminders.
 * Create under WhatsApp → Templates, map in a Live quick campaign, link in Automations.
 */
final class FeeReminderWhatsAppTemplate
{
    public const NAME = 'fee_reminder';

    public const CATEGORY = 'UTILITY';

    public const BODY = <<<'TXT'
Dear Parent/Guardian,
This is a fee reminder from {{1}}.

Student: {{2}}
Amount pending: Rs {{3}}
Due date: {{4}}

Please clear the pending fee at the earliest. For any query, contact the school office.
Thank you.
TXT;

    /**
     * @return array<int, array{label: string, example: string, crm_source: string}>
     */
    public static function variables(): array
    {
        return [
            1 => [
                'label' => 'Institute name',
                'example' => 'B.D.M. Kanya Degree College',
                'crm_source' => 'institute.name',
            ],
            2 => [
                'label' => 'Student name',
                'example' => 'Riya Sharma',
                'crm_source' => 'student.name',
            ],
            3 => [
                'label' => 'Pending amount',
                'example' => '5,000.00',
                'crm_source' => 'fee.pending_amount',
            ],
            4 => [
                'label' => 'Due date',
                'example' => '01 Jul 2026',
                'crm_source' => 'fee.due_date',
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
        return WhatsAppTemplateParamMappingInferrer::looksLikeFeeReminderTemplateName($name);
    }
}
