<?php

namespace App\Support;

/**
 * Sidebar group names and order — keep menu paths in hints/docs aligned with these labels.
 *
 * Leads → Calls → Messaging → Students → Academics → Reports → Settings → Admin → Website
 */
class CrmNavigation
{
    public const GROUP_LEADS = 'Leads & Enquiries';

    public const GROUP_CALLS = 'Calls';

    public const GROUP_MESSAGING = 'Messaging';

    public const GROUP_STUDENTS = 'Students & Admissions';

    public const GROUP_ACADEMICS = 'Academics';

    public const GROUP_REPORTS = 'Reports';

    public const GROUP_SETTINGS = 'Settings';

    public const GROUP_ADMIN = 'Administration';

    public const GROUP_WEBSITE = 'Website';

    /**
     * @return array<int, string>
     */
    public static function groups(): array
    {
        return [
            self::GROUP_LEADS,
            self::GROUP_CALLS,
            self::GROUP_MESSAGING,
            self::GROUP_STUDENTS,
            self::GROUP_ACADEMICS,
            self::GROUP_REPORTS,
            self::GROUP_SETTINGS,
            self::GROUP_ADMIN,
            self::GROUP_WEBSITE,
        ];
    }
}
