<?php

namespace App\Support;

/**
 * Canonical Meta Utility templates for fee reminders.
 * Admin types the name on Templates → New; body and samples auto-fill.
 */
final class FeeReminderWhatsAppTemplate
{
    public const NAME = 'fee_reminder';

    public const UPCOMING_NAME = 'fee_reminder_upcoming';

    public const DUE_NAME = 'fee_reminder_due';

    public const OVERDUE_NAME = 'fee_reminder_overdue';

    public const CATEGORY = 'UTILITY';

    public const BODY_UPCOMING = <<<'TXT'
Dear Parent,
This is a fee reminder from {{1}}.

Student: {{2}}
Amount due: Rs {{3}}
Due date: {{4}}

Please pay on or before the due date.
Thank you.
TXT;

    public const BODY_DUE = <<<'TXT'
Dear Parent,
This is a fee reminder from {{1}}.

Student: {{2}}
Amount due today: Rs {{3}}
Due date: {{4}}

Please complete the payment today.
Thank you.
TXT;

    public const BODY_OVERDUE = <<<'TXT'
Dear Parent,
This is a fee reminder from {{1}}.

Student: {{2}}
Pending amount: Rs {{3}}
Due date: {{4}}

The due date has passed. Please clear the pending fee at the earliest.
Thank you.
TXT;

    /** @deprecated Use BODY_OVERDUE — kept so existing tests and aliases still resolve. */
    public const BODY = self::BODY_OVERDUE;

    /**
     * @return array{category: string, body_text: string, body_variable_samples: list<array{index: int, label: string, example: string}>}|null
     */
    public static function formPresetForName(string $normalizedName): ?array
    {
        $body = match (true) {
            self::looksLikeUpcomingName($normalizedName) => self::BODY_UPCOMING,
            self::looksLikeDueName($normalizedName) => self::BODY_DUE,
            self::looksLikeOverdueName($normalizedName) => self::BODY_OVERDUE,
            default => null,
        };

        if ($body === null) {
            return null;
        }

        return [
            'category' => self::CATEGORY,
            'body_text' => $body,
            'body_variable_samples' => self::sampleRows(),
        ];
    }

    /**
     * @return array<int, array{label: string, example: string, crm_source: string}>
     */
    public static function variables(): array
    {
        return [
            1 => [
                'label' => 'Institute name',
                'example' => 'Springdale Public School',
                'crm_source' => 'institute.name',
            ],
            2 => [
                'label' => 'Student name',
                'example' => 'Riya Sharma',
                'crm_source' => 'student.name',
            ],
            3 => [
                'label' => 'Pending amount',
                'example' => '10000.00',
                'crm_source' => 'fee.pending_amount',
            ],
            4 => [
                'label' => 'Due date',
                'example' => '27 Aug 2026',
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
        return self::formPresetForName(MetaWhatsAppTemplateBuilder::normalizeName($name)) !== null
            || WhatsAppTemplateParamMappingInferrer::looksLikeFeeReminderTemplateName($name);
    }

    public static function looksLikeUpcomingName(string $name): bool
    {
        return str_contains(strtolower(trim($name)), 'fee_reminder_upcoming');
    }

    public static function looksLikeDueName(string $name): bool
    {
        $normalized = strtolower(trim($name));

        return str_contains($normalized, 'fee_reminder_due')
            && ! str_contains($normalized, 'overdue');
    }

    public static function looksLikeOverdueName(string $name): bool
    {
        $normalized = strtolower(trim($name));

        return $normalized === 'fee_reminder'
            || str_contains($normalized, 'fee_reminder_overdue');
    }
}
