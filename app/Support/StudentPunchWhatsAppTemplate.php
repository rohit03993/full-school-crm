<?php

namespace App\Support;

/**
 * Meta Utility templates for student biometric / manual IN/OUT WhatsApp (sent to parent mobile).
 */
final class StudentPunchWhatsAppTemplate
{
    public const IN_NAME = 'punch_in';

    public const OUT_NAME = 'punch_out';

    public const CATEGORY = 'UTILITY';

    public const IN_BODY = <<<'TXT'
Dear Parent,
Your ward {{1}} (Roll: {{2}}) has checked IN at {{3}} on {{4}}.
Status: {{5}}
Thank you.
TXT;

    public const OUT_BODY = <<<'TXT'
Dear Parent,
Your ward {{1}} (Roll: {{2}}) has checked OUT at {{3}} on {{4}}.
Status: {{5}}
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
                'label' => 'Roll number',
                'example' => '12-A-042',
                'crm_source' => 'student.enrollment_number',
            ],
            3 => [
                'label' => 'Punch time',
                'example' => '08:15 AM',
                'crm_source' => 'campaign.time',
            ],
            4 => [
                'label' => 'Date',
                'example' => '29 Aug 2026',
                'crm_source' => 'campaign.date',
            ],
            5 => [
                'label' => 'Attendance status',
                'example' => 'Present',
                'crm_source' => 'attendance.status',
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
        if (StaffPunchWhatsAppTemplate::looksLikeInName($name)) {
            return false;
        }

        $normalized = strtolower(trim($name));

        foreach ([
            'punch_in',
            'manual_in',
            'check_in',
            'checkin',
            'parent_attendance_manual_in',
            'parent_check_in',
            'student_punch_in',
            'attendance_in',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function looksLikeOutName(string $name): bool
    {
        if (StaffPunchWhatsAppTemplate::looksLikeOutName($name)) {
            return false;
        }

        $normalized = strtolower(trim($name));

        foreach ([
            'punch_out',
            'manual_out',
            'check_out',
            'checkout',
            'parent_attendance_manual_out',
            'parent_check_out',
            'student_punch_out',
            'attendance_out',
        ] as $needle) {
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
