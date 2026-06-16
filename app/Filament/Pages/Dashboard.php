<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CrmFinanceStatsWidget;
use App\Filament\Widgets\CrmLeadStatsWidget;
use App\Filament\Widgets\CourseAdmissionsChartWidget;
use App\Filament\Widgets\DashboardHeroWidget;
use App\Filament\Widgets\LeadSourceChartWidget;
use App\Filament\Widgets\MonthlyAdmissionsChartWidget;
use App\Filament\Widgets\MonthlyFeeCollectionChartWidget;
use App\Filament\Widgets\PendingAdmissionsWidget;
use App\Filament\Widgets\RecentEnquiriesWidget;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -200;

    public function getTitle(): string | Htmlable
    {
        return '';
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('searchStudent')
                ->label('Search Student')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->url(StudentSearchPage::getUrl())
                ->color('primary'),
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            DashboardHeroWidget::class,
            CrmLeadStatsWidget::class,
            CrmFinanceStatsWidget::class,
            RecentEnquiriesWidget::class,
            PendingAdmissionsWidget::class,
            MonthlyAdmissionsChartWidget::class,
            MonthlyFeeCollectionChartWidget::class,
            LeadSourceChartWidget::class,
            CourseAdmissionsChartWidget::class,
        ];
    }
}
