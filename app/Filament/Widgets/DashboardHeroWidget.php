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
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Services\CrmDashboardService;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use App\Support\InstituteSettings;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DashboardHeroWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -10;

    protected string $view = 'filament.widgets.dashboard-hero';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $stats = app(CrmDashboardService::class)->stats();
        $branding = InstituteSettings::forDocuments();
        $user = Auth::user();
        $pack = CrmNavigation::navRolePack($user);

        $filterActions = fn (array $actions): array => array_values(array_filter(
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

        return [
            'userName' => $user?->name ?? 'there',
            'instituteName' => $branding['name'],
            'tagline' => $branding['tagline'],
            'todayLabel' => now()->format('l, j F Y'),
            'isOwner' => $pack === 'owner',
            'showFeesSummary' => FeatureGate::enabled(LicenseFeature::Fees)
                && CrmAccess::canViewFees($user),
            'showEnquirySummary' => FeatureGate::enabled(LicenseFeature::Enquiries),
            'showAttendanceSummary' => FeatureGate::enabled(LicenseFeature::Attendance),
            'showAdmissionsSummary' => FeatureGate::enabled(LicenseFeature::Admissions),
            'todayEnquiries' => $stats['today_enquiries'],
            'feeToday' => $stats['fee_collection_today'],
            'pendingAdmissions' => $stats['pending_admissions'],
            'pendingFeesTotal' => $stats['pending_fees_total'],
            'activeStudents' => $stats['active_students'],
            'presentToday' => $stats['attendance_present_today'],
            'attendanceMarkedToday' => $stats['attendance_marked_today'],
            'quickActions' => $filterActions($this->actionsForPack($pack)),
        ];
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
            default => $this->defaultStaffActions(),
        };
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, feature?: ?LicenseFeature, can?: callable}>
     */
    protected function ownerActions(): array
    {
        $actions = [
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
            [
                'label' => 'Reports',
                'description' => 'Export CSV & PDF',
                'icon' => 'heroicon-o-document-chart-bar',
                'url' => ReportsPage::getUrl(),
                'feature' => LicenseFeature::Reports,
            ],
        ];

        $user = Auth::user();
        if ($user && $user->hasRole(RoleName::SuperAdmin->value)) {
            $actions[] = [
                'label' => 'All cases',
                'description' => 'Institute-wide support cases',
                'icon' => 'heroicon-o-rectangle-stack',
                'url' => MyMeetingsPage::getUrl(['tab' => 'all_cases']),
                'feature' => null,
            ];
        }

        return $actions;
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
            ],
            [
                'label' => 'Assigned to Call',
                'description' => 'Your admin-assigned calling list',
                'icon' => 'heroicon-o-user-group',
                'url' => MyLeadsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
            ],
            [
                'label' => 'Follow-ups',
                'description' => 'Due today',
                'icon' => 'heroicon-o-bell-alert',
                'url' => FollowUpsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
            ],
            [
                'label' => 'Find student',
                'description' => 'Open any profile',
                'icon' => 'heroicon-o-magnifying-glass',
                'url' => StudentSearchPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
                'can' => fn ($user): bool => StudentSearchPage::canAccess(),
            ],
            [
                'label' => 'My work',
                'description' => 'Meetings, cases, and assigned calls',
                'icon' => 'heroicon-o-briefcase',
                'url' => MyMeetingsPage::getUrl(),
                'feature' => null,
                'can' => fn ($user): bool => $user && CrmAccess::can($user, CrmPermission::CasesView),
            ],
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
                'label' => 'My classes',
                'description' => 'Your teaching assignments',
                'icon' => 'heroicon-o-academic-cap',
                'url' => MyTeachingAssignmentsPage::getUrl(),
                'feature' => null,
                'can' => fn ($user): bool => MyTeachingAssignmentsPage::canAccess(),
            ],
            [
                'label' => 'Reports',
                'description' => 'Academic exports',
                'icon' => 'heroicon-o-document-chart-bar',
                'url' => ReportsPage::getUrl(),
                'feature' => LicenseFeature::Reports,
            ],
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
            [
                'label' => 'Find student',
                'description' => 'Collect from a profile',
                'icon' => 'heroicon-o-magnifying-glass',
                'url' => StudentSearchPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
                'can' => fn ($user): bool => StudentSearchPage::canAccess(),
            ],
            [
                'label' => 'Reports',
                'description' => 'Fee and admission exports',
                'icon' => 'heroicon-o-document-chart-bar',
                'url' => ReportsPage::getUrl(),
                'feature' => LicenseFeature::Reports,
            ],
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
            ],
            [
                'label' => 'Call Queue',
                'description' => 'Start calling now',
                'icon' => 'heroicon-o-bars-3-bottom-left',
                'url' => CallQueuePage::getUrl(),
                'feature' => LicenseFeature::Calls,
            ],
            [
                'label' => 'Find student',
                'description' => 'Open any profile',
                'icon' => 'heroicon-o-magnifying-glass',
                'url' => StudentSearchPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
                'can' => fn ($user): bool => StudentSearchPage::canAccess(),
            ],
            [
                'label' => 'Follow-ups',
                'description' => 'Due today',
                'icon' => 'heroicon-o-bell-alert',
                'url' => FollowUpsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
            ],
            [
                'label' => 'My work',
                'description' => 'Meetings, cases, and assigned calls',
                'icon' => 'heroicon-o-briefcase',
                'url' => MyMeetingsPage::getUrl(),
                'feature' => null,
                'can' => fn ($user): bool => $user && CrmAccess::can($user, CrmPermission::CasesView),
            ],
        ];
    }
}
