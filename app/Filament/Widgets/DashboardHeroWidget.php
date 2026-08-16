<?php

namespace App\Filament\Widgets;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Filament\Pages\AttendanceHubPage;
use App\Filament\Pages\CallQueuePage;
use App\Filament\Pages\FeesHubPage;
use App\Filament\Pages\FollowUpsPage;
use App\Filament\Pages\HomeworkPage;
use App\Filament\Pages\MyLeadsPage;
use App\Filament\Pages\MyMeetingsPage;
use App\Filament\Pages\MyTeachingAssignmentsPage;
use App\Filament\Pages\ReportsPage;
use App\Filament\Pages\StaffAttendancePage;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Pages\WhatsAppInboxPage;
use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\AcademicSession;
use App\Services\CrmDashboardService;
use App\Services\DashboardOpsService;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavBadges;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use App\Support\InstituteSettings;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DashboardHeroWidget extends Widget
{
    use UsesDashboardFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = -10;

    protected string $view = 'filament.widgets.dashboard-hero';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $branding = InstituteSettings::forDocuments();
        $user = Auth::user();
        $pack = CrmNavigation::navRolePack($user);
        $filters = $this->dashboardFilters();

        return [
            'userName' => $user?->name ?? 'there',
            'initials' => $this->initials($user?->name),
            'instituteName' => $branding['name'],
            'tagline' => $branding['tagline'],
            'todayLabel' => now()->format('l, j F Y'),
            'scopeLabel' => $filters->rangeName(),
            'scopeDates' => $filters->rangeLabel(),
            'sessionLabel' => $this->sessionLabel($filters->sessionId),
            'metrics' => $this->metricsForPack($pack),
            'quickActions' => $this->visibleActions($this->actionsForPack($pack)),
        ];
    }

    protected function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name)) ?: [];
        $letters = array_slice(array_filter(array_map(
            fn (string $word): string => mb_substr($word, 0, 1),
            $words,
        )), 0, 2);

        return $letters === [] ? '?' : mb_strtoupper(implode('', $letters));
    }

    protected function sessionLabel(?int $sessionId): ?string
    {
        if ($sessionId === null) {
            return null;
        }

        return AcademicSession::query()->find($sessionId)?->code;
    }

    /**
     * Headline numbers for whoever is looking, so no role lands on a hero that
     * describes somebody else's job.
     *
     * @return list<array{label: string, value: string, meta: ?string, icon: string, tone: string, url: ?string}>
     */
    protected function metricsForPack(string $pack): array
    {
        return match ($pack) {
            'owner' => $this->ownerMetrics(),
            'calling' => $this->callingMetrics(),
            'academic' => $this->academicMetrics(),
            'finance' => $this->financeMetrics(),
            'messaging' => $this->messagingMetrics(),
            default => $this->defaultMetrics(),
        };
    }

    /**
     * @return array{label: string, value: string, meta: ?string, icon: string, tone: string, url: ?string}
     */
    protected function metric(
        string $label,
        string $value,
        string $icon,
        ?string $meta = null,
        string $tone = 'neutral',
        ?string $url = null,
    ): array {
        return compact('label', 'value', 'icon', 'meta', 'tone', 'url');
    }

    /**
     * Lakh/crore short forms keep six- and seven-figure amounts readable in a tile;
     * the tile's title attribute carries the exact rupee value.
     */
    protected function money(float $amount): string
    {
        $abs = abs($amount);

        return match (true) {
            $abs >= 10000000 => '₹'.$this->trim($amount / 10000000).'Cr',
            $abs >= 100000 => '₹'.$this->trim($amount / 100000).'L',
            $abs >= 1000 => '₹'.$this->trim($amount / 1000).'K',
            default => '₹'.number_format($amount, 0),
        };
    }

    protected function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    protected function exactMoney(float $amount): string
    {
        return '₹'.number_format($amount, 0);
    }

    protected function urlIf(bool $condition, string $url): ?string
    {
        return $condition ? $url : null;
    }

    /**
     * @return list<array{label: string, value: string, meta: ?string, icon: string, tone: string, url: ?string}>
     */
    protected function ownerMetrics(): array
    {
        $filters = $this->dashboardFilters();
        $stats = app(CrmDashboardService::class)->stats($filters);

        $metrics = [
            $this->metric(
                label: 'Enrolled',
                value: (string) $stats['active_students'],
                icon: 'heroicon-m-user-group',
                meta: $stats['active_batches'].' active classes',
                tone: 'primary',
                url: $this->urlIf(StudentSearchPage::canAccess(), StudentSearchPage::getUrl()),
            ),
        ];

        if (FeatureGate::enabled(LicenseFeature::Attendance)) {
            $metrics[] = $this->attendanceMetric($stats);

            if (StaffAttendancePage::canAccess()) {
                $pulse = app(DashboardOpsService::class)->todayPulse($filters, Auth::user());
                $metrics[] = $this->staffAttendanceMetric($pulse);
            }
        }

        if (FeatureGate::enabled(LicenseFeature::Enquiries)) {
            $metrics[] = $this->metric(
                label: 'New leads',
                value: (string) $stats['range_enquiries'],
                icon: 'heroicon-m-inbox-arrow-down',
                meta: $filters->rangeName(),
                tone: 'info',
                url: $this->urlIf(EnquiryResource::canViewAny(), EnquiryResource::getUrl('index')),
            );
        }

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array{label: string, value: string, meta: ?string, icon: string, tone: string, url: ?string}
     */
    protected function attendanceMetric(array $stats): array
    {
        $expected = (int) $stats['attendance_students_in_batches'];
        $present = (int) $stats['attendance_present_today'];
        $rate = $expected > 0 ? (int) round(($present / $expected) * 100) : 0;

        return $this->metric(
            label: 'Students attendance',
            value: (string) $present,
            icon: 'heroicon-m-check-circle',
            meta: $expected > 0
                ? $rate.'% of '.$expected.' · '.$stats['attendance_marked_today'].' marked'
                : 'No students in scope',
            tone: match (true) {
                $expected === 0 => 'neutral',
                $rate >= 75 => 'success',
                $rate > 0 => 'warning',
                default => 'danger',
            },
            url: $this->urlIf(AttendanceHubPage::canAccess(), AttendanceHubPage::getUrl()),
        );
    }

    /**
     * @param  array<string, mixed>  $pulse
     * @return array{label: string, value: string, meta: ?string, icon: string, tone: string, url: ?string}
     */
    protected function staffAttendanceMetric(array $pulse): array
    {
        $total = (int) ($pulse['staff_total'] ?? 0);
        $present = (int) ($pulse['staff_present_today'] ?? 0);
        $rate = $total > 0 ? (int) round(($present / $total) * 100) : 0;

        return $this->metric(
            label: 'Staff attendance',
            value: (string) $present,
            icon: 'heroicon-m-identification',
            meta: $total > 0 ? $rate.'% of '.$total.' staff present' : 'No staff in scope',
            tone: match (true) {
                $total === 0 => 'neutral',
                $rate >= 75 => 'success',
                $rate > 0 => 'warning',
                default => 'danger',
            },
            url: StaffAttendancePage::getUrl(),
        );
    }

    /**
     * @return list<array{label: string, value: string, meta: ?string, icon: string, tone: string, url: ?string}>
     */
    protected function callingMetrics(): array
    {
        // Calling pack uses Needs attention + Today pulse for live work numbers.
        return [];
    }

    /**
     * @return list<array{label: string, value: string, meta: ?string, icon: string, tone: string, url: ?string}>
     */
    protected function academicMetrics(): array
    {
        $stats = app(CrmDashboardService::class)->stats($this->dashboardFilters());
        $metrics = [];

        if (FeatureGate::enabled(LicenseFeature::Attendance)) {
            $metrics[] = $this->attendanceMetric($stats);
        }

        $metrics[] = $this->metric(
            label: 'Active classes',
            value: (string) $stats['active_batches'],
            icon: 'heroicon-m-academic-cap',
            meta: $stats['active_students'].' students enrolled',
            tone: 'primary',
            url: $this->urlIf(MyTeachingAssignmentsPage::canAccess(), MyTeachingAssignmentsPage::getUrl()),
        );

        return $metrics;
    }

    /**
     * @return list<array{label: string, value: string, meta: ?string, icon: string, tone: string, url: ?string}>
     */
    protected function financeMetrics(): array
    {
        $filters = $this->dashboardFilters();
        $service = app(CrmDashboardService::class);
        $stats = $service->stats($filters);
        $fees = $service->feeSummary($filters);
        $feesUrl = $this->urlIf(FeesHubPage::canAccess(), FeesHubPage::getUrl());

        return [
            $this->metric(
                label: 'Collected today',
                value: $this->money((float) $stats['fee_collection_today']),
                icon: 'heroicon-m-arrow-trending-up',
                meta: $this->exactMoney((float) $stats['fee_collection_today']),
                tone: 'primary',
                url: $feesUrl,
            ),
            $this->metric(
                label: 'Overdue students',
                value: (string) $fees['overdue_students_count'],
                icon: 'heroicon-m-clock',
                meta: 'Open on Fees dashboard',
                tone: $fees['overdue_students_count'] > 0 ? 'danger' : 'success',
                url: $feesUrl,
            ),
        ];
    }

    /**
     * @return list<array{label: string, value: string, meta: ?string, icon: string, tone: string, url: ?string}>
     */
    protected function messagingMetrics(): array
    {
        $stats = app(CrmDashboardService::class)->stats($this->dashboardFilters());

        return [
            $this->metric(
                label: 'Reachable students',
                value: (string) $stats['active_students'],
                icon: 'heroicon-m-user-group',
                meta: 'Enrolled in this scope',
                tone: 'primary',
                url: $this->urlIf(WhatsAppInboxPage::canAccess(), WhatsAppInboxPage::getUrl()),
            ),
            $this->metric(
                label: 'New leads',
                value: (string) $stats['range_enquiries'],
                icon: 'heroicon-m-inbox-arrow-down',
                meta: $this->dashboardFilters()->rangeName(),
                tone: 'info',
            ),
        ];
    }

    /**
     * @return list<array{label: string, value: string, meta: ?string, icon: string, tone: string, url: ?string}>
     */
    protected function defaultMetrics(): array
    {
        $staff = Auth::user();
        $metrics = [];

        if ($staff && CrmAccess::can($staff, CrmPermission::CasesView)) {
            $metrics[] = $this->metric(
                label: 'Open cases',
                value: (string) CrmNavBadges::myCasesOpen($staff),
                icon: 'heroicon-m-briefcase',
                meta: 'Assigned to you',
                tone: 'primary',
                url: MyMeetingsPage::getUrl(),
            );
        }

        if ($staff && FeatureGate::enabled(LicenseFeature::Enquiries)) {
            $metrics[] = $this->metric(
                label: 'Follow-ups due',
                value: (string) CrmNavBadges::followUpsDue(),
                icon: 'heroicon-m-bell-alert',
                meta: 'Today and overdue',
                tone: CrmNavBadges::followUpsDue() > 0 ? 'warning' : 'success',
                url: $this->urlIf(FollowUpsPage::canAccess(), FollowUpsPage::getUrl()),
            );
        }

        return $metrics;
    }

    /**
     * Drops tiles the viewer cannot open, so no shortcut leads to a 403.
     *
     * @param  list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>  $actions
     * @return list<array{label: string, description: string, icon: string, url: string}>
     */
    protected function visibleActions(array $actions): array
    {
        $user = Auth::user();

        return array_values(array_filter(
            $actions,
            function (array $action) use ($user): bool {
                if (($action['feature'] ?? null) !== null && ! FeatureGate::enabled($action['feature'])) {
                    return false;
                }

                if (isset($action['can']) && is_callable($action['can']) && ! $action['can']($user)) {
                    return false;
                }

                return true;
            },
        ));
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function actionsForPack(string $pack): array
    {
        return match ($pack) {
            'owner' => $this->ownerActions(),
            'calling' => $this->callingActions(),
            'academic' => $this->academicActions(),
            'finance' => $this->financeActions(),
            'messaging' => $this->messagingActions(),
            default => $this->defaultStaffActions(),
        };
    }

    /**
     * @return array{label: string, description: string, icon: string, url: string, feature: ?LicenseFeature, can: callable}
     */
    protected function reportsAction(string $description): array
    {
        return [
            'label' => 'Reports',
            'description' => $description,
            'icon' => 'heroicon-o-document-chart-bar',
            'url' => ReportsPage::getUrl(),
            'feature' => LicenseFeature::Reports,
            'can' => fn ($user): bool => ReportsPage::canAccess(),
        ];
    }

    /**
     * @return array{label: string, description: string, icon: string, url: string, feature: ?LicenseFeature, can: callable}
     */
    protected function myWorkAction(): array
    {
        return [
            'label' => 'My work',
            'description' => 'Meetings, cases, and assigned calls',
            'icon' => 'heroicon-o-briefcase',
            'url' => MyMeetingsPage::getUrl(),
            'feature' => null,
            'can' => fn ($user): bool => $user && CrmAccess::can($user, CrmPermission::CasesView),
        ];
    }

    /**
     * @return array{label: string, description: string, icon: string, url: string, feature: ?LicenseFeature, can: callable}
     */
    protected function findStudentAction(string $description): array
    {
        return [
            'label' => 'Find student',
            'description' => $description,
            'icon' => 'heroicon-o-magnifying-glass',
            'url' => StudentSearchPage::getUrl(),
            'feature' => null,
            'can' => fn ($user): bool => StudentSearchPage::canAccess(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function ownerActions(): array
    {
        return [
            [
                'label' => 'All Leads',
                'description' => 'Full enquiry pipeline',
                'icon' => 'heroicon-o-inbox-stack',
                'url' => EnquiryResource::getUrl('index'),
                'feature' => LicenseFeature::Enquiries,
            ],
            [
                'label' => 'Admissions',
                'description' => 'Review pending forms',
                'icon' => 'heroicon-o-clipboard-document-check',
                'url' => AdmissionResource::getUrl('index'),
                'feature' => LicenseFeature::Admissions,
            ],
            [
                'label' => 'Attendance',
                'description' => 'Live punches and class marking',
                'icon' => 'heroicon-o-calendar-days',
                'url' => AttendanceHubPage::getUrl(),
                'feature' => LicenseFeature::Attendance,
                'can' => fn ($user): bool => AttendanceHubPage::canAccess(),
            ],
            [
                'label' => 'Fees',
                'description' => 'Collections and bulk charges',
                'icon' => 'heroicon-o-banknotes',
                'url' => FeesHubPage::getUrl(),
                'feature' => LicenseFeature::Fees,
                'can' => fn ($user): bool => FeesHubPage::canAccess(),
            ],
            $this->reportsAction('Export CSV & PDF'),
            [
                'label' => 'All cases',
                'description' => 'Institute-wide support cases',
                'icon' => 'heroicon-o-rectangle-stack',
                'url' => MyMeetingsPage::getUrl(['tab' => 'all_cases']),
                'feature' => LicenseFeature::Cases,
                'can' => fn ($user): bool => $user && $user->hasRole(RoleName::SuperAdmin->value),
            ],
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function callingActions(): array
    {
        return [
            [
                'label' => 'Call Queue',
                'description' => 'Start calling now',
                'icon' => 'heroicon-o-bars-3-bottom-left',
                'url' => CallQueuePage::getUrl(),
                'feature' => LicenseFeature::Calls,
                'can' => fn ($user): bool => CallQueuePage::canAccess(),
            ],
            [
                'label' => 'Assigned to Call',
                'description' => 'Your admin-assigned calling list',
                'icon' => 'heroicon-o-user-group',
                'url' => MyLeadsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
                'can' => fn ($user): bool => MyLeadsPage::canAccess(),
            ],
            [
                'label' => 'Follow-ups',
                'description' => 'Due today',
                'icon' => 'heroicon-o-bell-alert',
                'url' => FollowUpsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
                'can' => fn ($user): bool => FollowUpsPage::canAccess(),
            ],
            $this->findStudentAction('Open any profile'),
            $this->myWorkAction(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function academicActions(): array
    {
        return [
            [
                'label' => 'Attendance',
                'description' => 'Live punches and class marking',
                'icon' => 'heroicon-o-calendar-days',
                'url' => AttendanceHubPage::getUrl(),
                'feature' => LicenseFeature::Attendance,
                'can' => fn ($user): bool => AttendanceHubPage::canAccess(),
            ],
            [
                'label' => 'Homework',
                'description' => 'Submit, review, and check',
                'icon' => 'heroicon-o-book-open',
                'url' => HomeworkPage::getUrl(),
                'feature' => LicenseFeature::Homework,
                'can' => fn ($user): bool => HomeworkPage::canAccess(),
            ],
            [
                'label' => CrmMenuLabels::examResults(),
                'description' => 'Enter, review, and publish marks',
                'icon' => 'heroicon-o-clipboard-document-list',
                'url' => ActivitySessionResource::getUrl('index'),
                'feature' => LicenseFeature::Marks,
                'can' => fn ($user): bool => ActivitySessionResource::canViewAny(),
            ],
            [
                'label' => 'My classes',
                'description' => 'Your teaching assignments',
                'icon' => 'heroicon-o-academic-cap',
                'url' => MyTeachingAssignmentsPage::getUrl(),
                'feature' => null,
                'can' => fn ($user): bool => MyTeachingAssignmentsPage::canAccess(),
            ],
            $this->reportsAction('Academic exports'),
            $this->myWorkAction(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function financeActions(): array
    {
        return [
            [
                'label' => 'Fees',
                'description' => 'Dashboard, bulk charges, adjustments',
                'icon' => 'heroicon-o-banknotes',
                'url' => FeesHubPage::getUrl(),
                'feature' => LicenseFeature::Fees,
                'can' => fn ($user): bool => FeesHubPage::canAccess(),
            ],
            $this->findStudentAction('Collect from a profile'),
            $this->reportsAction('Fee and admission exports'),
            $this->myWorkAction(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function messagingActions(): array
    {
        return [
            [
                'label' => 'WhatsApp inbox',
                'description' => 'Replies and live conversations',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'url' => WhatsAppInboxPage::getUrl(),
                'feature' => LicenseFeature::WhatsApp,
                'can' => fn ($user): bool => WhatsAppInboxPage::canAccess(),
            ],
            $this->findStudentAction('Open any profile'),
            $this->myWorkAction(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function defaultStaffActions(): array
    {
        return [
            [
                'label' => 'Assigned to Call',
                'description' => 'Your admin-assigned calling list',
                'icon' => 'heroicon-o-user-group',
                'url' => MyLeadsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
                'can' => fn ($user): bool => MyLeadsPage::canAccess(),
            ],
            [
                'label' => 'Call Queue',
                'description' => 'Start calling now',
                'icon' => 'heroicon-o-bars-3-bottom-left',
                'url' => CallQueuePage::getUrl(),
                'feature' => LicenseFeature::Calls,
                'can' => fn ($user): bool => CallQueuePage::canAccess(),
            ],
            $this->findStudentAction('Open any profile'),
            [
                'label' => 'Follow-ups',
                'description' => 'Due today',
                'icon' => 'heroicon-o-bell-alert',
                'url' => FollowUpsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
                'can' => fn ($user): bool => FollowUpsPage::canAccess(),
            ],
            $this->myWorkAction(),
        ];
    }
}
