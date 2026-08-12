<?php

namespace App\Support;

/**
 * Meta Utility templates for staff biometric IN/OUT WhatsApp (sent to staff mobile).
 * Auto-out does not use these — no message is sent when the day is closed at cutoff.
 */
final class StaffPunchWhatsAppTemplate
{
    public const IN_NAME = 'staff_punch_in';

    public const OUT_NAME = 'staff_punch_out';

    public const CATEGORY = 'UTILITY';

    public const IN_BODY = <<<'TXT'
Hello {{1}}, your staff attendance check-in has been recorded at {{2}} on {{3}}. This message is from {{4}}. Thank you.
TXT;

    public const OUT_BODY = <<<'TXT'
Hello {{1}}, your staff attendance check-out has been recorded at {{2}} on {{3}}. This message is from {{4}}. Thank you.
TXT;

    /**
     * @return array<int, array{label: string, example: string, crm_source: string}>
     */
    public static function variables(): array
    {
        return [
            1 => [
                'label' => 'Staff name',
                'example' => 'Hari Mohan Sir',
                'crm_source' => 'staff.name',
            ],
            2 => [
                'label' => 'Punch time',
                'example' => '13:27:56',
                'crm_source' => 'attendance.time',
            ],
            3 => [
                'label' => 'Date',
                'example' => '12 Aug 2026',
                'crm_source' => 'attendance.date',
            ],
            4 => [
                'label' => 'Institute name',
                'example' => 'B.D.M. Kanya Degree College',
                'crm_source' => 'institute.name',
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

    public static function looksLikeInName(string $name): bool
    {
        $normalized = strtolower(trim($name));

        foreach (['staff_punch_in', 'staff_check_in', 'staff_attendance_in'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function looksLikeOutName(string $name): bool
    {
        $normalized = strtolower(trim($name));

        foreach (['staff_punch_out', 'staff_check_out', 'staff_attendance_out'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function looksLikeName(string $name): bool
    {
        return self::looksLikeInName($name) || self::looksLikeOutName($name);
    }
}
