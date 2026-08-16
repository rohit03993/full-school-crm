<?php

namespace App\Filament\Widgets;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Pages\CallReportPage;
use App\Filament\Pages\CampusVisitsPage;
use App\Filament\Pages\FeesDashboardPage;
use App\Filament\Pages\HomeworkPage;
use App\Filament\Pages\StaffAttendancePage;
use App\Filament\Pages\WhatsAppAnalyticsPage;
use App\Filament\Pages\WhatsAppInboxPage;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Services\DashboardOpsService;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DashboardTodayPulseWidget extends Widget
{
    use UsesDashboardFilters;

    protected static bool $isLazy = false;

    protected static ?int $sort = -8;

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
        $data = app(DashboardOpsService::class)->todayPulse($this->dashboardFilters(), $user);

        return [
            'heading' => 'Today',
            'subheading' => $data['as_of_label'],
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

    protected function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function tilesForPack(string $pack, array $data, $user): array
    {
        $all = $this->allTiles($data, $user);

        return match ($pack) {
            'calling' => array_values(array_filter($all, fn (array $t): bool => in_array($t['key'], ['leads', 'calls', 'visits'], true))),
            'academic' => array_values(array_filter($all, fn (array $t): bool => in_array($t['key'], ['homework', 'leads'], true))),
            'finance' => array_values(array_filter($all, fn (array $t): bool => in_array($t['key'], ['fees'], true))),
            'messaging' => array_values(array_filter($all, fn (array $t): bool => in_array($t['key'], ['whatsapp', 'leads'], true))),
            'default' => array_values(array_filter($all, fn (array $t): bool => in_array($t['key'], ['leads', 'calls', 'visits'], true))),
            default => $all,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, label: string, value: string, meta: ?string, tone: string, url: ?string, show: bool}>
     */
    protected function allTiles(array $data, $user): array
    {
        $waUrl = null;
        if (WhatsAppAnalyticsPage::canAccess()) {
            $waUrl = WhatsAppAnalyticsPage::getUrl();
        } elseif (WhatsAppInboxPage::canAccess()) {
            $waUrl = WhatsAppInboxPage::getUrl();
        }

        return [
            [
                'key' => 'leads',
                'label' => 'New leads',
                'value' => (string) $data['leads_today'],
                'meta' => 'Enquiries today',
                'tone' => 'info',
                'url' => EnquiryResource::canViewAny() ? EnquiryResource::getUrl('index') : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries) && EnquiryResource::canViewAny(),
            ],
            [
                'key' => 'calls',
                'label' => 'Calls',
                'value' => (string) $data['calls_today'],
                'meta' => $data['calls_connected_today'].' connected',
                'tone' => 'primary',
                'url' => CallReportPage::canAccess() ? CallReportPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Calls)
                    && (CrmAccess::can($user, CrmPermission::DashboardCallingStats) || CrmAccess::can($user, CrmPermission::DashboardOwnerStats)),
            ],
            [
                'key' => 'visits',
                'label' => 'Campus visits',
                'value' => (string) $data['visits_today'],
                'meta' => 'Walk-ins today',
                'tone' => 'neutral',
                'url' => CampusVisitsPage::canAccess() ? CampusVisitsPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Enquiries) && CampusVisitsPage::canAccess(),
            ],
            [
                'key' => 'homework',
                'label' => 'Homework given',
                'value' => (string) $data['homework_given_today'],
                'meta' => 'Assignments for today',
                'tone' => 'primary',
                'url' => HomeworkPage::canAccess() ? HomeworkPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Homework) && HomeworkPage::canAccess(),
            ],
            [
                'key' => 'whatsapp',
                'label' => 'Messages sent',
                'value' => (string) $data['whatsapp_sent_today'],
                'meta' => $this->money((float) $data['whatsapp_cost_today']).' spend',
                'tone' => 'info',
                'url' => $waUrl,
                'show' => FeatureGate::enabled(LicenseFeature::WhatsApp)
                    && (WhatsAppAnalyticsPage::canAccess() || WhatsAppInboxPage::canAccess() || CrmAccess::can($user, CrmPermission::WhatsappOps)),
            ],
            [
                'key' => 'staff',
                'label' => 'Staff attendance',
                'value' => (string) ($data['staff_present_today'] ?? 0),
                'meta' => 'of '.($data['staff_total'] ?? 0).' staff',
                'tone' => 'success',
                'url' => StaffAttendancePage::canAccess() ? StaffAttendancePage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Attendance) && StaffAttendancePage::canAccess(),
            ],
            [
                'key' => 'fees',
                'label' => 'Fees in',
                'value' => $this->money((float) $data['fees_amount_today']),
                'meta' => $data['fees_payments_today'].' payments · ₹'.number_format((float) $data['fees_amount_today'], 0),
                'tone' => 'success',
                'url' => FeesDashboardPage::canAccess() ? FeesDashboardPage::getUrl() : null,
                'show' => FeatureGate::enabled(LicenseFeature::Fees) && CrmAccess::canViewFees($user),
            ],
        ];
    }
}
