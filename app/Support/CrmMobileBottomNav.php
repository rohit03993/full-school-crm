<?php

namespace App\Support;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Filament\Pages\AllCasesPage;
use App\Filament\Pages\AttendancePage;
use App\Filament\Pages\CallQueuePage;
use App\Filament\Pages\CallReportPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FollowUpsPage;
use App\Filament\Pages\HomeworkCheckPage;
use App\Filament\Pages\MyLeadsPage;
use App\Filament\Pages\MyMeetingsPage;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Pages\SubmitHomeworkPage;
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
            'academic' => self::academicTabs($user, $currentPath),
            'hybrid' => self::hybridTabs($currentPath, $dueCount),
            default => self::defaultTabs($user, $currentPath, $dueCount),
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
        $academic = in_array(StaffJobRole::AcademicCoordinator->value, $jobs, true)
            || in_array(StaffJobRole::Teacher->value, $jobs, true);

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
    private static function defaultTabs(User $user, string $currentPath, int $dueCount): array
    {
        return [
            self::tab('Home', Dashboard::getUrl(), 'heroicon-o-home', $currentPath, isHome: true),
            self::tab('Search', StudentSearchPage::getUrl(), 'heroicon-o-magnifying-glass', $currentPath, 'student-search-page'),
            self::tab('Leads', EnquiryResource::getUrl('index'), 'heroicon-o-inbox-stack', $currentPath, 'enquiries', visible: FeatureGate::enabled(LicenseFeature::Enquiries)),
            self::tab('Follow-ups', FollowUpsPage::getUrl(), 'heroicon-o-bell-alert', $currentPath, 'follow-ups-page', badge: $dueCount > 0 ? $dueCount : null, visible: FeatureGate::enabled(LicenseFeature::Enquiries)),
            self::callOrCasesTab($user, $currentPath),
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
    private static function academicTabs(User $user, string $currentPath): array
    {
        $jobs = CrmAccess::jobRoleNamesFor($user);
        $isTeacher = in_array(StaffJobRole::Teacher->value, $jobs, true);

        if ($isTeacher) {
            return [
                self::tab('Home', Dashboard::getUrl(), 'heroicon-o-home', $currentPath, isHome: true),
                self::tab('Give homework', SubmitHomeworkPage::getUrl(), 'heroicon-o-pencil-square', $currentPath, 'submit-homework-page', visible: SubmitHomeworkPage::canAccess()),
                self::tab('Check homework', HomeworkCheckPage::getUrl(), 'heroicon-o-clipboard-document-check', $currentPath, 'homework-check-page', visible: HomeworkCheckPage::canAccess()),
                self::tab('Attendance', AttendancePage::getUrl(), 'heroicon-o-calendar-days', $currentPath, 'attendance-page', visible: AttendancePage::canAccess()),
                self::callOrCasesTab($user, $currentPath),
            ];
        }

        return [
            self::tab('Home', Dashboard::getUrl(), 'heroicon-o-home', $currentPath, isHome: true),
            self::tab('Search', StudentSearchPage::getUrl(), 'heroicon-o-magnifying-glass', $currentPath, 'student-search-page'),
            self::tab('Attendance', AttendancePage::getUrl(), 'heroicon-o-calendar-days', $currentPath, 'attendance-page', visible: AttendancePage::canAccess()),
            self::tab('Homework', HomeworkAssignmentResource::getUrl('index'), 'heroicon-o-book-open', $currentPath, 'homework-assignments', visible: HomeworkAssignmentResource::canAccess()),
            self::callOrCasesTab($user, $currentPath),
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
     * One bottom slot. Callers keep Calls. Super Admin and everyone who cannot call get Cases.
     *
     * @return array{label: string, url: string, icon: string, active: bool, badge: ?int, visible: bool}
     */
    private static function callOrCasesTab(User $user, string $currentPath): array
    {
        $canCall = ! $user->hasRole(RoleName::SuperAdmin->value) && CallQueuePage::canAccess();

        if ($canCall) {
            return self::tab('Calls', CallQueuePage::getUrl(), 'heroicon-o-phone', $currentPath, 'call-queue-page');
        }

        $cases = self::casesTab($user, $currentPath);

        if ($cases['visible'] || ! CallQueuePage::canAccess()) {
            return $cases;
        }

        return self::tab('Calls', CallQueuePage::getUrl(), 'heroicon-o-phone', $currentPath, 'call-queue-page');
    }

    /**
     * Super Admin opens every case. Other staff open their own cases.
     *
     * @return array{label: string, url: string, icon: string, active: bool, badge: ?int, visible: bool}
     */
    private static function casesTab(User $user, string $currentPath): array
    {
        $super = $user->hasRole(RoleName::SuperAdmin->value);
        $visible = FeatureGate::enabled(LicenseFeature::Cases) && (
            $super || CrmAccess::can($user, CrmPermission::CasesView)
        );

        if ($super) {
            return self::tab('Cases', AllCasesPage::getUrl(), 'heroicon-o-rectangle-stack', $currentPath, 'all-cases-page', visible: $visible);
        }

        return self::tab(
            'Cases',
            MyMeetingsPage::getUrl(['tab' => 'my_cases']),
            'heroicon-o-rectangle-stack',
            $currentPath,
            'my-meetings-page',
            visible: $visible,
        );
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
