<?php

namespace App\Filament\Widgets;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Pages\AttendanceHubPage;
use App\Filament\Pages\CallQueuePage;
use App\Filament\Pages\FeesHubPage;
use App\Filament\Pages\FollowUpsPage;
use App\Filament\Pages\HomeworkPage;
use App\Filament\Pages\HomeworkReviewPage;
use App\Filament\Pages\MiscChargeAdjustmentRequestsPage;
use App\Filament\Pages\MyLeadsPage;
use App\Filament\Pages\MyMeetingsPage;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Services\DashboardOpsService;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DashboardAttentionWidget extends Widget
{
    use UsesDashboardFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = -9;

    protected string $view = 'filament.widgets.dashboard-ops-strip';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $pack = CrmNavigation::navRolePack($user);
        $data = app(DashboardOpsService::class)->attentionSnapshot($this->dashboardFilters(), $user);

        return [
            'heading' => 'Needs attention',
            'subheading' => 'Tap a tile to open the work queue',
            'poll' => '15s',
            'tiles' => $this->visibleTiles($this->tilesForPack($pack, $data, $user)),
        ];
    }

    /**
     * @param  list<array{label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>  $tiles
     * @return list<array{label: string, value: string, meta: ?string, tone: string, url: ?string}>
     */
    protected function visibleTiles(array $tiles): array
    {
        return array_values(array_map(
            fn (array $tile): array => [
                'label' => $tile['label'],
                'value' => $tile['value'],
                'meta' => $tile['meta'],
                'tone' => $tile['tone'],
                'url' => $tile['url'],
            ],
            array_filter($tiles, fn (array $tile): bool => $tile['show']),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function tilesForPack(string $pack, array $data, $user): array
    {
        return match ($pack) {
            'owner' => $this->ownerTiles($data),
            'calling' => $this->callingTiles($data),
            'academic' => $this->academicTiles($data),
            'finance' => $this->financeTiles($data),
            'messaging' => [],
            default => $this->defaultTiles($data, $user),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function ownerTiles(array $data): array
    {
        return [
            [
                'label' => 'Admissions',
                'value' => (string) $data['admissions_pending'],
                'meta' => 'Awaiting review',
                'tone' => $data['admissions_pending'] > 0 ? 'warning' : 'neutral',
                'url' => AdmissionResource::canViewAny() ? AdmissionResource::getUrl('index') : null,
                'show' => FeatureGate::enabled(LicenseFeature::Admissions) && AdmissionResource::canViewAny(),
            ],
            [
                'label' => 'Fee waives',
                'value' => (string) $data['fee_adjustments_pending'],
                'meta' => 'Discount / waive',
                'tone' => $data['fee_adjustments_pending'] > 0 ? 'warning' : 'neutral',
                'url' => MiscChargeAdjustmentRequestsPage::canAccess() ? MiscChargeAdjustmentRequestsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Fees) && MiscChargeAdjustmentRequestsPage::canAccess(),
            ],
            [
                'label' => 'Homework',
                'value' => (string) $data['homework_awaiting_approve'],
                'meta' => 'Awaiting approve',
                'tone' => $data['homework_awaiting_approve'] > 0 ? 'warning' : 'neutral',
                'url' => HomeworkReviewPage::canAccess() ? HomeworkReviewPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Homework) && HomeworkReviewPage::canAccess(),
            ],
            [
                'label' => 'Follow-ups',
                'value' => (string) $data['follow_ups_due'],
                'meta' => 'Due / overdue',
                'tone' => $data['follow_ups_due'] > 0 ? 'warning' : 'neutral',
                'url' => FollowUpsPage::canAccess() ? FollowUpsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries) && FollowUpsPage::canAccess(),
            ],
            [
                'label' => 'Uncalled',
                'value' => (string) $data['uncalled_leads'],
                'meta' => 'Assigned leads',
                'tone' => $data['uncalled_leads'] > 0 ? 'warning' : 'neutral',
                'url' => MyLeadsPage::canAccess() ? MyLeadsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries),
            ],
            [
                'label' => 'Open cases',
                'value' => (string) $data['open_cases'],
                'meta' => 'Institute-wide',
                'tone' => $data['open_cases'] > 0 ? 'info' : 'neutral',
                'url' => MyMeetingsPage::getUrl(['tab' => 'all_cases']),
                'show' => FeatureGate::enabled(LicenseFeature::Cases),
            ],
            [
                'label' => 'Students attendance',
                'value' => (string) $data['attendance_unmarked'],
                'meta' => $data['attendance_coverage_pct'].'% marked',
                'tone' => $data['attendance_unmarked'] > 0 ? 'warning' : 'success',
                'url' => AttendanceHubPage::canAccess() ? AttendanceHubPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Attendance),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function callingTiles(array $data): array
    {
        return [
            [
                'label' => 'In queue',
                'value' => (string) $data['queue_count'],
                'meta' => 'Ready to call now',
                'tone' => $data['queue_count'] > 0 ? 'warning' : 'success',
                'url' => CallQueuePage::canAccess() ? CallQueuePage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Calls) && CallQueuePage::canAccess(),
            ],
            [
                'label' => 'Follow-ups due',
                'value' => (string) $data['follow_ups_due'],
                'meta' => 'Visits + call callbacks',
                'tone' => $data['follow_ups_due'] > 0 ? 'danger' : 'success',
                'url' => FollowUpsPage::canAccess() ? FollowUpsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries) && FollowUpsPage::canAccess(),
            ],
            [
                'label' => 'Uncalled',
                'value' => (string) $data['uncalled_leads'],
                'meta' => 'Assigned — not yet dialled',
                'tone' => $data['uncalled_leads'] > 0 ? 'info' : 'success',
                'url' => MyLeadsPage::canAccess() ? MyLeadsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries) && MyLeadsPage::canAccess(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function academicTiles(array $data): array
    {
        return [
            [
                'label' => 'Students attendance',
                'value' => $data['attendance_coverage_pct'].'%',
                'meta' => $data['attendance_unmarked'].' still unmarked',
                'tone' => $data['attendance_unmarked'] > 0 ? 'warning' : 'success',
                'url' => AttendanceHubPage::canAccess() ? AttendanceHubPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Attendance),
            ],
            [
                'label' => 'Homework review',
                'value' => (string) $data['homework_awaiting_approve'],
                'meta' => 'Awaiting admin approve',
                'tone' => $data['homework_awaiting_approve'] > 0 ? 'warning' : 'success',
                'url' => HomeworkReviewPage::canAccess() ? HomeworkReviewPage::getUrl() : (HomeworkPage::canAccess() ? HomeworkPage::getUrl() : null),
                'show' => FeatureGate::enabled(LicenseFeature::Homework) && (HomeworkReviewPage::canAccess() || HomeworkPage::canAccess()),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function financeTiles(array $data): array
    {
        return [
            [
                'label' => 'Overdue students',
                'value' => (string) $data['overdue_students'],
                'meta' => 'Open on Fees dashboard',
                'tone' => $data['overdue_students'] > 0 ? 'danger' : 'success',
                'url' => FeesHubPage::canAccess() ? FeesHubPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Fees) && FeesHubPage::canAccess(),
            ],
            [
                'label' => 'Fee waives',
                'value' => (string) $data['fee_adjustments_pending'],
                'meta' => 'Discount / waive requests',
                'tone' => $data['fee_adjustments_pending'] > 0 ? 'warning' : 'success',
                'url' => MiscChargeAdjustmentRequestsPage::canAccess() ? MiscChargeAdjustmentRequestsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Fees) && MiscChargeAdjustmentRequestsPage::canAccess(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function defaultTiles(array $data, $user): array
    {
        return [
            [
                'label' => 'Follow-ups due',
                'value' => (string) $data['follow_ups_due'],
                'meta' => 'Due today and overdue',
                'tone' => $data['follow_ups_due'] > 0 ? 'warning' : 'success',
                'url' => FollowUpsPage::canAccess() ? FollowUpsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries) && FollowUpsPage::canAccess(),
            ],
            [
                'label' => 'Open cases',
                'value' => (string) $data['open_cases'],
                'meta' => 'Assigned to you',
                'tone' => $data['open_cases'] > 0 ? 'primary' : 'success',
                'url' => MyMeetingsPage::getUrl(),
                'show' => $user && CrmAccess::can($user, CrmPermission::CasesView) && FeatureGate::enabled(LicenseFeature::Cases),
            ],
            [
                'label' => 'Open meetings',
                'value' => (string) $data['open_meetings'],
                'meta' => 'Your visit meetings',
                'tone' => $data['open_meetings'] > 0 ? 'info' : 'success',
                'url' => MyMeetingsPage::getUrl(),
                'show' => true,
            ],
        ];
    }
}
