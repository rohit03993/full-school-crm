<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AttendancePage;
use App\Filament\Pages\CallQueuePage;
use App\Filament\Pages\FollowUpsPage;
use App\Filament\Pages\MyLeadsPage;
use App\Filament\Pages\ReportsPage;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Enums\RoleName;
use App\Enums\LicenseFeature;
use App\Services\CrmDashboardService;
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
        $isOwner = $user?->hasRole(RoleName::SuperAdmin->value) ?? false;

        $ownerActions = [
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
                'description' => 'Mark batch attendance',
                'icon' => 'heroicon-o-calendar-days',
                'url' => AttendancePage::getUrl(),
                'feature' => LicenseFeature::Attendance,
            ],
            [
                'label' => 'Reports',
                'description' => 'Export CSV & PDF',
                'icon' => 'heroicon-o-document-chart-bar',
                'url' => ReportsPage::getUrl(),
                'feature' => LicenseFeature::Reports,
            ],
        ];

        $staffActions = [
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
                'label' => 'Search Student',
                'description' => 'Open any profile',
                'icon' => 'heroicon-o-magnifying-glass',
                'url' => StudentSearchPage::getUrl(),
                'feature' => null,
            ],
            [
                'label' => 'Follow-ups',
                'description' => 'Due today',
                'icon' => 'heroicon-o-bell-alert',
                'url' => FollowUpsPage::getUrl(),
                'feature' => LicenseFeature::Enquiries,
            ],
        ];

        $filterActions = fn (array $actions): array => array_values(array_filter(
            $actions,
            fn (array $action): bool => $action['feature'] === null || FeatureGate::enabled($action['feature']),
        ));

        return [
            'userName' => $user?->name ?? 'there',
            'instituteName' => $branding['name'],
            'tagline' => $branding['tagline'],
            'todayLabel' => now()->format('l, j F Y'),
            'isOwner' => $isOwner,
            'showFeesSummary' => FeatureGate::enabled(LicenseFeature::Fees),
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
            'quickActions' => $isOwner ? $filterActions($ownerActions) : $filterActions($staffActions),
        ];
    }
}
