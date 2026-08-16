<?php

namespace App\Filament\Widgets;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Pages\AttendanceHubPage;
use App\Filament\Pages\CallQueuePage;
use App\Filament\Pages\FeesDashboardPage;
use App\Filament\Pages\FeesHubPage;
use App\Filament\Pages\FollowUpsPage;
use App\Filament\Pages\HomeworkPage;
use App\Filament\Pages\HomeworkReviewPage;
use App\Filament\Pages\MiscChargeAdjustmentRequestsPage;
use App\Filament\Pages\MyLeadsPage;
use App\Filament\Pages\MyMeetingsPage;
use App\Filament\Pages\PaymentCancellationRequestsPage;
use App\Filament\Pages\WhatsAppHubPage;
use App\Filament\Pages\WhatsAppInboxPage;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
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
        $packs = CrmNavigation::navRolePacks($user);
        $data = app(DashboardOpsService::class)->attentionSnapshot($this->dashboardFilters(), $user);

        return [
            'heading' => 'Needs attention',
            'subheading' => 'Tap a tile to open the work queue',
            'poll' => '15s',
            'tiles' => $this->visibleTiles($this->mergedTiles($packs, $data, $user)),
        ];
    }

    /**
     * @param  list<array{key?: string, label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>  $tiles
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
     * @param  list<string>  $packs
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function mergedTiles(array $packs, array $data, $user): array
    {
        $merged = [];
        $seen = [];

        foreach ($packs as $pack) {
            foreach ($this->tilesForPack($pack, $data, $user) as $tile) {
                $key = $tile['key'] ?? $tile['label'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $merged[] = $tile;
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function tilesForPack(string $pack, array $data, $user): array
    {
        return match ($pack) {
            'owner' => $this->ownerTiles($data),
            'calling' => $this->callingTiles($data),
            'admissions' => $this->admissionsTiles($data),
            'academic' => $this->academicTiles($data),
            'finance' => $this->financeTiles($data, $user),
            'messaging' => $this->messagingTiles($data, $user),
            default => $this->defaultTiles($data, $user),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function ownerTiles(array $data): array
    {
        return [
            [
                'key' => 'admissions',
                'label' => 'Admissions',
                'value' => (string) $data['admissions_pending'],
                'meta' => 'Awaiting review',
                'tone' => $data['admissions_pending'] > 0 ? 'warning' : 'neutral',
                'url' => AdmissionResource::canViewAny() ? AdmissionResource::getUrl('index') : null,
                'show' => FeatureGate::enabled(LicenseFeature::Admissions) && AdmissionResource::canViewAny(),
            ],
            [
                'key' => 'fee_waives',
                'label' => 'Fee waives',
                'value' => (string) $data['fee_adjustments_pending'],
                'meta' => 'Discount / waive',
                'tone' => $data['fee_adjustments_pending'] > 0 ? 'warning' : 'neutral',
                'url' => MiscChargeAdjustmentRequestsPage::canAccess() ? MiscChargeAdjustmentRequestsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Fees) && MiscChargeAdjustmentRequestsPage::canAccess(),
            ],
            [
                'key' => 'payment_cancels',
                'label' => 'Payment cancels',
                'value' => (string) ($data['payment_cancellations_pending'] ?? 0),
                'meta' => 'Awaiting approve',
                'tone' => ($data['payment_cancellations_pending'] ?? 0) > 0 ? 'danger' : 'neutral',
                'url' => PaymentCancellationRequestsPage::canAccess() ? PaymentCancellationRequestsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Fees) && PaymentCancellationRequestsPage::canAccess(),
            ],
            [
                'key' => 'homework_review',
                'label' => 'Homework',
                'value' => (string) $data['homework_awaiting_approve'],
                'meta' => 'Awaiting approve',
                'tone' => $data['homework_awaiting_approve'] > 0 ? 'warning' : 'neutral',
                'url' => HomeworkReviewPage::canAccess() ? HomeworkReviewPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Homework) && HomeworkReviewPage::canAccess(),
            ],
            [
                'key' => 'follow_ups',
                'label' => 'Follow-ups',
                'value' => (string) $data['follow_ups_due'],
                'meta' => 'Due / overdue',
                'tone' => $data['follow_ups_due'] > 0 ? 'warning' : 'neutral',
                'url' => FollowUpsPage::canAccess() ? FollowUpsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries) && FollowUpsPage::canAccess(),
            ],
            [
                'key' => 'uncalled',
                'label' => 'Uncalled',
                'value' => (string) $data['uncalled_leads'],
                'meta' => 'Assigned leads',
                'tone' => $data['uncalled_leads'] > 0 ? 'warning' : 'neutral',
                'url' => MyLeadsPage::canAccess() ? MyLeadsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries),
            ],
            [
                'key' => 'open_cases',
                'label' => 'Open cases',
                'value' => (string) $data['open_cases'],
                'meta' => 'Institute-wide',
                'tone' => $data['open_cases'] > 0 ? 'info' : 'neutral',
                'url' => MyMeetingsPage::getUrl(['tab' => 'all_cases']),
                'show' => FeatureGate::enabled(LicenseFeature::Cases),
            ],
            [
                'key' => 'attendance',
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
     * @return list<array{key: string, label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function callingTiles(array $data): array
    {
        return [
            [
                'key' => 'queue',
                'label' => 'In queue',
                'value' => (string) $data['queue_count'],
                'meta' => 'Ready to call now',
                'tone' => $data['queue_count'] > 0 ? 'warning' : 'success',
                'url' => CallQueuePage::canAccess() ? CallQueuePage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Calls) && CallQueuePage::canAccess(),
            ],
            [
                'key' => 'follow_ups',
                'label' => 'Follow-ups due',
                'value' => (string) $data['follow_ups_due'],
                'meta' => 'Visits + call callbacks',
                'tone' => $data['follow_ups_due'] > 0 ? 'danger' : 'success',
                'url' => FollowUpsPage::canAccess() ? FollowUpsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries) && FollowUpsPage::canAccess(),
            ],
            [
                'key' => 'uncalled',
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
     * @return list<array{key: string, label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function admissionsTiles(array $data): array
    {
        return [
            [
                'key' => 'admissions',
                'label' => 'Admissions',
                'value' => (string) $data['admissions_pending'],
                'meta' => 'Awaiting review',
                'tone' => $data['admissions_pending'] > 0 ? 'warning' : 'success',
                'url' => AdmissionResource::canViewAny() ? AdmissionResource::getUrl('index') : null,
                'show' => FeatureGate::enabled(LicenseFeature::Admissions) && AdmissionResource::canViewAny(),
            ],
            [
                'key' => 'follow_ups',
                'label' => 'Follow-ups due',
                'value' => (string) $data['follow_ups_due'],
                'meta' => 'Due today and overdue',
                'tone' => $data['follow_ups_due'] > 0 ? 'warning' : 'success',
                'url' => FollowUpsPage::canAccess() ? FollowUpsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries) && FollowUpsPage::canAccess(),
            ],
            [
                'key' => 'uncalled',
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
     * @return list<array{key: string, label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function academicTiles(array $data): array
    {
        return [
            [
                'key' => 'attendance',
                'label' => 'Students attendance',
                'value' => $data['attendance_coverage_pct'].'%',
                'meta' => $data['attendance_unmarked'].' still unmarked',
                'tone' => $data['attendance_unmarked'] > 0 ? 'warning' : 'success',
                'url' => AttendanceHubPage::canAccess() ? AttendanceHubPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Attendance),
            ],
            [
                'key' => 'homework_review',
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
     * Finance staff: overdue + collected-today. Waive/cancel approvals stay Super Admin only.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function financeTiles(array $data, $user): array
    {
        $pulse = app(DashboardOpsService::class)->todayPulse($this->dashboardFilters(), $user);
        $collected = (float) ($pulse['fees_amount_today'] ?? 0);

        return [
            [
                'key' => 'overdue',
                'label' => 'Overdue students',
                'value' => (string) $data['overdue_students'],
                'meta' => 'Open on Fees dashboard',
                'tone' => $data['overdue_students'] > 0 ? 'danger' : 'success',
                'url' => FeesHubPage::canAccess() ? FeesHubPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Fees) && FeesHubPage::canAccess(),
            ],
            [
                'key' => 'fees_today',
                'label' => 'Collected today',
                'value' => '₹'.number_format($collected, $collected < 1 && $collected > 0 ? 2 : 0),
                'meta' => ((int) ($pulse['fees_payments_today'] ?? 0)).' payments',
                'tone' => 'success',
                'url' => FeesDashboardPage::canAccess() ? FeesDashboardPage::getUrl() : (FeesHubPage::canAccess() ? FeesHubPage::getUrl() : null),
                'show' => FeatureGate::enabled(LicenseFeature::Fees) && CrmAccess::canViewFees($user),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function messagingTiles(array $data, $user): array
    {
        $inboxUrl = WhatsAppInboxPage::canAccess() ? WhatsAppInboxPage::getUrl() : null;
        $hubUrl = WhatsAppHubPage::canAccess() ? WhatsAppHubPage::getUrl() : null;
        $campaignsUrl = WhatsAppCampaignResource::canAccess() ? WhatsAppCampaignResource::getUrl('index') : null;

        return [
            [
                'key' => 'whatsapp_inbox',
                'label' => 'WhatsApp inbox',
                'value' => 'Open',
                'meta' => 'Replies and live chats',
                'tone' => 'info',
                'url' => $inboxUrl ?? $hubUrl,
                'show' => FeatureGate::enabled(LicenseFeature::WhatsApp) && ($inboxUrl || $hubUrl),
            ],
            [
                'key' => 'whatsapp_campaigns',
                'label' => 'Campaigns',
                'value' => 'Open',
                'meta' => 'Send and track campaigns',
                'tone' => 'primary',
                'url' => $campaignsUrl ?? $hubUrl,
                'show' => FeatureGate::enabled(LicenseFeature::WhatsApp) && ($campaignsUrl || $hubUrl),
            ],
            [
                'key' => 'open_cases',
                'label' => 'Open cases',
                'value' => (string) $data['open_cases'],
                'meta' => 'Assigned to you',
                'tone' => $data['open_cases'] > 0 ? 'primary' : 'success',
                'url' => MyMeetingsPage::getUrl(),
                'show' => $user && CrmAccess::can($user, CrmPermission::CasesView) && FeatureGate::enabled(LicenseFeature::Cases),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function defaultTiles(array $data, $user): array
    {
        return [
            [
                'key' => 'follow_ups',
                'label' => 'Follow-ups due',
                'value' => (string) $data['follow_ups_due'],
                'meta' => 'Due today and overdue',
                'tone' => $data['follow_ups_due'] > 0 ? 'warning' : 'success',
                'url' => FollowUpsPage::canAccess() ? FollowUpsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries) && FollowUpsPage::canAccess(),
            ],
            [
                'key' => 'open_cases',
                'label' => 'Open cases',
                'value' => (string) $data['open_cases'],
                'meta' => 'Assigned to you',
                'tone' => $data['open_cases'] > 0 ? 'primary' : 'success',
                'url' => MyMeetingsPage::getUrl(),
                'show' => $user && CrmAccess::can($user, CrmPermission::CasesView) && FeatureGate::enabled(LicenseFeature::Cases),
            ],
            [
                'key' => 'open_meetings',
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
