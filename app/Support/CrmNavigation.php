<?php

namespace App\Support;

use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;

/**
 * Sidebar group names and order — keep menu paths in hints/docs aligned with {@see CrmMenuLabels}.
 *
 * Ungrouped top (fixed): Dashboard → My meetings → My classes
 * Daily use: Leads → Students → Academics
 * Operations: Calls → Reports
 * Configuration (bottom): WhatsApp → Setup → Admin → Website
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
     * Sidebar group order — most-used sections first; setup/config at the bottom.
     *
     * @return list<string>
     */
    public static function groupOrder(): array
    {
        return [
            self::GROUP_LEADS,
            self::GROUP_STUDENTS,
            self::GROUP_ACADEMICS,
            self::GROUP_CALLS,
            self::GROUP_REPORTS,
            self::GROUP_META_WHATSAPP,
            self::GROUP_SETTINGS,
            self::GROUP_ADMIN,
            self::GROUP_WEBSITE,
        ];
    }

    /**
     * @return array<int, NavigationGroup>
     */
    public static function navigationGroups(): array
    {
        $icons = [
            self::GROUP_LEADS => Heroicon::OutlinedChatBubbleLeftRight,
            self::GROUP_STUDENTS => Heroicon::OutlinedAcademicCap,
            self::GROUP_ACADEMICS => Heroicon::OutlinedBookOpen,
            self::GROUP_CALLS => Heroicon::OutlinedPhone,
            self::GROUP_REPORTS => Heroicon::OutlinedChartBar,
            self::GROUP_META_WHATSAPP => Heroicon::OutlinedDevicePhoneMobile,
            self::GROUP_SETTINGS => Heroicon::OutlinedCog6Tooth,
            self::GROUP_ADMIN => Heroicon::OutlinedShieldCheck,
            self::GROUP_WEBSITE => Heroicon::OutlinedGlobeAlt,
        ];

        return array_map(
            fn (string $group): NavigationGroup => NavigationGroup::make($group)
                ->icon($icons[$group]),
            self::groupOrder(),
        );
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
