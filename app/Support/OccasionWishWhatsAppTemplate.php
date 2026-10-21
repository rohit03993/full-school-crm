<?php

namespace App\Support;

/**
 * Approved Meta Utility templates for birthday and anniversary wishes.
 * The morning job fills the blanks. Meta must approve the wording before any send.
 */
final class OccasionWishWhatsAppTemplate
{
    public const STUDENT_BIRTHDAY_NAME = 'student_birthday';

    public const STAFF_BIRTHDAY_NAME = 'staff_birthday';

    public const STAFF_ANNIVERSARY_NAME = 'staff_anniversary';

    public const DAILY_REPORT_NAME = 'occasion_daily_report';

    public const CATEGORY = 'UTILITY';

    public const WISH_PARAM_COUNT = 2;

    public const REPORT_PARAM_COUNT = 8;

    public const STUDENT_BIRTHDAY_BODY = <<<'TXT'
Hello {{1}}, wishing you a very happy birthday from {{2}}. Have a wonderful day.
TXT;

    public const STAFF_BIRTHDAY_BODY = <<<'TXT'
Hello {{1}}, the team at {{2}} wishes you a very happy birthday. Thank you for your work.
TXT;

    public const STAFF_ANNIVERSARY_BODY = <<<'TXT'
Hello {{1}}, wishing you a happy marriage anniversary from {{2}}. Have a wonderful day.
TXT;

    public const DAILY_REPORT_BODY = <<<'TXT'
Hello, here is the wishes report for {{1}} from {{2}}. Student birthdays: {{3}}. Staff birthdays: {{4}}. Anniversaries: {{5}}. Sent: {{6}}. Failed: {{7}}. Skipped: {{8}}. Thank you.
TXT;

    /**
     * @return array<int, array{label: string, example: string, crm_source: string}>
     */
    public static function wishVariables(): array
    {
        return [
            1 => [
                'label' => 'Person name',
                'example' => 'Aarav Sharma',
                'crm_source' => 'occasion.name',
            ],
            2 => [
                'label' => 'Institute name',
                'example' => 'Horizon School',
                'crm_source' => 'institute.name',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, example: string, crm_source: string}>
     */
    public static function reportVariables(): array
    {
        return [
            1 => ['label' => 'Date', 'example' => '21 Oct 2026', 'crm_source' => 'occasion.date'],
            2 => ['label' => 'Institute name', 'example' => 'Horizon School', 'crm_source' => 'institute.name'],
            3 => ['label' => 'Student birthdays', 'example' => '12', 'crm_source' => 'occasion.student_birthdays'],
            4 => ['label' => 'Staff birthdays', 'example' => '4', 'crm_source' => 'occasion.staff_birthdays'],
            5 => ['label' => 'Anniversaries', 'example' => '1', 'crm_source' => 'occasion.anniversaries'],
            6 => ['label' => 'Sent', 'example' => '15', 'crm_source' => 'occasion.sent'],
            7 => ['label' => 'Failed', 'example' => '1', 'crm_source' => 'occasion.failed'],
            8 => ['label' => 'Skipped', 'example' => '1', 'crm_source' => 'occasion.skipped'],
        ];
    }

    /**
     * @param  array<int, array{label: string, example: string, crm_source?: string}>  $variables
     * @return list<array{index: int, label: string, example: string, source: string}>
     */
    public static function sampleRows(array $variables): array
    {
        $rows = [];

        foreach ($variables as $index => $variable) {
            $rows[] = [
                'index' => $index,
                'label' => $variable['label'],
                'example' => $variable['example'],
                'source' => (string) ($variable['crm_source'] ?? ''),
            ];
        }

        return $rows;
    }

    public static function looksLikeStudentBirthday(string $name): bool
    {
        return str_contains(strtolower(trim($name)), 'student_birthday');
    }

    public static function looksLikeStaffBirthday(string $name): bool
    {
        return str_contains(strtolower(trim($name)), 'staff_birthday');
    }

    public static function looksLikeStaffAnniversary(string $name): bool
    {
        $normalized = strtolower(trim($name));

        return str_contains($normalized, 'staff_anniversary')
            || str_contains($normalized, 'marriage_anniversary');
    }

    public static function looksLikeDailyReport(string $name): bool
    {
        $normalized = strtolower(trim($name));

        return str_contains($normalized, 'occasion_daily_report')
            || str_contains($normalized, 'wishes_report');
    }
}
