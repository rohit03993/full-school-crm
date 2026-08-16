<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Models\User;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Sidebar group names and order — keep menu paths in hints/docs aligned with {@see CrmMenuLabels}.
 *
 * Ungrouped top (fixed): Dashboard → My work → My classes
 * Daily use: Leads → Students → Academics
 * Operations: Calls → Reports
 * Configuration (bottom): WhatsApp → Setup → Admin → Website
 *
 * Hub pattern: dense areas (Homework, Attendance, Fees, WhatsApp, Setup) expose one sidebar
 * entry; leaf screens stay reachable by URL and hub cards. Turning a license module OFF only
 * hides that module's hub/leaves.
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
                ->icon($icons[$group])
                ->collapsed(self::groupStartsCollapsed($group)),
            self::groupOrder(),
        );
    }

    /**
     * Config-heavy groups always start collapsed. Role packs collapse unused daily groups.
     */
    public static function groupStartsCollapsed(string $group): bool
    {
        if (in_array($group, [self::GROUP_SETTINGS, self::GROUP_META_WHATSAPP, self::GROUP_ADMIN], true)) {
            return true;
        }

        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return match (self::navRolePack($user)) {
            'calling' => in_array($group, [self::GROUP_ACADEMICS, self::GROUP_REPORTS, self::GROUP_WEBSITE], true),
            'academic' => in_array($group, [self::GROUP_LEADS, self::GROUP_CALLS, self::GROUP_WEBSITE], true),
            'finance' => in_array($group, [self::GROUP_LEADS, self::GROUP_CALLS, self::GROUP_ACADEMICS, self::GROUP_WEBSITE], true),
            'messaging' => in_array($group, [self::GROUP_CALLS, self::GROUP_ACADEMICS, self::GROUP_REPORTS, self::GROUP_WEBSITE], true),
            default => $group === self::GROUP_WEBSITE,
        };
    }

    /**
     * @return 'owner'|'calling'|'academic'|'finance'|'messaging'|'default'
     */
    public static function navRolePack(?User $user): string
    {
        if (! $user) {
            return 'default';
        }

        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return 'owner';
        }

        $jobs = CrmAccess::jobRoleNamesFor($user);
        $counsellor = in_array(StaffJobRole::Counsellor->value, $jobs, true);
        $academic = in_array(StaffJobRole::AcademicCoordinator->value, $jobs, true)
            || in_array(StaffJobRole::Teacher->value, $jobs, true);
        $finance = in_array(StaffJobRole::Accountant->value, $jobs, true)
            || in_array(StaffJobRole::FeeAdjuster->value, $jobs, true);
        $messaging = in_array(StaffJobRole::MessagingCoordinator->value, $jobs, true);

        if ($finance && ! $counsellor && ! $academic) {
            return 'finance';
        }

        if ($counsellor && ! $academic) {
            return 'calling';
        }

        if ($academic && ! $counsellor) {
            return 'academic';
        }

        if ($messaging && ! $counsellor && ! $academic && ! $finance) {
            return 'messaging';
        }

        return 'default';
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
