<?php

namespace App\Filament\Widgets;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Pages\AttendanceHubPage;
use App\Filament\Pages\FeesDashboardPage;
use App\Filament\Pages\FollowUpsPage;
use App\Filament\Pages\MyLeadsPage;
use App\Filament\Pages\MyMeetingsPage;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Services\DashboardOpsService;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DashboardAnalyticsWidget extends Widget
{
    use UsesDashboardFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = -7;

    protected string $view = 'filament.widgets.dashboard-analytics';

    protected int | string | array $columnSpan = 'full';

    public bool $showAnalytics = true;

    public string $trendMetric = 'leads';

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user
            && CrmAccess::can($user, CrmPermission::DashboardOwnerStats);
    }

    public function toggleAnalytics(): void
    {
        $this->showAnalytics = ! $this->showAnalytics;
    }

    public function setTrendMetric(string $metric): void
    {
        if (in_array($metric, ['leads', 'fees', 'admissions', 'calls'], true)) {
            $this->trendMetric = $metric;
        }
    }

    public function mount(): void
    {
        $tabs = $this->trendTabs();
        $keys = array_column($tabs, 'key');

        if ($keys !== [] && ! in_array($this->trendMetric, $keys, true)) {
            $this->trendMetric = $keys[0];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $ops = app(DashboardOpsService::class);
        $filters = $this->dashboardFilters();
        $pulse = $ops->todayPulse($filters, $user);
        $attention = $ops->attentionSnapshot($filters, $user);
        $series = $ops->lastSevenDaysSeries();
        $tabs = $this->trendTabs();
        $keys = array_column($tabs, 'key');
        $metric = in_array($this->trendMetric, $keys, true)
            ? $this->trendMetric
            : ($keys[0] ?? 'leads');

        return [
            'kpis' => $this->kpis($pulse, $attention, $user),
            'trendTabs' => $tabs,
            'trendMetric' => $metric,
            'trendColumns' => $this->trendColumns($series, $metric),
            'trendFoot' => $this->trendFoot($metric),
            'mixRows' => $this->mixRows($pulse, $attention),
            'funnelSteps' => $this->funnelSteps($attention),
            'showAnalytics' => $this->showAnalytics,
            'poll' => '30s',
        ];
    }

    /**
     * @param  array<string, mixed>  $pulse
     * @param  array<string, mixed>  $attention
     * @return list<array{label: string, value: string, sub: string, tone: string, url: ?string, show: bool}>
     */
    protected function kpis(array $pulse, array $attention, $user): array
    {
        $canFees = FeatureGate::enabled(LicenseFeature::Fees) && CrmAccess::canViewFees($user);

        return array_values(array_filter([
            [
                'label' => 'Today ₹',
                'value' => $this->money((float) ($pulse['fees_amount_today'] ?? 0)),
                'sub' => $this->money((float) ($pulse['fees_amount_week'] ?? 0)).' last 7 days',
                'tone' => 'money',
                'url' => FeesDashboardPage::canAccess() ? FeesDashboardPage::getUrl() : null,
                'show' => $canFees,
            ],
            [
                'label' => 'Students present',
                'value' => (string) ($pulse['present_today'] ?? 0),
                'sub' => ($pulse['active_students'] ?? 0).' active students',
                'tone' => 'primary',
                'url' => AttendanceHubPage::canAccess() ? AttendanceHubPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Attendance),
            ],
            [
                'label' => 'Attendance',
                'value' => ($attention['attendance_coverage_pct'] ?? 0).'%',
                'sub' => ($attention['attendance_unmarked'] ?? 0).' still unmarked',
                'tone' => 'primary',
                'url' => AttendanceHubPage::canAccess() ? AttendanceHubPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Attendance),
            ],
            [
                'label' => 'New leads',
                'value' => (string) ($pulse['leads_today'] ?? 0),
                'sub' => ($attention['admissions_pending'] ?? 0).' admissions pending',
                'tone' => 'primary',
                'url' => EnquiryResource::canViewAny() ? EnquiryResource::getUrl('index') : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries) && EnquiryResource::canViewAny(),
            ],
        ], fn (array $kpi): bool => $kpi['show']));
    }

    /**
     * @return list<array{key: string, label: string, show: bool}>
     */
    protected function trendTabs(): array
    {
        return array_values(array_filter([
            ['key' => 'leads', 'label' => 'Leads', 'show' => FeatureGate::enabled(LicenseFeature::Enquiries)],
            ['key' => 'fees', 'label' => '₹ fees', 'show' => FeatureGate::enabled(LicenseFeature::Fees)],
            ['key' => 'admissions', 'label' => 'Admits', 'show' => FeatureGate::enabled(LicenseFeature::Admissions)],
            ['key' => 'calls', 'label' => 'Calls', 'show' => FeatureGate::enabled(LicenseFeature::Calls)],
        ], fn (array $tab): bool => $tab['show']));
    }

    /**
     * @param  list<array{date: string, day: string, is_today: bool, leads: int, fees: float, admissions: int, calls: int}>  $series
     * @return list<array{day: string, is_today: bool, display: string, height: int}>
     */
    protected function trendColumns(array $series, string $metric): array
    {
        $values = array_map(fn (array $day): float => (float) ($day[$metric] ?? 0), $series);
        $max = max(1.0, ...$values);

        return array_map(function (array $day) use ($metric, $max): array {
            $raw = (float) ($day[$metric] ?? 0);
            $height = (int) max(4, round(($raw / $max) * 100));

            return [
                'day' => $day['day'],
                'is_today' => (bool) $day['is_today'],
                'display' => $metric === 'fees' ? $this->moneyShort($raw) : (string) (int) $raw,
                'height' => $height,
            ];
        }, $series);
    }

    protected function trendFoot(string $metric): string
    {
        return match ($metric) {
            'fees' => 'Fee collection by day',
            'admissions' => 'Approved admissions by day',
            'calls' => 'Calls logged by day',
            default => 'New leads by day',
        };
    }

    /**
     * @param  array<string, mixed>  $pulse
     * @param  array<string, mixed>  $attention
     * @return list<array{label: string, value: string, pct: int, tone: string}>
     */
    protected function mixRows(array $pulse, array $attention): array
    {
        $rows = [];

        if (FeatureGate::enabled(LicenseFeature::Attendance)) {
            $expected = max(0, (int) ($attention['attendance_expected'] ?? 0));
            $marked = max(0, (int) ($attention['attendance_marked'] ?? 0));
            $rows[] = [
                'label' => 'Attendance marked',
                'value' => $marked.' / '.$expected,
                'pct' => $expected > 0 ? (int) min(100, round(($marked / $expected) * 100)) : 0,
                'tone' => 'amber',
            ];
        }

        if (FeatureGate::enabled(LicenseFeature::Calls)) {
            $calls = max(0, (int) ($pulse['calls_today'] ?? 0));
            $connected = max(0, (int) ($pulse['calls_connected_today'] ?? 0));
            $rows[] = [
                'label' => 'Calls connected',
                'value' => $connected.' / '.$calls,
                'pct' => $calls > 0 ? (int) min(100, round(($connected / $calls) * 100)) : 0,
                'tone' => 'sky',
            ];
        }

        if (FeatureGate::enabled(LicenseFeature::Attendance)) {
            $active = max(0, (int) ($pulse['active_students'] ?? 0));
            $present = max(0, (int) ($pulse['present_today'] ?? 0));
            $rows[] = [
                'label' => 'Students present',
                'value' => $present.' / '.$active,
                'pct' => $active > 0 ? (int) min(100, round(($present / $active) * 100)) : 0,
                'tone' => 'primary',
            ];

            $staffTotal = max(0, (int) ($pulse['staff_total'] ?? 0));
            $staffPresent = max(0, (int) ($pulse['staff_present_today'] ?? 0));
            $rows[] = [
                'label' => 'Staff present',
                'value' => $staffPresent.' / '.$staffTotal,
                'pct' => $staffTotal > 0 ? (int) min(100, round(($staffPresent / $staffTotal) * 100)) : 0,
                'tone' => 'sky',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $attention
     * @return list<array{label: string, value: int, pct: int, url: ?string}>
     */
    protected function funnelSteps(array $attention): array
    {
        $steps = array_values(array_filter([
            [
                'label' => 'Follow-ups due',
                'value' => (int) ($attention['follow_ups_due'] ?? 0),
                'url' => FollowUpsPage::canAccess() ? FollowUpsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries),
            ],
            [
                'label' => 'Uncalled leads',
                'value' => (int) ($attention['uncalled_leads'] ?? 0),
                'url' => MyLeadsPage::canAccess() ? MyLeadsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries),
            ],
            [
                'label' => 'Admissions pending',
                'value' => (int) ($attention['admissions_pending'] ?? 0),
                'url' => AdmissionResource::canViewAny() ? AdmissionResource::getUrl('index') : null,
                'show' => FeatureGate::enabled(LicenseFeature::Admissions),
            ],
            [
                'label' => 'Open cases',
                'value' => (int) ($attention['open_cases'] ?? 0),
                'url' => MyMeetingsPage::getUrl(['tab' => 'all_cases']),
                'show' => FeatureGate::enabled(LicenseFeature::Cases),
            ],
            [
                'label' => 'Attendance unmarked',
                'value' => (int) ($attention['attendance_unmarked'] ?? 0),
                'url' => AttendanceHubPage::canAccess() ? AttendanceHubPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Attendance),
            ],
        ], fn (array $step): bool => $step['show']));

        $max = max(1, ...array_map(fn (array $step): int => $step['value'], $steps ?: [['value' => 0]]));

        return array_map(function (array $step) use ($max): array {
            return [
                'label' => $step['label'],
                'value' => $step['value'],
                'pct' => (int) min(100, round(($step['value'] / $max) * 100)),
                'url' => $step['url'],
            ];
        }, $steps);
    }

    protected function money(float $amount): string
    {
        $abs = abs($amount);

        return match (true) {
            $abs >= 10000000 => '₹'.$this->trim($amount / 10000000).'Cr',
            $abs >= 100000 => '₹'.$this->trim($amount / 100000).'L',
            $abs >= 1000 => '₹'.$this->trim($amount / 1000).'K',
            default => '₹'.number_format($amount, $abs < 1 && $abs > 0 ? 2 : 0),
        };
    }

    protected function moneyShort(float $amount): string
    {
        if ($amount <= 0) {
            return '0';
        }

        $abs = abs($amount);

        return match (true) {
            $abs >= 100000 => $this->trim($amount / 100000).'L',
            $abs >= 1000 => $this->trim($amount / 1000).'K',
            default => (string) (int) round($amount),
        };
    }

    protected function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
