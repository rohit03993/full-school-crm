<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Services\CrmDashboardService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CrmLeadStatsWidget extends StatsOverviewWidget
{
    use VisibleToSuperAdminOnly;

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
