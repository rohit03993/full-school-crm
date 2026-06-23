<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\BatchAttendancePage;
use App\Enums\CrmPermission;
use App\Filament\Widgets\Concerns\VisibleWithCrmPermission;
use App\Services\CrmDashboardService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CrmFinanceStatsWidget extends StatsOverviewWidget
{
    use VisibleWithCrmPermission;

    protected static function crmPermissionForWidget(): CrmPermission
    {
        return CrmPermission::DashboardFinanceStats;
    }

    protected static ?int $sort = 2;

    protected ?string $heading = 'Students & Finance';

    protected ?string $description = 'Enrollments, collections, and attendance today';

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
            Stat::make('Active Students', (string) $stats['active_students'])
                ->description('Currently enrolled')
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('success'),
            Stat::make('Present Today', (string) $stats['attendance_present_today'])
                ->description("{$stats['attendance_marked_today']} marked of {$stats['attendance_students_in_batches']} in batches")
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->url(BatchAttendancePage::getUrl()),
            Stat::make('Fee Collection Today', '₹'.number_format($stats['fee_collection_today'], 2))
                ->description('Payments recorded today')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Pending Fees', '₹'.number_format($stats['pending_fees_total'], 2))
                ->description('Outstanding across all students')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning'),
        ];
    }
}
