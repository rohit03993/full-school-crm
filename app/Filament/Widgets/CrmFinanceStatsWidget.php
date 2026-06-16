<?php

namespace App\Filament\Widgets;

use App\Services\CrmDashboardService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CrmFinanceStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Students & Finance';

    protected ?string $description = 'Operations and collections';

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
            Stat::make('Fee Collection Today', '₹'.number_format($stats['fee_collection_today'], 2))
                ->description('Payments recorded today')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
            Stat::make('Pending Fees', '₹'.number_format($stats['pending_fees_total'], 2))
                ->description('Outstanding across students')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning'),
            Stat::make('Active Batches', (string) $stats['active_batches'])
                ->description('Running now')
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color('gray'),
        ];
    }
}
