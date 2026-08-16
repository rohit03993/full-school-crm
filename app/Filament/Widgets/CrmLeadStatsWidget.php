<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Services\CrmDashboardService;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CrmLeadStatsWidget extends StatsOverviewWidget
{
    use UsesDashboardFilters;
    use VisibleToSuperAdminOnly;

    public static function canView(): bool
    {
        return FeatureGate::anyEnabled(LicenseFeature::Enquiries, LicenseFeature::Admissions)
            && CrmAccess::can(Auth::user(), CrmPermission::DashboardOwnerStats);
    }

    protected static ?int $sort = 1;

    protected ?string $heading = 'Leads & admissions';

    protected int | string | array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return 'Following the filters above · '.$this->dashboardFilters()->rangeLabel();
    }

    protected function getColumns(): int
    {
        return 4;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = app(CrmDashboardService::class)->stats($this->dashboardFilters());

        $conversion = $stats['range_enquiries'] > 0
            ? round(($stats['range_admissions'] / $stats['range_enquiries']) * 100)
            : 0;

        return [
            Stat::make('New leads', (string) $stats['range_enquiries'])
                ->description($stats['range_website'].' website · '.$stats['range_walk_in'].' walk-in')
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->color('primary')
                ->url(EnquiryResource::getUrl('index')),
            Stat::make('Admissions approved', (string) $stats['range_admissions'])
                ->description($stats['admissions_this_month'].' this month')
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->color('success')
                ->url(AdmissionResource::getUrl('index')),
            Stat::make('Lead to admission', $conversion.'%')
                ->description('Approved against leads in period')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->color($conversion >= 20 ? 'success' : 'gray'),
            Stat::make('Pending admissions', (string) $stats['pending_admissions'])
                ->description('Awaiting verification right now')
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color($stats['pending_admissions'] > 0 ? 'warning' : 'gray')
                ->url(AdmissionResource::getUrl('index')),
        ];
    }
}
