<?php

namespace App\Support;

use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;

/**
 * Sidebar group names and order — keep menu paths in hints/docs aligned with {@see CrmMenuLabels}.
 *
 * Leads → Calls → WhatsApp → Students → Academics → Reports → Setup → Admin → Website
 */
class CrmNavigation
{
    public const GROUP_LEADS = CrmMenuLabels::GROUP_LEADS;

    public const GROUP_CALLS = CrmMenuLabels::GROUP_CALLS;

    public const GROUP_MESSAGING = 'Messaging';

    /** WhatsApp via Meta Cloud API — per-school credentials in this CRM's database. */
    public const GROUP_META_WHATSAPP = CrmMenuLabels::GROUP_WHATSAPP;

    public static function whatsAppMenu(string $item): string
    {
        return CrmMenuLabels::whatsAppPath($item);
    }

    public const GROUP_STUDENTS = CrmMenuLabels::GROUP_STUDENTS;

    public const GROUP_ACADEMICS = CrmMenuLabels::GROUP_ACADEMICS;

    public const GROUP_REPORTS = CrmMenuLabels::GROUP_REPORTS;

    public const GROUP_SETTINGS = CrmMenuLabels::GROUP_SETTINGS;

    public const GROUP_ADMIN = CrmMenuLabels::GROUP_ADMIN;

    public const GROUP_WEBSITE = CrmMenuLabels::GROUP_WEBSITE;

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
            NavigationGroup::make(self::GROUP_META_WHATSAPP)
                ->icon(Heroicon::OutlinedDevicePhoneMobile),
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
