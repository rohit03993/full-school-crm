<?php

namespace App\Support;

use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;

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
     * @return array<int, NavigationGroup>
     */
    public static function navigationGroups(): array
    {
        return [
            NavigationGroup::make(self::GROUP_LEADS)
                ->icon(Heroicon::OutlinedChatBubbleLeftRight),
            NavigationGroup::make(self::GROUP_CALLS)
                ->icon(Heroicon::OutlinedPhone),
            NavigationGroup::make(self::GROUP_MESSAGING)
                ->icon(Heroicon::OutlinedChatBubbleOvalLeftEllipsis),
            NavigationGroup::make(self::GROUP_STUDENTS)
                ->icon(Heroicon::OutlinedAcademicCap),
            NavigationGroup::make(self::GROUP_ACADEMICS)
                ->icon(Heroicon::OutlinedBookOpen),
            NavigationGroup::make(self::GROUP_REPORTS)
                ->icon(Heroicon::OutlinedChartBar),
            NavigationGroup::make(self::GROUP_SETTINGS)
                ->icon(Heroicon::OutlinedCog6Tooth),
            NavigationGroup::make(self::GROUP_ADMIN)
                ->icon(Heroicon::OutlinedShieldCheck),
            NavigationGroup::make(self::GROUP_WEBSITE)
                ->icon(Heroicon::OutlinedGlobeAlt),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function groups(): array
    {
        return array_map(
            fn (NavigationGroup $group): string => (string) $group->getLabel(),
            self::navigationGroups(),
        );
    }
}
