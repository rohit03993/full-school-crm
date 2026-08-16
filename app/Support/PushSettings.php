<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\WebPushService;

class PushSettings
{
    public const KEY_ENABLED = 'push.enabled';

    public const KEY_FEE_REMINDERS = 'push.fee_reminders_enabled';

    public const KEY_FOLLOWUP_DIGEST = 'push.followup_digest_enabled';

    public const KEY_LEAD_ASSIGNED = 'push.lead_assigned_enabled';

    public const KEY_VISIT_ASSIGNED = 'push.visit_assigned_enabled';

    public const KEY_CASE_ASSIGNED = 'push.case_assigned_enabled';

    public const KEY_ATTENDANCE = 'push.attendance_enabled';

    public const KEY_HOMEWORK = 'push.homework_enabled';

    public const KEY_MARKS_PUBLISHED = 'push.marks_published_enabled';

    public const KEY_CASE_UPDATE = 'push.case_update_enabled';

    public static function enabled(): bool
    {
        return self::bool(self::KEY_ENABLED, true);
    }

    public static function feeRemindersEnabled(): bool
    {
        return self::enabled() && self::bool(self::KEY_FEE_REMINDERS, true);
    }

    public static function followUpDigestEnabled(): bool
    {
        return self::enabled() && self::bool(self::KEY_FOLLOWUP_DIGEST, true);
    }

    public static function leadAssignedEnabled(): bool
    {
        return self::enabled() && self::bool(self::KEY_LEAD_ASSIGNED, true);
    }

    public static function visitAssignedEnabled(): bool
    {
        return self::enabled() && self::bool(self::KEY_VISIT_ASSIGNED, true);
    }

    public static function caseAssignedEnabled(): bool
    {
        return self::enabled() && self::bool(self::KEY_CASE_ASSIGNED, true);
    }

    public static function attendanceEnabled(): bool
    {
        return self::enabled() && self::bool(self::KEY_ATTENDANCE, true);
    }

    public static function homeworkEnabled(): bool
    {
        return self::enabled() && self::bool(self::KEY_HOMEWORK, true);
    }

    public static function marksPublishedEnabled(): bool
    {
        return self::enabled() && self::bool(self::KEY_MARKS_PUBLISHED, true);
    }

    public static function caseUpdateEnabled(): bool
    {
        return self::enabled() && self::bool(self::KEY_CASE_UPDATE, true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDefaults(): array
    {
        return [
            'enabled' => self::bool(self::KEY_ENABLED, true),
            'fee_reminders_enabled' => self::bool(self::KEY_FEE_REMINDERS, true),
            'followup_digest_enabled' => self::bool(self::KEY_FOLLOWUP_DIGEST, true),
            'lead_assigned_enabled' => self::bool(self::KEY_LEAD_ASSIGNED, true),
            'visit_assigned_enabled' => self::bool(self::KEY_VISIT_ASSIGNED, true),
            'case_assigned_enabled' => self::bool(self::KEY_CASE_ASSIGNED, true),
            'attendance_enabled' => self::bool(self::KEY_ATTENDANCE, true),
            'homework_enabled' => self::bool(self::KEY_HOMEWORK, true),
            'marks_published_enabled' => self::bool(self::KEY_MARKS_PUBLISHED, true),
            'case_update_enabled' => self::bool(self::KEY_CASE_UPDATE, true),
            'vapid_configured' => app(WebPushService::class)->isConfigured(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function saveFormData(array $data): void
    {
        $map = [
            self::KEY_ENABLED => 'enabled',
            self::KEY_FEE_REMINDERS => 'fee_reminders_enabled',
            self::KEY_FOLLOWUP_DIGEST => 'followup_digest_enabled',
            self::KEY_LEAD_ASSIGNED => 'lead_assigned_enabled',
            self::KEY_VISIT_ASSIGNED => 'visit_assigned_enabled',
            self::KEY_CASE_ASSIGNED => 'case_assigned_enabled',
            self::KEY_ATTENDANCE => 'attendance_enabled',
            self::KEY_HOMEWORK => 'homework_enabled',
            self::KEY_MARKS_PUBLISHED => 'marks_published_enabled',
            self::KEY_CASE_UPDATE => 'case_update_enabled',
        ];

        foreach ($map as $key => $field) {
            Setting::setValue($key, ($data[$field] ?? false) ? '1' : '0', 'push');
        }

        Setting::flushValueCache();
    }

    protected static function bool(string $key, bool $default): bool
    {
        $value = Setting::getValue($key, $default ? '1' : '0');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
