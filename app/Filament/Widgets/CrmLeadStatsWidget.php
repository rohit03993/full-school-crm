<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
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
    use VisibleToSuperAdminOnly;

    public static function canView(): bool
    {
        return FeatureGate::anyEnabled(LicenseFeature::Enquiries, LicenseFeature::Admissions)
            && CrmAccess::can(Auth::user(), CrmPermission::DashboardOwnerStats);
    }

    protected static ?int $sort = 1;

    protected ?string $heading = 'Leads & Admissions';

    protected ?string $description = 'Pipeline at a glance';

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = app(CrmDashboardService::class)->stats();

        return [
            Stat::make('Total Enquiries', (string) $stats['total_enquiries'])
                ->description('All time')
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->color('gray')
                ->url(EnquiryResource::getUrl('index')),
            Stat::make("Today's Enquiries", (string) $stats['today_enquiries'])
                ->description("{$stats['website_today']} website · {$stats['walk_in_today']} walk-in")
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color('primary'),
            Stat::make('Admissions This Month', (string) $stats['admissions_this_month'])
                ->description('Approved enrollments')
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->color('success')
                ->url(AdmissionResource::getUrl('index')),
            Stat::make('Pending Admissions', (string) $stats['pending_admissions'])
                ->description('Awaiting verification')
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('warning')
                ->url(AdmissionResource::getUrl('index')),
        ];
    }
}
