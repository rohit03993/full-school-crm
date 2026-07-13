<?php

namespace App\Support;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Filament\Pages\AttendancePage;
use App\Filament\Pages\CallQueuePage;
use App\Filament\Pages\CallReportPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FollowUpsPage;
use App\Filament\Pages\MyLeadsPage;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\User;

final class CrmMobileBottomNav
{
    /**
     * @return list<array{label: string, url: string, icon: string, active: bool, badge: ?int, visible: bool}>
     */
    public static function tabs(?User $user, string $currentPath): array
    {
        if (! $user?->is_active) {
            return [];
        }

        $dueCount = CrmNavBadges::followUpsDue();

        $tabs = match (self::profileFor($user)) {
            'calling' => self::callingTabs($currentPath, $dueCount),
            'academic' => self::academicTabs($currentPath, $dueCount),
            'hybrid' => self::hybridTabs($currentPath, $dueCount),
            default => self::defaultTabs($currentPath, $dueCount),
        };

        return array_values(array_filter(
            $tabs,
            fn (array $tab): bool => ($tab['visible'] ?? true) && filled($tab['url'] ?? null),
        ));
    }

    private static function profileFor(User $user): string
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return 'default';
        }

        $jobs = CrmAccess::jobRoleNamesFor($user);
        $counsellor = in_array(StaffJobRole::Counsellor->value, $jobs, true);
        $academic = in_array(StaffJobRole::AcademicCoordinator->value, $jobs, true);

        if ($counsellor && $academic) {
            return 'hybrid';
        }

        if ($counsellor && FeatureGate::enabled(LicenseFeature::Calls)) {
            return 'calling';
        }

        if ($academic) {
            return 'academic';
        }

        return 'default';
    }

    /**
     * @return list<array{label: string, url: string, icon: string, active: bool, badge: ?int, visible: bool}>
     */
    private static function defaultTabs(string $currentPath, int $dueCount): array
    {
        return [
            self::tab('Home', Dashboard::getUrl(), 'heroicon-o-home', $currentPath, isHome: true),
            self::tab('Search', StudentSearchPage::getUrl(), 'heroicon-o-magnifying-glass', $currentPath, 'student-search-page'),
            self::tab('Leads', EnquiryResource::getUrl('index'), 'heroicon-o-inbox-stack', $currentPath, 'enquiries', visible: FeatureGate::enabled(LicenseFeature::Enquiries)),
            self::tab('Follow-ups', FollowUpsPage::getUrl(), 'heroicon-o-bell-alert', $currentPath, 'follow-ups-page', badge: $dueCount > 0 ? $dueCount : null, visible: FeatureGate::enabled(LicenseFeature::Enquiries)),
            self::tab('Calls', CallQueuePage::getUrl(), 'heroicon-o-phone', $currentPath, 'call-queue-page', visible: FeatureGate::enabled(LicenseFeature::Calls) && CallQueuePage::canAccess()),
        ];
    }

    /**
     * @return list<array{label: string, url: string, icon: string, active: bool, badge: ?int, visible: bool}>
     */
    private static function callingTabs(string $currentPath, int $dueCount): array
    {
        return [
            self::tab('Home', Dashboard::getUrl(), 'heroicon-o-home', $currentPath, isHome: true),
            self::tab('My Leads', MyLeadsPage::getUrl(), 'heroicon-o-user-group', $currentPath, 'my-leads-page', visible: MyLeadsPage::canAccess()),
            self::tab('Calls', CallQueuePage::getUrl(), 'heroicon-o-phone', $currentPath, 'call-queue-page', visible: CallQueuePage::canAccess()),
            self::tab('Follow-ups', FollowUpsPage::getUrl(), 'heroicon-o-bell-alert', $currentPath, 'follow-ups-page', badge: $dueCount > 0 ? $dueCount : null, visible: FeatureGate::enabled(LicenseFeature::Enquiries)),
            self::tab('Report', CallReportPage::getUrl(), 'heroicon-o-chart-bar', $currentPath, 'call-report-page', visible: CallReportPage::canAccess()),
        ];
    }

    /**
     * @return list<array{label: string, url: string, icon: string, active: bool, badge: ?int, visible: bool}>
     */
    private static function academicTabs(string $currentPath, int $dueCount): array
    {
        return [
            self::tab('Home', Dashboard::getUrl(), 'heroicon-o-home', $currentPath, isHome: true),
            self::tab('Search', StudentSearchPage::getUrl(), 'heroicon-o-magnifying-glass', $currentPath, 'student-search-page'),
            self::tab('Attendance', AttendancePage::getUrl(), 'heroicon-o-calendar-days', $currentPath, 'attendance-page', visible: AttendancePage::canAccess()),
            self::tab('Homework', HomeworkAssignmentResource::getUrl('index'), 'heroicon-o-book-open', $currentPath, 'homework-assignments', visible: HomeworkAssignmentResource::canAccess()),
            self::tab('Follow-ups', FollowUpsPage::getUrl(), 'heroicon-o-bell-alert', $currentPath, 'follow-ups-page', badge: $dueCount > 0 ? $dueCount : null, visible: FeatureGate::enabled(LicenseFeature::Enquiries)),
        ];
    }

    /**
     * @return list<array{label: string, url: string, icon: string, active: bool, badge: ?int, visible: bool}>
     */
    private static function hybridTabs(string $currentPath, int $dueCount): array
    {
        return [
            self::tab('Home', Dashboard::getUrl(), 'heroicon-o-home', $currentPath, isHome: true),
            self::tab('Leads', MyLeadsPage::getUrl(), 'heroicon-o-user-group', $currentPath, 'my-leads-page', visible: MyLeadsPage::canAccess()),
            self::tab('Calls', CallQueuePage::getUrl(), 'heroicon-o-phone', $currentPath, 'call-queue-page', visible: CallQueuePage::canAccess()),
            self::tab('Attend', AttendancePage::getUrl(), 'heroicon-o-calendar-days', $currentPath, 'attendance-page', visible: AttendancePage::canAccess()),
            self::tab('Follow-ups', FollowUpsPage::getUrl(), 'heroicon-o-bell-alert', $currentPath, 'follow-ups-page', badge: $dueCount > 0 ? $dueCount : null, visible: FeatureGate::enabled(LicenseFeature::Enquiries)),
        ];
    }

    /**
     * @return array{label: string, url: string, icon: string, active: bool, badge: ?int, visible: bool}
     */
    private static function tab(
        string $label,
        string $url,
        string $icon,
        string $currentPath,
        ?string $pathNeedle = null,
        bool $isHome = false,
        ?int $badge = null,
        bool $visible = true,
    ): array {
        $active = $isHome
            ? ($currentPath === 'admin' || str_ends_with($currentPath, '/admin'))
            : ($pathNeedle !== null && str_contains($currentPath, $pathNeedle));

        return [
            'label' => $label,
            'url' => $url,
            'icon' => $icon,
            'active' => $active,
            'badge' => $badge,
            'visible' => $visible,
        ];
    }
}
